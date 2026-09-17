<?php

$root =
    dirname(__DIR__);

$helper =
    $root
    . '/lib/stock_consumption_economics.php';

$source =
    file_get_contents(
        $helper
    );

if ($source === false) {
    echo "RESULT=FAIL\n";
    echo "FAILED=HELPER_MISSING\n";
    exit(1);
}

require_once $helper;

$checks = [];

function check_result(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

function sum_rows(
    array $rows
): string {
    $cents = 0;

    foreach ($rows as $row) {
        $cents +=
            (int)$row[
                'economic_amount_cents'
            ];
    }

    return
        number_format(
            $cents / 100,
            2,
            '.',
            ''
        );
}

function rows_for_parent(
    array $rows,
    int $parentId
): array {
    return
        array_values(
            array_filter(
                $rows,
                static fn(array $row): bool =>
                    (int)$row[
                        'stock_transaction_id'
                    ]
                    === $parentId
            )
        );
}

/*
 * Pure economic fixture:
 *
 * #100 farm-wide parent ₦2,000
 *   -> Layer cycle 56 ₦800
 *   -> Goat cycle 57 ₦700
 *   -> farm-wide remainder ₦500
 *
 * #200 Poultry/Layer production parent ₦1,000
 *   -> Layer cycle 56 ₦400
 *   -> Layer cycle 60 ₦300
 *   -> Layer production remainder ₦300
 *
 * #300 direct Layer cycle 56 feed ₦500
 */
$parents = [
    [
        'stock_transaction_id' =>
            100,

        'total_cost' =>
            '2000.00',

        'farm_type' =>
            'both',

        'production_type' =>
            'shared',

        'cycle_id' =>
            null,

        'cost_kind' =>
            'operating',

        'cost_classification' =>
            'consumables',
    ],
    [
        'stock_transaction_id' =>
            200,

        'total_cost' =>
            '1000.00',

        'farm_type' =>
            'poultry',

        'production_type' =>
            'layer',

        'cycle_id' =>
            null,

        'cost_kind' =>
            'operating',

        'cost_classification' =>
            'medication_vaccine',
    ],
    [
        'stock_transaction_id' =>
            300,

        'total_cost' =>
            '500.00',

        'farm_type' =>
            'poultry',

        'production_type' =>
            'layer',

        'cycle_id' =>
            56,

        'cost_kind' =>
            'feed',

        'cost_classification' =>
            'feed',
    ],
];

$allocations = [
    [
        'allocation_id' =>
            1,

        'stock_transaction_id' =>
            100,

        'cycle_id' =>
            56,

        'allocated_amount' =>
            '800.00',

        'allocation_revision_no' =>
            1,

        'target_farm_type' =>
            'poultry',

        'target_production_type' =>
            'layer',

        'target_cycle_code' =>
            'Layer 56',
    ],
    [
        'allocation_id' =>
            2,

        'stock_transaction_id' =>
            100,

        'cycle_id' =>
            57,

        'allocated_amount' =>
            '700.00',

        'allocation_revision_no' =>
            1,

        'target_farm_type' =>
            'ruminant',

        'target_production_type' =>
            'goat',

        'target_cycle_code' =>
            'Goat 57',
    ],
    [
        'allocation_id' =>
            3,

        'stock_transaction_id' =>
            200,

        'cycle_id' =>
            56,

        'allocated_amount' =>
            '400.00',

        'allocation_revision_no' =>
            1,

        'target_farm_type' =>
            'poultry',

        'target_production_type' =>
            'layer',

        'target_cycle_code' =>
            'Layer 56',
    ],
    [
        'allocation_id' =>
            4,

        'stock_transaction_id' =>
            200,

        'cycle_id' =>
            60,

        'allocated_amount' =>
            '300.00',

        'allocation_revision_no' =>
            1,

        'target_farm_type' =>
            'poultry',

        'target_production_type' =>
            'layer',

        'target_cycle_code' =>
            'Layer 60',
    ],
];

check_result(
    $checks,
    'CENTRAL_READER_FUNCTION',
    function_exists(
        'stock_consumption_economics_rows'
    )
    &&
    function_exists(
        'stock_consumption_economics_summary'
    )
);

check_result(
    $checks,
    'PURE_SELECTOR_FUNCTION',
    function_exists(
        'stock_consumption_economics_select_rows'
    )
);

check_result(
    $checks,
    'READS_CURRENT_ALLOCATION_PROJECTION',
    strpos(
        $source,
        'FROM stock_consumption_allocations'
    ) !== false
);

check_result(
    $checks,
    'USES_EFFECTIVE_STOCK_POLICY',
    strpos(
        $source,
        'stock_effective_sql_predicate'
    ) !== false
);

check_result(
    $checks,
    'USES_CANONICAL_FEED_POLICY',
    strpos(
        $source,
        'stock_feed_item_sql_predicate'
    ) !== false
);

check_result(
    $checks,
    'USES_OPERATING_CLASSIFICATION_POLICY',
    strpos(
        $source,
        'inventory_operating_consumption_classifications'
    ) !== false
);

check_result(
    $checks,
    'NO_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?(?:stock_transactions|stock_consumption_allocations|stock_consumption_allocation_revisions)\b/i',
        $source
    ) !== 1
);

