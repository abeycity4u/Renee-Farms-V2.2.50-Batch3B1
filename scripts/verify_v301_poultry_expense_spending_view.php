<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$workspacePath =
    $root
    . '/lib/poultry_expense_workspace.php';

$workspace =
    (string)file_get_contents(
        $workspacePath
    );

$inventory =
    (string)file_get_contents(
        $root
        . '/lib/inventory_financial.php'
    );

$page =
    (string)file_get_contents(
        $root
        . '/poultry/expenses.php'
    );

$checks = 0;
$failed = 0;

function spending_view_check(
    string $name,
    bool $ok
): void {
    global $checks, $failed;

    $checks++;

    echo
        $name
        . '='
        . (
            $ok
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$ok) {
        $failed++;
    }
}


spending_view_check(
    'ONE_GENERIC_MANUAL_CATEGORY_TOTALS_HELPER',
    preg_match_all(
        '/function\s+poultry_expense_workspace_manual_category_totals\s*\(/',
        $workspace
    ) === 1
);


spending_view_check(
    'ONE_GENERIC_TAB_SPENDING_VIEW',
    preg_match_all(
        '/function\s+poultry_expense_workspace_spending_view\s*\(/',
        $workspace
    ) === 1
);


spending_view_check(
    'PRODUCTION_ONLY_SPENDING_AUTHORITY_RETIRED',
    strpos(
        $workspace,
        'poultry_expense_workspace_production_spending_view('
    ) === false
    &&
    strpos(
        $page,
        'poultry_expense_workspace_production_spending_view('
    ) === false
);


spending_view_check(
    'ONE_INVENTORY_PRODUCTION_NORMALIZER',
    preg_match_all(
        '/function\s+poultry_expense_workspace_inventory_purchase_production_type\s*\(/',
        $workspace
    ) === 1
);


spending_view_check(
    'ONE_PERMISSION_AWARE_INVENTORY_FILTER',
    preg_match_all(
        '/function\s+poultry_expense_workspace_filter_inventory_purchases\s*\(/',
        $workspace
    ) === 1
);


spending_view_check(
    'SPENDING_VIEW_REUSES_CANONICAL_PERMISSION_AUTHORITY',
    strpos(
        $workspace,
        'poultry_expense_workspace_can_view_production('
    ) !== false
);


spending_view_check(
    'SPENDING_VIEW_REUSES_CANONICAL_INVENTORY_READER',
    strpos(
        $workspace,
        'inventory_financial_receipts('
    ) !== false
);


spending_view_check(
    'ALL_CAN_READ_POULTRY_RECEIPTS_ONCE',
    strpos(
        $workspace,
        "\$tab === 'all'"
    ) !== false
    &&
    strpos(
        $workspace,
        "? null"
    ) !== false
);


spending_view_check(
    'WORKSPACE_OWNS_NO_STOCK_SQL',
    stripos(
        $workspace,
        'FROM stock_transactions'
    ) === false
);


spending_view_check(
    'CANONICAL_INVENTORY_SERVICE_OWNS_STOCK_SQL',
    preg_match(
        '/FROM\s+stock_transactions\b/i',
        $inventory
    ) === 1
);


spending_view_check(
    'HUB_CONSUMES_ONE_GENERIC_SPENDING_VIEW',
    preg_match_all(
        '/poultry_expense_workspace_spending_view\s*\(/',
        $page
    ) === 1
);


spending_view_check(
    'HUB_RETIRES_MANUAL_ONLY_ALL_SHARED_SUMMARY',
    strpos(
        $page,
        'Visible Poultry Expenses'
    ) === false
    &&
    strpos(
        $page,
        '$workspaceTotals'
    ) === false
);


spending_view_check(
    'HUB_HAS_UNIFIED_SPENDING_PRESENTATION',
    strpos(
        $page,
        'Total Spending'
    ) !== false
    &&
    strpos(
        $page,
        'Inventory Purchases'
    ) !== false
    &&
    strpos(
        $page,
        'Detailed Expenses'
    ) !== false
);


spending_view_check(
    'HUB_SHARED_COPY_STATES_NO_DUPLICATION',
    strpos(
        $page,
        'Not duplicated into Layer or Broiler totals.'
    ) !== false
);


spending_view_check(
    'HUB_ALL_COPY_STATES_COUNT_ONCE',
    strpos(
        $page,
        'Each recorded parent or Inventory receipt is counted once.'
    ) !== false
);


spending_view_check(
    'HUB_REUSES_INVENTORY_PRODUCTION_NORMALIZER',
    strpos(
        $page,
        'poultry_expense_workspace_inventory_purchase_production_type('
    ) !== false
);


require_once $workspacePath;


/*
 * Manual expense arithmetic.
 */
$manualTotals =
    poultry_expense_workspace_manual_category_totals(
        [
            [
                'category' => 'salary',
                'amount' => 100,
                'unit' => 2,
            ],
            [
                'category' => 'fuel',
                'amount' => 50,
                'unit' => 1,
            ],
            [
                'category' => '',
                'amount' => 10,
                'unit' => 1,
            ],
        ]
    );

spending_view_check(
    'MANUAL_TOTALS_FIXTURE_TOTAL_IS_CORRECT',
    abs(
        array_sum(
            $manualTotals
        )
        -
        260.0
    ) < 0.0001
);


/*
 * Inventory attribution and permission fixtures.
 */
$fixture = [
    [
        'id' => 1,
        'production_type' => 'layer',
    ],
    [
        'id' => 2,
        'production_type' => 'broiler',
    ],
    [
        'id' => 3,
        'production_type' => 'shared',
    ],
    [
        'id' => 4,
        'production_type' => '',
    ],
    [
        'id' => 5,
        'production_type' => 'unknown',
    ],
];


$all =
    poultry_expense_workspace_filter_inventory_purchases(
        $fixture,
        'all',
        [
            'layer',
            'broiler',
            'shared',
        ]
    );


$layerVisibleOnly =
    poultry_expense_workspace_filter_inventory_purchases(
        $fixture,
        'all',
        [
            'layer',
        ]
    );


$shared =
    poultry_expense_workspace_filter_inventory_purchases(
        $fixture,
        'shared',
        [
            'layer',
            'broiler',
            'shared',
        ]
    );


$broiler =
    poultry_expense_workspace_filter_inventory_purchases(
        $fixture,
        'broiler',
        [
            'broiler',
        ]
    );


spending_view_check(
    'ALL_COUNTS_EACH_VISIBLE_RECEIPT_ONCE',
    array_column(
        $all,
        'id'
    ) === [
        1,
        2,
        3,
    ]
);


spending_view_check(
    'ALL_PERMISSION_FILTER_PREVENTS_CROSS_PRODUCTION_LEAK',
    array_column(
        $layerVisibleOnly,
        'id'
    ) === [
        1,
    ]
);


spending_view_check(
    'SHARED_ACCEPTS_ONLY_EXPLICIT_SHARED_RECEIPTS',
    array_column(
        $shared,
        'id'
    ) === [
        3,
    ]
);


spending_view_check(
    'BROILER_ACCEPTS_ONLY_EXPLICIT_BROILER_RECEIPTS',
    array_column(
        $broiler,
        'id'
    ) === [
        2,
    ]
);


spending_view_check(
    'UNKNOWN_PRODUCTION_ATTRIBUTION_FAILS_CLOSED',
    poultry_expense_workspace_inventory_purchase_production_type(
        [
            'production_type' =>
                'unknown',
        ]
    ) === ''
);


echo
    'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . $failed
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
