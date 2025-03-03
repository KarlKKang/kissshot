<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$default_retention_policy = [
    'keep_all_within' => 24 * 60 * 60,
    'keep_hourly_within' => 3 * 24 * 60 * 60,
    'keep_daily_within' => 30 * 24 * 60 * 60,
    'keep_weekly_within' => 365 * 24 * 60 * 60 / 4,
    'keep_monthly_within' => 365 * 24 * 60 * 60,
];
$short_retention_policy = [
    'keep_all_within' => 24 * 60 * 60,
    'keep_daily_within' => 7 * 24 * 60 * 60,
    'keep_weekly_within' => 30 * 24 * 60 * 60,
];

$config = [
    [
        'domain' => [
            'name' => 'TSUKIHI',
            'trim_schedule' => 'daily',
        ],
        'datasets' => [
            'kokorowatari/domains/TSUKIHI/vdisk_c' => [
                'retention_policy' => $default_retention_policy,
            ],
            'kokorowatari/domains/TSUKIHI/vdisk_d' => [
                'retention_policy' => $default_retention_policy,
            ],
            'kokorowatari/domains/TSUKIHI/vdisk_e' => [
                'retention_policy' => $short_retention_policy,
            ],
        ]
    ],
    [
        'datasets' => [
            'kokorowatari/appdata' => [
                'retention_policy' => $default_retention_policy,
            ],
        ],
    ],
    [
        'datasets' => [
            'kokorowatari/cloud_backups' => [
                'retention_policy' => $default_retention_policy,
            ],
        ],
    ],
    [
        'datasets' => [
            'kokorowatari/documents' => [
                'retention_policy' => $default_retention_policy,
                'send_to_restic' => true,
            ],
        ],
    ],
    [
        'datasets' => [
            'kokorowatari/document_archives' => [
                'retention_policy' => $default_retention_policy,
            ],
        ],
    ],
    [
        'datasets' => [
            'kokorowatari/local_share' => [
                'retention_policy' => $short_retention_policy,
            ],
        ],
    ],
    [
        'datasets' => [
            'kokorowatari/media' => [
                'retention_policy' => $short_retention_policy,
            ],
        ],
    ],
    [
        'datasets' => [
            'kokorowatari/media_archives' => [
                'retention_policy' => $default_retention_policy,
            ],
        ],
    ],
];

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

const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
const SNAPSHOT_PREFIX = 'zfs-auto-snap_';
const RUNTIME_DIR = '/mnt/user/system/zfs-auto-snapshot';
const RUNTIME_FILE = 'runtime.json';
const RESTIC_RUNTIME_FILE = 'restic_runtime.json';
const RESTIC_DIR = '/mnt/user/system/restic';

class FileLockException extends Exception {}

abstract class RuntimeStateTemplate
{
    public array $state = [];
    private mixed $runtime_fp;
    private string $original_state_str;

