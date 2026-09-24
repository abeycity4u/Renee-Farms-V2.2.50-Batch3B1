<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$paths = [
    'sales' =>
        $root
        .
        '/management/sales_records.php',

    'js' =>
        $root
        .
        '/assets/js/management-sales-records.js',

    'delete' =>
        $root
        .
        '/api/delete_sale.php',

    'dispatch' =>
        $root
        .
        '/lib/slaughter_output_sale_dispatch.php',

    'poultry' =>
        $root
        .
        '/lib/poultry_slaughter_sale_consumption.php',

    'ruminant' =>
        $root
        .
        '/lib/ruminant_slaughter_sale_consumption.php',

    'shared_sale_stock' =>
        $root
        .
        '/lib/slaughter_output_sale_stock.php',
];

$sources = [];

foreach (
    $paths
    as $key => $path
) {
    $sources[$key] =
        is_file($path)
        &&
        is_readable($path)
            ? (string)file_get_contents(
                $path
            )
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

        echo
            ($ok ? 'PASS: ' : 'FAIL: ')
            .
            $label
            .
            PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };


$check(
    'Required Sales slaughter wiring sources are readable',
    !in_array(
        '',
        $sources,
        true
    )
);


$check(
    'Sales page loads only the shared slaughter-output dispatcher boundary',
    str_contains(
        $sources['sales'],
        'slaughter_output_sale_dispatch.php'
    )
    &&
    !str_contains(
        $sources['sales'],
        'ruminant_slaughter_sale_consumption.php'
    )
    &&
    !str_contains(
        $sources['sales'],
        'poultry_slaughter_sale_consumption.php'
    )
);


$check(
    'Sales reads combined Poultry/Ruminant lots and durable history centrally',
    str_contains(
        $sources['sales'],
        'slaughter_output_sale_available_lots('
    )
    &&
    str_contains(
        $sources['sales'],
        'slaughter_output_sale_history_for_sales('
    )
);


$check(
    'Add and Edit share one dispatcher-backed request adapter',
    str_contains(
        $sources['sales'],
        '$prepareSlaughterSaleInput'
    )
    &&
    str_contains(
        $sources['sales'],
        'slaughter_output_sale_selection_from_post('
    )
);


$check(
    'Lot selection server-derives financial Sale identity',
    str_contains(
        $sources['sales'],
        "\$input['farm_type']"
    )
    &&
    str_contains(
        $sources['sales'],
        "\$selection['farm_type']"
    )
    &&
    str_contains(
        $sources['sales'],
        "\$input['production_type']"
    )
    &&
    str_contains(
        $sources['sales'],
        "\$input['cycle_id']"
    )
    &&
    str_contains(
        $sources['sales'],
        "\$input['product_type']"
    )
    &&
    str_contains(
        $sources['sales'],
        "\$input['quantity']"
    )
    &&
    str_contains(
        $sources['sales'],
        "\$selection['unit_of_measure']"
    )
);


$check(
    'Processed lot sale forces financial-only population behavior',
    str_contains(
        $sources['sales'],
        "\$input['population_effect_mode']"
    )
    &&
    str_contains(
        $sources['sales'],
        "'financial_only'"
    )
);


$check(
    'Add and Edit both synchronize through shared dual-domain dispatcher',
    substr_count(
        $sources['sales'],
        'slaughter_output_sale_sync('
    ) === 2
    &&
    !str_contains(
        $sources['sales'],
        'ruminant_slaughter_sale_sync('
    )
    &&
    !str_contains(
        $sources['sales'],
        'poultry_slaughter_sale_sync('
    )
);


$check(
    'Add and Edit carry explicit hidden Slaughter Output source domain',
    str_contains(
        $sources['sales'],
        'id="addSlaughterOutputDomain"'
    )
    &&
    str_contains(
        $sources['sales'],
        'id="editSlaughterOutputDomain"'
    )
    &&
    substr_count(
        $sources['sales'],
        'name="slaughter_output_domain"'
    ) === 2
);


