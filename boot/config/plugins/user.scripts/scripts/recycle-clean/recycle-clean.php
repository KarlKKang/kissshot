<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

const RUNTIME_DIR = '/mnt/user/system/recycle-clean';
const RUNTIME_FILE = 'lock';
const EXPIRATION = 30 * 24 * 60 * 60;

class RuntimeState
{
    private mixed $runtime_fp;

    public function __construct()
    {
        if (!is_dir(RUNTIME_DIR) && !mkdir(RUNTIME_DIR, 0777)) {
            throw new Exception('Cannot create runtime directory');
        }
        $file_path = RUNTIME_DIR . '/' . RUNTIME_FILE;
        if (!file_exists($file_path)) {
            logger('Runtime file not found, creating new one: ' . $file_path, LOG_LEVEL::WARNING);
        }
        $runtime_fp = fopen($file_path, 'c+');
        if ($runtime_fp === false) {
            throw new Exception('Cannot open runtime file');
        }
        if (flock($runtime_fp, LOCK_EX | LOCK_NB) === false) {
            throw new Exception('Cannot lock runtime file, other instance is running');
        }
        $this->runtime_fp = $runtime_fp;
    }

    public function commit(): void
    {
        if (!flock($this->runtime_fp, LOCK_UN)) {
            throw new Exception('Cannot unlock runtime file');
        }
        if (!fclose($this->runtime_fp)) {
            throw new Exception('Cannot close runtime file');
        }
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

function logger(string $message, LOG_LEVEL $level = LOG_LEVEL::INFO): void
{
    if ($level !== LOG_LEVEL::INFO) {
        try {
            exec('/usr/local/emhttp/webGui/scripts/notify -e "Recycle Clean" -d ' . escapeshellarg($message) . ' -i ' . $level->unraid_level());
        } catch (ValueError $e) {
            echo 'Cannot send notification: ' . $e->getMessage() . PHP_EOL;
        }
    }
    $message = '[' . $level->value . '] ' . $message;
    try {
        exec('logger -t recycle-clean ' . escapeshellarg($message));
    } catch (ValueError $e) {
        echo 'Cannot log message: ' . $e->getMessage() . PHP_EOL;
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
