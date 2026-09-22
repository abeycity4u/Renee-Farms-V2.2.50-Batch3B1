<?php

declare(strict_types=1);

/**
 * V3.0.1 — slaughter-output Sales lot-consumption foundation verifier.
 * Source-only: no database connection and no business mutation.
 */

$root = dirname(__DIR__);

$paths = [
    'migration' =>
        $root . '/migrations/074_ruminant_slaughter_sale_lot_consumption.sql',

    'service' =>
        $root . '/lib/ruminant_slaughter_sale_consumption.php',

    'stock' =>
        $root . '/lib/stock_service.php',

    'role' =>
        $root . '/lib/inventory_category_role.php',
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

$check =
    static function (
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
    'Required slaughter-sale lot sources are readable',
    !in_array('', $sources, true)
);

$check(
    'Migration creates durable sale-to-output lot allocation history',
    str_contains(
        $sources['migration'],
        'CREATE TABLE IF NOT EXISTS ruminant_slaughter_sale_allocations'
    )
    && str_contains(
        $sources['migration'],
        'sale_id INT NOT NULL'
    )
    && str_contains(
        $sources['migration'],
        'output_id BIGINT UNSIGNED NOT NULL'
    )
);

$check(
    'Lot history retains both stock decrement and reversal transaction identity',
    str_contains(
        $sources['migration'],
        'stock_transaction_id INT NULL'
    )
    && str_contains(
        $sources['migration'],
        'reversal_stock_transaction_id INT NULL'
    )
);

$check(
    'Lot history stores quantity and frozen COGS snapshots',
    str_contains(
        $sources['migration'],
        'quantity DECIMAL(12,2)'
    )
    && str_contains(
        $sources['migration'],
        'unit_cost_snapshot DECIMAL(14,4)'
    )
    && str_contains(
        $sources['migration'],
        'total_cost_snapshot DECIMAL(14,2)'
    )
);

$check(
    'Migration performs no guessed historical sale backfill',
    !preg_match(
        '/INSERT\s+INTO\s+ruminant_slaughter_sale_allocations\s*\([^;]+SELECT/is',
        $sources['migration']
    )
);

$check(
    'Sale lot selection is explicit and never inferred from product text',
    str_contains(
        $sources['service'],
        "sale_stock_source"
    )
    && str_contains(
        $sources['service'],
        "slaughter_output_ids"
    )
    && str_contains(
        $sources['service'],
        "slaughter_output_quantities"
    )
);

$check(
    'One sale line is constrained to one item unit production and cycle',
    str_contains(
        $sources['service'],
        'same product, unit, production type and production cycle'
    )
);

$check(
    'Selection refuses sales dated before slaughter',
    str_contains(
        $sources['service'],
        'Sale date cannot be earlier than the slaughter date'
    )
);

$check(
    'Selection validates requested quantity against exact lot availability',
    str_contains(
        $sources['service'],
        'remaining_quantity'
    )
    && str_contains(
        $sources['service'],
        'exceeds the available lot balance'
    )
);

$check(
    'Physical sale decrement delegates to canonical stock writer',
    preg_match(
        "/stock_apply_movement\(.*?'used'.*?'ruminant_slaughter_sale'/s",
        $sources['service']
    ) === 1
);

$check(
    'Sale lot service contains no direct stock item mutation',
    !preg_match(
        '/UPDATE\s+stock_items/i',
        $sources['service']
    )
    && !preg_match(
        '/INSERT\s+INTO\s+stock_transactions/i',
        $sources['service']
    )
);

$check(
    'Source output remaining quantity changes with sale lot movement',
    str_contains(
        $sources['service'],
        'UPDATE ruminant_slaughter_outputs'
    )
    && str_contains(
        $sources['service'],
        'remaining_quantity=?'
    )
);

$check(
    'Lot-specific outgoing COGS is passed to canonical stock writer',
    str_contains(
        $sources['service'],
        '$unitCost'
    )
    && str_contains(
        $sources['service'],
        '$totalCost'
    )
    && str_contains(
        $sources['stock'],
        '?float $outgoingUnitCost = null'
    )
    && str_contains(
        $sources['stock'],
        '?float $outgoingTotalCost = null'
    )
);

$check(
    'Final depletion conserves remaining frozen output cost after rounding',
    str_contains(
        $sources['service'],
        '$newRemaining <= 0.00001'
    )
    && str_contains(
        $sources['service'],
        '$allocatedCost'
    )
    && str_contains(
        $sources['service'],
        '$alreadyCost'
    )
);

$check(
    'Slaughter-output role permits only sourced sale usage and sourced restoration',
    str_contains(
        $sources['role'],
        "'ruminant_slaughter_sale'"
    )
    && str_contains(
        $sources['role'],
        "'ruminant_slaughter_sale_reversal'"
    )
);

$check(
    'Sale correction creates append-only stock restoration and reversal linkage',
    str_contains(
        $sources['service'],
        "'ruminant_slaughter_sale_reversal'"
    )
    && str_contains(
        $sources['service'],
        'SET is_reversed=1'
    )
    && str_contains(
        $sources['service'],
        'reversal_of_id=?'
    )
);

$check(
    'Batch completion derives from zero remaining output quantity',
    str_contains(
        $sources['service'],
        'ruminant_slaughter_sale_refresh_batch_status'
    )
    && str_contains(
        $sources['service'],
        "'completed'"
    )
    && str_contains(
        $sources['service'],
        "'open'"
    )
);

$check(
    'Sale lot service owns no population mutation',
    !str_contains(
        $sources['service'],
        'production_population_movements'
    )
    && !str_contains(
        $sources['service'],
        'production_population_projection_sync'
    )
);

$check(
    'Sales with slaughter lot history are protected from hard delete',
    str_contains(
        $sources['service'],
        'function ruminant_slaughter_sale_assert_deletable('
    )
    && str_contains(
        $sources['service'],
        'cannot be hard-deleted'
    )
);

$check(
    'Migration records checkpoint 074',
    str_contains(
        $sources['migration'],
        '074_ruminant_slaughter_sale_lot_consumption.sql'
    )
);

echo PHP_EOL;
echo 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . (
        $failures === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
