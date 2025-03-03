<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$zvol_mounts = [
    'kokorowatari/system/docker' => '/mnt/kokorowatari/system/docker',
];

enum LOG_LEVEL: string
{
    case INFO = 'notice';
    case WARNING = 'warning';
    case ERROR = 'error';

    public function unraid_level(): string
    {
        return match ($this) {
            self::INFO => 'normal',
            self::WARNING => 'warning',
            self::ERROR => 'alert',
        };
    }
}

function logger(string $message, LOG_LEVEL $level = LOG_LEVEL::INFO): void
{
    if ($level !== LOG_LEVEL::INFO) {
        try {
            exec('/usr/local/emhttp/webGui/scripts/notify -e "ZFS Automount" -d ' . escapeshellarg($message) . ' -i ' . $level->unraid_level());
        } catch (ValueError $e) {
            echo 'Cannot send notification: ' . $e->getMessage() . PHP_EOL;
        }
    }
    $message = '[' . $level->value . '] ' . $message;
    try {
        exec('logger -t zfs-automount ' . escapeshellarg($message));
    } catch (ValueError $e) {
        echo 'Cannot log message: ' . $e->getMessage() . PHP_EOL;
    }
}

function system_command(string $command, array &$output = []): bool
{
    $command .= ' 2>&1';
    try {
        $result = exec($command, $output, $retval);
    } catch (ValueError $e) {
        logger('Cannot execute system command: ' . $command, LOG_LEVEL::ERROR);
        logger($e->getMessage());
        return false;
    }
    if ($retval !== 0 || $result === false) {
        logger('Command exited with error code ' . $retval . ': ' . $command, LOG_LEVEL::ERROR);
        foreach ($output as $line) {
            logger($line);
        }
        return false;
    }
    return true;
}

function get_encrypted_datasets(): array|false
{
    $datasets = [];
    $output = [];
    // Not tested: sorting by mountpoint allows mounting of parent datasets before children, so that children
    //  can have their own encryption root.
    system_command('zfs list -t filesystem,volume -H -o name,keylocation,mountpoint -s mountpoint', $output);
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

function mount_zvol(string $dataset, string $mountpoint): bool
{
    if (!create_mountpoint($mountpoint)) {
        return false;
    }
    if (!system_command('mount ' . escapeshellarg('/dev/zvol/' . $dataset) . ' ' . escapeshellarg($mountpoint))) {
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
    } elseif ($action === 'unmount_zvols') {
        foreach ($zvol_mounts as $mountpoint) {
            unmount_zvol($mountpoint);
        }
    } elseif ($action === 'unload_keys') {
        unload_all_keys();
    } else {
        logger('Invalid action: ' . $action, LOG_LEVEL::ERROR);
    }

}

main($argv, $zvol_mounts);