$check(
    'Browser uses composite domain plus output-id lot identity',
    str_contains(
        $sources['js'],
        'function slaughterSaleKey('
    )
    &&
    str_contains(
        $sources['js'],
        'data-slaughter-domain'
    )
    &&
    str_contains(
        $sources['js'],
        'slaughter_output_domain'
    ) === false
    &&
    str_contains(
        $sources['js'],
        '#addSlaughterOutputDomain'
    )
    &&
    str_contains(
        $sources['js'],
        '#editSlaughterOutputDomain'
    )
);


$check(
    'Browser still posts numeric lot ids and quantities',
    str_contains(
        $sources['js'],
        "'slaughter_output_ids[]'"
    )
    &&
    str_contains(
        $sources['js'],
        "'slaughter_output_quantities[]'"
    )
);


$check(
    'Browser prevents one Sale line from mixing Poultry and Ruminant lots',
    str_contains(
        $sources['js'],
        'One sale line cannot mix Poultry and Ruminant slaughter-output lots.'
    )
    &&
    str_contains(
        $sources['js'],
        'firstDomain'
    )
);


$check(
    'Browser derives Sale farm domain from source lot instead of forcing Ruminant',
    str_contains(
        $sources['js'],
        '$(ids.farm)'
    )
    &&
    str_contains(
        $sources['js'],
        '.val('
    )
    &&
    str_contains(
        $sources['js'],
        'firstDomain'
    )
);


$check(
    'Edit browser restores durable lot domain and quantity history',
    str_contains(
        $sources['js'],
        'activeSlaughterSaleHistory'
    )
    &&
    str_contains(
        $sources['js'],
        'loadEditSlaughterSale'
    )
    &&
    str_contains(
        $sources['js'],
        'row.slaughter_domain'
    )
);


$check(
    'Sales row protects durable lot history from hard delete',
    str_contains(
        $sources['sales'],
        '$slaughterSaleHistoryRows'
    )
    &&
    str_contains(
        $sources['sales'],
        'cannot be hard-deleted'
    )
);


$check(
    'Delete API uses shared dual-domain hard-delete guard',
    str_contains(
        $sources['delete'],
        'slaughter_output_sale_dispatch.php'
    )
    &&
    str_contains(
        $sources['delete'],
        'slaughter_output_sale_assert_deletable('
    )
    &&
    str_contains(
        $sources['delete'],
        'catch(SlaughterOutputSaleException $e)'
    )
    &&
    !str_contains(
        $sources['delete'],
        'ruminant_slaughter_sale_assert_deletable('
    )
);


$check(
    'Existing receivable revenue population and Ruminant animal services remain wired',
    str_contains(
        $sources['sales'],
        'receivable_sync_sale_edit('
    )
    &&
    str_contains(
        $sources['sales'],
        'sales_refresh_automatic_allocation('
    )
    &&
    str_contains(
        $sources['sales'],
        'sale_population_effect_sync('
    )
    &&
    str_contains(
        $sources['sales'],
        'ruminant_sale_save_animal_allocations('
    )
);


$check(
    'Dispatcher remains the only page-level Poultry/Ruminant lot router',
    str_contains(
        $sources['dispatch'],
        'poultry_slaughter_sale_sync('
    )
    &&
    str_contains(
        $sources['dispatch'],
        'ruminant_slaughter_sale_sync('
    )
);


$check(
    'Domain services still delegate physical Inventory movement through shared boundary',
    str_contains(
        $sources['poultry'],
        'slaughter_output_sale_stock_consume('
    )
    &&
    str_contains(
        $sources['ruminant'],
        'slaughter_output_sale_stock_consume('
    )
    &&
    str_contains(
        $sources['shared_sale_stock'],
        'stock_apply_movement('
    )
);


$check(
    'Processed Sales services own no live-population mutation',
    !str_contains(
        $sources['poultry'],
        'production_population_projection_sync('
    )
    &&
    !str_contains(
        $sources['ruminant'],
        'production_population_projection_sync('
    )
    &&
    !str_contains(
        $sources['dispatch'],
        'sale_population_effect_sync('
    )
);


echo PHP_EOL;
echo 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    .
    (
        $failures === 0
            ? 'PASS'
            : 'FAIL'
    )
    .
    PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