check_result(
    $checks,
    'NO_TRANSACTION_OWNERSHIP',
    preg_match(
        '/->\s*(?:beginTransaction|commit|rollBack)\s*\(/',
        $source
    ) !== 1
);

/*
 * Farm level: every parent exactly once.
 */
$farmRows =
    stock_consumption_economics_select_rows(
        $parents,
        $allocations,
        stock_consumption_economics_scope(
            'all'
        )
    );

check_result(
    $checks,
    'FARM_PARENT_TOTAL_ONCE',
    sum_rows(
        $farmRows
    ) === '3500.00'
);

check_result(
    $checks,
    'FARM_WIDE_PARENT_NOT_DOUBLED',
    count(
        rows_for_parent(
            $farmRows,
            100
        )
    ) === 1
    &&
    sum_rows(
        rows_for_parent(
            $farmRows,
            100
        )
    ) === '2000.00'
);

/*
 * Module boundaries.
 */
$poultryRows =
    stock_consumption_economics_select_rows(
        $parents,
        $allocations,
        stock_consumption_economics_scope(
            'poultry'
        )
    );

check_result(
    $checks,
    'POULTRY_MODULE_TOTAL',
    sum_rows(
        $poultryRows
    ) === '2300.00'
);

check_result(
    $checks,
    'FARM_WIDE_TO_POULTRY_EXPLICIT_ONLY',
    sum_rows(
        rows_for_parent(
            $poultryRows,
            100
        )
    ) === '800.00'
);

$ruminantRows =
    stock_consumption_economics_select_rows(
        $parents,
        $allocations,
        stock_consumption_economics_scope(
            'ruminant'
        )
    );

check_result(
    $checks,
    'RUMINANT_MODULE_EXPLICIT_ONLY',
    sum_rows(
        $ruminantRows
    ) === '700.00'
);

/*
 * Production scope.
 */
$layerRows =
    stock_consumption_economics_select_rows(
        $parents,
        $allocations,
        stock_consumption_economics_scope(
            'poultry',
            'layer'
        )
    );

check_result(
    $checks,
    'LAYER_PRODUCTION_TOTAL',
    sum_rows(
        $layerRows
    ) === '2300.00'
);

check_result(
    $checks,
    'NATIVE_LAYER_PARENT_COUNTED_ONCE',
    sum_rows(
        rows_for_parent(
            $layerRows,
            200
        )
    ) === '1000.00'
);

$goatRows =
    stock_consumption_economics_select_rows(
        $parents,
        $allocations,
        stock_consumption_economics_scope(
            'ruminant',
            'goat'
        )
    );

check_result(
    $checks,
    'GOAT_PRODUCTION_EXPLICIT_ONLY',
    sum_rows(
        $goatRows
    ) === '700.00'
);

/*
 * Cycle scope.
 */
$cycle56Rows =
    stock_consumption_economics_select_rows(
        $parents,
        $allocations,
        stock_consumption_economics_scope(
            'poultry',
            'layer',
            56
        )
    );

check_result(
    $checks,
    'CYCLE56_TOTAL',
    sum_rows(
        $cycle56Rows
    ) === '1700.00'
);

check_result(
    $checks,
    'CYCLE56_DIRECT_PARENT_INCLUDED',
    sum_rows(
        rows_for_parent(
            $cycle56Rows,
            300
        )
    ) === '500.00'
);

check_result(
    $checks,
    'CYCLE56_FARMWIDE_ALLOCATION_INCLUDED',
    sum_rows(
        rows_for_parent(
            $cycle56Rows,
            100
        )
    ) === '800.00'
);

check_result(
    $checks,
    'CYCLE56_PRODUCTION_ALLOCATION_INCLUDED',
    sum_rows(
        rows_for_parent(
            $cycle56Rows,
            200
        )
    ) === '400.00'
);

$cycle57Rows =
    stock_consumption_economics_select_rows(
        $parents,
        $allocations,
        stock_consumption_economics_scope(
            'ruminant',
            'goat',
            57
        )
    );

