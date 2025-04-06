<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

const SCRIPT_NAME = 'trim';
const NOTIFICATION_TITLE = 'TRIM';

require __DIR__ . '/helper.php';

$fstrim_mounts = [
    '/mnt/kokorowatari/system/docker',
];
$zpools = [
    'kokorowatari',
];

function main(array $fstrim_mounts, array $zpools): void
{
    foreach ($fstrim_mounts as $mountpoint) {
        if (!system_command('fstrim -v ' . escapeshellarg($mountpoint))) {
            logger('Cannot run fstrim on mountpoint: ' . $mountpoint, LOG_LEVEL::ERROR);
        }
    }
    foreach ($zpools as $zpool) {
        if (!system_command('zpool trim -w ' . escapeshellarg($zpool))) {
            logger('Cannot run zpool trim on zpool: ' . $zpool, LOG_LEVEL::ERROR);
        }
    }
}

main($fstrim_mounts, $zpools);
