<?php
/**
 * Focused static contract verifier for V2.3 commercial-attempt disposition.
 */

$root = dirname(__DIR__);

$migrationPath =
    $root
    . '/migrations/047_billing_commercial_attempt_disposition.sql';

$helperPath =
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

$source = is_file($helperPath)
    ? file_get_contents($helperPath)
    : false;

$check(
    $migration !== false
    && $source !== false,
    'migration 047 and commercial disposition helper both exist'
);

if ($migration === false || $source === false) {
    echo "\nChecks: {$checks}\n";
    echo "Failures: {$failures}\n";
    echo "V2.3 COMMERCIAL ATTEMPT DISPOSITION FOUNDATION: FAILED\n";
    exit(1);
}

$compact = preg_replace('/\s+/', '', $source);

$requiredColumns = [
    'commercial_disposition',
    'commercial_superseded_at',
    'commercial_supersession_verified_at',
    'commercial_superseded_by_user_id',
    'commercial_supersession_reason',
];

$allColumns = true;

foreach ($requiredColumns as $column) {
    if (strpos($migration, $column) === false) {
        $allColumns = false;
        break;
    }
}

$check(
    $allColumns,
    'migration 047 persists disposition and complete supersession audit evidence'
);

$check(
    strpos(
        $migration,
        "commercial_disposition VARCHAR(24) NOT NULL DEFAULT ''eligible''"
    ) !== false,
    'existing and new payment attempts default to commercially eligible'
);

$check(
    strpos(
        $migration,
        'idx_billing_attempt_farm_purpose_disposition_status'
    ) !== false
    && strpos(
        $migration,
        '(farm_id, purpose, commercial_disposition, status)'
    ) !== false,
    'migration indexes tenant purpose disposition and provider status together'
);

$check(
    strpos(
        $migration,
        "047_billing_commercial_attempt_disposition.sql"
    ) !== false
    && strpos(
        $migration,
        'schema_migrations'
    ) !== false,
    'migration 047 records its schema checkpoint'
);

$check(
    strpos(
        $source,
        "require_once __DIR__ . '/billing_payment_foundation.php';"
    ) !== false
    && strpos(
        $source,
        "require_once __DIR__ . '/billing_payment_audit_state.php';"
    ) !== false,
    'commercial disposition reuses canonical payment and audit foundations'
);

$check(
    preg_match(
        "/return\\['eligible','superseded',?\\];/",
        $compact
    ) === 1,
    'commercial applicability is finite and separate from provider payment status'
);

$check(
    strpos(
        $source,
        'billing_commercial_attempt_disposition_storage_ready'
    ) !== false
    && strpos(
        $source,
        'information_schema.columns'
    ) !== false
    && strpos(
        $source,
        'information_schema.statistics'
    ) !== false,
    'disposition helper fails closed unless migration 047 storage is ready'
);

$check(
    strpos(
        $source,
        'Commercial attempt supersession requires an active caller transaction.'
    ) !== false
    && strpos(
        $source,
        '$pdo->beginTransaction()'
    ) === false
    && strpos(
        $source,
        '$pdo->commit()'
    ) === false,
    'supersession requires and preserves caller-owned transaction scope'
);

$attemptLockPos = strpos(
    $source,
    'billing_audit_attempt_by_id('
);

$farmLockPos = strpos(
    $source,
    "FOR UPDATE",
    $attemptLockPos === false
        ? 0
        : $attemptLockPos
);

$check(
    $attemptLockPos !== false
    && $farmLockPos !== false
    && $attemptLockPos < $farmLockPos,
    'supersession preserves payment-attempt then tenant-farm lock order'
);

$check(
    strpos(
        $source,
        "billing_payment_attempt_purpose("
    ) !== false
    && strpos(
        $source,
        "'subscription'"
    ) !== false
    && strpos(
        $source,
        'Only subscription payment attempts may be commercially superseded.'
    ) !== false,
    'only subscription-purpose attempts may be superseded'
);

$check(
    strpos(
        $compact,
        "['failed','cancelled']"
    ) !== false
    && strpos(
        $source,
        'A payment attempt with paid evidence cannot be superseded.'
    ) !== false
    && strpos(
        $source,
        'An applied subscription payment attempt cannot be superseded.'
    ) !== false,
    'only unpaid failed or cancelled unapplied attempts may be superseded'
);

$check(
    strpos(
        $source,
        '$actualVerifiedAt'
    ) !== false
    && strpos(
        $source,
        '$expectedVerifiedAt'
    ) !== false
    && strpos(
        $source,
        'commercial_supersession_verified_at'
    ) !== false
    && strpos(
        $source,
        'must bind to the exact provider verification'
    ) !== false,
    'supersession is bound to the exact provider verification that justified it'
);

$check(
    strpos(
        $source,
        "commercial_disposition = 'superseded'"
    ) !== false
    && strpos(
        $source,
        "commercial_disposition = 'eligible'"
    ) !== false
    && strpos(
        $source,
        "status IN ('failed', 'cancelled')"
    ) !== false
    && strpos(
        $source,
        'paid_at IS NULL'
    ) !== false
    && strpos(
        $source,
        'applied_subscription_record_id IS NULL'
    ) !== false,
    'supersession update is guarded against payment/application races'
);

$check(
    strpos(
        $source,
        "'idempotent' => true"
    ) !== false
    && strpos(
        $source,
        'Existing commercial supersession belongs to a different provider verification.'
    ) !== false,
    'same verified terminal supersession is idempotent but conflicting evidence fails closed'
);

$forbiddenDml = [
    'UPDATE farms',
    'INSERT INTO farms',
    'DELETE FROM farms',
    'UPDATE farm_modules',
    'INSERT INTO farm_modules',
    'DELETE FROM farm_modules',
    'UPDATE subscriptions',
    'INSERT INTO subscriptions',
    'DELETE FROM subscriptions',
    'UPDATE farm_subscription_seat_addons',
    'INSERT INTO farm_subscription_seat_addons',
    'DELETE FROM farm_subscription_seat_addons',
    'UPDATE farm_role_limits',
    'INSERT INTO farm_role_limits',
    'DELETE FROM farm_role_limits',
];

$hasCommercialDml = false;

foreach ($forbiddenDml as $needle) {
    if (stripos($source, $needle) !== false) {
        $hasCommercialDml = true;
        break;
    }
}

$check(
    !$hasCommercialDml,
    'disposition foundation performs no entitlement or subscription-state mutation'
);

$check(
    strpos(
        $source,
        'billing_provider_'
    ) === false
    && strpos(
        $source,
        'curl_'
    ) === false,
    'disposition foundation performs no provider or network work'
);

$check(
    strpos(
        $migration,
        'CREATE TABLE'
    ) === false
    && strpos(
        $migration,
        'REFERENCES users'
    ) === false
    && strpos(
        $migration,
        'REFERENCES billing_payment_attempts'
    ) === false,
    'migration 047 adds no new table or foreign-key purge dependency'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 COMMERCIAL ATTEMPT DISPOSITION FOUNDATION: FAILED\n";
    exit(1);
}

echo "V2.3 COMMERCIAL ATTEMPT DISPOSITION FOUNDATION: PASSED\n";
?>
