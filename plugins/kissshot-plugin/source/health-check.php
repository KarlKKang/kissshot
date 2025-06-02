<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

function check_zpool(string $zpool, bool $scrub, RuntimeState $runtime): void
{
    if ($scrub) {
        if (system_command('zpool scrub ' . escapeshellarg($zpool))) {
            logger('zpool scrub started: ' . $zpool, send_notification: true);
        } else {
            logger('Cannot start zpool scrub: ' . $zpool, LOG_LEVEL::ERROR);
            return;
        }
    }
    $output = [];
    if (!system_command('zpool status -x -v ' . escapeshellarg($zpool), $output)) {
        logger('ZFS errors detected on: ' . $zpool, LOG_LEVEL::ERROR);
        return;
    }
    $previously_healthy = $runtime->state['zfs'][$zpool]['healthy'] ?? true;
    $healthy = true;
    foreach ($output as $line) {
        if (empty($line)) {
            continue;
        }
        if ($line !== "pool '" . $zpool . "' is healthy") {
            if ($previously_healthy) {
                logger($line);
            }
            $healthy = false;
        }
    }
    $runtime->state['zfs'][$zpool]['healthy'] = $healthy;
    if ($healthy) {
        if ($previously_healthy) {
            logger('zpool is healthy: ' . $zpool);
        } else {
            logger('zpool returned to healthy state: ' . $zpool, send_notification: true);
        }
    } else {
        if ($previously_healthy) {
            logger('ZFS errors detected on: ' . $zpool, LOG_LEVEL::ERROR);
        } else {
            logger('zpool remains unhealthy: ' . $zpool);
        }
    }
}

function check_btrfs(string $device, bool $scrub, RuntimeState $runtime): void
{
    $output = [];
    if ($scrub) {
        if (system_command('btrfs scrub start ' . escapeshellarg($device), $output)) {
            logger('Btrfs scrub started: ' . $device, send_notification: true);
        } else {
            logger('Cannot start Btrfs scrub: ' . $device, LOG_LEVEL::ERROR);
            return;
        }
    }
    $previously_healthy = $runtime->state['btrfs'][$device]['healthy'] ?? true;
    $healthy = system_command('btrfs dev stats -c ' . escapeshellarg($device));
    $runtime->state['btrfs'][$device]['healthy'] = $healthy;
    if ($healthy) {
        if ($previously_healthy) {
            logger('Btrfs device is healthy: ' . $device);
        } else {
            logger('Btrfs returned to healthy state: ' . $device, send_notification: true);
        }
    } else {
        if ($previously_healthy) {
            logger('Btrfs errors detected on: ' . $device, LOG_LEVEL::ERROR);
        } else {
            logger('Btrfs device remains unhealthy: ' . $device);
        }
    }
}

function get_all_fstype(): array|false
{
    $output = [];
    if (!system_command('findmnt -l -n -o FSTYPE', $output)) {
        logger('Cannot list mounted filesystems', LOG_LEVEL::ERROR);
        return false;
    }
    return array_map('trim', $output);
}

function health_check(RuntimeState $runtime): void
{
    $all_fstypes = get_all_fstype();
    if ($all_fstypes === false) {
        return;
    }
    $current_month = date('Y-m');
    $last_scrub = $runtime->state['last_scrub'] ?? null;
    $runtime->state['last_scrub'] = $current_month;
    $scrub = $last_scrub !== $current_month;
    if (in_array('zfs', $all_fstypes, true)) {
        $zpools = get_zpools();
        foreach ($zpools as $zpool) {
            check_zpool($zpool, $scrub, $runtime);
        }
    }
    if (in_array('btrfs', $all_fstypes, true)) {
        $btrfs_devices = get_btrfs_devices();
        foreach ($btrfs_devices as $device) {
            check_btrfs($device, $scrub, $runtime);
        }
    }
}

function main(): void
{
    try {
        $array_lock = new ArrayLock();
    } catch (ArrayLockException) {
        return;
    }
    try {
        $runtime = new RuntimeState();
    } catch (RuntimeStateException) {
        return;
    }
    health_check($runtime);
    $runtime->commit();
    $array_lock->release();
}

main();
