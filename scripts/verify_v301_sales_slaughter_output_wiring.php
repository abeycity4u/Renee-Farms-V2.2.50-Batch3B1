<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$paths = [
    'sales' =>
        $root . '/management/sales_records.php',

    'js' =>
        $root . '/assets/js/management-sales-records.js',

    'delete' =>
        $root . '/api/delete_sale.php',

    'service' =>
        $root . '/lib/ruminant_slaughter_sale_consumption.php',
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
    'Required Sales slaughter wiring sources are readable',
    !in_array('', $sources, true)
);

$check(
    'Existing Sales page loads central lot service',
    str_contains(
        $sources['sales'],
        'ruminant_slaughter_sale_consumption.php'
    )
);

$check(
    'Sales reads available lots and durable sale history centrally',
    str_contains(
        $sources['sales'],
        'ruminant_slaughter_sale_available_lots('
    )
    && str_contains(
        $sources['sales'],
        'ruminant_slaughter_sale_history_for_sales('
    )
);

$check(
    'Add and Edit share one explicit lot POST adapter',
    str_contains(
        $sources['sales'],
        '$prepareSlaughterSaleInput'
    )
    && str_contains(
        $sources['sales'],
        'ruminant_slaughter_sale_rows_from_post('
    )
    && str_contains(
        $sources['sales'],
        'ruminant_slaughter_sale_selection('
    )
);

$check(
    'Lot selection server-derives financial sale identity',
    str_contains(
        $sources['sales'],
        "\$input['farm_type']"
    )
    && str_contains(
        $sources['sales'],
        "\$input['production_type']"
    )
    && str_contains(
        $sources['sales'],
        "\$input['cycle_id']"
    )
    && str_contains(
        $sources['sales'],
        "\$input['product_type']"
    )
    && str_contains(
        $sources['sales'],
        "\$input['quantity']"
    )
    && str_contains(
        $sources['sales'],
        "\$selection['unit_of_measure']"
    )
);

$check(
    'Lot sale forces financial-only population behavior',
    str_contains(
        $sources['sales'],
        "\$input['population_effect_mode']"
    )
    && str_contains(
        $sources['sales'],
        "'financial_only'"
    )
);

$check(
    'Lot sale prevents duplicate tagged-animal exit ownership',
    str_contains(
        $sources['sales'],
        "\$input['sale_animal_allocation_mode']"
    )
    && str_contains(
        $sources['sales'],
        "'shared'"
    )
);

$check(
    'Add and Edit both synchronize through central lot service',
    substr_count(
        $sources['sales'],
        'ruminant_slaughter_sale_sync('
    ) === 2
);

$check(
    'Existing Add and Edit modals expose Sales Stock Source',
    str_contains(
        $sources['sales'],
        'id="addSaleStockSource"'
    )
    && str_contains(
        $sources['sales'],
        'id="editSaleStockSource"'
    )
);

$check(
    'Browser posts explicit lot ids and quantities',
    str_contains(
        $sources['js'],
        "'slaughter_output_ids[]'"
    )
    && str_contains(
        $sources['js'],
        "'slaughter_output_quantities[]'"
    )
);

$check(
    'Browser receives available lots and durable history',
    str_contains(
        $sources['sales'],
        'data-slaughter-sale-lots='
    )
    && str_contains(
        $sources['sales'],
        'data-slaughter-sale-history-map='
    )
    && str_contains(
        $sources['js'],
        'slaughterSaleHistoryMap'
    )
);

$check(
    'Browser derives and locks lot-owned fields',
    str_contains(
        $sources['js'],
        'refreshSlaughterDerived'
    )
    && str_contains(
        $sources['js'],
        'setSlaughterLocks'
    )
);

$check(
    'Edit browser restores active lot selection',
    str_contains(
        $sources['js'],
        'activeSlaughterSaleHistory'
    )
    && str_contains(
        $sources['js'],
        'loadEditSlaughterSale'
    )
);

$check(
    'Sales row protects durable lot history from hard delete',
    str_contains(
        $sources['sales'],
        '$slaughterSaleHistoryRows'
    )
    && str_contains(
        $sources['sales'],
        'cannot be hard-deleted'
    )
);

$check(
    'Delete API enforces central slaughter history guard',
    str_contains(
        $sources['delete'],
        'ruminant_slaughter_sale_assert_deletable('
    )
    && str_contains(
        $sources['delete'],
        'catch(RuminantSlaughterSaleException $e)'
    )
);

$check(
    'Existing receivable revenue population and animal services remain wired',
    str_contains(
        $sources['sales'],
        'receivable_sync_sale_edit('
    )
    && str_contains(
        $sources['sales'],
        'sales_refresh_automatic_allocation('
    )
    && str_contains(
        $sources['sales'],
        'sale_population_effect_sync('
    )
    && str_contains(
        $sources['sales'],
        'ruminant_sale_save_animal_allocations('
    )
);

$check(
    'Lot service still delegates physical movement to canonical stock writer',
    str_contains(
        $sources['service'],
        'stock_apply_movement('
    )
    && !preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+stock_items/i',
        $sources['service']
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
