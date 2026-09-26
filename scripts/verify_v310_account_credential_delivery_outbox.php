<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    $root
    . '/includes/account_credential_outbox.php';

$worker =
    $root
    . '/scripts/run_v310_account_credential_outbox.php';

$migration =
    $root
    . '/migrations/086_account_credential_delivery_outbox.sql';

$failures = 0;

function check_v310_outbox_runtime(
    bool $condition,
    string $message
): void {
    global $failures;

    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    echo 'FAIL: ' . $message . PHP_EOL;
    $failures++;
}

check_v310_outbox_runtime(
    is_file($service),
    'shared credential outbox service exists'
);

check_v310_outbox_runtime(
    is_file($worker),
    'bounded credential outbox CLI worker exists'
);

check_v310_outbox_runtime(
    is_file($migration),
    'credential outbox migration exists'
);

if (!is_file($service)
    || !is_file($worker)
    || !is_file($migration)) {
    echo "V310_ACCOUNT_CREDENTIAL_OUTBOX_RUNTIME=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents(
        $service
    );

$workerSource =
    (string)file_get_contents(
        $worker
    );

foreach ([
    'account_credential_outbox_storage_ready',
    'account_credential_outbox_assert_ready',
    'account_credential_outbox_error_code',
    'account_credential_outbox_retry_delay_seconds',
    'account_credential_outbox_enqueue',
    'account_credential_outbox_claim_next',
    'account_credential_outbox_mark_terminal',
    'account_credential_outbox_retry_or_fail',
    'account_credential_outbox_process_one',
] as $function) {
    check_v310_outbox_runtime(
        str_contains(
            $source,
            'function ' . $function . '('
        ),
        $function . ' is centrally defined'
    );
}

check_v310_outbox_runtime(
    str_contains(
        $source,
        "require_once __DIR__ . '/account_credential_delivery.php';"
    ),
    'outbox delegates actual credential delivery to central delivery service'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        'ACCOUNT_CREDENTIAL_OUTBOX_MAX_ATTEMPTS = 5'
    ),
    'outbox retry attempts are centrally bounded'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        'ACCOUNT_CREDENTIAL_OUTBOX_LEASE_SECONDS = 300'
    ),
    'processing lease has a central five-minute expiry'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        "INSERT INTO account_credential_delivery_outbox"
    )
    && str_contains(
        $source,
        "'pending'"
    ),
    'enqueue persists a durable pending job'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        'FOR UPDATE'
    ),
    'job claim uses database row locking'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        "status = 'processing'"
    )
    && str_contains(
        $source,
        'DATE_SUB('
    )
    && str_contains(
        $source,
        'updated_at <='
    ),
    'abandoned processing jobs are recoverable through bounded lease expiry'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        'attempt_count = attempt_count + 1'
    ),
    'attempt count increments atomically when a job is claimed'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        '$attemptLimitReached'
    )
    && str_contains(
        $source,
        '$attemptCountBeforeClaim'
    )
    && str_contains(
        $source,
        'ACCOUNT_CREDENTIAL_OUTBOX_MAX_ATTEMPTS'
    ),
    'stale lease recovery explicitly recognizes the delivery attempt ceiling'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        "'attempt_limit_recovered'"
    )
    && str_contains(
        $source,
        "'attempt_limit_reached'"
    ),
    'attempt-ceiling recovery carries only a non-secret terminal classification'
);

check_v310_outbox_runtime(
    preg_match(
        '/attempt_limit_reached[\s\S]{0,1400}account_credential_outbox_mark_terminal\([\s\S]{0,500}[\'"]failed[\'"]/i',
        $source
    ) === 1,
    'recovered exhausted job is terminally failed before another delivery attempt'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        "status = 'pending'"
    )
    && str_contains(
        $source,
        'DATE_ADD('
    ),
    'failed delivery is rescheduled durably with database time'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        "'discarded'"
    )
    && str_contains(
        $source,
        "'no_eligible_account'"
    ),
    'null-user jobs terminate as non-secret discarded outcomes'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        'account_credential_send('
    ),
    'worker processing delegates activation/reset delivery through shared service'
);

check_v310_outbox_runtime(
    str_contains(
        $source,
        "'mail_not_sent'"
    )
    && str_contains(
        $source,
        "'delivery_exception'"
    ),
    'delivery failures persist bounded non-secret classifications'
);

check_v310_outbox_runtime(
    !str_contains(
        $source,
        'getMessage()'
    ),
    'outbox service never logs or persists raw exception messages'
);

check_v310_outbox_runtime(
    !preg_match(
        '/error_log\s*\([^;]*(username|workspace|email|token|recipient)/is',
        $source
    ),
    'outbox operator logging excludes supplied account and credential identifiers'
);

check_v310_outbox_runtime(
    str_contains(
        $workerSource,
        "PHP_SAPI !== 'cli'"
    ),
    'worker is CLI-only'
);

check_v310_outbox_runtime(
    str_contains(
        $workerSource,
        "'--send'"
    ),
    'worker requires explicit send mode for mutation and mail delivery'
);

check_v310_outbox_runtime(
    str_contains(
        $workerSource,
        'flock('
    )
    && str_contains(
        $workerSource,
        'LOCK_EX | LOCK_NB'
    ),
    'worker prevents overlapping local cron execution'
);

check_v310_outbox_runtime(
    str_contains(
        $workerSource,
        '$maxJobs = 25'
    )
    && str_contains(
        $workerSource,
        '$maxJobs > 100'
    ),
    'worker execution batch is bounded'
);

check_v310_outbox_runtime(
    str_contains(
        $workerSource,
        'account_credential_outbox_process_one('
    ),
    'worker uses the shared outbox processing service'
);

check_v310_outbox_runtime(
    !str_contains(
        $workerSource,
        'getMessage()'
    ),
    'worker output excludes raw exception messages'
);

check_v310_outbox_runtime(
    !preg_match(
        '/fwrite\s*\([^;]*(username|workspace|email|token|recipient)/is',
        $workerSource
    ),
    'worker diagnostics exclude account and credential identifiers'
);

/*
 * Runtime source must not persist credential secrets or lookup identity.
 * Inspect only INSERT/UPDATE schema vocabulary rather than comments.
 */
check_v310_outbox_runtime(
    !preg_match(
        '/INSERT\s+INTO\s+account_credential_delivery_outbox[\s\S]{0,800}\b(username|workspace|email|token|recipient|subject|body)\b/i',
        $source
    ),
    'enqueue does not persist account lookup identity or credential delivery secrets'
);

check_v310_outbox_runtime(
    !preg_match(
        '/UPDATE\s+account_credential_delivery_outbox[\s\S]{0,800}\b(username|workspace|email|token|recipient|subject|body)\b/i',
        $source
    ),
    'outbox transitions do not persist account lookup identity or credential delivery secrets'
);

if ($failures > 0) {
    echo "V310_ACCOUNT_CREDENTIAL_OUTBOX_RUNTIME=FAIL\n";
    exit(1);
}

echo "V310_ACCOUNT_CREDENTIAL_OUTBOX_RUNTIME=PASS\n";
