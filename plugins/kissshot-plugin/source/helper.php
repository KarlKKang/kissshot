<?php

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
        logger('Cannot execute system command: ' . $command);
        logger($e->getMessage());
        return false;
    }
    if ($retval !== 0 || $result === false) {
        logger('Command exited with error code ' . $retval . ': ' . $command);
        foreach ($output as $line) {
            logger($line);
        }
        return false;
    }
    return true;
}