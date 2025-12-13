<?php

declare(strict_types=1);

function log_message(string $message, $stream = null): void
{
    if ($stream === null) {
        $stream = STDOUT;
    }
    $timestamp = date('Y-m-d H:i:s');
    $formatted = sprintf('[%s] %s', $timestamp, $message);
    fwrite($stream, $formatted . PHP_EOL);
}

class PIDRuntimeException extends RuntimeException
{
}

function create_pid_file(string $pid_file_path): void
{
    $pid = getmypid();
    if ($pid === false) {
        throw new PIDRuntimeException('Failed to get current PID');
    }
    $pid_str = (string)$pid;

    $existing_pid = @file_get_contents($pid_file_path);
    if ($existing_pid === $pid_str) {
        // The process is restarted, PID file is valid
        return;
    }

    $fp = fopen($pid_file_path, 'x');
    if ($fp === false) {
        throw new PIDRuntimeException('Another instance is running (PID file exists): ' . $pid_file_path);
    }
    if (fwrite($fp, $pid_str) !== strlen($pid_str)) {
        fclose($fp);
        remove_pid_file($pid_file_path);
        throw new PIDRuntimeException('Failed to write PID to file: ' . $pid_file_path);
    }
    fclose($fp);
}

function remove_pid_file(string $pid_file_path): void
{
    if (file_exists($pid_file_path)) {
        unlink($pid_file_path);
    }
}