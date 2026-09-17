<?php

$root = dirname(__DIR__);

$rearingPath =
    $root
    . '/lib/poultry_rearing_economics.php';

$pagePath =
    $root
    . '/management/poultry_cycle.php';

$rearing =
    file_get_contents(
        $rearingPath
    );

$page =
    file_get_contents(
        $pagePath
    );

if (
    $rearing === false
    ||
    $page === false
) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_MISSING\n";
    exit(1);
}

$checks = [];

function f1_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

f1_check(
    $checks,
    'DIRECT_FEED_FIELD_PRESENT',
    strpos(
        $rearing,
        "'direct_feed_consumed_cost' => 0.0"
    ) !== false
);

f1_check(
    $checks,
    'ALLOCATED_FEED_FIELD_PRESENT',
    strpos(
        $rearing,
        "'allocated_feed_consumed_cost' => 0.0"
    ) !== false
);

f1_check(
    $checks,
    'DIRECT_OPERATING_FIELD_PRESENT',
    strpos(
        $rearing,
        "'direct_inventory_operating_cost' => 0.0"
    ) !== false
);

f1_check(
    $checks,
    'ALLOCATED_OPERATING_FIELD_PRESENT',
    strpos(
        $rearing,
        "'allocated_inventory_operating_cost' => 0.0"
    ) !== false
);

f1_check(
    $checks,
    'DIRECT_FEED_CAPTURED_BY_READER',
    strpos(
        $rearing,
        "'direct_feed_consumed_cost'\n            ] +=\n                \$value;"
    ) !== false
);

f1_check(
    $checks,
    'DIRECT_OPERATING_CAPTURED_BY_READER',
    strpos(
        $rearing,
        "'direct_inventory_operating_cost'\n                ] +=\n                    \$value;"
    ) !== false
);

f1_check(
    $checks,
    'ALLOCATED_FEED_CAPTURED_BY_READER',
    strpos(
        $rearing,
        "'allocated_feed_consumed_cost'\n            ] +=\n                \$value;"
    ) !== false
);

f1_check(
    $checks,
    'ALLOCATED_OPERATING_CAPTURED_BY_READER',
    strpos(
        $rearing,
        "'allocated_inventory_operating_cost'\n        ] +=\n            \$value;"
    ) !== false
);

f1_check(
    $checks,
    'FEED_COMPOSITION_FAILS_CLOSED',
    strpos(
        $rearing,
        'Rearing Feed attribution composition does not conserve the Feed total.'
    ) !== false
);

f1_check(
    $checks,
    'OPERATING_COMPOSITION_FAILS_CLOSED',
    strpos(
        $rearing,
        'Rearing operating-stock attribution composition does not conserve the operating-stock total.'
    ) !== false
);

f1_check(
    $checks,
    'EXISTING_REARING_INVESTMENT_FORMULA_PRESERVED',
    strpos(
        $rearing,
        "\$investment = \$acqCost + \$base['feed_consumed_cost'] + \$base['inventory_operating_cost'] + \$base['direct_expenses'] + \$base['allocated_shared_expenses'];"
    ) !== false
);

f1_check(
    $checks,
    'PAGE_CONSUMES_CANONICAL_REARING_READER',
    strpos(
        $page,
        'poultry_rearing_economics('
    ) !== false
);

f1_check(
    $checks,
    'PAGE_DOES_NOT_CALL_STOCK_ECONOMICS_READER',
    strpos(
        $page,
        'stock_consumption_economics_rows('
    ) === false
);

f1_check(
    $checks,
    'PAGE_RENDERS_ALL_FOUR_COMPOSITION_FIELDS',
    strpos(
        $page,
        "\$rearingEconomics['direct_feed_consumed_cost']"
    ) !== false
    &&
    strpos(
        $page,
        "\$rearingEconomics['allocated_feed_consumed_cost']"
    ) !== false
    &&
    strpos(
        $page,
        "\$rearingEconomics['direct_inventory_operating_cost']"
    ) !== false
    &&
    strpos(
        $page,
        "\$rearingEconomics['allocated_inventory_operating_cost']"
    ) !== false
);

f1_check(
    $checks,
    'DIRECT_NATIVE_COPY_PRESENT',
    strpos(
        $page,
        'Direct/native cycle stock use'
    ) !== false
);

f1_check(
    $checks,
    'EXPLICIT_ALLOCATION_COPY_PRESENT',
    strpos(
        $page,
        'Explicit consumed-stock allocation'
    ) !== false
);

f1_check(
    $checks,
    'OUTSIDE_CYCLE_COPY_PRESENT',
    strpos(
        $page,
        'Any unallocated shared balance remains outside this cycle'
    ) !== false
    &&
    strpos(
        $page,
        'is not included in Attributed Rearing Investment'
    ) !== false
);

f1_check(
    $checks,
    'PAGE_HAS_NO_STOCK_ALLOCATION_SQL',
    preg_match(
        '/\b(?:FROM|JOIN)\s+stock_consumption_allocations\b/i',
        $page
    ) !== 1
);

f1_check(
    $checks,
    'EXISTING_UNALLOCATED_EXPENSE_DISCLOSURE_PRESERVED',
    strpos(
        $page,
        'Unallocated shared Layer expense pool in this rearing window:'
    ) !== false
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
