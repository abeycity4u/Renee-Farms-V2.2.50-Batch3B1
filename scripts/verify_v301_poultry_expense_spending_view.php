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

$inventoryPath =
    $root
    . '/lib/inventory_financial.php';

$inventory =
    (string)file_get_contents(
        $inventoryPath
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


/*
 * Contract existence checks deliberately use regex/function identity rather
 * than exact indentation or formatting.
 */
spending_view_check(
    'WORKSPACE_LOADS_CANONICAL_INVENTORY_FINANCIAL_SERVICE',
    preg_match(
        '/require_once\s+__DIR__\s*\.\s*[\'"]\/inventory_financial\.php[\'"]\s*;/s',
        $workspace
    ) === 1
);


spending_view_check(
    'ONE_GENERIC_MANUAL_CATEGORY_TOTALS_HELPER',
    preg_match_all(
        '/function\s+poultry_expense_workspace_manual_category_totals\s*\(/',
        $workspace
    ) === 1
);


spending_view_check(
    'ONE_GENERIC_PRODUCTION_SPENDING_VIEW',
    preg_match_all(
        '/function\s+poultry_expense_workspace_production_spending_view\s*\(/',
        $workspace
    ) === 1
);


spending_view_check(
    'SPENDING_VIEW_REUSES_WORKSPACE_FILTER_AUTHORITY',
    strpos(
        $workspace,
        'poultry_expense_workspace_filter_rows('
    ) !== false
    &&
    strpos(
        $workspace,
        'poultry_expense_workspace_can_view_production('
    ) !== false
);


spending_view_check(
    'SPENDING_VIEW_REUSES_CANONICAL_INVENTORY_RECEIPTS',
    strpos(
        $workspace,
        'inventory_financial_receipts('
    ) !== false
);


spending_view_check(
    'SPENDING_VIEW_REUSES_CANONICAL_INVENTORY_TOTALS',
    strpos(
        $workspace,
        'inventory_financial_receipt_total('
    ) !== false
    &&
    strpos(
        $workspace,
        'inventory_financial_receipt_category_totals('
    ) !== false
    &&
    strpos(
        $workspace,
        'inventory_financial_combined_spending_totals('
    ) !== false
);


spending_view_check(
    'WORKSPACE_OWNS_NO_STOCK_TRANSACTION_SQL',
    stripos(
        $workspace,
        'FROM stock_transactions'
    ) === false
);


spending_view_check(
    'WORKSPACE_OWNS_ONE_MANUAL_EXPENSE_SELECT',
    preg_match_all(
        '/FROM\s+farm_expenses\b/i',
        $workspace
    ) === 1
);


spending_view_check(
    'WORKSPACE_OWNS_NO_CREATE_AUTHORITY',
    strpos(
        $workspace,
        'poultry_expense_entry_create('
    ) === false
);


spending_view_check(
    'CANONICAL_INVENTORY_SERVICE_REMAINS_SQL_OWNER',
    preg_match(
        '/FROM\s+stock_transactions\b/i',
        $inventory
    ) === 1
);


/*
 * Execute the pure aggregation helper against representative manual rows.
 * This protects behavior without depending on source whitespace.
 */
require_once $workspacePath;

$fixture = [
    [
        'category' =>
            'salary',

        'amount' =>
            100,

        'unit' =>
            2,
    ],

    [
        'category' =>
            'fuel',

        'amount' =>
            50,

        'unit' =>
            1,
    ],

    [
        'category' =>
            '',

        'amount' =>
            10,

        'unit' =>
            1,
    ],
];

$totals =
    poultry_expense_workspace_manual_category_totals(
        $fixture
    );

spending_view_check(
    'MANUAL_TOTALS_AGGREGATE_AMOUNT_TIMES_UNIT',
    isset(
        $totals[
            'salary'
        ]
    )
    &&
    abs(
        (float)$totals[
            'salary'
        ]
        -
        200.0
    ) < 0.0001
);


spending_view_check(
    'MANUAL_TOTALS_KEEP_CATEGORY_BREAKDOWN',
    isset(
        $totals[
            'fuel'
        ]
    )
    &&
    abs(
        (float)$totals[
            'fuel'
        ]
        -
        50.0
    ) < 0.0001
);


spending_view_check(
    'MANUAL_TOTALS_NORMALIZE_EMPTY_CATEGORY_TO_MISC',
    isset(
        $totals[
            'misc'
        ]
    )
    &&
    abs(
        (float)$totals[
            'misc'
        ]
        -
        10.0
    ) < 0.0001
);


spending_view_check(
    'MANUAL_TOTALS_FIXTURE_TOTAL_IS_CORRECT',
    abs(
        array_sum(
            $totals
        )
        -
        260.0
    ) < 0.0001
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
