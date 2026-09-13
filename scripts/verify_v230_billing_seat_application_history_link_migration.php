<?php
/**
 * V2.3 Gap D Phase 3B Foundation 1 verifier.
 *
 * Safe in both states:
 * - before migration 051: proves the migration is safe to apply and that no
 *   legacy applied seat top-up requires backfill;
 * - after migration 051: proves the exact column/index/FK/migration contract.
 *
 * This verifier performs SELECT / information_schema reads only.
 */

require_once __DIR__ . '/../init.php';

$migrationPath =
    __DIR__
    . '/../migrations/051_billing_seat_application_history_link.sql';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS' : 'FAIL')
        . ': '
        . $message
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    is_file($migrationPath),
    'migration 051 exists'
);

$sql = is_file($migrationPath)
    ? (string)file_get_contents($migrationPath)
    : '';

$check(
    strpos(
        $sql,
        '051_billing_seat_application_history_link.sql'
    ) !== false,
    'migration records canonical filename'
);

$check(
    preg_match(
        '/ADD\s+COLUMN\s+'
        . 'applied_subscription_record_id\s+INT\s+NULL'
        . '\s+AFTER\s+payment_attempt_id/i',
        $sql
    ) === 1,
    'migration adds nullable signed INT applied-history identity'
);

$check(
    strpos(
        $sql,
        'idx_billing_seat_change_applied_subscription'
    ) !== false
    && preg_match(
        '/ADD\s+INDEX\s+'
        . 'idx_billing_seat_change_applied_subscription\s*'
        . '\(\s*applied_subscription_record_id\s*\)/i',
        $sql
    ) === 1,
    'migration adds explicit applied-history index'
);

$check(
    strpos(
        $sql,
        'fk_billing_seat_change_applied_subscription'
    ) !== false
    && preg_match(
        '/FOREIGN\s+KEY\s*'
        . '\(\s*applied_subscription_record_id\s*\)\s*'
        . 'REFERENCES\s+subscriptions\s*'
        . '\(\s*id\s*\)\s*'
        . 'ON\s+DELETE\s+RESTRICT/i',
        $sql
    ) === 1,
    'migration binds applied history to immutable subscriptions with RESTRICT'
);

$protectedDml =
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+'
        . '(?:billing_seat_change_requests|subscriptions|farms|'
        . 'farm_modules|farm_role_limits|farm_subscription_seat_addons|'
        . 'billing_payment_attempts|billing_refund_resolutions)\b/i',
        $sql
    );

$check(
    $protectedDml === 0,
    'migration contains no commercial-state data mutation or backfill'
);

$check(
    stripos($sql, 'curl_') === false
    && stripos($sql, 'paystack') === false
    && stripos($sql, 'flutterwave') === false,
    'migration contains no provider or network operation'
);

$appliedSeatTopups =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_seat_change_requests
         WHERE change_kind = 'add'
           AND status = 'applied'
           AND applied_at IS NOT NULL"
    )->fetchColumn();

echo 'APPLIED_SEAT_TOPUPS='
    . $appliedSeatTopups
    . PHP_EOL;

$columnStmt =
    $pdo->prepare(
        "SELECT
             column_type,
             is_nullable
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'billing_seat_change_requests'
           AND column_name = 'applied_subscription_record_id'
         LIMIT 1"
    );

$columnStmt->execute();

$column =
    $columnStmt->fetch(PDO::FETCH_ASSOC)
    ?: null;

$migrationStmt =
    $pdo->prepare(
        "SELECT COUNT(*)
         FROM schema_migrations
         WHERE filename = ?"
    );

$migrationStmt->execute([
    '051_billing_seat_application_history_link.sql',
]);

$migrationRecorded =
    (int)$migrationStmt->fetchColumn();

if ($column === null) {
    echo 'SCHEMA_STATE=PRE_MIGRATION'
        . PHP_EOL;

    $check(
        $appliedSeatTopups === 0,
        'pre-migration state has zero applied paid seat top-ups requiring backfill'
    );

    $check(
        $migrationRecorded === 0,
        'pre-migration state has not falsely recorded migration 051'
    );
} else {
    echo 'SCHEMA_STATE=POST_MIGRATION'
        . PHP_EOL;

    $columnType =
        strtolower(
            trim(
                (string)(
                    $column['column_type']
                    ?? ''
                )
            )
        );

    $nullable =
        strtoupper(
            trim(
                (string)(
                    $column['is_nullable']
                    ?? ''
                )
            )
        );

    $check(
        preg_match(
            '/^int(?:\(\d+\))?$/',
            $columnType
        ) === 1
        && $nullable === 'YES',
        'applied-history column is nullable signed INT'
    );

    $indexStmt =
        $pdo->prepare(
            "SELECT
                 COUNT(*)
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'billing_seat_change_requests'
               AND index_name =
                   'idx_billing_seat_change_applied_subscription'
               AND column_name =
                   'applied_subscription_record_id'"
        );

    $indexStmt->execute();

    $check(
        (int)$indexStmt->fetchColumn() === 1,
        'post-migration applied-history index is installed'
    );

    $fkStmt =
        $pdo->prepare(
            "SELECT
                 kcu.column_name,
                 kcu.referenced_table_name,
                 kcu.referenced_column_name,
                 rc.delete_rule
             FROM information_schema.referential_constraints rc
             INNER JOIN information_schema.key_column_usage kcu
               ON kcu.constraint_schema =
                  rc.constraint_schema
              AND kcu.constraint_name =
                  rc.constraint_name
              AND kcu.table_name =
                  rc.table_name
             WHERE rc.constraint_schema = DATABASE()
               AND rc.table_name =
                   'billing_seat_change_requests'
               AND rc.constraint_name =
                   'fk_billing_seat_change_applied_subscription'
             LIMIT 1"
        );

    $fkStmt->execute();

    $fk =
        $fkStmt->fetch(PDO::FETCH_ASSOC)
        ?: null;

    $check(
        is_array($fk)
        && (string)$fk['column_name']
            === 'applied_subscription_record_id'
        && (string)$fk['referenced_table_name']
            === 'subscriptions'
        && (string)$fk['referenced_column_name']
            === 'id'
        && strtoupper(
            (string)$fk['delete_rule']
        ) === 'RESTRICT',
        'post-migration applied-history FK is exact and RESTRICT'
    );

    $check(
        $migrationRecorded === 1,
        'migration 051 is recorded exactly once'
    );
}

echo 'CHECKS='
    . $checks
    . PHP_EOL;

echo 'FAILURES='
    . $failures
    . PHP_EOL;

echo $failures === 0
    ? 'V2.3 SEAT APPLICATION HISTORY LINK MIGRATION: PASSED'
    : 'V2.3 SEAT APPLICATION HISTORY LINK MIGRATION: FAILED';

echo PHP_EOL;

exit($failures === 0 ? 0 : 1);
