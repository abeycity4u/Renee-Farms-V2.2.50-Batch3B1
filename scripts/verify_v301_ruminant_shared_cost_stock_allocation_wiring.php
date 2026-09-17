<?php

$root =
    dirname(__DIR__);

$sharedPath =
    $root
    . '/lib/ruminant_shared_cost_economics.php';

$source =
    file_get_contents(
        $sharedPath
    );

if ($source === false) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_MISSING\n";
    exit(1);
}

require_once $sharedPath;

$checks = [];

function ruminant_stock_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

ruminant_stock_check(
    $checks,
    'REQUIRES_CENTRAL_STOCK_ECONOMICS',
    strpos(
        $source,
        "require_once __DIR__.'/stock_consumption_economics.php';"
    ) !== false
);

ruminant_stock_check(
    $checks,
    'CALLS_CENTRAL_STOCK_ECONOMICS',
    strpos(
        $source,
        'stock_consumption_economics_rows('
    ) !== false
);

ruminant_stock_check(
    $checks,
    'DECOMPOSITION_MODE_ENABLED',
    strpos(
        $source,
        '$decomposeNativeStockAllocations ='
    ) !== false
    &&
    strpos(
        $source,
        '$decomposeNativeStockAllocations'
    ) !== false
    &&
    strpos(
        $source,
        "        true;\n"
    ) !== false
);

ruminant_stock_check(
    $checks,
    'NO_DIRECT_STOCK_TRANSACTION_SQL',
    preg_match(
        '/\bFROM\s+stock_transactions\b/i',
        $source
    ) !== 1
);

ruminant_stock_check(
    $checks,
    'NO_DIRECT_STOCK_ALLOCATION_SQL',
    preg_match(
        '/\bFROM\s+stock_consumption_allocations\b/i',
        $source
    ) !== 1
);

/*
 * Pure row-mapping fixtures.
 */
$base = [
    'stock_transaction_id' =>
        262,

    'transaction_date' =>
        '2026-09-01',

    'economic_amount' =>
        '700.00',

    'cost_kind' =>
        'operating',

    'cost_classification' =>
        'consumables',

    'item_name' =>
        'Shared Consumables',

    'cycle_id' =>
        null,

    'target_cycle_id' =>
        null,

    'target_cycle_code' =>
        null,

    'allocation_id' =>
        null,
];

$explicit =
    $base;

$explicit[
    'attribution_mode'
] =
    'explicit_allocation';

$explicit[
    'target_cycle_id'
] =
    42;

$explicit[
    'target_cycle_code'
] =
    'FARMA-CATTLE-AUDIT-02';

$explicit[
    'allocation_id'
] =
    99;

$explicitMapped =
    ruminant_shared_cost_stock_row_from_economics(
        $explicit,
        'cattle'
    );

ruminant_stock_check(
    $checks,
    'EXPLICIT_ALLOCATION_TARGETS_CYCLE',
    is_array(
        $explicitMapped
    )
    &&
    (int)$explicitMapped[
        'cycle_id'
    ] === 42
    &&
    $explicitMapped[
        'cycle_code'
    ] === 'FARMA-CATTLE-AUDIT-02'
);

$nativeCycle =
    $base;

$nativeCycle[
    'attribution_mode'
] =
    'native_parent';

$nativeCycle[
    'cycle_id'
] =
    42;

$nativeCycleMapped =
    ruminant_shared_cost_stock_row_from_economics(
        $nativeCycle,
        'cattle'
    );

ruminant_stock_check(
    $checks,
    'NATIVE_DIRECT_CYCLE_PRESERVED',
    is_array(
        $nativeCycleMapped
    )
    &&
    (int)$nativeCycleMapped[
        'cycle_id'
    ] === 42
);

$nativeSpecies =
    $base;

$nativeSpecies[
    'attribution_mode'
] =
    'native_parent';

$nativeSpeciesMapped =
    ruminant_shared_cost_stock_row_from_economics(
        $nativeSpecies,
        'cattle'
    );

ruminant_stock_check(
    $checks,
    'NATIVE_SPECIES_POOL_REMAINS_SPECIES_WIDE',
    is_array(
        $nativeSpeciesMapped
    )
    &&
    $nativeSpeciesMapped[
        'cycle_id'
    ] === null
);

$remainder =
    $base;

$remainder[
    'attribution_mode'
] =
    'unallocated_remainder';

/*
 * Deliberately provide a misleading source cycle.
 * Remainder must still stay species-wide.
 */
$remainder[
    'cycle_id'
] =
    42;

$remainderMapped =
    ruminant_shared_cost_stock_row_from_economics(
        $remainder,
        'cattle'
    );

ruminant_stock_check(
    $checks,
    'UNALLOCATED_REMAINDER_STAYS_SPECIES_WIDE',
    is_array(
        $remainderMapped
    )
    &&
    $remainderMapped[
        'cycle_id'
    ] === null
);

ruminant_stock_check(
    $checks,
    'ECONOMIC_AMOUNT_MAPPED_TO_POOL',
    abs(
        (float)$explicitMapped[
            'pool_amount'
        ]
        - 700.00
    ) < 0.005
);

ruminant_stock_check(
    $checks,
    'CLASSIFICATION_PRESERVED',
    $explicitMapped[
        'classification'
    ] === 'consumables'
);

ruminant_stock_check(
    $checks,
    'STOCK_SOURCE_IDENTITY_PRESERVED',
    (int)$explicitMapped[
        'source_id'
    ] === 262
    &&
    $explicitMapped[
        'source_type'
    ] === 'inventory_use'
    &&
    $explicitMapped[
        'source_date'
    ] === '2026-09-01'
);

ruminant_stock_check(
    $checks,
    'ALLOCATION_ID_PRESERVED_FOR_AUDIT',
    (int)$explicitMapped[
        'stock_allocation_id'
    ] === 99
    &&
    $explicitMapped[
        'stock_attribution_mode'
    ] === 'explicit_allocation'
);

$failedClosed = false;

try {
    $bad =
        $base;

    $bad[
        'attribution_mode'
    ] =
        'invented_mode';

    ruminant_shared_cost_stock_row_from_economics(
        $bad,
        'cattle'
    );

} catch (RuntimeException $e) {
    $failedClosed =
        true;
}

ruminant_stock_check(
    $checks,
    'UNKNOWN_ATTRIBUTION_MODE_FAILS_CLOSED',
    $failedClosed
);

ruminant_stock_check(
    $checks,
    'NO_STOCK_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE\s+stock_|DELETE\s+FROM\s+stock_)/i',
        $source
    ) !== 1
);

ruminant_stock_check(
    $checks,
    'NO_TRANSACTION_OWNERSHIP',
    preg_match(
        '/->\s*(?:beginTransaction|commit|rollBack)\s*\(/',
        $source
    ) !== 1
);

$failed = [];

foreach (
    $checks
    as $name => $passed
) {
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

foreach (
    $checks
    as $name => $passed
) {
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
