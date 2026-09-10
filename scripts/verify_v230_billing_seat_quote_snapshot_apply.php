<?php
/**
 * Static contract verifier for the dedicated migration-046 runner.
 *
 * Does not load config.php and does not connect to the database.
 */

$root = dirname(__DIR__);

$runnerPath =
    $root
    . '/scripts/apply_v230_billing_seat_quote_snapshot.php';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }

    $failures++;
    echo 'FAIL: ' . $label . PHP_EOL;
};

$check(
    is_file($runnerPath),
    'dedicated migration-046 runner exists'
);

$runner = is_file($runnerPath)
    ? (string)file_get_contents($runnerPath)
    : '';

$check(
    strpos(
        $runner,
        "'--preflight', '--apply'"
    ) !== false
    && strpos(
        $runner,
        "\$mode === '--preflight'"
    ) !== false,
    'runner has explicit read-only preflight and apply modes'
);

$check(
    strpos(
        $runner,
        "'046_billing_seat_quote_snapshot.sql'"
    ) !== false,
    'runner is pinned to migration 046'
);

$check(
    strpos(
        $runner,
        "'045_billing_seat_change_foundation.sql'"
    ) !== false
    && strpos(
        $runner,
        'must be applied before migration 046'
    ) !== false,
    'runner fails closed unless migration 045 is present'
);

$check(
    strpos(
        $runner,
        'refusing a second apply'
    ) !== false,
    'runner refuses accidental duplicate apply after marker'
);

$requiredAuditTables = [
    'billing_payment_attempts',
    'billing_provider_events',
    'subscriptions',
    'farm_subscription_seat_addons',
    'farm_role_limits',
    'billing_seat_change_requests',
];

$allAuditTables = true;

foreach ($requiredAuditTables as $table) {
    if (strpos($runner, "'{$table}'") === false) {
        $allAuditTables = false;
        break;
    }
}

$check(
    $allAuditTables,
    'runner audits protected commercial row counts'
);

$snapshotColumns = [
    'quoted_at',
    'lineage_start_at',
    'segment_start_at',
    'segment_end_at',
    'pricing_version',
    'pricing_hash',
    'unit_amount',
    'partial_unit_amount',
    'future_full_periods',
    'per_seat_amount',
    'latest_paid_subscription_id',
    'latest_paid_attempt_id',
];

$allSnapshotColumns = true;

foreach ($snapshotColumns as $column) {
    if (strpos($runner, "'{$column}'") === false) {
        $allSnapshotColumns = false;
        break;
    }
}

$check(
    $allSnapshotColumns,
    'runner post-validates all authoritative snapshot columns'
);

$requiredFks = [
    'fk_billing_seat_change_payment_attempt',
    'fk_billing_seat_change_latest_subscription',
    'fk_billing_seat_change_latest_attempt',
];

$allFks = true;

foreach ($requiredFks as $fk) {
    if (strpos($runner, "'{$fk}'") === false) {
        $allFks = false;
        break;
    }
}

$check(
    $allFks
    && strpos(
        $runner,
        "!== 'RESTRICT'"
    ) !== false,
    'runner post-validates all restrictive paid-lineage foreign keys'
);

$check(
    strpos(
        $runner,
        "'idx_billing_seat_change_latest_paid_subscription'"
    ) !== false
    && strpos(
        $runner,
        "'idx_billing_seat_change_latest_paid_attempt'"
    ) !== false,
    'runner post-validates paid-lineage indexes'
);

$check(
    strpos(
        $runner,
        'MySQL DDL may have partially committed'
    ) !== false
    && strpos(
        $runner,
        'inspect schema state before retrying'
    ) !== false,
    'runner warns explicitly about MySQL DDL partial-commit recovery'
);

$check(
    strpos(
        $runner,
        'curl_'
    ) === false
    && strpos(
        $runner,
        'billing_payment_attempt_create'
    ) === false
    && strpos(
        $runner,
        'subscription_seat_save_addons'
    ) === false,
    'runner contains no provider, payment-creation, or entitlement action'
);

$check(
    preg_match(
        '/(?:migrations\/|migrationName\s*=\s*)[^\n]*003/i',
        $runner
    ) !== 1,
    'runner does not target protected migration 003'
);

$markerGuardPos = strpos(
    $runner,
    'refusing a second apply'
);

$preflightPassPos = strpos(
    $runner,
    'preflight baseline is clean and read-only'
);

$check(
    $markerGuardPos !== false
    && $preflightPassPos !== false
    && $markerGuardPos < $preflightPassPos,
    'already-applied marker is rejected before preflight can report PASS'
);

$check(
    strpos(
        $runner,
        'seat-change request storage is not empty before migration 046.'
    ) !== false,
    'first migration-046 apply requires empty seat-change request storage'
);

$check(
    strpos(
        $runner,
        'migration 046 partial schema artifact exists: column '
    ) !== false
    && strpos(
        $runner,
        'migration 046 partial schema artifact exists: foreign key '
    ) !== false
    && strpos(
        $runner,
        'migration 046 partial schema artifact exists: index '
    ) !== false,
    'preflight fails closed on partial migration-046 schema artifacts'
);

$check(
    strpos(
        $runner,
        'migration 045 payment-attempt foreign key must begin with ON DELETE SET NULL.'
    ) !== false,
    'preflight requires the expected migration-045 payment FK baseline'
);

$check(
    strpos(
        $runner,
        'subscriptions.id must be signed INT before migration 046.'
    ) !== false
    && strpos(
        $runner,
        'billing_payment_attempts.id must be unsigned BIGINT before migration 046.'
    ) !== false,
    'preflight validates parent key types before any DDL executes'
);

$check(
    strpos(
        $runner,
        "getenv('RENEE_MIGRATION_CONFIG')"
    ) !== false,
    'runner supports an explicit migration config bootstrap override'
);

$check(
    preg_match(
        "/bootstrapOverride\\s*!==\\s*''[\\s\\S]*?"
        . "dirname\\(__DIR__\\)\\s*\\.\\s*'\\/config\\.php'/",
        $runner
    ) === 1,
    'runner retains repository config as the default bootstrap'
);

$check(
    strpos(
        $runner,
        'is_file($configPath)'
    ) !== false
    && strpos(
        $runner,
        'is_readable($configPath)'
    ) !== false,
    'runner refuses an unavailable bootstrap path'
);

$check(
    strpos(
        $runner,
        "\$_SERVER['DOCUMENT_ROOT'] = dirname(\$configPath)"
    ) !== false,
    'CLI bootstrap supplies a document root without altering config.php'
);

$check(
    strpos(
        $runner,
        '!($pdo instanceof PDO)'
    ) !== false,
    'runner fails closed unless bootstrap provides a PDO connection'
);

echo PHP_EOL;
echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;

if ($failures > 0) {
    exit(1);
}

echo "V2.3 BILLING SEAT QUOTE SNAPSHOT APPLY RUNNER: PASSED\n";
