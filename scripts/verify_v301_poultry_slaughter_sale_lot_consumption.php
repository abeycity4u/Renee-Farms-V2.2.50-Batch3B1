<?php

declare(strict_types=1);

/**
 * Stage 14E-4F — Poultry processed-product Sales lot contract.
 *
 * Source-only verifier. No DB connection or business mutation.
 */

$root =
    dirname(
        __DIR__
    );

$paths = [
    'migration' =>
        $root
        .
        '/migrations/079_poultry_slaughter_processing_foundation.sql',

    'service' =>
        $root
        .
        '/lib/poultry_slaughter_sale_consumption.php',

    'common' =>
        $root
        .
        '/lib/slaughter_output_sale_common.php',

    'stock_boundary' =>
        $root
        .
        '/lib/slaughter_output_sale_stock.php',

    'role' =>
        $root
        .
        '/lib/inventory_category_role.php',
];

$sources = [];

foreach ($paths as $key => $path) {
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
    'Required Poultry slaughter-sale sources are readable',
    !in_array(
        '',
        $sources,
        true
    )
);


$check(
    'Migration 079 owns durable Poultry sale-to-output allocation history',
    str_contains(
        $sources['migration'],
        'CREATE TABLE IF NOT EXISTS poultry_slaughter_sale_allocations'
    )
    &&
    str_contains(
        $sources['migration'],
        'sale_id INT NOT NULL'
    )
    &&
    str_contains(
        $sources['migration'],
        'output_id BIGINT UNSIGNED NOT NULL'
    )
    &&
    str_contains(
        $sources['migration'],
        'stock_transaction_id INT NULL'
    )
    &&
    str_contains(
        $sources['migration'],
        'reversal_stock_transaction_id INT NULL'
    )
);


$check(
    'Poultry sale service delegates species-neutral Sales semantics',
    str_contains(
        $sources['service'],
        'slaughter_output_sale_common_require_transaction('
    )
    &&
    str_contains(
        $sources['service'],
        'slaughter_output_sale_common_sales_unit('
    )
    &&
    str_contains(
        $sources['service'],
        'slaughter_output_sale_common_normalize_rows('
    )
    &&
    str_contains(
        $sources['service'],
        'slaughter_output_sale_common_rows_from_post('
    )
    &&
    str_contains(
        $sources['service'],
        'slaughter_output_sale_common_semantic_rows('
    )
);


$check(
    'Lot selection is explicit and never inferred from product text',
    str_contains(
        $sources['common'],
        "'sale_stock_source'"
    )
    &&
    str_contains(
        $sources['common'],
        "'slaughter_output_ids'"
    )
    &&
    str_contains(
        $sources['common'],
        "'slaughter_output_quantities'"
    )
    &&
    !preg_match(
        '/product_type.*slaughter_output_ids/is',
        $sources['service']
    )
);


$check(
    'Poultry lot selection derives farm cycle production product quantity and unit from source lots',
    str_contains(
        $sources['service'],
        "'farm_type'"
    )
    &&
    str_contains(
        $sources['service'],
        "'poultry'"
    )
    &&
    str_contains(
        $sources['service'],
        "'production_type'"
    )
    &&
    str_contains(
        $sources['service'],
        "'cycle_id'"
    )
    &&
    str_contains(
        $sources['service'],
        "'product_type'"
    )
    &&
    str_contains(
        $sources['service'],
        "'unit_of_measure'"
    )
);


$check(
    'Lot selection requires finalized non-reversed Poultry slaughter provenance',
    str_contains(
        $sources['service'],
        'cost_basis_finalized_at'
    )
    &&
    str_contains(
        $sources['service'],
        "=== 'reversed'"
    )
);


$check(
    'One sale line requires same item unit production type and cycle',
    str_contains(
        $sources['service'],
        'same item, unit, Poultry production type and production cycle'
    )
);


$check(
    'Sale date cannot precede Poultry slaughter',
    str_contains(
        $sources['service'],
        'Sale date cannot be earlier than the slaughter date'
    )
    ||
    str_contains(
        $sources['service'],
        'Sale date cannot be earlier than the Poultry slaughter date'
    )
);


$check(
    'Selection protects exact source-lot available quantity',
    str_contains(
        $sources['service'],
        'remaining_quantity'
    )
    &&
    str_contains(
        $sources['service'],
        'exceeds the available lot balance'
    )
);


$check(
    'Poultry physical decrement delegates to shared slaughter-sale stock boundary',
    str_contains(
        $sources['service'],
        'slaughter_output_sale_stock_consume('
    )
    &&
    str_contains(
        $sources['stock_boundary'],
        'stock_apply_movement('
    )
    &&
    str_contains(
        $sources['role'],
        "'poultry_slaughter_sale'"
    )
);


