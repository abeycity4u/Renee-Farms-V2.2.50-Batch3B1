<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$paths = [
    'common' =>
        $root
        .
        '/lib/slaughter_output_sale_economics_common.php',

    'poultry' =>
        $root
        .
        '/lib/poultry_slaughter_sale_economics.php',

    'ruminant' =>
        $root
        .
        '/lib/ruminant_slaughter_sale_economics.php',

    'migration' =>
        $root
        .
        '/migrations/079_poultry_slaughter_processing_foundation.sql',

    'financial' =>
        $root
        .
        '/includes/financial.php',
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
    'Required Poultry slaughter-sale economics sources are readable',
    !in_array(
        '',
        $sources,
        true
    )
);


$check(
    'Shared species-neutral monetary arithmetic exists',
    str_contains(
        $sources['common'],
        'function slaughter_output_sale_economics_money_cents('
    )
    &&
    str_contains(
        $sources['common'],
        'function slaughter_output_sale_economics_proportional_cents('
    )
);


$check(
    'Ruminant compatibility helpers delegate to shared arithmetic authority',
    str_contains(
        $sources['ruminant'],
        'slaughter_output_sale_economics_money_cents('
    )
    &&
    str_contains(
        $sources['ruminant'],
        'slaughter_output_sale_economics_proportional_cents('
    )
);


$check(
    'Poultry reader recognises only active explicit sale-lot allocations',
    str_contains(
        $sources['poultry'],
        'FROM poultry_slaughter_sale_allocations a'
    )
    &&
    str_contains(
        $sources['poultry'],
        'a.is_active=1'
    )
);


$check(
    'Poultry reader requires canonical Poultry slaughter-sale stock provenance',
    str_contains(
        $sources['poultry'],
        "'poultry_slaughter_sale'"
    )
    &&
    str_contains(
        $sources['poultry'],
        "'source_id'"
    )
    &&
    str_contains(
        $sources['poultry'],
        "'is_reversed'"
    )
    &&
    str_contains(
        $sources['poultry'],
        "'reversal_of_id'"
    )
);


$check(
    'Poultry reader proves financial Sale cycle production and Inventory provenance',
    str_contains(
        $sources['poultry'],
        'sale_farm_type'
    )
    &&
    str_contains(
        $sources['poultry'],
        'sale_production_type'
    )
    &&
    str_contains(
        $sources['poultry'],
        'sale_cycle_id'
    )
    &&
    str_contains(
        $sources['poultry'],
        'tx_stock_item_id'
    )
);


$check(
    'Poultry reader requires finalized effective slaughter cost basis',
    str_contains(
        $sources['poultry'],
        'cost_basis_finalized_at'
    )
    &&
    str_contains(
        $sources['poultry'],
        "batch_status"
    )
    &&
    str_contains(
        $sources['poultry'],
        "=== 'reversed'"
    )
);


$check(
    'Poultry frozen full-cost basis contains capital pre-slaughter operating and processing operating components',
    str_contains(
        $sources['poultry'],
        'capital_basis_transferred'
    )
    &&
    str_contains(
        $sources['poultry'],
        'embedded_operating_basis_transferred'
    )
    &&
    str_contains(
        $sources['poultry'],
        'processing_operating_cost'
    )
    &&
    str_contains(
        $sources['poultry'],
        'full_cost_basis_amount'
    )
);


$check(
    'Poultry reader enforces frozen batch full-cost conservation',
    str_contains(
        $sources['poultry'],
        '$basisCapitalCents'
    )
    &&
    str_contains(
        $sources['poultry'],
        '$basisEmbeddedOperatingCents'
    )
    &&
    str_contains(
        $sources['poultry'],
        '$basisProcessingOperatingCents'
    )
    &&
    str_contains(
        $sources['poultry'],
        '$basisFullCostCents'
    )
    &&
    str_contains(
        $sources['poultry'],
        'frozen cost basis does not conserve full cost'
    )
);


