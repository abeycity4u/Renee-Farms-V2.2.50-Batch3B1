<?php
/**
 * Static contract verifier for V2.3 billing extra-seat change foundation.
 *
 * Does not connect to the database and does not mutate files.
 */

$root = dirname(__DIR__);
$migrationPath = $root . '/migrations/045_billing_seat_change_foundation.sql';
$runnerPath = $root . '/scripts/apply_v230_billing_seat_change_foundation.php';

$checks = 0;
$failures = 0;

$check = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
};

$migration = is_file($migrationPath)
    ? (string)file_get_contents($migrationPath)
    : '';

$runner = is_file($runnerPath)
    ? (string)file_get_contents($runnerPath)
    : '';

$check($migration !== '', 'migration 045 exists');
$check($runner !== '', 'targeted migration runner exists');

$check(
    strpos($migration, 'billing_payment_attempts') !== false
    && strpos($migration, 'purpose VARCHAR(32)') !== false
    && strpos($migration, "DEFAULT ''subscription''") !== false,
    'payment purpose defaults existing flow to subscription'
);

$check(
    strpos($migration, 'idx_billing_attempt_farm_purpose_status') !== false,
    'payment purpose has tenant/status lookup index'
);

$check(
    strpos($migration, 'CREATE TABLE IF NOT EXISTS billing_seat_change_requests') !== false,
    'durable seat-change request table is declared'
);

foreach ([
    'change_kind',
    'status',
    'role_code',
    'from_extra_seats',
    'to_extra_seats',
    'plan_code',
    'billing_interval',
    'modules_snapshot',
    'amount',
    'currency',
    'current_period_ends_at',
    'effective_at',
    'payment_attempt_id',
    'initiated_by_user_id',
    'request_hash',
    'applied_at',
    'cancelled_at',
] as $column) {
    $check(
        strpos($migration, $column) !== false,
        'seat-change schema includes ' . $column
    );
}

$check(
    strpos($migration, 'uniq_billing_seat_change_payment_attempt') !== false,
    'one payment attempt can link to at most one seat-change request'
);

$check(
    strpos($migration, 'fk_billing_seat_change_farm') !== false
    && strpos($migration, 'fk_billing_seat_change_payment_attempt') !== false,
    'seat-change table has tenant and payment foreign keys'
);

$check(
    strpos($migration, "045_billing_seat_change_foundation.sql") !== false,
    'migration records its own schema marker'
);

$check(
    strpos($migration, '003_multi_tenant_saas.sql') === false,
    'migration does not invoke migration 003'
);

$check(
    strpos($runner, "\$migrationName = '045_billing_seat_change_foundation.sql';") !== false,
    'runner is pinned to migration 045 only'
);

$check(
    strpos($runner, 'billing_seat_change_requests') !== false
    && strpos($runner, 'billing_payment_attempts') !== false,
    'runner verifies both new seat-change storage and billing purpose'
);

$check(
    strpos($runner, 'provider') !== false
    && strpos($runner, 'no provider call') !== false,
    'runner explicitly records provider-call prohibition'
);

$check(
    strpos($runner, 'migration 003 was not invoked') !== false,
    'runner explicitly records migration 003 prohibition'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    exit(1);
}

echo "V2.3 BILLING SEAT-CHANGE FOUNDATION CONTRACT: PASSED\n";
