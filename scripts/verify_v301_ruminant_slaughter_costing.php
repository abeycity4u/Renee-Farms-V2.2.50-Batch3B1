<?php

declare(strict_types=1);

/**
 * V3.0.1 — Slaughter output costing verifier.
 * Source-only; no DB connection or business mutation.
 */

$root = dirname(__DIR__);

$paths = [
    'migration' =>
        $root . '/migrations/071_ruminant_slaughter_output_costing.sql',
    'costing' =>
        $root . '/lib/ruminant_slaughter_costing.php',
    'service' =>
        $root . '/lib/ruminant_slaughter_processing.php',
    'page' =>
        $root . '/ruminant/slaughter_processing.php',
    'inventory' =>
        $root . '/inventory.php',
    'inventory_js' =>
        $root . '/assets/js/inventory.js',
    'stock' =>
        $root . '/lib/stock_service.php',

    'precision_migration' =>
        $root . '/migrations/072_stock_receipt_cost_precision.sql',
];

$sources = [];

foreach ($paths as $key => $path) {
    $sources[$key] =
        is_file($path)
        && is_readable($path)
            ? (string)file_get_contents($path)
            : '';
}

$checks = 0;
$failures = 0;

$check = static function (
    string $label,
    bool $ok
) use (
    &$checks,
    &$failures
): void {
    $checks++;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    'Required slaughter-costing sources are readable',
    !in_array('', $sources, true)
);

$check(
    'Migration stores frozen batch cost components',
    str_contains(
        $sources['migration'],
        'cost_basis_amount DECIMAL(14,2)'
    )
    && str_contains(
        $sources['migration'],
        'cost_basis_purchase DECIMAL(14,2)'
    )
    && str_contains(
        $sources['migration'],
        'cost_basis_direct_expense DECIMAL(14,2)'
    )
    && str_contains(
        $sources['migration'],
        'cost_basis_shared DECIMAL(14,2)'
    )
    && str_contains(
        $sources['migration'],
        'cost_basis_snapshot_at DATETIME'
    )
);

$check(
    'Migration stores per-output cost allocation snapshot',
    str_contains(
        $sources['migration'],
        'cost_share_percent DECIMAL(7,4)'
    )
    && str_contains(
        $sources['migration'],
        'allocated_cost DECIMAL(14,2)'
    )
    && str_contains(
        $sources['migration'],
        'unit_cost_snapshot DECIMAL(14,4)'
    )
);

$check(
    'Migration performs no guessed historical costing backfill',
    !preg_match(
        '/UPDATE\s+ruminant_slaughter_(?:batches|outputs)/i',
        $sources['migration']
    )
);

$check(
    'Cost basis excludes Sales and selling price',
    !str_contains(
        $sources['costing'],
        'sales_records'
    )
    && !str_contains(
        $sources['costing'],
        'unit_price'
    )
);

$check(
    'Cost basis includes purchase, direct animal expense and shared cost',
    str_contains(
        $sources['costing'],
        'purchase_cost'
    )
    && str_contains(
        $sources['costing'],
        'ruminant_expense_animal_allocations'
    )
    && str_contains(
        $sources['costing'],
        'ruminant_shared_cost_economics('
    )
);

$check(
    'Cost basis is date-bounded to slaughter date',
    str_contains(
        $sources['costing'],
        'e.expense_date<=?'
    )
    && str_contains(
        $sources['costing'],
        '$sourceDate > $asOfDate'
    )
);

$check(
    'Batch freezes cost basis before first output receipt',
    str_contains(
        $sources['service'],
        "cost_basis_amount'] === null"
    )
    && str_contains(
        $sources['service'],
        'ruminant_slaughter_costing_as_of('
    )
    && str_contains(
        $sources['service'],
        'cost_basis_snapshot_at=NOW()'
    )
);

$check(
    'Output requires explicit batch cost share and caps total at 100 percent',
    str_contains(
        $sources['service'],
        '$costSharePercent'
    )
    && str_contains(
        $sources['service'],
        '$newPercent > 100.0001'
    )
);

$check(
    'Final allocation conserves remaining batch cost after rounding',
    str_contains(
        $sources['service'],
        '$newPercent >= 99.9999'
    )
    && str_contains(
        $sources['service'],
        '$batchCost'
    )
    && str_contains(
        $sources['service'],
        '$alreadyCost'
    )
);

$check(
    'Calculated output unit cost is passed to canonical stock writer',
    str_contains(
        $sources['service'],
        '$unitCostSnapshot'
    )
    && str_contains(
        $sources['service'],
        'stock_apply_movement('
    )
    && str_contains(
        $sources['service'],
        "'ruminant_slaughter_output'"
    )
);

$check(
    'Zero-cost sourced receipt participates in weighted-average costing',
    str_contains(
        $sources['stock'],
        'if ($incomingUnitCost !== null)'
    )
    && str_contains(
        $sources['stock'],
        '$incomingUnitCost < 0'
    )
    && !str_contains(
        $sources['stock'],
        '$incomingUnitCost <= 0'
    )
);

$check(
    'Slaughter writer preserves exact allocated output total in stock receipt',
    str_contains(
        $sources['service'],
        "\$unitCostSnapshot,\n            (string)\$batch['production_type'],\n            \$allocatedCost"
    )
);

$check(
    'Current Inventory unit-cost cache has four-decimal schema precision',
    str_contains(
        $sources['precision_migration'],
        'DECIMAL(14,4)'
    )
    && str_contains(
        $sources['precision_migration'],
        'MODIFY COLUMN unit_cost'
    )
);

$check(
    'Processing UI separates batch cost allocation from selling price',
    str_contains(
        $sources['page'],
        'Batch cost share (%)'
    )
    && str_contains(
        $sources['page'],
        'selling price remains a'
    )
    && str_contains(
        $sources['page'],
        'Frozen cost basis:'
    )
);

$check(
    'Slaughter output Add Item UI locks Unit Cost for automatic costing',
    str_contains(
        $sources['inventory'],
        'id="addItemUnitCost"'
    )
    && str_contains(
        $sources['inventory_js'],
        'addItemUnitCost'
    )
    && str_contains(
        $sources['inventory_js'],
        'Calculated automatically from the slaughter batch cost basis'
    )
);

$check(
    'Migration records checkpoint 071',
    str_contains(
        $sources['migration'],
        '071_ruminant_slaughter_output_costing.sql'
    )
);

echo PHP_EOL;
echo 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . ($failures === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
