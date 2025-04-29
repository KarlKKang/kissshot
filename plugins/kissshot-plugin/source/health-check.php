<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

const SCRIPT_NAME = 'health-check';
const NOTIFICATION_TITLE = 'Health Check';

require __DIR__ . '/helper.php';

$zpools = [
    'kokorowatari',
    'golden_chocolate',
];
$btrfs_mounts = [
    '/boot/config'
];

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

function check_btrfs(string $mountpoint): void
{
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

function main(array $zpools, array $btrfs_mounts): void
{
    foreach ($zpools as $zpool) {
        check_zpool($zpool);
    }
    foreach ($btrfs_mounts as $mountpoint) {
        check_btrfs($mountpoint);
    }
}

main($zpools, $btrfs_mounts);