$check(
    'Poultry correction delegates source-owned append-only stock reversal',
    str_contains(
        $sources['service'],
        'slaughter_output_sale_stock_reverse('
    )
    &&
    str_contains(
        $sources['stock_boundary'],
        'stock_reverse_transaction('
    )
    &&
    str_contains(
        $sources['role'],
        "'poultry_slaughter_sale_reversal'"
    )
);


$check(
    'Poultry domain service owns no duplicate direct stock ledger mutation',
    !str_contains(
        $sources['service'],
        'stock_apply_movement('
    )
    &&
    !str_contains(
        $sources['service'],
        'stock_reverse_transaction('
    )
    &&
    !preg_match(
        '/UPDATE\s+stock_transactions/i',
        $sources['service']
    )
    &&
    !preg_match(
        '/INSERT\s+INTO\s+stock_transactions/i',
        $sources['service']
    )
);


$check(
    'Source-output remaining quantity changes with lot consumption and correction',
    str_contains(
        $sources['service'],
        'UPDATE poultry_slaughter_outputs'
    )
    &&
    str_contains(
        $sources['service'],
        'remaining_quantity=?'
    )
);


$check(
    'Final lot depletion conserves exact frozen output cost after rounding',
    str_contains(
        $sources['service'],
        '$newRemaining <= 0.00001'
    )
    &&
    str_contains(
        $sources['service'],
        '$allocatedCost'
    )
    &&
    str_contains(
        $sources['service'],
        '$alreadyCost'
    )
);


$check(
    'Financial sale identity is revalidated before physical synchronization',
    str_contains(
        $sources['service'],
        'FROM sales_records'
    )
    &&
    str_contains(
        $sources['service'],
        'The financial Sale does not match its explicit Poultry slaughter-output lot selection.'
    )
);


$check(
    'Unchanged semantic lot selection is idempotent',
    str_contains(
        $sources['service'],
        '$currentSemantic'
    )
    &&
    str_contains(
        $sources['service'],
        '$desiredSemantic'
    )
    &&
    str_contains(
        $sources['service'],
        "'changed'"
    )
);


$check(
    'Sale corrections reverse old allocations before appending replacements',
    strpos(
        $sources['service'],
        'poultry_slaughter_sale_reverse_allocation('
    )
        <
        strrpos(
            $sources['service'],
            'poultry_slaughter_sale_apply_output('
        )
);


$check(
    'Poultry batch status derives from remaining processed-output quantity',
    str_contains(
        $sources['service'],
        'function poultry_slaughter_sale_refresh_batch_status('
    )
    &&
    str_contains(
        $sources['service'],
        "'completed'"
    )
    &&
    str_contains(
        $sources['service'],
        "'open'"
    )
    &&
    str_contains(
        $sources['service'],
        '$remainingQuantity'
    )
);


$check(
    'Poultry processed sale owns no live-population mutation',
    !str_contains(
        $sources['service'],
        'production_population_projection_sync'
    )
    &&
    !str_contains(
        $sources['service'],
        'production_population_record_movement'
    )
    &&
    !str_contains(
        $sources['service'],
        'production_population_movements'
    )
    &&
    !str_contains(
        $sources['service'],
        'sale_population_effect_sync'
    )
);


$check(
    'Poultry lot service does not create financial Sales rows',
    !preg_match(
        '/INSERT\s+INTO\s+sales_records/i',
        $sources['service']
    )
);


$check(
    'Any Poultry slaughter-output allocation history blocks hard delete',
    str_contains(
        $sources['service'],
        'function poultry_slaughter_sale_assert_deletable('
    )
    &&
    str_contains(
        $sources['service'],
        'cannot be hard-deleted'
    )
    &&
    str_contains(
        $sources['service'],
        'FROM poultry_slaughter_sale_allocations'
    )
);


require_once $paths['service'];


$rows =
    poultry_slaughter_sale_rows_from_post(
        [
            'sale_stock_source' =>
                'slaughter_output',

            'slaughter_output_ids' =>
                [
                    '21',
                    '9',
                ],

            'slaughter_output_quantities' =>
                [
                    '1.25',
                    '2.50',
                ],
        ]
    );

$check(
    'Poultry public wrapper preserves deterministic shared lot parsing',
    array_keys(
        $rows
    )
        ===
        [
            9,
            21,
        ]
    &&
    abs(
        (float)$rows[9][
            'quantity'
        ]
        -
        2.50
    ) < 0.00001
    &&
    abs(
        (float)$rows[21][
            'quantity'
        ]
        -
        1.25
    ) < 0.00001
);


$check(
    'Poultry processed-sale provenance maps to Poultry stock source',
    slaughter_output_sale_stock_source(
        'poultry'
    )
        ===
        'poultry_slaughter_sale'
    &&
    slaughter_output_sale_stock_reversal_source(
        'poultry'
    )
        ===
        'poultry_slaughter_sale_reversal'
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
