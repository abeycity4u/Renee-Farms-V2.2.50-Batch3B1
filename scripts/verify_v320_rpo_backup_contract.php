<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'backup' =>
        $root .
        '/deployment/private-workers/v320-production-backup/' .
        'run_production_backup.sh',

    'retention' =>
        $root .
        '/deployment/private-workers/v320-production-backup/' .
        'run_backup_retention.php',

    'cycle' =>
        $root .
        '/deployment/private-workers/v320-production-backup/' .
        'run_production_backup_cycle.sh',

    'freshness' =>
        $root .
        '/deployment/private-workers/v320-monitoring/' .
        'run_backup_freshness_monitor.sh',

    'mailer' =>
        $root .
        '/deployment/private-workers/v320-monitoring/' .
        'send_monitoring_alert.php',

    'installer' =>
        $root . '/deployment/install_private_workers.sh',

    'cron' =>
        $root . '/deployment/show_cron_jobs.sh',
];

$failures = 0;

function pass(string $message): void
{
    echo "PASS: {$message}\n";
}

function failCheck(string $message): void
{
    global $failures;

    ++$failures;
    echo "FAIL: {$message}\n";
}

function content(string $file): string
{
    $value = file_get_contents($file);

    if ($value === false) {
        return '';
    }

    return $value;
}

function containsAll(
    string $label,
    string $source,
    array $tokens
): void {
    foreach ($tokens as $token) {
        if (str_contains($source, $token)) {
            pass("{$label} contains {$token}");
        } else {
            failCheck("{$label} missing {$token}");
        }
    }
}

foreach ($files as $label => $file) {
    if (is_file($file)) {
        pass("{$label} source exists");
    } else {
        failCheck("{$label} source missing");
    }
}

$backup = content($files['backup']);
$retention = content($files['retention']);
$cycle = content($files['cycle']);
$freshness = content($files['freshness']);
$mailer = content($files['mailer']);
$installer = content($files['installer']);
$cron = content($files['cron']);

/*
 * Production backup contract.
 */
containsAll(
    'backup',
    $backup,
    [
        'RENEE_APP_ROOT',
        'RENEE_BACKUP_ROOT',
        'RENEE_PRIVATE_ROOT',
        'RENEE_B2_ENV',
        '[ "$DB_NAME" = "renee_testdb" ]',
        'RPO_POLICY_HOURS=6',
        'BACKUP_STATUS=PASS',
        'B2_PREFIX',
    ]
);

if (!str_contains($backup, '/home/renee')) {
    pass('backup source has no account-specific absolute path');
} else {
    failCheck('backup source contains account-specific absolute path');
}

/*
 * Retention policy contract.
 */
containsAll(
    'retention',
    $retention,
    [
        '$recentLimit = 8;',
        '$dailyLimit = 7;',
        '$weeklyLimit = 4;',
        '--execute-delete',
        'Remote deletion must succeed',
        'RENEE_BACKUP_ROOT',
        'RENEE_B2_ENV',
    ]
);

if (!str_contains($retention, '/home/renee')) {
    pass('retention source has no account-specific absolute path');
} else {
    failCheck('retention source contains account-specific absolute path');
}

/*
 * Backup-cycle contract.
 */
containsAll(
    'cycle',
    $cycle,
    [
        '.backup-cycle.lock',
        'BACKUP_PHASE=PASS',
        'RETENTION_PHASE=PASS',
        '--execute-delete',
        'BACKUP_CYCLE_STATUS=PASS',
    ]
);

/*
 * Freshness / RPO monitor contract.
 */
containsAll(
    'freshness',
    $freshness,
    [
        'THRESHOLD_HOURS="6"',
        'RPO_POLICY_HOURS=6',
        '"$SHA256_BIN" -c SHA256SUMS',
        'DATABASE_NAME=renee_testdb',
        'INVALID_NEWER_COUNT=0',
        'RESTORE_ID="$CANDIDATE_ID"',
        'endpoint="backup"',
        'state="FAIL"',
        'state="RECOVERED"',
        'age_hours="$AGE_HOURS"',
        'threshold_hours="$THRESHOLD_HOURS"',
    ]
);

if (!str_contains($freshness, '/home/renee')) {
    pass('freshness monitor has no account-specific absolute path');
} else {
    failCheck('freshness monitor contains account-specific absolute path');
}

/*
 * Shared alert-mailer contract.
 */
containsAll(
    'mailer',
    $mailer,
    [
        "'production' => 'Production'",
        "'staging' => 'Staging'",
        "'backup' => 'Production Backup'",
        "'age_hours:'",
        "'threshold_hours:'",
        'MONITOR_BACKUP_AGE=INVALID',
        'MONITOR_BACKUP_THRESHOLD=INVALID',
        'production backup stale',
        'production backup fresh',
        'Renee AgriSuite availability alert.',
    ]
);

/*
 * Installer contract.
 */
containsAll(
    'installer',
    $installer,
    [
        'v320-production-backup/run_production_backup.sh',
        'v320-production-backup/run_backup_retention.php',
        'v320-production-backup/run_production_backup_cycle.sh',
        'run_backup_freshness_monitor.sh',
        '"$PRIVATE_ROOT/production-backup"',
    ]
);

/*
 * Schedule contract.
 *
 * Cron uses the server cron timezone. The RPO property that matters here
 * is an even six-hour interval, not a particular civil timezone.
 */
containsAll(
    'cron',
    $cron,
    [
        '43 0,6,12,18 * * * /bin/sh ' .
            '$PRIVATE_ROOT/production-backup/' .
            'run_production_backup_cycle.sh',

        '9,24,39,54 * * * * ' .
            '$PRIVATE_ROOT/v320-monitoring/' .
            'run_backup_freshness_monitor.sh',
    ]
);

/*
 * Simple hard-coded-secret guard.
 * Variable assignments and runtime credential reads are allowed;
 * literal credential values are not.
 */
$secretPatterns = [
    '/B2_APPLICATION_KEY=["\'][^$"\']+["\']/',
    '/DB_PASS=["\'][^$("\'][^"\']*["\']/',
];

foreach (
    [
        'backup' => $backup,
        'retention' => $retention,
        'freshness' => $freshness,
        'mailer' => $mailer,
    ] as $label => $source
) {
    $hit = false;

    foreach ($secretPatterns as $pattern) {
        if (preg_match($pattern, $source) === 1) {
            $hit = true;
            break;
        }
    }

    if (!$hit) {
        pass("{$label} has no detected hard-coded credential value");
    } else {
        failCheck("{$label} appears to contain a hard-coded credential");
    }
}

echo "RPO_CONTRACT_FAILURES={$failures}\n";

if ($failures !== 0) {
    echo "RPO_CONTRACT=FAIL\n";
    exit(1);
}

echo "RPO_CONTRACT=PASS\n";
exit(0);
