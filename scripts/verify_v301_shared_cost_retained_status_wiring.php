<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$financialWorkspace =
    (string)file_get_contents(
        $root
        . '/lib/financial_allocation_workspace.php'
    );

$stockWorkspace =
    (string)file_get_contents(
        $root
        . '/lib/stock_consumption_allocation_workspace.php'
    );

$profitability =
    (string)file_get_contents(
        $root
        . '/lib/profitability_unallocated_shared.php'
    );

$checks = 0;
$failed = 0;

function retained_status_check(
    string $name,
    bool $condition
): void {
    global $checks, $failed;

    $checks++;

    echo
        $name
        . '='
        . (
            $condition
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$condition) {
        $failed++;
    }
}


retained_status_check(
    'MANUAL_WORKSPACE_HAS_READONLY_LATEST_REVISION',
    strpos(
        $financialWorkspace,
        'function financial_allocation_workspace_latest_revision'
    ) !== false
    &&
    strpos(
        $financialWorkspace,
        'FROM farm_expense_revisions'
    ) !== false
);


retained_status_check(
    'MANUAL_WORKSPACE_RECOGNIZES_RETAIN_SHARED',
    strpos(
        $financialWorkspace,
        "\$latestRevisionAction === 'retain_shared'"
    ) !== false
);


retained_status_check(
    'MANUAL_WORKSPACE_EXPOSES_RESOLUTION_STATUS',
    strpos(
        $financialWorkspace,
        "'resolution_status'"
    ) !== false
    &&
    strpos(
        $financialWorkspace,
        "'retained_shared_reason'"
    ) !== false
);


retained_status_check(
    'STOCK_WORKSPACE_RECOGNIZES_RETAIN_SHARED',
    strpos(
        $stockWorkspace,
        "\$latestRevisionAction === 'retain_shared'"
    ) !== false
);


retained_status_check(
    'STOCK_WORKSPACE_EXPOSES_RESOLUTION_STATUS',
    strpos(
        $stockWorkspace,
        "'resolution_status'"
    ) !== false
    &&
    strpos(
        $stockWorkspace,
        "'retained_shared_reason'"
    ) !== false
);


retained_status_check(
    'PROFITABILITY_HAS_BATCH_COST_REVISION_MAP',
    strpos(
        $profitability,
        'function profitability_unallocated_shared_latest_cost_revision_map'
    ) !== false
);


retained_status_check(
    'PROFITABILITY_BATCHES_EXPENSE_DECISIONS',
    strpos(
        $profitability,
        "array_column(\n                \$expenses,\n                'id'"
    ) !== false
    &&
    strpos(
        $profitability,
        "'expense'"
    ) !== false
);


retained_status_check(
    'PROFITABILITY_BATCHES_STOCK_DECISIONS',
    strpos(
        $profitability,
        "array_column(\n                \$stockParents,\n                'stock_transaction_id'"
    ) !== false
    &&
    strpos(
        $profitability,
        "'stock'"
    ) !== false
);


retained_status_check(
    'PROFITABILITY_MANUAL_COST_RETAINED_STATUS',
    strpos(
        $profitability,
        "\$latestExpenseAction === 'retain_shared'"
    ) !== false
);


retained_status_check(
    'PROFITABILITY_STOCK_COST_RETAINED_STATUS',
    strpos(
        $profitability,
        "\$latestStockAction === 'retain_shared'"
    ) !== false
);


retained_status_check(
    'PROFITABILITY_PARTIAL_MANUAL_COST_STATUS',
    strpos(
        $profitability,
        "\$allocatedCents > 0\n                        ? 'partially_allocated'"
    ) !== false
);


retained_status_check(
    'PROFITABILITY_PARTIAL_STOCK_COST_STATUS',
    strpos(
        $profitability,
        "\$allocatedCents > 0\n                ? 'partially_allocated'"
    ) !== false
);


retained_status_check(
    'PROFITABILITY_EXPOSES_RETAIN_REASON',
    substr_count(
        $profitability,
        "'resolution_reason'"
    ) >= 3
);


retained_status_check(
    'CASH_FEED_PURCHASE_STATUS_PRESERVED',
    strpos(
        $profitability,
        "? 'cash_only_waiting_allocation'"
    ) !== false
);


retained_status_check(
    'BATCH_READER_USES_LATEST_REVISION_NUMBER',
    strpos(
        $profitability,
        'MAX(revision_no) AS revision_no'
    ) !== false
);


retained_status_check(
    'NO_COST_STATUS_DB_WRITES',
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?(?:farm_expense_revisions|stock_consumption_allocation_revisions)\b/i',
        $profitability
    ) !== 1
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
