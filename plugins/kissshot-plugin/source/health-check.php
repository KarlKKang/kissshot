<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

const SCRIPT_NAME = 'health-check';
const NOTIFICATION_TITLE = 'Health Check';

require __DIR__ . '/helper.php';

$btrfs_mounts = [
    '/boot/config'
];

const RUNTIME_DIR = '/mnt/user/system/health-check';
const RUNTIME_FILE = 'runtime.json';

class RuntimeState extends RuntimeStateTemplate
{
    public function __construct()
    {
        parent::__construct(RUNTIME_DIR, RUNTIME_FILE);
    }
}

function get_zpools(): array
{
    $output = [];
    $zpools = [];
    if (!system_command('zpool list -H -o name', $output)) {
        logger('Cannot list ZFS pools', LOG_LEVEL::ERROR);
        return [];
    }
    foreach ($output as $line) {
        if (empty($line)) {
            continue;
        }
        $zpools[] = $line;
    }
    return $zpools;
}

function check_zpool(string $zpool): void
{
    $output = [];
    if (!system_command('zpool status -x -v ' . escapeshellarg($zpool), $output)) {
        logger('Cannot get zpool status for: ' . $zpool, LOG_LEVEL::ERROR);
        return;
    }
    $healthy = true;
    foreach ($output as $line) {
        if (empty($line)) {
            continue;
        }
        if ($line !== "pool '" . $zpool . "' is healthy") {
            logger($line);
            $healthy = false;
        }
    }
    if ($healthy) {
        logger('ZFS pool is healthy: ' . $zpool);
    } else {
        logger('ZFS errors detected on: ' . $zpool, LOG_LEVEL::ERROR);
    }
}

function check_btrfs(string $mountpoint, bool $scrub): void
{
    $output = [];
    if ($scrub) {
        if (!system_command('btrfs scrub start -B ' . escapeshellarg($mountpoint), $output)) {
            logger('Btrfs scrub error on: ' . $mountpoint, LOG_LEVEL::ERROR);
            return;
        }
        foreach ($output as $line) {
            if (empty($line)) {
                continue;
            }
            logger($line);
        }
    }
    $output = [];
    try {
        exec('btrfs dev stats -c ' . escapeshellarg($mountpoint), $output, $retval);
    } catch (ValueError $e) {
        logger($e->getMessage());
        logger('Cannot get btrfs status for: ' . $mountpoint, LOG_LEVEL::ERROR);
        return;
    }
    if ($retval === 0) {
        logger('Btrfs device is healthy: ' . $mountpoint);
    } else {
        foreach ($output as $line) {
            if (empty($line)) {
                continue;
            }
            logger($line);
        }
        logger('Btrfs errors detected on: ' . $mountpoint, LOG_LEVEL::ERROR);
    }
}

function main(array $btrfs_mounts): void
{
    if (!UnraidStatus::array_started()) {
        return;
    }
    try {
        $runtime = new RuntimeState();
    } catch (RuntimeStateException) {
        return;
    }
    $zpools = get_zpools();
    foreach ($zpools as $zpool) {
        check_zpool($zpool);
    }
    $current_month = date('Y-m');
    $last_scrub = $runtime->state['last_scrub'] ?? null;
    $runtime->state['last_scrub'] = $current_month;
    foreach ($btrfs_mounts as $mountpoint) {
        check_btrfs($mountpoint, $last_scrub !== $current_month);
    }
    $runtime->commit();
}

main($btrfs_mounts);
