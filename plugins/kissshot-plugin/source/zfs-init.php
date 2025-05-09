<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

const NOTIFICATION_TITLE = 'ZFS Initialization';

require __DIR__ . '/helper.php';

const ARC_RESERVE_BYTES = 24576 * 2 * 1024 * 1024;

function get_arcstats(): array|false
{
    $arcstats = file_get_contents('/proc/spl/kstat/zfs/arcstats');
    if ($arcstats === false) {
        logger('Cannot read /proc/spl/kstat/zfs/arcstats', LOG_LEVEL::ERROR);
        return false;
    }

    $memory_all_bytes = null;
    $arc_min = null;

    $arcstats = explode("\n", $arcstats);
    $arcstats = array_slice($arcstats, 2);
    foreach ($arcstats as $line_str) {
        if ($line_str === '') {
            continue;
        }

        $line = preg_split('/\s+/', $line_str);
        if ($line === false || count($line) !== 3) {
            continue;
        }

        $key = $line[0];
        $value = $line[2];
        if (!ctype_digit($value)) {
            continue;
        }

        if ($key === 'memory_all_bytes') {
            $memory_all_bytes = intval($value);
        } elseif ($key === 'c_min') {
            $arc_min = intval($value);
        }
    }

    if ($memory_all_bytes === null) {
        logger('Cannot find memory_all_bytes in /proc/spl/kstat/zfs/arcstats', LOG_LEVEL::ERROR);
        return false;
    }
    if ($arc_min === null) {
        logger('Cannot find arc_min in /proc/spl/kstat/zfs/arcstats', LOG_LEVEL::ERROR);
        return false;
    }
    return [$memory_all_bytes, $arc_min];
}

function set_arc_max(int $bytes): void
{
    $bytes = strval($bytes);
    $written = file_put_contents('/sys/module/zfs/parameters/zfs_arc_max', $bytes);
    if ($written === false || $written !== strlen($bytes)) {
        logger('Cannot write to /sys/module/zfs/parameters/zfs_arc_max', LOG_LEVEL::ERROR);
    } else {
        logger('Set zfs_arc_max to ' . $bytes);
    }
}

function main(): void
{
    $arcstats = get_arcstats();
    if ($arcstats === false) {
        return;
    }
    [$memory_all_bytes, $arc_min] = $arcstats;

    # default: max(5/8 of total RAM, total RAM - 1 GiB, 67108864 B)
    # We will simply offset the default value by reserved bytes, instead of re-calculating it from the new available memory.
    $arc_max = intval(max($memory_all_bytes * 5 / 8, $memory_all_bytes - 1024 * 1024 * 1024));
    $arc_max -= ARC_RESERVE_BYTES;
    if ($arc_max < $arc_min || $arc_max < 67108864) {
        logger('arc_max too low: ' . $arc_max, LOG_LEVEL::ERROR);
        return;
    }
    set_arc_max($arc_max);
}

main();
