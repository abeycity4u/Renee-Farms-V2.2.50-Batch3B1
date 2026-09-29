<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$workerPath =
    $root
    . '/scripts/run_v320_subscription_lifecycle.php';

$lifecyclePath =
    $root
    . '/includes/subscription_lifecycle.php';

$worker =
    file_get_contents($workerPath);

$lifecycle =
    file_get_contents($lifecyclePath);

$fail = 0;

function check(bool $ok, string $message): void
{
    global $fail;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$ok) {
        $fail++;
    }
}

check(
    is_string($worker)
    && strpos(
        $worker,
        "PHP_SAPI === 'cli'"
    ) !== false
    && strpos(
        $worker,
        "realpath("
    ) !== false
    && strpos(
        $worker,
        "=== __FILE__"
    ) !== false,
    'worker executes only as its direct CLI entrypoint'
);

check(
    is_string($worker)
    && strpos(
        $worker,
        'function subscription_lifecycle_worker_run('
    ) !== false,
    'worker loop is reusable by rollback-only QA without invoking CLI entrypoint'
);

check(
    is_string($worker)
    && strpos(
        $worker,
        "subscription_status IN ('trial', 'active')"
    ) !== false
    && strpos(
        $worker,
        'subscription_ends_at IS NOT NULL'
    ) !== false,
    'worker discovers only expirable current farm snapshots'
);

check(
    is_string($worker)
    && strpos(
        $worker,
        'subscription_lifecycle_refresh_farm('
    ) !== false,
    'worker delegates lifecycle mutation to shared authority'
);

check(
    is_string($worker)
    && strpos(
        $worker,
        "UPDATE farms"
    ) === false
    && strpos(
        $worker,
        "INSERT INTO subscriptions"
    ) === false
    && strpos(
        $worker,
        "subscription_record_capture("
    ) === false,
    'worker does not duplicate lifecycle mutation or history SQL'
);

check(
    is_string($worker)
    && strpos(
        $worker,
        "['trial', 'active']"
    ) !== false
    && strpos(
        $worker,
        "\$afterStatus === 'past_due'"
    ) !== false,
    'worker reports only canonical trial/active to past_due transitions'
);

check(
    is_string($worker)
    && strpos(
        $worker,
        'error_log('
    ) !== false
    && strpos(
        $worker,
        'FAILED='
    ) !== false,
    'worker exposes per-run failure observability'
);

check(
    is_string($lifecycle)
    && strpos(
        $lifecycle,
        "SET subscription_status = 'past_due'"
    ) !== false
    && strpos(
        $lifecycle,
        "subscription_record_capture(\$pdo, \$farmId, 'subscription_expired', null)"
    ) !== false,
    'shared lifecycle retains canonical past_due and expiry-history authority'
);

if ($fail === 0) {
    echo "SUBSCRIPTION_LIFECYCLE_WORKER_VERIFIER=PASS\n";
    exit(0);
}

echo "SUBSCRIPTION_LIFECYCLE_WORKER_VERIFIER=FAIL\n";
exit(1);
