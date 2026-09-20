<?php

$root =
    dirname(__DIR__);

$financialPath =
    $root
    . '/includes/financial.php';

$readerPath =
    $root
    . '/lib/stock_consumption_economics.php';

$unallocatedPath =
    $root
    . '/lib/profitability_unallocated_shared.php';

$profitabilityPagePath =
    $root
    . '/management/profitability.php';

$financial =
    file_get_contents(
        $financialPath
    );

$reader =
    file_get_contents(
        $readerPath
    );

$unallocated =
    file_get_contents(
        $unallocatedPath
    );

$profitabilityPage =
    file_get_contents(
        $profitabilityPagePath
    );

if (
    $financial === false
    ||
    $reader === false
    ||
    $unallocated === false
    ||
    $profitabilityPage === false
) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_MISSING\n";
    exit(1);
}

$checks = [];

function verify_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

/*
 * Isolate only getProfitabilitySummary().
 * getPoultryUnitEconomics() follows immediately afterward.
 */
$profitStart =
    strpos(
        $financial,
        "if (!function_exists('getProfitabilitySummary'))"
    );

$profitEnd =
    strpos(
        $financial,
        "if (!function_exists('getPoultryUnitEconomics'))"
    );

$profitSource =
    (
        $profitStart !== false
        &&
        $profitEnd !== false
        &&
        $profitEnd > $profitStart
    )
        ? substr(
            $financial,
            $profitStart,
            $profitEnd - $profitStart
        )
        : '';

verify_check(
    $checks,
    'PROFITABILITY_FUNCTION_FOUND',
    $profitSource !== ''
);

verify_check(
    $checks,
    'REQUIRES_CANONICAL_STOCK_ECONOMICS',
    strpos(
        $financial,
        "require_once __DIR__ . '/../lib/stock_consumption_economics.php';"
    ) !== false
);

verify_check(
    $checks,
    'PROFITABILITY_CALLS_CANONICAL_STOCK_SUMMARY',
    strpos(
        $profitSource,
        'stock_consumption_economics_summary('
    ) !== false
);

verify_check(
    $checks,
    'FEED_COST_FROM_CANONICAL_SUMMARY',
    strpos(
        $profitSource,
        "'feed_consumption_cost'"
    ) !== false
    &&
    strpos(
        $profitSource,
        '$feedCost ='
    ) !== false
);

verify_check(
    $checks,
    'OPERATING_COST_FROM_CANONICAL_SUMMARY',
    strpos(
        $profitSource,
        "'inventory_operating_consumption_cost'"
    ) !== false
    &&
    strpos(
        $profitSource,
        '$inventoryOperatingConsumption ='
    ) !== false
);

verify_check(
    $checks,
    'OPERATING_BREAKDOWN_FROM_CANONICAL_SUMMARY',
    strpos(
        $profitSource,
        "'inventory_operating_consumption_breakdown'"
    ) !== false
);

verify_check(
    $checks,
    'PROFITABILITY_NO_DIRECT_STOCK_TRANSACTION_QUERY',
    preg_match(
        '/\bFROM\s+stock_transactions\b/i',
        $profitSource
    ) !== 1
);

verify_check(
    $checks,
    'PROFITABILITY_NO_DIRECT_STOCK_ALLOCATION_QUERY',
    preg_match(
        '/\bFROM\s+stock_consumption_allocations\b/i',
        $profitSource
    ) !== 1
);

verify_check(
    $checks,
    'CENTRAL_READER_OWNS_STOCK_ALLOCATION_QUERY',
    strpos(
        $reader,
        'FROM stock_consumption_allocations'
    ) !== false
);

verify_check(
    $checks,
    'CENTRAL_READER_OWNS_EFFECTIVE_STOCK_POLICY',
    strpos(
        $reader,
        'stock_effective_sql_predicate'
    ) !== false
);

verify_check(
    $checks,
    'CENTRAL_READER_OWNS_TRANSACTION_FEED_POLICY',
    strpos(
        $reader,
        'stock_feed_transaction_sql_predicate'
    ) !== false
);

verify_check(
    $checks,
    'CENTRAL_READER_OWNS_OPERATING_CLASSIFICATION_POLICY',
    strpos(
        $reader,
        'inventory_operating_consumption_classifications'
    ) !== false
);

verify_check(
    $checks,
    'CASH_FEED_REMAINS_SEPARATE_SPENDING_SIGNAL',
    strpos(
        $profitSource,
        '$cashFeedSql='
    ) !== false
    &&
    strpos(
        $profitSource,
        "'cash_feed_expenses'=>$cashFeed"
    ) !== false
);

verify_check(
    $checks,
    'TOTAL_COST_FORMULA_PRESERVED',
    strpos(
        $profitSource,
        '$nonFeedExpenses=$manualNonFeedExpenses+$inventoryOperatingConsumption;'
    ) !== false
    &&
    strpos(
        $profitSource,
        '$totalCost=$nonFeedExpenses+$feedCost;'
    ) !== false
);

$profitFormulaPattern = <<<'REGEX'
/['"]profit['"]\s*=>\s*\$revenue\s*-\s*\$totalCost/
REGEX;

verify_check(
    $checks,
    'PROFIT_FORMULA_PRESERVED',
    preg_match(
        $profitFormulaPattern,
        $profitSource
    ) === 1
);

verify_check(
    $checks,
    'NO_PROFITABILITY_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?'
        . '(?:stock_transactions|stock_consumption_allocations)\b/i',
        $profitSource
    ) !== 1
);


verify_check(
    $checks,
    'UNALLOCATED_STOCK_ROW_EXPOSES_CANONICAL_WORKSPACE_URL',
    substr_count(
        $unallocated,
        'stock_consumption_allocation_workspace_url('
    ) === 1
    &&
    strpos(
        $unallocated,
        "'allocation_url'"
    ) !== false
);

verify_check(
    $checks,
    'PROFITABILITY_ACTION_IS_PERMISSION_GATED',
    strpos(
        $profitabilityPage,
        'stock_consumption_allocation_workspace_can_manage()'
    ) !== false
    &&
    strpos(
        $profitabilityPage,
        '$canManageStockAllocation'
    ) !== false
);

verify_check(
    $checks,
    'PROFITABILITY_RENDERS_CANONICAL_ALLOCATION_LINK',
    strpos(
        $profitabilityPage,
        "'allocation_url'"
    ) !== false
    &&
    strpos(
        $profitabilityPage,
        '$allocationUrl'
    ) !== false
    &&
    strpos(
        $profitabilityPage,
        'Open consumed-stock allocation workspace'
    ) !== false
);

verify_check(
    $checks,
    'NON_ACTIONABLE_STATUS_BADGES_REMAIN_NON_LINKS',
    strpos(
        $profitabilityPage,
        'Correct source attribution'
    ) !== false
    &&
    strpos(
        $profitabilityPage,
        'Cash-only balance'
    ) !== false
    &&
    strpos(
        $profitabilityPage,
        '<span class="badge bg-warning text-dark">'
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