$check(
    'Only transferred Poultry capital becomes new P&L COGS',
    str_contains(
        $sources['poultry'],
        'slaughter_output_sale_economics_proportional_cents('
    )
    &&
    str_contains(
        $sources['poultry'],
        '$basisCapitalCents'
    )
    &&
    str_contains(
        $sources['poultry'],
        '$recognizedCogsCents'
    )
);


$check(
    'Already-recognized operating economics remain embedded valuation disclosure',
    str_contains(
        $sources['poultry'],
        '$embeddedOperatingCents'
    )
    &&
    str_contains(
        $sources['poultry'],
        '$snapshotCents'
    )
    &&
    str_contains(
        $sources['poultry'],
        '$recognizedCogsCents'
    )
    &&
    str_contains(
        $sources['poultry'],
        "'slaughter_output_embedded_operating_cost'"
    )
);


$check(
    'Poultry sold valuation is reconciled to canonical stock movement cost',
    str_contains(
        $sources['poultry'],
        'total_cost_snapshot'
    )
    &&
    str_contains(
        $sources['poultry'],
        'tx_total_cost'
    )
    &&
    str_contains(
        $sources['poultry'],
        'unit_cost_snapshot'
    )
    &&
    str_contains(
        $sources['poultry'],
        'tx_unit_cost'
    )
);


$check(
    'Active lot quantities still conserve each financial Sale',
    str_contains(
        $sources['poultry'],
        '$saleQuantityTotals'
    )
    &&
    str_contains(
        $sources['poultry'],
        'active lot quantity does not conserve its sale'
    )
);


$check(
    'Sold cost cannot exceed frozen source-output valuation',
    str_contains(
        $sources['poultry'],
        '$outputSoldCostTotals'
    )
    &&
    str_contains(
        $sources['poultry'],
        '$outputAllocatedCosts'
    )
    &&
    str_contains(
        $sources['poultry'],
        'exceeds the frozen source-output valuation'
    )
);


$check(
    'Poultry economics reader remains read-only',
    !preg_match(
        '/\bINSERT\s+INTO\b/i',
        $sources['poultry']
    )
    &&
    !preg_match(
        '/\bUPDATE\s+[A-Za-z0-9_`]+\s+SET\b/i',
        $sources['poultry']
    )
    &&
    !preg_match(
        '/\bDELETE\s+FROM\b/i',
        $sources['poultry']
    )
);


$check(
    'Poultry economics reader owns no population or Inventory mutation',
    !str_contains(
        $sources['poultry'],
        'production_population_'
    )
    &&
    !str_contains(
        $sources['poultry'],
        'stock_apply_movement('
    )
    &&
    !str_contains(
        $sources['poultry'],
        'stock_reverse_transaction('
    )
);


$check(
    'Canonical profitability is intentionally not yet wired to Poultry reader',
    !str_contains(
        $sources['financial'],
        'poultry_slaughter_sale_economics.php'
    )
    &&
    !str_contains(
        $sources['financial'],
        'poultry_slaughter_sale_economics_summary('
    )
);


require_once $paths['common'];


$check(
    'Shared arithmetic converts monetary values deterministically',
    slaughter_output_sale_economics_money_cents(
        '123.45'
    ) === 12345
);


$check(
    'Shared proportional arithmetic releases frozen basis deterministically',
    slaughter_output_sale_economics_proportional_cents(
        10000,
        2500,
        10000
    ) === 2500
);


$zeroBasisRejected =
    false;

try {
    slaughter_output_sale_economics_proportional_cents(
        1,
        0,
        0
    );

} catch (RuntimeException $e) {
    $zeroBasisRejected =
        str_contains(
            $e->getMessage(),
            'no frozen cost basis'
        );
}

$check(
    'Non-zero sold valuation with zero frozen basis fails closed',
    $zeroBasisRejected
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
