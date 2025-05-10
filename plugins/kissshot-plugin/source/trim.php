<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/helper.php';

$fstrim_mounts = [];
$zpools = [
    'kokorowatari',
];

const RUNTIME_DIR = '/mnt/user/system/trim';
const RUNTIME_FILE = 'lock';

class RuntimeState extends RuntimeStateTemplate
{
    public function __construct()
    {
        parent::__construct(RUNTIME_DIR, RUNTIME_FILE);
    }
}

function main(array $fstrim_mounts, array $zpools): void
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
    foreach ($fstrim_mounts as $mountpoint) {
        if (system_command('fstrim -v ' . escapeshellarg($mountpoint))) {
            logger('Trimmed mountpoint: ' . $mountpoint, LOG_LEVEL::INFO);
        } else {
            logger('Cannot run fstrim on mountpoint: ' . $mountpoint, LOG_LEVEL::ERROR);
        }
    }
    foreach ($zpools as $zpool) {
        if (system_command('zpool trim -w ' . escapeshellarg($zpool))) {
            logger('Trimmed zpool: ' . $zpool, LOG_LEVEL::INFO);
        } else {
            logger('Cannot run zpool trim on zpool: ' . $zpool, LOG_LEVEL::ERROR);
        }
    }
    $runtime->commit();
    $array_lock->release();
}

main($fstrim_mounts, $zpools);
