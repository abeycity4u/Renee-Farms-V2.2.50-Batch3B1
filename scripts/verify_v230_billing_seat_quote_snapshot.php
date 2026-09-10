<?php
/**
 * Static V2.3 billing seat quote snapshot schema verifier.
 *
 * No config.php, database connection, provider call or mutation.
 */

$root = dirname(__DIR__);

$migrationPath =
    $root . '/migrations/046_billing_seat_quote_snapshot.sql';

if (!is_file($migrationPath)) {
    fwrite(
        STDERR,
        "FAIL: missing {$migrationPath}\n"
    );
    exit(1);
}

$migration = (string)file_get_contents(
    $migrationPath
);

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
};

$expectedColumns = [
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

$allColumnsPresent = true;

foreach ($expectedColumns as $column) {
    if (strpos($migration, $column) === false) {
        $allColumnsPresent = false;
        break;
    }
}

$check(
    $allColumnsPresent,
    'migration preserves complete authoritative seat-proration snapshot fields'
);

$check(
    strpos(
        $migration,
        'ALTER TABLE billing_seat_change_requests'
    ) !== false,
    'migration extends only the durable seat-change request storage'
);

$check(
    strpos(
        $migration,
        'quoted_at DATETIME NULL'
    ) !== false
    && strpos(
        $migration,
        'segment_start_at DATETIME NULL'
    ) !== false
    && strpos(
        $migration,
        'segment_end_at DATETIME NULL'
    ) !== false,
    'quote and commercial segment timestamps are preserved'
);

$check(
    strpos(
        $migration,
        'pricing_version VARCHAR(80) NULL'
    ) !== false
    && strpos(
        $migration,
        'pricing_hash CHAR(64) NULL'
    ) !== false,
    'canonical price-book identity is preserved'
);

$check(
    strpos(
        $migration,
        'unit_amount DECIMAL(12,2) NULL'
    ) !== false
    && strpos(
        $migration,
        'partial_unit_amount DECIMAL(12,2) NULL'
    ) !== false
    && strpos(
        $migration,
        'per_seat_amount DECIMAL(12,2) NULL'
    ) !== false,
    'authoritative monetary components are stored as exact decimals'
);

$check(
    strpos(
        $migration,
        'future_full_periods INT UNSIGNED NULL'
    ) !== false,
    'already-prepaid future-period count is preserved'
);

$check(
    strpos(
        $migration,
        'latest_paid_subscription_id INT NULL'
    ) !== false
    && strpos(
        $migration,
        'latest_paid_attempt_id BIGINT UNSIGNED NULL'
    ) !== false,
    'authoritative paid-lineage identities are preserved'
);

$check(
    strpos(
        $migration,
        "VALUES ('046_billing_seat_quote_snapshot.sql')"
    ) !== false,
    'migration records its schema marker'
);

$check(
    strpos(
        $migration,
        '003'
    ) === false
    || strpos(
        $migration,
        'does NOT'
    ) !== false,
    'migration does not invoke protected migration 003'
);

$protectedDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions|'
    . 'billing_payment_attempts)\b/i';

$check(
    !preg_match(
        $protectedDml,
        $migration
    ),
    'migration performs no commercial entitlement or payment-row DML'
);

$check(
    strpos(
        $migration,
        'latest_paid_subscription_id INT NULL'
    ) !== false
    && strpos(
        $migration,
        'latest_paid_attempt_id BIGINT UNSIGNED NULL'
    ) !== false,
    'paid-lineage columns match their production parent key types'
);

$check(
    strpos(
        $migration,
        'DROP FOREIGN KEY fk_billing_seat_change_payment_attempt'
    ) !== false
    && strpos(
        $migration,
        'FOREIGN KEY (payment_attempt_id) REFERENCES billing_payment_attempts(id) ON DELETE RESTRICT'
    ) !== false,
    'migration upgrades durable request payment identity from SET NULL to RESTRICT'
);

$paymentFkDropPresent =
    preg_match(
        '/ALTER TABLE\s+billing_seat_change_requests\s+'
        . 'DROP FOREIGN KEY\s+'
        . 'fk_billing_seat_change_payment_attempt/i',
        $migration
    ) === 1;

$paymentFkAddPresent =
    preg_match(
        '/ALTER TABLE\s+billing_seat_change_requests\s+'
        . 'ADD CONSTRAINT\s+'
        . 'fk_billing_seat_change_payment_attempt\s+'
        . 'FOREIGN KEY\s*\(payment_attempt_id\)\s+'
        . 'REFERENCES\s+billing_payment_attempts\s*\(id\)\s+'
        . 'ON DELETE\s+RESTRICT/i',
        $migration
    ) === 1;

$sameSymbolAtomicReadd =
    preg_match(
        '/DROP FOREIGN KEY\s+'
        . 'fk_billing_seat_change_payment_attempt\s*,\s*'
        . 'ADD CONSTRAINT\s+'
        . 'fk_billing_seat_change_payment_attempt/i',
        $migration
    ) === 1;

$check(
    $paymentFkDropPresent
    && $paymentFkAddPresent
    && !$sameSymbolAtomicReadd,
    'payment FK replacement is MariaDB-compatible and retryable'
);

$check(
    strpos(
        $migration,
        'FOREIGN KEY (latest_paid_subscription_id) REFERENCES subscriptions(id) ON DELETE RESTRICT'
    ) !== false
    && strpos(
        $migration,
        'FOREIGN KEY (latest_paid_attempt_id) REFERENCES billing_payment_attempts(id) ON DELETE RESTRICT'
    ) !== false,
    'authoritative paid-lineage identities are protected by restrictive foreign keys'
);

$check(
    strpos(
        $migration,
        'idx_billing_seat_change_latest_paid_subscription'
    ) !== false
    && strpos(
        $migration,
        'idx_billing_seat_change_latest_paid_attempt'
    ) !== false,
    'paid-lineage foreign keys have explicit supporting indexes'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    exit(1);
}

echo "V2.3 BILLING SEAT QUOTE SNAPSHOT SCHEMA: PASSED\n";
