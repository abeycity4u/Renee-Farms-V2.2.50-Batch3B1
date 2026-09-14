<?php
/**
 * Focused static verifier for migration 053.
 */

$root = dirname(__DIR__);

$migrationPath =
    $root
    . '/migrations/053_billing_legacy_open_subscription_supersession.sql';

$dispositionPath =
    $root
    . '/includes/billing_commercial_attempt_disposition.php';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

$migration = is_file($migrationPath)
    ? file_get_contents($migrationPath)
    : false;

$source = is_file($dispositionPath)
    ? file_get_contents($dispositionPath)
    : false;

$check(
    $migration !== false
    && $source !== false,
    'migration 053 and commercial disposition source both exist'
);

if ($migration === false || $source === false) {
    echo "\nChecks: {$checks}\n";
    echo "Failures: {$failures}\n";
    echo "V2.3 LEGACY OPEN SUBSCRIPTION SUPERSESSION: FAILED\n";
    exit(1);
}

$compact =
    preg_replace('/\s+/', ' ', $migration);

$check(
    strpos(
        $source,
        'Migration 053 is a one-time normalization for pre-hardening open attempts'
    ) !== false
    && strpos(
        $source,
        'does not broaden the runtime helper below'
    ) !== false,
    'runtime disposition contract explicitly separates migration 053 from normal supersession'
);

$check(
    stripos($migration, 'START TRANSACTION;') !== false
    && stripos($migration, 'COMMIT;') !== false,
    'legacy normalization and migration checkpoint share one transaction'
);

$check(
    strpos(
        $compact,
        "legacy.purpose = 'subscription'"
    ) !== false
    && strpos(
        $compact,
        "legacy.commercial_disposition = 'eligible'"
    ) !== false
    && strpos(
        $compact,
        "legacy.status IN ('initialized', 'pending')"
    ) !== false
    && strpos(
        $compact,
        'legacy.verified_at IS NULL'
    ) !== false
    && strpos(
        $compact,
        'legacy.paid_at IS NULL'
    ) !== false
    && strpos(
        $compact,
        'legacy.failed_at IS NULL'
    ) !== false
    && strpos(
        $compact,
        'legacy.applied_subscription_record_id IS NULL'
    ) !== false,
    'target is strictly an open unpaid unapplied eligible subscription attempt'
);

$check(
    strpos(
        $compact,
        'newer.farm_id = legacy.farm_id'
    ) !== false
    && strpos(
        $compact,
        "newer.purpose = 'subscription'"
    ) !== false
    && strpos(
        $compact,
        'newer.id > legacy.id'
    ) !== false
    && strpos(
        $compact,
        "newer.status = 'paid'"
    ) !== false
    && strpos(
        $compact,
        'newer.verified_at IS NOT NULL'
    ) !== false
    && strpos(
        $compact,
        'newer.paid_at IS NOT NULL'
    ) !== false
    && strpos(
        $compact,
        'newer.applied_subscription_record_id IS NOT NULL'
    ) !== false,
    'supersession requires strictly newer verified paid-and-applied evidence in the same tenant'
);

$check(
    strpos(
        $compact,
        'LEFT JOIN billing_payment_attempts AS newer_later'
    ) !== false
    && strpos(
        $compact,
        'newer_later.id > newer.id'
    ) !== false
    && strpos(
        $compact,
        'newer_later.id IS NULL'
    ) !== false,
    'migration deterministically uses the latest qualifying newer applied attempt'
);

$setClause = '';

if (preg_match(
    '/\bSET\b(.*?)\bWHERE\s+legacy\.purpose\s*=/is',
    $migration,
    $match
) === 1) {
    $setClause = $match[1];
}

$check(
    $setClause !== ''
    && strpos(
        $setClause,
        "legacy.commercial_disposition = 'superseded'"
    ) !== false
    && strpos(
        $setClause,
        'legacy.commercial_supersession_verified_at = newer.verified_at'
    ) !== false
    && strpos(
        $setClause,
        'legacy.commercial_superseded_by_user_id = newer.initiated_by_user_id'
    ) !== false
    && strpos(
        $setClause,
        "'legacy_newer_applied_attempt_'"
    ) !== false,
    'migration persists complete deterministic supersession audit evidence'
);

$check(
    $setClause !== ''
    && preg_match(
        '/legacy\.(status|verified_at|paid_at|failed_at|applied_subscription_record_id)\s*=/i',
        $setClause
    ) !== 1,
    'migration does not rewrite provider payment facts or application linkage'
);

$forbidden = [
    'UPDATE farms',
    'UPDATE subscriptions',
    'UPDATE farm_modules',
    'UPDATE farm_subscription_seat_addons',
    'UPDATE farm_role_limits',
    'INSERT INTO subscriptions',
    'DELETE FROM subscriptions',
];

$hasForbidden = false;

foreach ($forbidden as $needle) {
    if (stripos($migration, $needle) !== false) {
        $hasForbidden = true;
        break;
    }
}

$check(
    !$hasForbidden,
    'migration performs no tenant entitlement or subscription-history mutation'
);

$check(
    strpos(
        $migration,
        'billing_provider_'
    ) === false
    && strpos(
        $migration,
        'curl_'
    ) === false,
    'migration performs no provider or network work'
);

$check(
    strpos(
        $migration,
        "053_billing_legacy_open_subscription_supersession.sql"
    ) !== false
    && strpos(
        $migration,
        'schema_migrations'
    ) !== false,
    'migration records its schema checkpoint'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 LEGACY OPEN SUBSCRIPTION SUPERSESSION: FAILED\n";
    exit(1);
}

echo "V2.3 LEGACY OPEN SUBSCRIPTION SUPERSESSION: PASSED\n";
