<?php

declare(strict_types=1);

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

function get_script_name(): string
{
    $script_name = basename($_SERVER['SCRIPT_FILENAME'] ?? '', '.php');
    if (empty($script_name)) {
        $script_name = 'kissshot-plugin';
    }
    return $script_name;
}

function logger(string $message, LOG_LEVEL $level = LOG_LEVEL::INFO, bool|null $send_notification = null): void
{
    if ($send_notification === null) {
        $send_notification = $level !== LOG_LEVEL::INFO;
    }
    if ($send_notification) {
        $notification_title = getenv('NOTIFICATION_TITLE');
        if (!is_string($notification_title) || empty($notification_title)) {
            $notification_title = 'kissshot-plugin';
        }
        try {
            exec('/usr/local/emhttp/webGui/scripts/notify -e ' . escapeshellarg($notification_title) .  ' -d ' . escapeshellarg($message) . ' -i ' . $level->unraid_level());
        } catch (ValueError $e) {
            echo 'Cannot send notification: ' . $e->getMessage() . PHP_EOL;
        }
    }
    $message = '[' . $level->value . '] ' . $message;
    try {
        exec('logger -t ' . escapeshellarg(get_script_name()) . ' ' . escapeshellarg($message));
    } catch (ValueError $e) {
        echo 'Cannot log message: ' . $e->getMessage() . PHP_EOL;
    }
}

function system_command(string $command, array &$output = [], bool $log_error = true): bool
{
    $command .= ' 2>&1';
    exec($command, $output, $retval);
    if ($retval === 0) {
        return true;
    }
    if ($log_error) {
        logger('Command exited with error code ' . $retval . ': ' . $command);
        foreach ($output as $line) {
            if (empty($line)) {
                continue;
            }
            logger($line);
        }
    }
    return false;
}

class RuntimeStateException extends Exception {}

abstract class RuntimeStateTemplate
{
    public array $state = [];
    private mixed $runtime_fp;
    private string $original_state_str;

    public function __construct(string $dir, string $file_name)
    {
        if (!is_dir($dir) && !mkdir($dir, 0777)) {
            self::log('Cannot create runtime directory', LOG_LEVEL::ERROR);
            throw new RuntimeStateException();
        }
        $file_path = $dir . '/' . $file_name;
        if (!file_exists($file_path)) {
            self::log('Runtime file not found, creating new one: ' . $file_path, LOG_LEVEL::WARNING);
        }
        $runtime_fp = fopen($file_path, 'c+');
        if ($runtime_fp === false) {
            self::log('Cannot open runtime file', LOG_LEVEL::ERROR);
            throw new RuntimeStateException();
        }
        if (flock($runtime_fp, LOCK_EX | LOCK_NB) === false) {
            self::log('Cannot lock runtime file, another instance is running', LOG_LEVEL::WARNING);
            throw new RuntimeStateException();
        }
        $this->runtime_fp = $runtime_fp;

        $file_size = filesize($file_path);
        if ($file_size === false) {
            self::log('Cannot get runtime file size', LOG_LEVEL::ERROR);
            throw new RuntimeStateException();
        }
        $file_contents = fread($runtime_fp, $file_size + 1);
        if ($file_contents === false || strlen($file_contents) !== $file_size) {
            self::log('Cannot read runtime file contents', LOG_LEVEL::ERROR);
            throw new RuntimeStateException();
        }
        if ($file_size === 0) {
            $this->state = [];
            $this->original_state_str = '';
        } else {
            $this->original_state_str = $file_contents;
            $state = json_decode($file_contents, true);
            if (!is_array($state)) {
                self::log('Cannot decode runtime file contents', LOG_LEVEL::ERROR);
                throw new RuntimeStateException();
            }
            $this->state = $state;
        }
    }

    public function commit(): bool
    {
        $runtime_fp = $this->runtime_fp;
        if ($runtime_fp === null) {
            throw new Exception('Double commit of runtime state');
        }
        $this->runtime_fp = null;

        if (count($this->state) === 0) {
            $state_str = '';
        } else {
            $state_str = json_encode($this->state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($state_str === false) {
                self::log('Cannot encode runtime state', LOG_LEVEL::ERROR);
                return false;
            }
        }

        if ($state_str !== $this->original_state_str) {
            if (!ftruncate($runtime_fp, 0)) {
                self::log('Cannot truncate runtime file', LOG_LEVEL::ERROR);
                return false;
            }
            if (!rewind($runtime_fp)) {
                self::log('Cannot rewind runtime file', LOG_LEVEL::ERROR);
                return false;
            }
            $state_str_len = strlen($state_str);
            if ($state_str_len > 0 && fwrite($runtime_fp, $state_str) !== $state_str_len) {
                self::log('Cannot write runtime state', LOG_LEVEL::ERROR);
                return false;
            }
        }

        if (!flock($runtime_fp, LOCK_UN)) {
            self::log('Cannot unlock runtime file', LOG_LEVEL::ERROR);
        }
        if (!fclose($runtime_fp)) {
            self::log('Cannot close runtime file', LOG_LEVEL::ERROR);
            return false;
        }
        return true;
    }

    private static function log(string $message, LOG_LEVEL $level): void
    {
        logger(static::class . ': ' . $message, $level);
    }
}

class ArrayLockException extends Exception {}

class ArrayLock
{
    private mixed $fp;
    private const LOCK_FILE = '/root/ready';

    public function __construct()
    {
        $this->fp = fopen(self::LOCK_FILE, 'r');
        if ($this->fp === false) {
            throw new ArrayLockException();
        }
        if (flock($this->fp, LOCK_SH | LOCK_NB) === false) {
            throw new ArrayLockException();
        }
        if (!is_file(self::LOCK_FILE)) {
            throw new ArrayLockException();
        }
    }

    public function release(): void
    {
        if ($this->fp === null) {
            throw new Exception('Double release of array lock');
        }
        if (!flock($this->fp, LOCK_UN)) {
            logger('Cannot unlock array lock file', LOG_LEVEL::ERROR);
        }
        if (!fclose($this->fp)) {
            logger('Cannot close array lock file', LOG_LEVEL::ERROR);
        }
        $this->fp = null;
    }
}