check_result(
    $checks,
    'CYCLE57_TOTAL',
    sum_rows(
        $cycle57Rows
    ) === '700.00'
);

/*
 * Decomposition mode:
 * native Layer #200 must expose 400 + 300 + remainder 300,
 * while farm-wide #100 contributes only its explicit Layer 800.
 */
$layerComponents =
    stock_consumption_economics_select_rows(
        $parents,
        $allocations,
        stock_consumption_economics_scope(
            'poultry',
            'layer'
        ),
        true
    );

$parent200Components =
    rows_for_parent(
        $layerComponents,
        200
    );

$parent200Modes =
    array_count_values(
        array_map(
            static fn(array $row): string =>
                (string)$row[
                    'attribution_mode'
                ],
            $parent200Components
        )
    );

check_result(
    $checks,
    'DECOMPOSE_NATIVE_TOTAL_PRESERVED',
    sum_rows(
        $parent200Components
    ) === '1000.00'
);

check_result(
    $checks,
    'DECOMPOSE_NATIVE_TWO_ALLOCATIONS',
    (
        $parent200Modes[
            'explicit_allocation'
        ]
        ?? 0
    ) === 2
);

check_result(
    $checks,
    'DECOMPOSE_NATIVE_REMAINDER_PRESENT',
    (
        $parent200Modes[
            'unallocated_remainder'
        ]
        ?? 0
    ) === 1
    &&
    array_values(
        array_filter(
            $parent200Components,
            static fn(array $row): bool =>
                $row[
                    'attribution_mode'
                ]
                === 'unallocated_remainder'
        )
    )[0][
        'economic_amount'
    ] === '300.00'
);

check_result(
    $checks,
    'DECOMPOSE_CROSS_BOUNDARY_ONLY_EXPLICIT',
    sum_rows(
        rows_for_parent(
            $layerComponents,
            100
        )
    ) === '800.00'
);

check_result(
    $checks,
    'DECOMPOSE_REPORT_TOTAL_UNCHANGED',
    sum_rows(
        $layerComponents
    ) === '2300.00'
);

/*
 * Integrity failures must fail closed.
 */
$overAllocationRejected = false;

try {
    stock_consumption_economics_select_rows(
        [
            [
                'stock_transaction_id' =>
                    900,

                'total_cost' =>
                    '100.00',

                'farm_type' =>
                    'both',

                'production_type' =>
                    'shared',

                'cycle_id' =>
                    null,
            ],
        ],
        [
            [
                'stock_transaction_id' =>
                    900,

                'cycle_id' =>
                    1,

                'allocated_amount' =>
                    '100.01',

                'target_farm_type' =>
                    'poultry',

                'target_production_type' =>
                    'layer',
            ],
        ],
        stock_consumption_economics_scope(
            'all'
        )
    );

} catch (RuntimeException $e) {
    $overAllocationRejected =
        strpos(
            $e->getMessage(),
            'exceeds its parent'
        ) !== false;
}

check_result(
    $checks,
    'OVERALLOCATION_FAILS_CLOSED',
    $overAllocationRejected
);

$duplicateRejected = false;

try {
    stock_consumption_economics_select_rows(
        [
            [
                'stock_transaction_id' =>
                    901,

                'total_cost' =>
                    '100.00',

                'farm_type' =>
                    'both',

                'production_type' =>
                    'shared',

                'cycle_id' =>
                    null,
            ],
        ],
        [
            [
                'stock_transaction_id' =>
                    901,

                'cycle_id' =>
                    1,

                'allocated_amount' =>
                    '25.00',

                'target_farm_type' =>
                    'poultry',

                'target_production_type' =>
                    'layer',
            ],
            [
                'stock_transaction_id' =>
                    901,

                'cycle_id' =>
                    1,

                'allocated_amount' =>
                    '25.00',

                'target_farm_type' =>
                    'poultry',

                'target_production_type' =>
                    'layer',
            ],
        ],
        stock_consumption_economics_scope(
            'all'
        )
    );

} catch (RuntimeException $e) {
    $duplicateRejected =
        strpos(
            $e->getMessage(),
            'target is duplicated'
        ) !== false;
}

check_result(
    $checks,
    'DUPLICATE_TARGET_FAILS_CLOSED',
    $duplicateRejected
);

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] =
            $name;
    }
}

echo 'RESULT='
    . (
        $failed
            ? 'FAIL'
            : 'PASS'
    )
    . PHP_EOL;

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

foreach ($checks as $name => $passed) {
    echo $name
        . '='
        . (
            $passed
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;
}

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

if ($failed) {
    echo 'FAILED='
        . implode(
            ',',
            $failed
        )
        . PHP_EOL;

    exit(1);
}

exit(0);
