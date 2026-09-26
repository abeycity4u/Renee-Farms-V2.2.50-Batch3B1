<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__
    . '/v310_private_cli_bridge.php';

$appRoot =
    trim(
        (string)(
            getenv(
                'RENEE_CREDENTIAL_APP_ROOT'
            )
            ?: ''
        )
    );

if ($appRoot === '') {
    $appRoot =
        dirname(__DIR__);
}

$appRootReal =
    realpath($appRoot);

if (
    $appRootReal === false
    || !is_dir($appRootReal)
) {
    fwrite(
        STDERR,
        "FAIL: credential worker application root is unavailable.\n"
    );
    exit(1);
}

$authoritySource =
    trim(
        (string)(
            getenv(
                'RENEE_CREDENTIAL_ENV_SOURCE'
            )
            ?: ''
        )
    );

if ($authoritySource !== '') {
    try {
        v310_private_cli_bridge_import(
            $authoritySource
        );
    } catch (Throwable $bridgeError) {
        /*
         * Never emit authority values or source lines.
         */
        fwrite(
            STDERR,
            'FAIL: private credential worker authority import failed ['
            . get_class($bridgeError)
            . '].'
            . PHP_EOL
        );
        exit(1);
    }
}

/*
 * CLI has no trustworthy web document root.
 * Point application bootstrap at the certified runtime root.
 */
$_SERVER['DOCUMENT_ROOT'] =
    $appRootReal;

require_once $appRootReal
    . '/config.php';

require_once $appRootReal
    . '/includes/account_credential_outbox.php';

$send =
    in_array(
        '--send',
        $argv ?? [],
        true
    );

$maxJobs = 25;

foreach ($argv ?? [] as $argument) {
    if (preg_match(
        '/^--max=(\d+)$/',
        (string)$argument,
        $match
    )) {
        $maxJobs =
            (int)$match[1];
    }
}

if ($maxJobs < 1 || $maxJobs > 100) {
    fwrite(
        STDERR,
        "FAIL: --max must be between 1 and 100.\n"
    );
    exit(1);
}

/*
 * SEND is fail-closed. A worker may only enter delivery mode when
 * database, canonical HTTPS public URL, and explicit SMTP authorities
 * are all present. Dry-run remains available without SEND authority.
 */
if (
    $send
    && !v310_private_cli_send_authority_ready()
) {
    fwrite(
        STDERR,
        "FAIL: credential worker SEND authority is incomplete.\n"
    );
    exit(1);
}

if (!account_credential_outbox_storage_ready($pdo)) {
    fwrite(
        STDERR,
        "FAIL: credential outbox storage is not ready.\n"
    );
    exit(1);
}

/*
 * Shared-host single-run protection.
 *
 * Database row locking remains the canonical per-job concurrency control.
 * This file lock additionally prevents two local cron invocations from
 * competing unnecessarily on the same deployment.
 */
$lockName =
    'renee-credential-outbox-'
    . substr(
        hash(
            'sha256',
            dirname(__DIR__)
        ),
        0,
        16
    )
    . '.lock';

$lockPath =
    rtrim(
        sys_get_temp_dir(),
        DIRECTORY_SEPARATOR
    )
    . DIRECTORY_SEPARATOR
    . $lockName;

$lockHandle =
    fopen(
        $lockPath,
        'c'
    );

if ($lockHandle === false) {
    fwrite(
        STDERR,
        "FAIL: unable to open credential worker lock.\n"
    );
    exit(1);
}

if (!flock(
    $lockHandle,
    LOCK_EX | LOCK_NB
)) {
    fwrite(
        STDERR,
        "FAIL: another credential outbox worker is already running.\n"
    );
    fclose($lockHandle);
    exit(1);
}

if (!$send) {
    echo "Mode: DRY-RUN\n";
    echo "PASS: credential outbox storage is ready; no jobs were claimed or sent.\n";

    flock(
        $lockHandle,
        LOCK_UN
    );
    fclose($lockHandle);
    exit(0);
}

$processed = 0;
$sent = 0;
$retried = 0;
$discarded = 0;
$failed = 0;
$workerErrors = 0;

for (
    $iteration = 0;
    $iteration < $maxJobs;
    $iteration++
) {
    try {
        $result =
            account_credential_outbox_process_one(
                $pdo
            );
    } catch (Throwable $workerError) {
        $workerErrors++;

        /*
         * No supplied account identifiers or exception message are emitted.
         */
        fwrite(
            STDERR,
            'FAIL: credential outbox processing exception ['
            . get_class($workerError)
            . '].'
            . PHP_EOL
        );

        break;
    }

    if (($result['job_found'] ?? false) !== true) {
        break;
    }

    $processed++;

    $outcome =
        (string)($result['outcome'] ?? '');

    if ($outcome === 'sent') {
        $sent++;
    } elseif ($outcome === 'retry') {
        $retried++;
    } elseif ($outcome === 'discarded') {
        $discarded++;
    } elseif ($outcome === 'failed') {
        $failed++;
    } else {
        $workerErrors++;

        fwrite(
            STDERR,
            "FAIL: credential outbox returned an unsupported outcome.\n"
        );

        break;
    }
}

echo PHP_EOL;
echo 'Mode:              SEND' . PHP_EOL;
echo 'Maximum jobs:      ' . $maxJobs . PHP_EOL;
echo 'Processed:         ' . $processed . PHP_EOL;
echo 'Sent:              ' . $sent . PHP_EOL;
echo 'Retry scheduled:   ' . $retried . PHP_EOL;
echo 'Discarded:         ' . $discarded . PHP_EOL;
echo 'Terminal failed:   ' . $failed . PHP_EOL;
echo 'Worker errors:     ' . $workerErrors . PHP_EOL;

flock(
    $lockHandle,
    LOCK_UN
);

fclose(
    $lockHandle
);

if ($workerErrors > 0) {
    exit(1);
}

echo "PASS: credential outbox worker completed.\n";