    public function __construct(string $file_name)
    {
        if (!is_dir(RUNTIME_DIR) && !mkdir(RUNTIME_DIR, 0777)) {
            throw new Exception('Cannot create runtime directory');
        }
        $file_path = RUNTIME_DIR . '/' . $file_name;
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
        $state_str = json_encode($this->state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($state_str === false) {
            throw new Exception('Cannot encode runtime state');
        }

        if ($state_str !== $this->original_state_str) {
            if (!ftruncate($this->runtime_fp, 0)) {
                throw new Exception('Cannot truncate runtime file');
            }
            if (!rewind($this->runtime_fp)) {
                throw new Exception('Cannot rewind runtime file');
            }
            if (fwrite($this->runtime_fp, $state_str) !== strlen($state_str)) {
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

class RuntimeState extends RuntimeStateTemplate
{
    public function __construct()
    {
        parent::__construct(RUNTIME_FILE);
    }
}

class ResticRuntimeState extends RuntimeStateTemplate
{
    public function __construct()
    {
        parent::__construct(RESTIC_RUNTIME_FILE);
    }
}

function logger(string $message, LOG_LEVEL $level = LOG_LEVEL::INFO): void
{
    if ($level !== LOG_LEVEL::INFO) {
        try {
            exec('/usr/local/emhttp/webGui/scripts/notify -e "ZFS Auto Snapshot" -d ' . escapeshellarg($message) . ' -i ' . $level->unraid_level());
        } catch (ValueError $e) {
            echo 'Cannot send notification: ' . $e->getMessage() . PHP_EOL;
        }
    }
    $message = '[' . $level->value . '] ' . $message;
    try {
        exec('logger -t zfs-auto-snapshot ' . escapeshellarg($message));
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

function get_snapshots(string $dataset): array|false
{
    $snapshots = [];
    $output = [];
    if (!system_command('zfs list -t snapshot -H -p -o name,creation ' . escapeshellarg($dataset), $output)) {
        logger('Cannot get snapshots for dataset ' . $dataset, LOG_LEVEL::ERROR);
        return false;
    }
    foreach ($output as $line_str) {
        $line = explode("\t", $line_str);
        if (count($line) !== 2) {
            logger('Cannot get snapshots for dataset ' . $dataset . ': ' . $line_str, LOG_LEVEL::ERROR);
            return false;
        }
        $snapshot_name = $line[0];
        if (!str_starts_with($snapshot_name, $dataset . '@')) {
            logger('Invalid snapshot name for dataset ' . $dataset . ': ' . $line_str, LOG_LEVEL::ERROR);
            return false;
        }
        $snapshot_name = substr($snapshot_name, strlen($dataset) + 1);
        if (!str_starts_with($snapshot_name, SNAPSHOT_PREFIX)) {
            continue;
        }
        $snapshot_name = substr($snapshot_name, strlen(SNAPSHOT_PREFIX));
        $creation_time_str = $line[1];
        if (!ctype_digit($creation_time_str)) {
            logger('Invalid snapshot creation time for dataset ' . $dataset . ': ' . $line_str, LOG_LEVEL::ERROR);
            return false;
        }
        $creation_time = intval($creation_time_str);
        if ($creation_time === 0) {
            logger('Invalid snapshot creation time for dataset ' . $dataset . ': ' . $line_str, LOG_LEVEL::ERROR);
            return false;
        }
        $snapshots[$snapshot_name] = $creation_time;
    }
    return $snapshots;
}

function generate_random_string(int $length): string
{
    $alphabet_length = strlen(ALPHABET);
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= ALPHABET[rand(0, $alphabet_length - 1)];
    }
    return $random_string;
}

function destroy_snapshot(string $dataset, string $snapshot_name): void
{
    $full_snapshot_name = $dataset . '@' . SNAPSHOT_PREFIX . $snapshot_name;
    if (!system_command('zfs destroy ' . escapeshellarg($full_snapshot_name))) {
        logger('Cannot destroy snapshot ' . $full_snapshot_name, LOG_LEVEL::ERROR);
    }
}

function cleanup_snapshots(string $dataset, array $snapshots, array $retention_policy): void
{
    $retain_snapshots = [];
    $current_time = time();

    $keep_all_within = $retention_policy['keep_all_within'] ?? null;
    if ($keep_all_within !== null) {
        if (!is_int($keep_all_within)) {
            logger('Invalid keep_all_within retention rule for dataset ' . $dataset . ': ' . json_encode($retention_policy), LOG_LEVEL::ERROR);
            return;
        }
        foreach ($snapshots as $snapshot_name => $creation_time) {
            if ($current_time - $creation_time <= $keep_all_within) {
                $retain_snapshots[$snapshot_name] = true;
            }
        }
    }

    $grouping_patterns = [
        'keep_hourly_within' => 'Y-m-d H',
        'keep_daily_within' => 'Y-m-d',
        'keep_weekly_within' => 'Y-W',
        'keep_monthly_within' => 'Y-m',
    ];

    foreach ($grouping_patterns as $retention_rule => $pattern) {
        $retention_period = $retention_policy[$retention_rule] ?? null;
        if ($retention_period === null) {
            continue;
        }
        if (!is_int($retention_period)) {
            logger('Invalid ' . $retention_rule . ' retention rule for dataset ' . $dataset . ': ' . json_encode($retention_policy), LOG_LEVEL::ERROR);
            return;
        }
        $grouped_snapshots = [];
        foreach ($snapshots as $snapshot_name => $creation_time) {
            if ($current_time - $creation_time <= $retention_period) {
                $grouped_snapshots[date($pattern, $creation_time)][$snapshot_name] = $creation_time;
            }
        }
        foreach ($grouped_snapshots as $snapshot_group) {
            arsort($snapshot_group);
            $retain_snapshots[key($snapshot_group)] = true;
        }
    }

    foreach ($snapshots as $snapshot_name => $_) {
        if (!array_key_exists($snapshot_name, $retain_snapshots)) {
            destroy_snapshot($dataset, $snapshot_name);
        }
    }
}

function get_domain_state(string $domain): string|false
{
    $output = [];
    if (!system_command('virsh domstate ' . escapeshellarg($domain), $output)) {
        logger('Cannot get domain state for domain ' . $domain, LOG_LEVEL::ERROR);
        return false;
    }
    $state = $output[0] ?? null;
    if (!is_string($state)) {
        logger('Invalid domain state for domain ' . $domain . ': ' . json_encode($output), LOG_LEVEL::ERROR);
        return false;
    }
    return $state;
}

function fs_freeze(string $domain): bool
{
    $result = system_command('virsh domfsfreeze ' . escapeshellarg($domain));
    if (!$result) {
        logger('Cannot freeze filesystem for domain ' . $domain, LOG_LEVEL::ERROR);
    }
    return $result;
}

function fs_thaw(string $domain): void
{
    if (!system_command('virsh domfsthaw ' . escapeshellarg($domain))) {
        logger('Cannot thaw filesystem for domain ' . $domain, LOG_LEVEL::ERROR);
    }
}

function fs_trim(string $domain): void
{
    if (!system_command('virsh domfstrim ' . escapeshellarg($domain))) {
        logger('Cannot trim filesystem for domain ' . $domain, LOG_LEVEL::ERROR);
    }
}

function snapshot_txg(array $txg, RuntimeState $runtime, array &$snapshots_to_restic): void
{
    $datasets = $txg['datasets'] ?? null;
    if (!is_array($datasets)) {
        logger('Invalid datasets configuration: ' . json_encode($txg), LOG_LEVEL::ERROR);
        return;
    }
    if (count($datasets) === 0) {
        logger('No datasets configured: ' . json_encode($txg), LOG_LEVEL::WARNING);
        return;
    }

    $create_cmd = 'zfs snapshot';
    $cleanup_args = [];
    $txg_snapshots_to_restic = [];
    foreach ($datasets as $dataset => $dataset_config) {
        if (!is_string($dataset) || !is_array($dataset_config)) {
            logger('Invalid dataset configuration: ' . json_encode([$dataset => $dataset_config]), LOG_LEVEL::ERROR);
            return;
        }
        $retention_policy = $dataset_config['retention_policy'] ?? null;
        if (!is_array($retention_policy)) {
            logger('Invalid retention policy configuration for dataset ' . $dataset . ': ' . json_encode($dataset_config), LOG_LEVEL::ERROR);
            return;
        }
        $existing_snapshots = get_snapshots($dataset);
        if ($existing_snapshots === false) {
            return;
        }
        $new_snapshot_name = generate_random_string(8);
        while (array_key_exists($new_snapshot_name, $existing_snapshots)) {
            $new_snapshot_name = generate_random_string(8);
        }
        $new_snapshot_name = SNAPSHOT_PREFIX . $new_snapshot_name;
        $create_cmd .= ' ' . escapeshellarg($dataset . '@' . $new_snapshot_name);
        $cleanup_args[] = [$dataset, $existing_snapshots, $retention_policy];
        if ($dataset_config['send_to_restic'] ?? false) {
            $txg_snapshots_to_restic[$dataset] = $new_snapshot_name;
        }
    }

    $domain = $txg['domain']['name'] ?? null;
    $domain_is_running = false;
    if ($domain !== null) {
        if (!is_string($domain)) {
            logger('Invalid domain configuration: ' . json_encode($txg), LOG_LEVEL::ERROR);
            return;
        }
        $domain_state = get_domain_state($domain);
        if ($domain_state === false) {
            return;
        }
        $domain_is_running = $domain_state === 'running';
        if ($domain_is_running) {
            $trim_schedule = $txg['domain']['trim_schedule'] ?? null;
            if ($trim_schedule !== null) {
                if (!is_string($trim_schedule)) {
                    logger('Invalid trim schedule for domain ' . $domain . ': ' . json_encode($txg), LOG_LEVEL::ERROR);
                    return;
                }
                $trim_date_patterns = [
                    'daily' => 'Y-m-d',
                    'weekly' => 'Y-W',
                    'monthly' => 'Y-m',
                ];
                $trim_date_pattern = $trim_date_patterns[$trim_schedule] ?? null;
                if ($trim_date_pattern === null) {
                    logger('Invalid trim schedule for domain ' . $domain . ': ' . $trim_schedule, LOG_LEVEL::ERROR);
                    return;
                }
                $last_trimmed = $runtime->state['domains'][$domain]['last_trimmed'] ?? null;
                $current_trim_date = date($trim_date_pattern);
                if ($last_trimmed !== $current_trim_date) {
                    fs_trim($domain);
                    $runtime->state['domains'][$domain]['last_trimmed'] = $current_trim_date;
                }
            }
            if (!fs_freeze($domain)) {
                return;
            }
        }
    }

    $create_success = system_command($create_cmd);
    if ($domain_is_running) {
        fs_thaw($domain);
    }
    if (!$create_success) {
        logger('Error encountered during snapshot creation', LOG_LEVEL::ERROR);
        return;
    }

    foreach ($cleanup_args as $cleanup_arg) {
        cleanup_snapshots(...$cleanup_arg);
    }

    $snapshots_to_restic = array_merge($snapshots_to_restic, $txg_snapshots_to_restic);
}

function send_to_restic(array $snapshots_to_restic, ResticRuntimeState $runtime): void
{
    if (count($snapshots_to_restic) === 0) {
        return;
    }

    $cmd_docker_prefix = 'docker run -i --rm --name restic --hostname KISSSHOT -v /mnt/user/appdata/restic:/config:ro -v ' . RESTIC_DIR . '/cache:/cache -e RESTIC_REPOSITORY_FILE=/config/repository -e AWS_SHARED_CREDENTIALS_FILE=/config/application.key -e RESTIC_PASSWORD_FILE=/config/repository.key -e RESTIC_CACHE_DIR=/cache';
    $cmd = $cmd_docker_prefix;
    foreach ($snapshots_to_restic as $dataset => $snapshot_name) {
        $cmd .= ' -v ' . escapeshellarg('/mnt/' . $dataset . '/.zfs/snapshot/' . $snapshot_name) . ':' . escapeshellarg('/data/' . $dataset) . ':ro';
    }
    $cmd .= ' restic/restic backup -q';
    $last_force_run = $runtime->state['force_run'] ?? null;
    $current_month = date('Y-m');
    if ($last_force_run !== $current_month) {
        $cmd .= ' --force';
    }
    $cmd .= ' /data';
    if (!system_command($cmd)) {
        logger('Cannot send snapshots to restic', LOG_LEVEL::ERROR);
        return;
    }

    $runtime->state['force_run'] = $current_month;
    $cmd = $cmd_docker_prefix . ' restic/restic forget -q --keep-within 1d --keep-within-hourly 3d --keep-within-daily 1m --keep-within-weekly 3m --keep-within-monthly 1y --prune';
    if (!system_command($cmd)) {
        logger('Cannot prune restic snapshots', LOG_LEVEL::ERROR);
    }

    $last_checked = $runtime->state['check'] ?? null;
    $current_week = date('Y-W');
    if ($last_checked === $current_week) {
        return;
    }
    $check_subset_numerator = $runtime->state['check_subset_numerator'] ?? null;
    if (!is_int($check_subset_numerator) || $check_subset_numerator < 0) {
        $check_subset_numerator = 0;
    }
    $check_subset_denominator = $runtime->state['check_subset_denominator'] ?? null;
    if (!is_int($check_subset_denominator) || $check_subset_denominator < 1) {
        $check_subset_denominator = 4;
    }
    $runtime->state['check'] = $current_week;
    $runtime->state['check_subset_numerator'] = ($check_subset_numerator + 1) % $check_subset_denominator;
    $runtime->state['check_subset_denominator'] = $check_subset_denominator;
    $cmd = $cmd_docker_prefix . ' restic/restic check -q --read-data-subset ' . ($check_subset_numerator % $check_subset_denominator + 1) . '/' . $check_subset_denominator;
    if (!system_command($cmd)) {
        logger('Error during restic check', LOG_LEVEL::ERROR);
    }
}

function main(array $config): void
{
    try {
        $runtime = new RuntimeState();
    } catch (FileLockException $e) {
        logger('Cannot lock runtime file, another instance is running', LOG_LEVEL::WARNING);
        return;
    } catch (Exception $e) {
        logger('Runtime file error: ' . $e->getMessage(), LOG_LEVEL::ERROR);
        return;
    }
    $snapshots_to_restic = [];
    foreach ($config as $txg) {
        if (!is_array($txg)) {
            logger('Invalid transaction group configuration: ' . json_encode($txg), LOG_LEVEL::ERROR);
            continue;
        }
        snapshot_txg($txg, $runtime, $snapshots_to_restic);
    }
    try {
        $runtime->commit();
    } catch (Exception $e) {
        logger('Runtime file error: ' . $e->getMessage(), LOG_LEVEL::ERROR);
        return;
    }

    try {
        $runtime = new ResticRuntimeState();
    } catch (FileLockException $e) {
        logger('Cannot lock restic runtime file, another instance is running', LOG_LEVEL::WARNING);
        return;
    } catch (Exception $e) {
        logger('Restic runtime file error: ' . $e->getMessage(), LOG_LEVEL::ERROR);
        return;
    }
    if (!is_dir(RESTIC_DIR) && !mkdir(RESTIC_DIR, 0777)) {
        logger('Cannot create restic directory', LOG_LEVEL::ERROR);
        return;
    }
    if (!is_dir(RESTIC_DIR . '/cache') && !mkdir(RESTIC_DIR . '/cache', 0777)) {
        logger('Cannot create restic cache directory', LOG_LEVEL::ERROR);
        return;
    }
    send_to_restic($snapshots_to_restic, $runtime);
    try {
        $runtime->commit();
    } catch (Exception $e) {
        logger('Restic runtime file error: ' . $e->getMessage(), LOG_LEVEL::ERROR);
        return;
    }
}

main($config);
