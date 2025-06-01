<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/helper.php';

$default_retention_policy = [
    'keep_all_within' => 24 * 60 * 60,
    'keep_hourly_within' => 3 * 24 * 60 * 60,
    'keep_daily_within' => 30 * 24 * 60 * 60,
];
$short_retention_policy = [
    'keep_all_within' => 24 * 60 * 60,
    'keep_daily_within' => 7 * 24 * 60 * 60,
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
                'send_to_restic' => true,
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
    [
        'datasets' => [
            'kokorowatari/photos' => [
                'retention_policy' => $default_retention_policy,
                'send_to_restic' => true,
            ],
        ],
    ],
];

const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
const SNAPSHOT_PREFIX = 'zfs-auto-snap_';
const RUNTIME_DIR = '/mnt/user/system/zfs-backup';
const RUNTIME_FILE = 'runtime.json';
const RESTIC_RUNTIME_FILE = 'restic_runtime.json';

class RuntimeState extends RuntimeStateTemplate
{
    public function __construct()
    {
        parent::__construct(RUNTIME_DIR, RUNTIME_FILE);
    }
}

class ResticRuntimeState extends RuntimeStateTemplate
{
    public function __construct()
    {
        parent::__construct(RUNTIME_DIR, RESTIC_RUNTIME_FILE);
    }
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

function fs_thaw(string $domain): bool
{
    $result = system_command('virsh domfsthaw ' . escapeshellarg($domain));
    if (!$result) {
        logger('Cannot thaw filesystem for domain ' . $domain, LOG_LEVEL::ERROR);
    }
    return $result;
}

function fs_trim(string $domain): bool
{
    $result = system_command('virsh domfstrim ' . escapeshellarg($domain));
    if ($result) {
        logger('Trimmed filesystems for domain: ' . $domain);
    } else {
        logger('Cannot trim filesystems for domain: ' . $domain, LOG_LEVEL::ERROR);
    }
    return $result;
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
    $txg_snapshots = [];
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
        $create_cmd .= ' ' . escapeshellarg($dataset . '@' . SNAPSHOT_PREFIX . $new_snapshot_name);
        $cleanup_args[] = [$dataset, $existing_snapshots, $retention_policy];
        $txg_snapshots[$dataset] = [
            'name' => $new_snapshot_name,
            'send_to_restic' => $dataset_config['send_to_restic'] ?? false,
        ];
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
            if ($trim_schedule === 'all') {
                if (!fs_trim($domain)) {
                    return;
                }
            } elseif ($trim_schedule !== null) {
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
                    if (!fs_trim($domain)) {
                        return;
                    }
                    $runtime->state['domains'][$domain]['last_trimmed'] = $current_trim_date;
                }
            }
            if (!fs_freeze($domain)) {
                return;
            }
        }
    }

    $create_success = system_command($create_cmd);
    $invalid_txg = false;
    if ($domain_is_running) {
        $invalid_txg = !fs_thaw($domain);
    }
    if (!$create_success) {
        logger('Error encountered during snapshot creation', LOG_LEVEL::ERROR);
        return;
    }
    if ($invalid_txg) {
        foreach ($txg_snapshots as $dataset => $snapshot) {
            destroy_snapshot($dataset, $snapshot['name']);
        }
        logger('Invalid snapshots destroyed for datasets: ' . implode(', ', array_keys($txg_snapshots)));
        return;
    }

    foreach ($cleanup_args as $cleanup_arg) {
        cleanup_snapshots(...$cleanup_arg);
    }

    foreach ($txg_snapshots as $dataset => $snapshot) {
        if ($snapshot['send_to_restic']) {
            $snapshots_to_restic[$dataset] = $snapshot['name'];
        }
    }

    logger('Snapshots created for datasets: ' . implode(', ', array_keys($txg_snapshots)));
}

function is_mountpoint(string $path): bool
{
    return system_command('mountpoint -q ' . escapeshellarg($path), log_error: false);
}

function unmount_snapshots(array $snapshots): void
{
    foreach ($snapshots as $dataset => $snapshot_name) {
        $mountpoint = '/mnt/' . $dataset . '/.zfs/snapshot/' . SNAPSHOT_PREFIX . $snapshot_name;
        if (!is_mountpoint($mountpoint)) {
            logger('Snapshot ' . $dataset . '@' . $snapshot_name . ' is not mounted, skipping unmount');
            continue;
        }
        if (!system_command('umount ' . escapeshellarg($mountpoint))) {
            logger('Cannot unmount snapshot ' . $dataset . '@' . $snapshot_name, LOG_LEVEL::ERROR);
        }
    }
}

function send_to_restic(array $snapshots_to_restic, ResticRuntimeState $runtime): void
{
    if (count($snapshots_to_restic) === 0) {
        return;
    }

    $cmd_docker_prefix = 'docker run --rm --name restic --hostname KISSSHOT --network host' .
        ' -v /mnt/user/appdata/restic:/config:ro -v restic-cache:/cache' .
        ' -e RESTIC_REPOSITORY_FILE=/config/repository -e AWS_SHARED_CREDENTIALS_FILE=/config/application.key' .
        ' -e RESTIC_PASSWORD_FILE=/config/repository.key -e RESTIC_CACHE_DIR=/cache' .
        ' --memory 1g --memory-swap -1';
    $cmd = $cmd_docker_prefix;
    foreach ($snapshots_to_restic as $dataset => $snapshot_name) {
        $cmd .= ' -v ' . escapeshellarg('/mnt/' . $dataset . '/.zfs/snapshot/' . SNAPSHOT_PREFIX . $snapshot_name) . ':' . escapeshellarg('/data/' . $dataset) . ':ro';
    }
    $cmd .= ' restic/restic backup -q';
    $last_force_run = $runtime->state['force_run'] ?? null;
    $current_month = date('Y-m');
    if ($last_force_run !== $current_month) {
        $cmd .= ' --force';
        logger('Sending snapshots to restic with a force rescan');
    }
    $cmd .= ' /data';
    $ret = system_command($cmd);
    unmount_snapshots($snapshots_to_restic);
    if (!$ret) {
        logger('Cannot send snapshots to restic', LOG_LEVEL::ERROR);
        return;
    }
    $runtime->state['force_run'] = $current_month;
    logger('Snapshots sent to restic for datasets: ' . implode(', ', array_keys($snapshots_to_restic)));

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
    $check_subset_numerator = $check_subset_numerator % $check_subset_denominator + 1;
    $runtime->state['check'] = $current_week;
    $runtime->state['check_subset_numerator'] = $check_subset_numerator;
    $runtime->state['check_subset_denominator'] = $check_subset_denominator;
    $subset = $check_subset_numerator . '/' . $check_subset_denominator;
    $cmd = $cmd_docker_prefix . ' restic/restic check -q --read-data-subset ' . $subset;
    if (system_command($cmd)) {
        logger('Restic repository check completed successfully for subset ' . $subset);
    } else {
        logger('Error during restic check', LOG_LEVEL::ERROR);
    }
}

function main(array $config): void
{
    try {
        $array_lock = new ArrayLock();
    } catch (ArrayLockException) {
        return;
    }

    try {
        $runtime = new RuntimeState();
    } catch (RuntimeStateException) {
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
    if (!$runtime->commit()) {
        return;
    }

    try {
        $runtime = new ResticRuntimeState();
    } catch (RuntimeStateException) {
        return;
    }
    send_to_restic($snapshots_to_restic, $runtime);
    $runtime->commit();

    $array_lock->release();
}

main($config);
