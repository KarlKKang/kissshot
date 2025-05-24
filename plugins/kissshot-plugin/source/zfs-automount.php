<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/helper.php';

$extra_mounts = [
    'LABEL=ROOT' => [
        'mountpoint' => '/mnt/kokorowatari/system',
        'type' => 'btrfs',
        'options' => 'noatime,subvol=/system',
    ],
];

function get_datasets(): array|false
{
    $root_datasets = [];
    $encrypted_datasets = [];
    $output = [];
    // Not tested: sorting by mountpoint allows mounting of parent datasets before children, so that children
    //  can have their own encryption root.
    if (!system_command('zfs list -t filesystem,volume -H -o name,keylocation,mountpoint -s mountpoint', $output)) {
        logger('Cannot list ZFS datasets', LOG_LEVEL::ERROR);
        return false;
    }
    foreach ($output as $line_str) {
        $line = explode("\t", $line_str);
        if (count($line) !== 3) {
            logger('Cannot parse ZFS list output: ' . $line_str, LOG_LEVEL::ERROR);
            return false;
        }
        [$name, $keylocation, $mountpoint] = $line;
        if (strpos($name, '/') === false && $keylocation === 'none') {
            $root_datasets[$name] = $mountpoint;
            continue;
        }
        if ($keylocation === 'prompt' || $keylocation === 'none') {
            continue;
        }
        if ($mountpoint === '-') {
            $mountpoint = null;
        }
        $encrypted_datasets[$name] = $mountpoint;
    }
    return [$root_datasets, $encrypted_datasets];
}

function remount_noatime(string $mountpoint): void
{
    # Unraid mounts root datasets with `-o atime=off`, but this leads to `relatime` in `/proc/mounts`.
    # We will remount the root datasets with `noatime` for the correct behavior.
    if (system_command('mount -o remount,noatime ' . escapeshellarg($mountpoint))) {
        logger('Remounted with noatime: ' . $mountpoint);
    } else {
        logger('Cannot remount with noatime: ' . $mountpoint, LOG_LEVEL::ERROR);
    }
}

function create_mountpoint(string $mountpoint): bool
{
    if (!is_dir($mountpoint) && !mkdir($mountpoint, 0777, true)) {
        logger('Cannot create mountpoint: ' . $mountpoint, LOG_LEVEL::ERROR);
        return false;
    }
    if (!system_command('chattr +i ' . escapeshellarg($mountpoint))) {
        logger('Cannot set mountpoint immutable: ' . $mountpoint, LOG_LEVEL::ERROR);
        return false;
    }
    return true;
}

function mount(string $dataset, string|null $mountpoint): bool
{
    if ($mountpoint !== null) {
        if (!create_mountpoint($mountpoint)) {
            return false;
        }
    }
    if (!system_command('zfs load-key ' . escapeshellarg($dataset))) {
        logger('Cannot load key for dataset: ' . $dataset, LOG_LEVEL::ERROR);
        return false;
    }
    if ($mountpoint === null) {
        logger('Loaded key for dataset: ' . $dataset);
        return true;
    }
    if (!system_command('zfs mount -R ' . escapeshellarg($dataset))) {
        logger('Cannot mount dataset: ' . $dataset, LOG_LEVEL::ERROR);
        return false;
    }
    logger('Mounted dataset: ' . $dataset . ' at ' . $mountpoint);
    return true;
}

function wait_for_device(string $device_path): bool
{
    $timeout = 30;
    while ($timeout > 0) {
        try {
            if (str_starts_with($device_path, 'LABEL=')) {
                $label = substr($device_path, 6);
                exec('blkid -L ' . escapeshellarg($label), $output, $retval);
            } else {
                exec('lsblk ' . escapeshellarg($device_path), $output, $retval);
            }
            if ($retval === 0) {
                return true;
            }
        } catch (ValueError $e) {
            logger($e->getMessage());
            logger('Cannot check device: ' . $device_path, LOG_LEVEL::ERROR);
            return false;
        }
        sleep(1);
        $timeout--;
    }
    logger('Timeout waiting for device: ' . $device_path, LOG_LEVEL::ERROR);
    return false;
}

