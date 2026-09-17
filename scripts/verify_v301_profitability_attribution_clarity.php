<?php

$root =
    dirname(__DIR__);

$financial =
    file_get_contents(
        $root
        . '/includes/financial.php'
    );

$page =
    file_get_contents(
        $root
        . '/management/profitability.php'
    );

$stock =
    file_get_contents(
        $root
        . '/lib/stock_consumption_economics.php'
    );

$animal =
    file_get_contents(
        $root
        . '/ruminant/animal_view.php'
    );

if (
    $financial === false
    ||
    $page === false
    ||
    $stock === false
    ||
    $animal === false
) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_LOAD\n";
    exit(1);
}

$checks = [];

function f4_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

f4_check(
    $checks,
    'FINANCIAL_USES_CANONICAL_STOCK_SUMMARY',
    strpos(
        $financial,
        'stock_consumption_economics_summary('
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_ATTRIBUTION_COMPOSITION',
    strpos(
        $financial,
        "'attribution_composition'=>["
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_DIRECT_REVENUE',
    strpos(
        $financial,
        "'direct_sales'=>$directRevenue"
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_EXPLICIT_REVENUE_ALLOCATION',
    strpos(
        $financial,
        "'explicit_sale_allocation'=>$allocatedRevenue"
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_UNALLOCATED_POOLED_REVENUE',
    strpos(
        $financial,
        "'unallocated_pooled_revenue'=>$unallocatedPooledRevenue"
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_SHARED_REVENUE_INCLUDED',
    strpos(
        $financial,
        "'allocated_shared_revenue'=>$allocatedSharedRevenue"
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_DIRECT_NATIVE_FEED',
    strpos(
        $financial,
        "'direct_native_stock'=>\$feedDirectNativeCents / 100"
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_EXPLICIT_FEED_ALLOCATION',
    strpos(
        $financial,
        "'explicit_stock_allocation'=>\$feedExplicitAllocationCents / 100"
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_DIRECT_NATIVE_OPERATING_STOCK',
    strpos(
        $financial,
        "'direct_native_stock'=>\$operatingDirectNativeCents / 100"
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_EXPLICIT_OPERATING_STOCK_ALLOCATION',
    strpos(
        $financial,
        "'explicit_stock_allocation'=>\$operatingExplicitAllocationCents / 100"
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_DIRECT_MANUAL_EXPENSE',
    strpos(
        $financial,
        "'direct_expense'=>$directManualNonFeedExpenses"
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_EXPOSES_EXPLICIT_SHARED_EXPENSE_ALLOCATION',
    strpos(
        $financial,
        "'explicit_shared_expense_allocation'=>$allocatedManualNonFeedExpenses"
    ) !== false
);

f4_check(
    $checks,
    'STOCK_COMPOSITION_CONSUMES_CANONICAL_ROWS',
    strpos(
        $financial,
        "\$stockConsumption['rows']"
    ) !== false
    &&
    strpos(
        $financial,
        "'attribution_mode'"
    ) !== false
);

f4_check(
    $checks,
    'STOCK_COMPOSITION_ACCEPTS_CANONICAL_MODES_ONLY',
    strpos(
        $financial,
        "'native_parent'"
    ) !== false
    &&
    strpos(
        $financial,
        "'explicit_allocation'"
    ) !== false
);

f4_check(
    $checks,
    'FEED_COMPOSITION_CONSERVATION_GUARD',
    strpos(
        $financial,
        'Profitability Feed attribution composition does not conserve its canonical total.'
    ) !== false
);

f4_check(
    $checks,
    'OPERATING_STOCK_CONSERVATION_GUARD',
    strpos(
        $financial,
        'Profitability operating-stock attribution composition does not conserve its canonical total.'
    ) !== false
);

f4_check(
    $checks,
    'MANUAL_EXPENSE_CONSERVATION_GUARD',
    strpos(
        $financial,
        'Profitability manual-expense attribution composition does not conserve its canonical total.'
    ) !== false
);

f4_check(
    $checks,
    'REVENUE_CONSERVATION_GUARD',
    strpos(
        $financial,
        'Profitability revenue attribution composition does not conserve its canonical total.'
    ) !== false
);

f4_check(
    $checks,
    'PAGE_NO_LONGER_OWNS_SHARED_REVENUE_SQL',
    strpos(
        $page,
        '$pooledSql'
    ) === false
    &&
    strpos(
        $page,
        '$sharedIncludedSql'
    ) === false
    &&
    stripos(
        $page,
        'FROM sales_allocations'
    ) === false
);

f4_check(
    $checks,
    'PAGE_CONSUMES_CENTRAL_ATTRIBUTION_COMPOSITION',
    strpos(
        $page,
        "\$summary['attribution_composition']"
    ) !== false
);

f4_check(
    $checks,
    'PAGE_RENDERS_DIRECT_NATIVE_CONSUMED_STOCK',
    strpos(
        $page,
        'Direct/native consumed stock'
    ) !== false
);

f4_check(
    $checks,
    'PAGE_RENDERS_EXPLICIT_STOCK_ALLOCATION',
    strpos(
        $page,
        'Explicit consumed-stock allocation'
    ) !== false
);

f4_check(
    $checks,
    'PAGE_RENDERS_DIRECT_AND_ALLOCATED_EXPENSES',
    strpos(
        $page,
        'Direct farm expenses'
    ) !== false
    &&
    strpos(
        $page,
        'Explicit shared-expense allocation'
    ) !== false
);

f4_check(
    $checks,
    'PAGE_EXPLAINS_SOURCE_PROVENANCE',
    strpos(
        $page,
        'Source / provenance'
    ) !== false
    &&
    strpos(
        $page,
        'Sales records / sales allocations'
    ) !== false
    &&
    strpos(
        $page,
        'Consumed stock ledger / stock allocations'
    ) !== false
);

f4_check(
    $checks,
    'PAGE_EXPLAINS_OUTSIDE_CYCLE_SHARED_COST',
    strpos(
        $page,
        'Unallocated broader shared cost remains outside the selected cycle'
    ) !== false
);

f4_check(
    $checks,
    'PAGE_DOES_NOT_REIMPLEMENT_PROFIT_FORMULA',
    substr_count(
        $page,
        "\$summary['profit']"
    ) >= 1
    &&
    strpos(
        $page,
        '$summary[\'revenue\'] -'
    ) === false
);

f4_check(
    $checks,
    'CENTRAL_PROFIT_FORMULA_PRESERVED',
    strpos(
        $financial,
        "'profit'=>\$revenue-\$totalCost"
    ) !== false
);

f4_check(
    $checks,
    'CENTRAL_TOTAL_COST_FORMULA_PRESERVED',
    strpos(
        $financial,
        '$nonFeedExpenses=$manualNonFeedExpenses+$inventoryOperatingConsumption;'
    ) !== false
    &&
    strpos(
        $financial,
        '$totalCost=$nonFeedExpenses+$feedCost;'
    ) !== false
);

f4_check(
    $checks,
    'FINANCIAL_HAS_NO_DIRECT_STOCK_TRANSACTION_QUERY',
    preg_match(
        '/\bFROM\s+stock_transactions\b/i',
        $financial
    ) !== 1
);

f4_check(
    $checks,
    'FINANCIAL_HAS_NO_DIRECT_STOCK_ALLOCATION_QUERY',
    preg_match(
        '/\bFROM\s+stock_consumption_allocations\b/i',
        $financial
    ) !== 1
);

f4_check(
    $checks,
    'ANIMAL_VIEW_REMAINS_EXISTING_AUTHORITY_CONSUMER',
    strpos(
        $animal,
        'Fully Allocated Animal Economics'
    ) !== false
    &&
    strpos(
        $animal,
        'Allocated Shared Cost History'
    ) !== false
);

$verifierSource =
    file_get_contents(
        __FILE__
    );

f4_check(
    $checks,
    'VERIFIER_SOURCE_ONLY',
    $verifierSource !== false
    &&
    strpos(
        $verifierSource,
        'new ' . 'PDO('
    ) === false
    &&
    strpos(
        $verifierSource,
        '->pre' . 'pare('
    ) === false
    &&
    strpos(
        $verifierSource,
        '->exe' . 'cute('
    ) === false
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
