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
            logger($line);
        }
        return false;
    }
    return true;
}

class FileLockException extends Exception {}

abstract class RuntimeStateTemplate
{
    public array $state = [];
    private mixed $runtime_fp;
    private string $original_state_str;

    public function __construct(string $dir, string $file_name)
    {
        if (!is_dir($dir) && !mkdir($dir, 0777)) {
            throw new Exception('Cannot create runtime directory');
        }
        $file_path = $dir . '/' . $file_name;
        if (!file_exists($file_path)) {
            logger('Runtime file not found, creating new one: ' . $file_path, LOG_LEVEL::WARNING);
        }
        $runtime_fp = fopen($file_path, 'c+');
        if ($runtime_fp === false) {
            throw new Exception('Cannot open runtime file');
        }
        if (flock($runtime_fp, LOCK_EX | LOCK_NB) === false) {
            throw new FileLockException();
        }
        $this->runtime_fp = $runtime_fp;

        $file_size = filesize($file_path);
        if ($file_size === 0) {
            $this->state = [];
            $this->original_state_str = '';
        } else if ($file_size === false) {
            throw new Exception('Cannot get runtime file size');
        } else {
            $file_contents = fread($runtime_fp, $file_size);
            if ($file_contents === false) {
                throw new Exception('Cannot read runtime file contents');
            }
            $this->original_state_str = $file_contents;
            $state = json_decode($file_contents, true);
            if (!is_array($state)) {
                throw new Exception('Cannot decode runtime file contents');
            }
            $this->state = $state;
        }
    }

    public function commit(): void
    {
        if (count($this->state) === 0) {
            $state_str = '';
        } else {
            $state_str = json_encode($this->state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($state_str === false) {
                throw new Exception('Cannot encode runtime state');
            }
        }

        if ($state_str !== $this->original_state_str) {
            if (!ftruncate($this->runtime_fp, 0)) {
                throw new Exception('Cannot truncate runtime file');
            }
            if (!rewind($this->runtime_fp)) {
                throw new Exception('Cannot rewind runtime file');
            }
            $state_str_len = strlen($state_str);
            if ($state_str_len > 0 && fwrite($this->runtime_fp, $state_str) !== $state_str_len) {
                throw new Exception('Cannot write runtime state');
            }
        }

        if (!flock($this->runtime_fp, LOCK_UN)) {
            throw new Exception('Cannot unlock runtime file');
        }
        if (!fclose($this->runtime_fp)) {
            throw new Exception('Cannot close runtime file');
        }
    }
}

function get_unraid_vars(): array|false
{
    $unraid_vars = parse_ini_file('/var/local/emhttp/var.ini');
    if ($unraid_vars === false) {
        logger('Cannot read Unraid variables', LOG_LEVEL::ERROR);
        return false;
    }
    return $unraid_vars;
}

function unraid_array_started(array|false $unraid_vars): bool
{
    return ($unraid_vars['mdState'] ?? null) === 'STARTED';
}
