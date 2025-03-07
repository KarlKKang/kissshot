<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

const SCRIPT_NAME = 'recycle-clean';
const NOTIFICATION_TITLE = 'Recycle Clean';

require __DIR__ . '/helper.php';

const RUNTIME_DIR = '/mnt/user/system/recycle-clean';
const RUNTIME_FILE = 'lock';
const EXPIRATION = 30 * 24 * 60 * 60;

class RuntimeState extends RuntimeStateTemplate
{
    public function __construct()
    {
        parent::__construct(RUNTIME_DIR, RUNTIME_FILE);
    }
}

class FileSystem
{
    public static function scandir(string $dir): array|false
    {
        $files = scandir($dir);
        if ($files === false) {
            logger('Cannot scan directory: ' . $dir, LOG_LEVEL::WARNING);
            return false;
        }
        return array_diff($files, ['.', '..']);
    }

    public static function unlink(string $file): bool
    {
        if (!unlink($file)) {
            logger('Cannot delete file: ' . $file, LOG_LEVEL::WARNING);
            return false;
        }
        logger('Deleted file: ' . $file);
        return true;
    }

    public static function rmdir(string $dir): bool
    {
        if (!rmdir($dir)) {
            logger('Cannot delete directory: ' . $dir, LOG_LEVEL::WARNING);
            return false;
        }
        logger('Deleted directory: ' . $dir);
        return true;
    }

    public static function lstat(string $file): array|false
    {
        $stat = lstat($file);
        if ($stat === false) {
            logger('Cannot stat file: ' . $file, LOG_LEVEL::WARNING);
            return false;
        }
        return $stat;
    }
}

function is_expired(string $file): bool
{
    $stat = FileSystem::lstat($file);
    if ($stat === false) {
        return false;
    }
    $file_times = [];
    $time_labels = ['mtime', 'ctime'];
    foreach ($time_labels as $label) {
        $file_time = $stat[$label] ?? null;
        if ($file_time === null) {
            logger('Cannot get ' . $label . ' for file: ' . $file, LOG_LEVEL::WARNING);
            return false;
        }
        $file_times[$label] = $file_time;
    }
    $max_file_time = max($file_times);
    return time() - $max_file_time > EXPIRATION;
}

function clean_directory(string $dir): bool
{
    $dir_expired = is_expired($dir);
    $delete_count = 0;
    $remaining_count = 0;

    $files = FileSystem::scandir($dir);
    if ($files === false) {
        return false;
    }
    foreach ($files as $file) {
        $file_path = $dir . '/' . $file;
        if (is_dir($file_path)) {
            if (clean_directory($file_path)) {
                $delete_count++;
            } else {
                $remaining_count++;
            }
        } else {
            if (is_expired($file_path) && FileSystem::unlink($file_path)) {
                $delete_count++;
            } else {
                $remaining_count++;
            }
        }
    }

    if ($delete_count === 0 && $remaining_count === 0) {
        return $dir_expired && FileSystem::rmdir($dir);
    }
    if ($remaining_count === 0) {
        return FileSystem::rmdir($dir);
    }
    return false;
}

function main(): void
{
    try {
        $runtime = new RuntimeState();
    } catch (FileLockException $e) {
        logger('Cannot lock runtime file, another instance is running', LOG_LEVEL::WARNING);
        return;
    } catch (Exception $e) {
        logger($e->getMessage(), LOG_LEVEL::ERROR);
        return;
    }
    $files = FileSystem::scandir('/mnt/user');
    if ($files === false) {
        return;
    }
    foreach ($files as $file) {
        if (str_starts_with($file, '.')) {
            continue;
        }
        $recycle_dir = '/mnt/user/' . $file . '/.recycle';
        if (!is_dir($recycle_dir)) {
            continue;
        }
        clean_directory($recycle_dir);
    }
    try {
        $runtime->commit();
    } catch (Exception $e) {
        logger($e->getMessage(), LOG_LEVEL::ERROR);
        return;
    }
}

main();
