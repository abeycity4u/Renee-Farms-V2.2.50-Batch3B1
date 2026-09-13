<?php
/**
 * V2.3 Gap D Phase 3B migration 052 verifier.
 *
 * Read-only.
 *
 * Valid states:
 * - PRE_MIGRATION:
 *   column/index/FK/migration record are all absent.
 *
 * - POST_MIGRATION:
 *   column/index/FK/migration record are all present and exact.
 *
 * Any partial state fails closed.
 */

require_once __DIR__ . '/../init.php';

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

$tableExistsStmt =
    $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'billing_refund_resolutions'"
    );

$tableExistsStmt->execute();

$tableExists =
    (int)$tableExistsStmt->fetchColumn() === 1;

$check(
    $tableExists,
    'billing_refund_resolutions table exists'
);

$columnStmt =
    $pdo->prepare(
        "SELECT
             column_type,
             is_nullable
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'billing_refund_resolutions'
           AND column_name =
               'reversal_subscription_record_id'
         LIMIT 1"
    );

$columnStmt->execute();

$column =
    $columnStmt->fetch(PDO::FETCH_ASSOC)
    ?: null;

$columnPresent =
    is_array($column);

$columnExact =
    $columnPresent
    && preg_match(
        '/^int(?:\([0-9]+\))?$/i',
        (string)(
            $column['column_type']
            ?? ''
        )
    ) === 1
    && strtoupper(
        (string)(
            $column['is_nullable']
            ?? ''
        )
    ) === 'YES';

$indexStmt =
    $pdo->prepare(
        "SELECT
             column_name,
             seq_in_index,
             non_unique
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = 'billing_refund_resolutions'
           AND index_name =
               'idx_billing_refund_reversal_subscription'
         ORDER BY seq_in_index"
    );

$indexStmt->execute();

$indexRows =
    $indexStmt->fetchAll(PDO::FETCH_ASSOC)
    ?: [];

$indexPresent =
    count($indexRows) > 0;

$indexExact =
    count($indexRows) === 1
    && (string)(
        $indexRows[0]['column_name']
        ?? ''
    ) === 'reversal_subscription_record_id'
    && (int)(
        $indexRows[0]['seq_in_index']
        ?? 0
    ) === 1
    && (int)(
        $indexRows[0]['non_unique']
        ?? 0
    ) === 1;

$fkStmt =
    $pdo->prepare(
        "SELECT
             rc.table_name,
             rc.referenced_table_name,
             rc.delete_rule,
             kcu.column_name,
             kcu.referenced_column_name
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
               'billing_refund_resolutions'
           AND rc.constraint_name =
               'fk_billing_refund_reversal_subscription'
         LIMIT 1"
    );

$fkStmt->execute();

$fk =
    $fkStmt->fetch(PDO::FETCH_ASSOC)
    ?: null;

$fkPresent =
    is_array($fk);

$deleteRule =
    $fkPresent
        ? strtoupper(trim(
            (string)(
                $fk['delete_rule']
                ?? ''
            )
        ))
        : '';

$fkExact =
    $fkPresent
    && (string)(
        $fk['table_name']
        ?? ''
    ) === 'billing_refund_resolutions'
    && (string)(
        $fk['column_name']
        ?? ''
    ) === 'reversal_subscription_record_id'
    && (string)(
        $fk['referenced_table_name']
        ?? ''
    ) === 'subscriptions'
    && (string)(
        $fk['referenced_column_name']
        ?? ''
    ) === 'id'
    && in_array(
        $deleteRule,
        [
            'RESTRICT',
            'NO ACTION',
        ],
        true
    );

$migrationStmt =
    $pdo->prepare(
        "SELECT COUNT(*)
         FROM schema_migrations
         WHERE filename = ?"
    );

$migrationStmt->execute([
    '052_billing_refund_reversal_history_link.sql',
]);

$migrationCount =
    (int)$migrationStmt->fetchColumn();

$migrationPresent =
    $migrationCount > 0;

$presence = [
    'column' =>
        $columnPresent,
    'index' =>
        $indexPresent,
    'fk' =>
        $fkPresent,
    'migration' =>
        $migrationPresent,
];

$presentCount =
    count(
        array_filter(
            $presence,
            static fn(bool $value): bool =>
                $value
        )
    );

$schemaState = 'PARTIAL_INVALID';

if ($presentCount === 0) {
    $schemaState =
        'PRE_MIGRATION';
} elseif ($presentCount === 4) {
    $schemaState =
        'POST_MIGRATION';
}

echo 'SCHEMA_STATE='
    . $schemaState
    . PHP_EOL;

$check(
    $schemaState !== 'PARTIAL_INVALID',
    'migration 052 is either wholly absent or wholly present'
);

if ($schemaState === 'PRE_MIGRATION') {
    $check(
        $migrationCount === 0,
        'migration 052 is not recorded before deployment'
    );

    $check(
        $columnPresent === false,
        'reversal history column is absent before deployment'
    );

    $check(
        $indexPresent === false,
        'reversal history index is absent before deployment'
    );

    $check(
        $fkPresent === false,
        'reversal history foreign key is absent before deployment'
    );
}

if ($schemaState === 'POST_MIGRATION') {
    $check(
        $columnExact,
        'reversal history column is nullable INT'
    );

    $check(
        $indexExact,
        'reversal history index targets exactly the reversal history column'
    );

    $check(
        $fkExact,
        'reversal history foreign key targets subscriptions(id) with RESTRICT semantics'
    );

    $check(
        $migrationCount === 1,
        'migration 052 is recorded exactly once'
    );
}

$refundCount =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_refund_resolutions"
    )->fetchColumn();

echo 'REFUND_RESOLUTION_ROWS='
    . $refundCount
    . PHP_EOL;

$check(
    $refundCount === 0,
    'production has no historical refund-resolution row requiring backfill'
);

$protectedAttemptCount =
    (int)$pdo->query(
        "SELECT COUNT(*)
         FROM billing_payment_attempts
         WHERE id IN (21, 22)"
    )->fetchColumn();

$check(
    $protectedAttemptCount === 2,
    'protected payment attempts 21 and 22 remain present'
);

echo 'CHECKS='
    . $checks
    . PHP_EOL;

echo 'FAILURES='
    . $failures
    . PHP_EOL;

echo $failures === 0
    ? 'V2.3 REFUND REVERSAL HISTORY LINK MIGRATION: PASSED'
    : 'V2.3 REFUND REVERSAL HISTORY LINK MIGRATION: FAILED';

echo PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
