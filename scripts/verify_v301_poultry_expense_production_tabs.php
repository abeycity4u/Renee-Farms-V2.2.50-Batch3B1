<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$hub =
    (string)file_get_contents(
        $root
        . '/poultry/expenses.php'
    );

$workspace =
    (string)file_get_contents(
        $root
        . '/lib/poultry_expense_workspace.php'
    );

$layer =
    (string)file_get_contents(
        $root
        . '/poultry/layer_expenses.php'
    );

$broiler =
    (string)file_get_contents(
        $root
        . '/poultry/broiler_expenses.php'
    );

$checks = 0;
$failed = 0;

function production_tab_check(
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


production_tab_check(
    'HUB_CONSUMES_ONE_GENERIC_PRODUCTION_SPENDING_VIEW',
    preg_match_all(
        '/poultry_expense_workspace_production_spending_view\s*\(/',
        $hub
    ) === 1
);


production_tab_check(
    'PRODUCTION_SPENDING_VIEW_IS_LAYER_BROILER_ONLY',
    strpos(
        $hub,
        "'layer'"
    ) !== false
    &&
    strpos(
        $hub,
        "'broiler'"
    ) !== false
    &&
    strpos(
        $hub,
        '$productionSpendingView'
    ) !== false
);


production_tab_check(
    'HUB_OWNS_NO_DIRECT_INVENTORY_RECEIPT_READ',
    strpos(
        $hub,
        'inventory_financial_receipts('
    ) === false
);


production_tab_check(
    'HUB_OWNS_NO_EXPENSE_OR_STOCK_SQL',
    preg_match(
        '/FROM\s+(?:farm_expenses|stock_transactions)\b/i',
        $hub
    ) !== 1
);


production_tab_check(
    'WORKSPACE_REMAINS_MANUAL_EXPENSE_SQL_OWNER',
    preg_match_all(
        '/FROM\s+farm_expenses\b/i',
        $workspace
    ) === 1
);


production_tab_check(
    'WORKSPACE_DELEGATES_INVENTORY_TO_SHARED_SERVICE',
    strpos(
        $workspace,
        'inventory_financial_receipts('
    ) !== false
    &&
    preg_match(
        '/FROM\s+stock_transactions\b/i',
        $workspace
    ) !== 1
);


production_tab_check(
    'HUB_HAS_TOTAL_SPENDING_SUMMARY',
    stripos(
        $hub,
        'Total Spending'
    ) !== false
    &&
    strpos(
        $hub,
        "'total_spending'"
    ) !== false
);


production_tab_check(
    'HUB_HAS_INVENTORY_PURCHASE_SUMMARY',
    stripos(
        $hub,
        'Inventory Purchases'
    ) !== false
    &&
    strpos(
        $hub,
        "'inventory_purchase_total'"
    ) !== false
);


production_tab_check(
    'HUB_HAS_DETAILED_EXPENSE_SUMMARY',
    stripos(
        $hub,
        'Detailed Expenses'
    ) !== false
    &&
    strpos(
        $hub,
        "'manual_expense_total'"
    ) !== false
);


production_tab_check(
    'HUB_RENDERS_SHARED_CATEGORY_BREAKDOWN',
    strpos(
        $hub,
        'inventory_financial_spending_label('
    ) !== false
    &&
    strpos(
        $hub,
        "'spending_category_totals'"
    ) !== false
);


production_tab_check(
    'HUB_RENDERS_ONE_GENERIC_INVENTORY_TABLE',
    preg_match_all(
        '/Received stock shown from the Inventory ledger\./',
        $hub
    ) === 1
);


production_tab_check(
    'INVENTORY_ATTRIBUTION_REUSES_SHARED_LABEL_POLICY',
    strpos(
        $hub,
        'attribution_production_label('
    ) !== false
    &&
    strpos(
        $hub,
        'attribution_cycle_label('
    ) !== false
);


production_tab_check(
    'ALL_SHARED_WORKSPACE_TOTAL_CARDS_PRESERVED',
    strpos(
        $hub,
        "\$workspaceTotals['all']"
    ) !== false
    &&
    strpos(
        $hub,
        "\$workspaceTotals['layer']"
    ) !== false
    &&
    strpos(
        $hub,
        "\$workspaceTotals['broiler']"
    ) !== false
    &&
    strpos(
        $hub,
        "\$workspaceTotals['shared']"
    ) !== false
);


production_tab_check(
    'ONE_COMMON_ADD_EXPENSE_AUTHORITY_PRESERVED',
    preg_match_all(
        '/poultry_expense_entry_create\s*\(/',
        $hub
    ) === 1
);


production_tab_check(
    'ADD_FORM_REMAINS_SINGLE_POINT_ENTRY',
    preg_match_all(
        '/id=["\']addPoultryExpenseModal["\']/',
        $hub
    ) === 1
);


production_tab_check(
    'LEGACY_LAYER_PAGE_NOT_RETARGETED_IN_E3',
    strpos(
        $layer,
        'Inventory Purchases'
    ) !== false
    &&
    strpos(
        $layer,
        'layer-expenses.js'
    ) !== false
);


production_tab_check(
    'LEGACY_BROILER_PAGE_NOT_RETARGETED_IN_E3',
    strpos(
        $broiler,
        'Inventory Purchases'
    ) !== false
    &&
    strpos(
        $broiler,
        'broiler-expenses.js'
    ) !== false
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
