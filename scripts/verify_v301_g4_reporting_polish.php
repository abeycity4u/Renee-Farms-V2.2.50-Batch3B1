<?php

/**
 * V3.0.1 G4A Financial Attribution Reporting Polish.
 *
 * Source-only verifier.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$paths = [
    'management_expenses_js' =>
        $root . '/assets/js/management-expenses.js',

    'ruminant_expenses' =>
        $root . '/ruminant/ruminant_expenses.php',

    'sales_pdf' =>
        $root . '/management/sales_report_pdf.php',

    'expense_pdf' =>
        $root . '/management/expense_report_pdf.php',

    'expense_allocation' =>
        $root . '/management/expense_allocation.php',

    'stock_allocation' =>
        $root . '/management/stock_consumption_allocation.php',
];

$source = [];

foreach ($paths as $name => $path) {
    $source[$name] =
        file_get_contents($path);

    if ($source[$name] === false) {
        echo "RESULT=FAIL\n";
        echo "FAILED=SOURCE_LOAD:$name\n";
        echo "DATABASE_CONNECTION=NONE\n";
        echo "DATABASE_WRITE=NONE\n";
        exit(1);
    }
}

$checks = [];
$failures = [];

$check =
    static function (
        string $name,
        bool $ok
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks[$name] = $ok;

        if (!$ok) {
            $failures[] = $name;
        }
    };

$check(
    'MANAGEMENT_EXPENSE_SHARED_LABELS_CANONICAL',
    strpos(
        $source['management_expenses_js'],
        "shared:'Shared Poultry / Other Poultry'"
    ) !== false
    &&
    strpos(
        $source['management_expenses_js'],
        "shared:'Shared Ruminant / Other Ruminant'"
    ) !== false
);

$check(
    'RUMINANT_ADD_FORM_SHARED_LABEL_CANONICAL',
    strpos(
        $source['ruminant_expenses'],
        '>Shared Ruminant / Other Ruminant</option>'
    ) !== false
);

$check(
    'RUMINANT_COPY_SEPARATES_SOURCE_FROM_ALLOCATION_STATE',
    strpos(
        $source['ruminant_expenses'],
        'Shared Ruminant records the source attribution.'
    ) !== false
    &&
    strpos(
        $source['ruminant_expenses'],
        'any unallocated remainder is handled separately in the allocation workspace.'
    ) !== false
);

$combined =
    implode(
        "\n",
        array_values($source)
    );

$check(
    'STALE_SHARED_UNALLOCATED_LABELS_RETIRED',
    strpos(
        $combined,
        'Shared / Unallocated'
    ) === false
);

$check(
    'STALE_SHARED_UNASSIGNED_LABEL_RETIRED',
    strpos(
        $combined,
        'Shared / Unassigned'
    ) === false
);

$check(
    'SALES_PDF_USES_CANONICAL_PRODUCTION_LABELS',
    strpos(
        $source['sales_pdf'],
        'attribution_production_label('
    ) !== false
    &&
    strpos(
        $source['sales_pdf'],
        '$saleProductionLabel($sale)'
    ) !== false
);

$check(
    'SALES_PDF_USES_CANONICAL_CYCLE_LABELS',
    strpos(
        $source['sales_pdf'],
        'attribution_cycle_label('
    ) !== false
    &&
    strpos(
        $source['sales_pdf'],
        '$saleCycleLabel($sale)'
    ) !== false
    &&
    strpos(
        $source['sales_pdf'],
        "return 'No production cycle';"
    ) !== false
);

$check(
    'EXPENSE_PDF_USES_CANONICAL_PRODUCTION_LABELS',
    substr_count(
        $source['expense_pdf'],
        'attribution_production_label('
    ) >= 2
);

$check(
    'RAW_EXPENSE_PRIMARY_KEY_HIDDEN_FROM_WORKSPACE',
    strpos(
        $source['expense_allocation'],
        "#<?php echo (int)\$expense['id']; ?>"
    ) === false
    &&
    strpos(
        $source['expense_allocation'],
        'Expense Category'
    ) !== false
);

$check(
    'RAW_STOCK_MOVEMENT_PRIMARY_KEY_HIDDEN_FROM_WORKSPACE',
    strpos(
        $source['stock_allocation'],
        "#<?php echo (int)\$movement['id']; ?>"
    ) === false
    &&
    strpos(
        $source['stock_allocation'],
        'Stock Movement'
    ) === false
);

$check(
    'INTERNAL_ROUTING_IDENTIFIERS_PRESERVED',
    strpos(
        $source['expense_allocation'],
        'data-expense-id='
    ) !== false
    &&
    strpos(
        $source['stock_allocation'],
        '$stockTransactionId'
    ) !== false
);

$check(
    'SALES_PDF_EMPTY_STATE_MATCHES_13_COLUMNS',
    strpos(
        $source['sales_pdf'],
        '<th>Recorded By</th>'
    ) !== false
    &&
    strpos(
        $source['sales_pdf'],
        'colspan="13">No sales records for this period.'
    ) !== false
    &&
    strpos(
        $source['sales_pdf'],
        'colspan="12">No sales records for this period.'
    ) === false
);

foreach ($checks as $name => $ok) {
    echo $name
        . '='
        . ($ok ? 'PASS' : 'FAIL')
        . "\n";
}

echo 'CHECK_COUNT='
    . count($checks)
    . "\n";

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

if ($failures) {
    echo 'FAILED='
        . implode(',', $failures)
        . "\n";

    echo "RESULT=FAIL\n";
    exit(1);
}

echo "RESULT=PASS\n";
