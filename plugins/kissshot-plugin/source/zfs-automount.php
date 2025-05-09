<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/helper.php';

$zvol_mounts = [
    'kokorowatari/system/docker' => '/mnt/kokorowatari/system/docker',
];

function get_encrypted_datasets(): array|false
{
    $datasets = [];
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
        if ($keylocation === 'prompt' || $keylocation === 'none') {
            continue;
        }
        if ($mountpoint === '-') {
            $mountpoint = null;
        }
        $datasets[$name] = $mountpoint;
    }
    return $datasets;
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
            exec('lsblk ' . escapeshellarg($device_path), $output, $retval);
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

function mount_zvol(string $dataset, string $mountpoint): bool
{
    if (!create_mountpoint($mountpoint)) {
        return false;
    }
    $device_path = '/dev/zvol/' . $dataset;
    if (!wait_for_device($device_path)) {
        return false;
    }
    if (!system_command('mount ' . escapeshellarg($device_path) . ' ' . escapeshellarg($mountpoint))) {
        logger('Cannot mount ZFS volume: ' . $dataset, LOG_LEVEL::ERROR);
        return false;
    }
    logger('Mounted ZFS volume: ' . $dataset . ' at ' . $mountpoint);
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

function unmount_zvol(string $mountpoint): void
{
    if (!system_command('umount ' . escapeshellarg($mountpoint))) {
        logger('Cannot unmount ZFS volume: ' . $mountpoint, LOG_LEVEL::ERROR);
        return;
    }
    logger('Unmounted ZFS volume: ' . $mountpoint);
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

function main(array $argv, array $zvol_mounts): void
{
    if (count($argv) !== 2) {
        logger('Invalid number of arguments', LOG_LEVEL::ERROR);
        return;
    }
    $action = $argv[1];
    if ($action === 'mount') {
        $datasets = get_encrypted_datasets();
        if ($datasets === false) {
            return;
        }
        foreach ($datasets as $dataset => $mountpoint) {
            mount($dataset, $mountpoint);
        }
        foreach ($zvol_mounts as $dataset => $mountpoint) {
            mount_zvol($dataset, $mountpoint);
        }
    } elseif ($action === 'unmount') {
        foreach ($zvol_mounts as $mountpoint) {
            unmount_zvol($mountpoint);
        }
        unmount_nonroot_datasets();
        unload_all_keys();
    } else {
        logger('Invalid action: ' . $action, LOG_LEVEL::ERROR);
    }
}

main($argv, $zvol_mounts);