function mount_extra(string $device, array $mount_config): bool
{
    $mountpoint = $mount_config['mountpoint'] ?? null;
    $type = $mount_config['type'] ?? null;
    $options = $mount_config['options'] ?? null;
    if (!is_string($mountpoint) || !is_string($type) || !is_string($options)) {
        logger('Invalid mount config for: ' . $device, LOG_LEVEL::ERROR);
        return false;
    }
    if (!create_mountpoint($mountpoint)) {
        return false;
    }
    if (!wait_for_device($device)) {
        return false;
    }
    if (!system_command('mount -t ' . escapeshellarg($type) . ' -o ' . escapeshellarg($options) . ' ' . escapeshellarg($device) . ' ' . escapeshellarg($mountpoint))) {
        logger('Cannot mount ' . $device . ' at ' . $mountpoint, LOG_LEVEL::ERROR);
        return false;
    }
    logger('Mounted ' . $device . ' at ' . $mountpoint);
    return true;
}

function unload_all_keys(): void
{
    if (!system_command('zfs unload-key -a')) {
        logger('Cannot unload all keys', LOG_LEVEL::ERROR);
        return;
    }
    logger('All keys unloaded');
}

function unmount_extra(string $mountpoint): void
{
    if (!system_command('umount ' . escapeshellarg($mountpoint))) {
        logger('Cannot unmount: ' . $mountpoint, LOG_LEVEL::ERROR);
        return;
    }
    logger('Unmounted: ' . $mountpoint);
}

function unmount_dataset(string $dataset): void
{
    $wait = 1;
    while (true) {
        if (!system_command('zfs unmount ' . escapeshellarg($dataset))) {
            logger('Retry unmounting dataset after ' . $wait . ' seconds: ' . $dataset);
            sleep($wait);
            $wait = min($wait * 2, 10);
            continue;
        }
        logger('Unmounted dataset: ' . $dataset);
        return;
    }
}

function unmount_nonroot_datasets(): void
{
    $output = [];
    if (!system_command('zfs list -t filesystem -H -o name,mounted -S mountpoint', $output)) {
        logger('Cannot list ZFS datasets', LOG_LEVEL::ERROR);
        return;
    }
    foreach ($output as $line_str) {
        $line = explode("\t", $line_str);
        if (count($line) !== 2) {
            logger('Cannot parse ZFS list output: ' . $line_str, LOG_LEVEL::ERROR);
            return;
        }
        [$name, $mounted] = $line;
        if ($mounted !== 'yes') {
            continue;
        }
        $slash_pos = strpos($name, '/');
        if ($slash_pos === false) {
            continue;
        }
        if ($slash_pos !== strrpos($name, '/')) {
            continue;
        }
        unmount_dataset($name);
    }
}

function main(array $argv, array $extra_mounts): void
{
    if (count($argv) !== 2) {
        logger('Invalid number of arguments', LOG_LEVEL::ERROR);
        return;
    }
    $action = $argv[1];
    if ($action === 'mount') {
        $datasets = get_datasets();
        if ($datasets === false) {
            return;
        }
        [$root_datasets, $encrypted_datasets] = $datasets;
        foreach ($root_datasets as $mountpoint) {
            remount_noatime($mountpoint);
        }
        foreach ($encrypted_datasets as $dataset => $mountpoint) {
            mount($dataset, $mountpoint);
        }
        foreach ($extra_mounts as $device => $mount_config) {
            if (!is_string($device) || !is_array($mount_config)) {
                logger('Invalid mount config for: ' . $device, LOG_LEVEL::ERROR);
                continue;
            }
            mount_extra($device, $mount_config);
        }
    } elseif ($action === 'unmount') {
        foreach ($extra_mounts as $device => $mount_config) {
            $mountpoint = $mount_config['mountpoint'] ?? null;
            if (!is_string($mountpoint)) {
                logger('Invalid mount config for: ' . $device, LOG_LEVEL::ERROR);
                continue;
            }
            unmount_extra($mountpoint);
        }
        unmount_nonroot_datasets();
        unload_all_keys();
    } else {
        logger('Invalid action: ' . $action, LOG_LEVEL::ERROR);
    }
}

main($argv, $extra_mounts);
