<?php
/**
 * Renee Farms V3.0.1
 * Record-reference persistence/backfill verifier.
 *
 * Source-only.
 * No PDO connection.
 * No database writes.
 */

$root =
    dirname(__DIR__);

$persistencePath =
    $root
    . '/lib/record_reference_persistence.php';

$backfillPath =
    $root
    . '/scripts/backfill_record_references.php';

$expenseProvenancePath =
    $root
    . '/lib/expense_revision_provenance.php';

$stockProvenancePath =
    $root
    . '/lib/stock_consumption_allocation_provenance.php';

$persistence =
    is_file($persistencePath)
        ? (string)file_get_contents(
            $persistencePath
        )
        : '';

$backfill =
    is_file($backfillPath)
        ? (string)file_get_contents(
            $backfillPath
        )
        : '';

$expenseProvenance =
    is_file($expenseProvenancePath)
        ? (string)file_get_contents(
            $expenseProvenancePath
        )
        : '';

$stockProvenance =
    is_file($stockProvenancePath)
        ? (string)file_get_contents(
            $stockProvenancePath
        )
        : '';

$checks = 0;
$failures = 0;

$check =
    static function (
        bool $ok,
        string $label
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo
            ($ok ? '[PASS] ' : '[FAIL] ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };

$check(
    $persistence !== '',
    'Central record-reference persistence service exists'
);

$check(
    $backfill !== '',
    'Guarded legacy backfill worker exists'
);

$check(
    strpos(
        $persistence,
        "require_once __DIR__ . '/record_reference.php';"
    ) !== false,
    'Persistence delegates format generation to pure reference foundation'
);

foreach ([
    'stock_transactions'
        => 'uniq_stock_transaction_public_reference',

    'farm_expenses'
        => 'uniq_farm_expense_public_reference',

    'sales_records'
        => 'uniq_sale_public_reference',
] as $table => $index) {
    $check(
        strpos(
            $persistence,
            "'table' =>\n                    '{$table}'"
        ) !== false
        &&
        strpos(
            $persistence,
            "'unique_index' =>\n                    '{$index}'"
        ) !== false,
        $table
            . ' has fixed canonical persistence mapping'
    );
}

$check(
    substr_count(
        $persistence,
        'function record_reference_persistence_with_retry('
    ) === 1,
    'Collision retry has one central implementation'
);

$check(
    strpos(
        $persistence,
        'record_reference_generate('
    ) !== false,
    'Collision retry delegates candidate generation centrally'
);

$check(
    strpos(
        $persistence,
        "\$sqlState === '23000'"
    ) !== false
    &&
    strpos(
        $persistence,
        '$driverCode === 1062'
    ) !== false,
    'Collision recognition requires MySQL duplicate-key identity'
);

$check(
    strpos(
        $persistence,
        "\$storage['unique_index']"
    ) !== false,
    'Collision recognition is restricted to the expected reference index'
);

$check(
    preg_match(
        '/UPDATE\s+\{\$table\}[\s\S]*?SET\s+public_reference=\?[\s\S]*?WHERE\s+id=\?[\s\S]*?AND\s+farm_id=\?[\s\S]*?AND\s+public_reference\s+IS\s+NULL/i',
        $persistence
    ) === 1,
    'Existing assignment is tenant-scoped and NULL-only'
);

$check(
    preg_match(
        '/function\s+record_reference_persistence_assign_existing\s*\(\s*PDO\s+\$pdo\s*,\s*string\s+\$entity\s*,\s*int\s+\$farmId\s*,\s*int\s+\$recordId\s*\)/s',
        $persistence
    ) === 1,
    'Assignment API does not permit caller-supplied creation dates'
);

$check(
    preg_match(
        '/SELECT[\s\S]*?public_reference,[\s\S]*?created_at[\s\S]*?FROM\s+\{\$table\}[\s\S]*?WHERE\s+id=\?[\s\S]*?AND\s+farm_id=\?[\s\S]*?FOR\s+UPDATE/i',
        $persistence
    ) === 1,
    'Assignment reads immutable creation timestamp from the locked source row'
);

$check(
    strpos(
        $persistence,
        'transaction_date'
    ) === false
    &&
    strpos(
        $persistence,
        'expense_date'
    ) === false
    &&
    strpos(
        $persistence,
        'sale_date'
    ) === false,
    'Backfill never derives reference date from editable business dates'
);

$check(
    strpos(
        $backfill,
        "PHP_SAPI !== 'cli'"
    ) !== false,
    'Backfill worker is CLI-only'
);

$check(
    strpos(
        $backfill,
        "in_array(\n        '--apply'"
    ) !== false,
    'Backfill requires explicit --apply mutation flag'
);

$check(
    strpos(
        $backfill,
        'MODE=DRY_RUN'
    ) !== false
    &&
    strpos(
        $backfill,
        'DRY_RUN_ONLY=YES'
    ) !== false,
    'Backfill defaults to read-only dry-run behavior'
);

$check(
    strpos(
        $backfill,
        '066_human_facing_record_references.sql'
    ) !== false,
    'Backfill requires migration 066 prerequisite'
);

$check(
    strpos(
        $backfill,
        'record_reference_persistence_backfill_batch('
    ) !== false,
    'Backfill mutation delegates to central persistence service'
);

$check(
    preg_match(
        '/UPDATE\s+(stock_transactions|farm_expenses|sales_records)/i',
        $backfill
    ) !== 1,
    'Backfill command owns no duplicated business-table UPDATE SQL'
);

$check(
    strpos(
        $expenseProvenance,
        'public_reference'
    ) === false,
    'Expense provenance fingerprints remain independent of display reference'
);

$check(
    strpos(
        $stockProvenance,
        'public_reference'
    ) === false,
    'Stock allocation provenance fingerprints remain independent of display reference'
);

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
