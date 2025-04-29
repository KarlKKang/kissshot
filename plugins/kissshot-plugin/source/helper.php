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
            exec('/usr/local/emhttp/webGui/scripts/notify -e ' . escapeshellarg(NOTIFICATION_TITLE) .  ' -d ' . escapeshellarg($message) . ' -i ' . $level->unraid_level());
        } catch (ValueError $e) {
            echo 'Cannot send notification: ' . $e->getMessage() . PHP_EOL;
        }
    }
    $message = '[' . $level->value . '] ' . $message;
    try {
        exec('logger -t ' . escapeshellarg(SCRIPT_NAME) . ' ' . escapeshellarg($message));
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
            if (empty($line)) {
                continue;
            }
            logger($line);
        }
        return false;
    }
    return true;
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
        if ($file_size === 0) {
            $this->state = [];
            $this->original_state_str = '';
        } else if ($file_size === false) {
            self::log('Cannot get runtime file size', LOG_LEVEL::ERROR);
            throw new RuntimeStateException();
        } else {
            $file_contents = fread($runtime_fp, $file_size);
            if ($file_contents === false) {
                self::log('Cannot read runtime file contents', LOG_LEVEL::ERROR);
                throw new RuntimeStateException();
            }
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
            if (!ftruncate($this->runtime_fp, 0)) {
                self::log('Cannot truncate runtime file', LOG_LEVEL::ERROR);
                return false;
            }
            if (!rewind($this->runtime_fp)) {
                self::log('Cannot rewind runtime file', LOG_LEVEL::ERROR);
                return false;
            }
            $state_str_len = strlen($state_str);
            if ($state_str_len > 0 && fwrite($this->runtime_fp, $state_str) !== $state_str_len) {
                self::log('Cannot write runtime state', LOG_LEVEL::ERROR);
                return false;
            }
        }

        if (!flock($this->runtime_fp, LOCK_UN)) {
            self::log('Cannot unlock runtime file', LOG_LEVEL::ERROR);
            return false;
        }
        if (!fclose($this->runtime_fp)) {
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

class UnraidStatus
{
    private static array|null|false $unraid_vars = null;

    private static function get_unraid_vars(): array|false
    {
        if (self::$unraid_vars === null) {
            self::$unraid_vars = parse_ini_file('/var/local/emhttp/var.ini');
            if (self::$unraid_vars === false) {
                logger('Cannot read Unraid variables', LOG_LEVEL::ERROR);
            }
        }
        return self::$unraid_vars;
    }

    public static function array_started(): bool
    {
        return (self::get_unraid_vars()['mdState'] ?? null) === 'STARTED';
    }
}
