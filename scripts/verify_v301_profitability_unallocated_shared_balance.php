<?php

/**
 * V3.0.1 Profitability Unallocated Shared Balance.
 *
 * Source-only verifier.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$helper =
    file_get_contents(
        $root
        . '/lib/profitability_unallocated_shared.php'
    );

$page =
    file_get_contents(
        $root
        . '/management/profitability.php'
    );

if (
    $helper === false
    || $page === false
) {
    echo "RESULT=FAIL\n";
    echo "FAILED=SOURCE_LOAD\n";
    exit(1);
}

$checks = [];

$check = static function (
    string $name,
    bool $ok
) use (&$checks): void {
    $checks[$name] = $ok;
};

$check(
    'CENTRAL_READ_MODEL_EXISTS',
    substr_count(
        $helper,
        'function profitability_unallocated_shared_summary('
    ) === 1
);

$check(
    'SHARED_AND_UNALLOCATED_DISTINCT',
    strpos(
        $helper,
        '"Shared Operation" is a source attribution.'
    ) !== false
    &&
    strpos(
        $helper,
        '"Unallocated" is a state of a broader parent.'
    ) !== false
);

$check(
    'USES_SHARED_COST_CONTRACT',
    strpos(
        $helper,
        'shared_cost_contract_parent('
    ) !== false
    &&
    strpos(
        $helper,
        'shared_cost_contract_conservation('
    ) !== false
);

$check(
    'USES_CANONICAL_STOCK_READER',
    strpos(
        $helper,
        'stock_consumption_economics_source_rows('
    ) !== false
    &&
    strpos(
        $helper,
        'stock_consumption_economics_allocation_rows('
    ) !== false
);

$check(
    'USES_CANONICAL_STOCK_ELIGIBILITY',
    strpos(
        $helper,
        'stock_consumption_allocation_workspace_eligibility('
    ) !== false
);

$check(
    'REVENUE_REMAINDER_DISCLOSED',
    strpos(
        $helper,
        "'revenue_unallocated'"
    ) !== false
);

$check(
    'MANUAL_OPERATING_REMAINDER_DISCLOSED',
    strpos(
        $helper,
        "'manual_operating_unallocated'"
    ) !== false
);

$check(
    'FEED_CONSUMPTION_REMAINDER_DISCLOSED',
    strpos(
        $helper,
        "'stock_feed_unallocated'"
    ) !== false
);

$check(
    'OPERATING_STOCK_REMAINDER_DISCLOSED',
    strpos(
        $helper,
        "'stock_operating_unallocated'"
    ) !== false
);

$check(
    'FEED_PURCHASE_CASH_SEPARATED',
    strpos(
        $helper,
        "'cash_feed_purchase_unallocated'"
    ) !== false
    &&
    strpos(
        $page,
        'Cash/spending disclosure only.'
    ) !== false
);

$check(
    'SOURCE_DRIFT_SEPARATED_FROM_UNALLOCATED',
    strpos(
        $helper,
        "'stock_attribution_exception_count'"
    ) !== false
    &&
    strpos(
        $helper,
        "'attribution_exception'"
    ) !== false
);

$check(
    'GENERAL_REVENUE_NOT_SHARED_PARENT',
    strpos(
        $helper,
        "AND farm_type IN (\n                   'poultry',\n                   'ruminant'"
    ) !== false
);

$check(
    'READ_MODEL_OWNS_NO_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+/i',
        $helper
    ) !== 1
);

$check(
    'PAGE_REQUIRES_CENTRAL_READ_MODEL',
    strpos(
        $page,
        "require_once(__DIR__ . '/../lib/profitability_unallocated_shared.php');"
    ) !== false
);

$check(
    'PAGE_CALLS_READ_MODEL_ONCE',
    substr_count(
        $page,
        'profitability_unallocated_shared_summary('
    ) === 1
);

$check(
    'PAGE_HAS_UNALLOCATED_SHARED_PANEL',
    strpos(
        $page,
        'id="unallocated-shared-balances"'
    ) !== false
);

$check(
    'PAGE_EXPLAINS_NO_SECOND_PROFIT_FORMULA',
    strpos(
        $page,
        'do not create another Profit / Loss formula'
    ) !== false
);

$check(
    'PAGE_SHOWS_ATTRIBUTION_EXCEPTION_SEPARATELY',
    strpos(
        $page,
        'Correct source attribution'
    ) !== false
);


$revenueStart =
    strpos(
        $helper,
        ' * SHARED / POOLED REVENUE'
    );

$expenseStart =
    strpos(
        $helper,
        ' * MANUAL SHARED EXPENSES'
    );

$stockStart =
    strpos(
        $helper,
        ' * CONSUMED STOCK'
    );

$presentationStart =
    strpos(
        $helper,
        ' * PRESENTATION TOTALS'
    );

$revenueSection =
    (
        $revenueStart !== false
        &&
        $expenseStart !== false
        &&
        $expenseStart > $revenueStart
    )
        ? substr(
            $helper,
            $revenueStart,
            $expenseStart - $revenueStart
        )
        : '';

$expenseSection =
    (
        $expenseStart !== false
        &&
        $stockStart !== false
        &&
        $stockStart > $expenseStart
    )
        ? substr(
            $helper,
            $expenseStart,
            $stockStart - $expenseStart
        )
        : '';

$stockSection =
    (
        $stockStart !== false
        &&
        $presentationStart !== false
        &&
        $presentationStart > $stockStart
    )
        ? substr(
            $helper,
            $stockStart,
            $presentationStart - $stockStart
        )
        : '';

$check(
    'USES_CANONICAL_FINANCIAL_ALLOCATION_WORKSPACE',
    strpos(
        $helper,
        "require_once __DIR__ . '/financial_allocation_workspace.php';"
    ) !== false
);

$check(
    'MANUAL_EXPENSE_LINK_USES_CANONICAL_ELIGIBILITY_AND_PERMISSION',
    $expenseSection !== ''
    &&
    strpos(
        $expenseSection,
        'financial_allocation_workspace_parent_is_eligible('
    ) !== false
    &&
    strpos(
        $expenseSection,
        'financial_allocation_workspace_can_access('
    ) !== false
    &&
    strpos(
        $expenseSection,
        'financial_allocation_workspace_url('
    ) !== false
);

$check(
    'CASH_FEED_PURCHASE_REMAINS_NON_ACTIONABLE',
    $expenseSection !== ''
    &&
    strpos(
        $expenseSection,
        '!$isFeedPurchase'
    ) !== false
    &&
    strpos(
        $expenseSection,
        "'cash_only_waiting_allocation'"
    ) !== false
);

$check(
    'SHARED_REVENUE_HAS_NO_FAKE_MANUAL_WORKSPACE',
    $revenueSection !== ''
    &&
    strpos(
        $revenueSection,
        "'allocation_url'"
    ) === false
);

$check(
    'STOCK_ACTION_KIND_REMAINS_EXPLICIT',
    $stockSection !== ''
    &&
    strpos(
        $stockSection,
        "'allocation_kind'"
    ) !== false
    &&
    strpos(
        $stockSection,
        "'stock'"
    ) !== false
    &&
    strpos(
        $stockSection,
        'stock_consumption_allocation_workspace_url('
    ) !== false
);

$check(
    'PAGE_SUPPORTS_STOCK_AND_EXPENSE_ACTIONS',
    strpos(
        $page,
        '$allocationKind'
    ) !== false
    &&
    strpos(
        $page,
        "'Open shared-expense allocation workspace'"
    ) !== false
    &&
    strpos(
        $page,
        "'Open consumed-stock allocation workspace'"
    ) !== false
    &&
    strpos(
        $page,
        "\$allocationKind === 'stock'"
    ) !== false
);

$failed = [];

foreach ($checks as $name => $ok) {
    echo $name
        . '='
        . ($ok ? 'PASS' : 'FAIL')
        . PHP_EOL;

    if (!$ok) {
        $failed[] = $name;
    }
}

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

if ($failed) {
    echo 'FAILED='
        . implode(',', $failed)
        . PHP_EOL;

    echo "RESULT=FAIL\n";
    exit(1);
}

echo "RESULT=PASS\n";
exit(0);
