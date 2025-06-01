<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/helper.php';

const CURRENT_MICROCODE_REVISION = '0x0800820d'; // AMD Ryzen Threadripper 2990WX 32-Core Processor (family: 0x17, model: 0x8, stepping: 0x2)

function main(): void
{
    $output = [];
    if (!system_command('(dmesg -t | grep microcode:)', $output)) {
        logger('Cannot get microcode information', LOG_LEVEL::ERROR);
        return;
    }

    $updated = false;
    $updated_early = false;
    foreach ($output as $line) {
        if (empty($line)) {
            continue;
        }
        $line = trim($line);
        $prefix = 'microcode: Current revision: ';
        if (str_starts_with($line, $prefix)) {
            $revision = substr($line, strlen($prefix));
            if ($revision === CURRENT_MICROCODE_REVISION) {
                $updated = true;
            } else {
                logger('Mismatched microcode revision: ' . $revision, LOG_LEVEL::ERROR);
                return;
            }
        } elseif (str_starts_with($line, 'microcode: Updated early from: ')) {
            $updated_early = true;
        }
    }
    if (!$updated) {
        logger('Microcode not updated', LOG_LEVEL::ERROR);
        return;
    }
    if (!$updated_early) {
        logger('Microcode not updated early', LOG_LEVEL::ERROR);
        return;
    }
    logger('Microcode update successfully verified');
}

main();
