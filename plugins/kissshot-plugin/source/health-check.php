<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

const SCRIPT_NAME = 'health-check';
const NOTIFICATION_TITLE = 'Health Check';

require __DIR__ . '/helper.php';

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

function get_btrfs_devices(): array
{
    $output = [];
    $devices = [];
    if (!system_command('btrfs filesystem show -m', $output)) {
        logger('Cannot list Btrfs devices', LOG_LEVEL::ERROR);
        return [];
    }
    foreach ($output as $line) {
        if (empty($line)) {
            continue;
        }
        $line = trim($line);
        if (!str_starts_with($line, 'devid ')) {
            continue;
        }
        $path_index = strpos($line, 'path ');
        if ($path_index === false) {
            continue;
        }
        $path = substr($line, $path_index + 5);
        $devices[] = trim($path);
    }
    return $devices;
}

function check_zpool(string $zpool): void
{
    $output = [];
    if (!system_command('zpool status -x -v ' . escapeshellarg($zpool), $output)) {
        logger('ZFS errors detected on: ' . $zpool, LOG_LEVEL::ERROR);
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

function check_btrfs(string $device, bool $scrub): void
{
    $output = [];
    if ($scrub) {
        if (!system_command('btrfs scrub start -B ' . escapeshellarg($device), $output)) {
            logger('Btrfs scrub error on: ' . $device, LOG_LEVEL::ERROR);
            return;
        }
        foreach ($output as $line) {
            if (empty($line)) {
                continue;
            }
            logger($line);
        }
    }
    if (system_command('btrfs dev stats -c ' . escapeshellarg($device))) {
        logger('Btrfs device is healthy: ' . $device);
    } else {
        logger('Btrfs errors detected on: ' . $device, LOG_LEVEL::ERROR);
    }
}

function main(): void
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
    $btrfs_devices = get_btrfs_devices();
    foreach ($btrfs_devices as $device) {
        check_btrfs($device, $last_scrub !== $current_month);
    }
    $runtime->commit();
}

main();
