<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'expense_page' =>
        $root
        . '/management/expense_allocation.php',

    'financial_js' =>
        $root
        . '/assets/js/financial-allocation-workspace.js',

    'stock_page' =>
        $root
        . '/management/stock_consumption_allocation.php',

    'stock_js' =>
        $root
        . '/assets/js/stock-consumption-allocation-workspace.js',

    'profit_page' =>
        $root
        . '/management/profitability.php',
];

$src = [];

foreach ($files as $key => $file) {
    $src[$key] =
        is_file($file)
            ? (string)file_get_contents($file)
            : '';
}

$checks = 0;
$failed = 0;

function retained_ui_check(
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


retained_ui_check(
    'EXPENSE_PAGE_SHOWS_RETAINED_STATE',
    strpos(
        $src['expense_page'],
        'Retained as shared cost.'
    ) !== false
    &&
    strpos(
        $src['expense_page'],
        "'retained_shared_reason'"
    ) !== false
);


retained_ui_check(
    'EXPENSE_PAGE_HAS_KEEP_SHARED_ACTION',
    strpos(
        $src['expense_page'],
        'financialAllocationRetainSharedButton'
    ) !== false
    &&
    strpos(
        $src['expense_page'],
        'Keep as Shared Cost'
    ) !== false
    &&
    strpos(
        $src['expense_page'],
        'data-allocation-decision="retain_shared"'
    ) !== false
);


retained_ui_check(
    'EXPENSE_PAGE_NORMAL_SAVE_EXPLICIT',
    strpos(
        $src['expense_page'],
        'data-allocation-decision="allocate"'
    ) !== false
);


retained_ui_check(
    'EXPENSE_PAGE_RETAIN_AVAILABLE_WITH_ZERO_TARGETS',
    strpos(
        $src['expense_page'],
        "<?php endif; ?>\n\n            <form\n                id=\"financialAllocationWorkspaceForm\""
    ) !== false
);


retained_ui_check(
    'EXPENSE_PAGE_ALLOCATION_CONTROLS_DISABLE_WITH_ZERO_TARGETS',
    substr_count(
        $src['expense_page'],
        "!\$workspace['cycles']"
    ) >= 3
);


retained_ui_check(
    'EXPENSE_PAGE_CONFIGURES_COST_RETAIN_MESSAGES',
    strpos(
        $src['expense_page'],
        'data-retain-clear-message='
    ) !== false
    &&
    strpos(
        $src['expense_page'],
        'data-retain-reason-message='
    ) !== false
);


retained_ui_check(
    'SHARED_JS_PRESERVES_REVENUE_DEFAULT_MESSAGES',
    strpos(
        $src['financial_js'],
        'Clear cycle amounts before retaining this revenue as shared.'
    ) !== false
    &&
    strpos(
        $src['financial_js'],
        'Enter a reason for keeping this revenue at shared-operation level.'
    ) !== false
);


retained_ui_check(
    'SHARED_JS_USES_CONFIGURED_RETAIN_MESSAGES',
    strpos(
        $src['financial_js'],
        'form.dataset.retainClearMessage'
    ) !== false
    &&
    strpos(
        $src['financial_js'],
        'form.dataset.retainReasonMessage'
    ) !== false
);


retained_ui_check(
    'STOCK_PAGE_SHOWS_RETAINED_STATE',
    strpos(
        $src['stock_page'],
        'Retained as shared cost.'
    ) !== false
    &&
    strpos(
        $src['stock_page'],
        "'retained_shared_reason'"
    ) !== false
);


retained_ui_check(
    'STOCK_PAGE_HAS_KEEP_SHARED_ACTION',
    strpos(
        $src['stock_page'],
        'stockConsumptionAllocationRetainSharedButton'
    ) !== false
    &&
    strpos(
        $src['stock_page'],
        'Keep as Shared Cost'
    ) !== false
    &&
    strpos(
        $src['stock_page'],
        'data-allocation-decision="retain_shared"'
    ) !== false
);


retained_ui_check(
    'STOCK_PAGE_NORMAL_SAVE_EXPLICIT',
    strpos(
        $src['stock_page'],
        'data-allocation-decision="allocate"'
    ) !== false
);


retained_ui_check(
    'STOCK_PAGE_RETAIN_AVAILABLE_WITH_ZERO_TARGETS',
    strpos(
        $src['stock_page'],
        "<?php endif; ?>\n\n            <form\n                id=\"stockConsumptionAllocationWorkspaceForm\""
    ) !== false
);


retained_ui_check(
    'STOCK_PAGE_REASON_SURFACE_ALWAYS_EXISTS',
    strpos(
        $src['stock_page'],
        'Reason for allocation / shared-retention decision'
    ) !== false
    &&
    strpos(
        $src['stock_page'],
        'required to retain this cost as shared'
    ) !== false
);


retained_ui_check(
    'STOCK_JS_READS_SUBMITTER_DECISION',
    strpos(
        $src['stock_js'],
        'submitter.dataset.allocationDecision'
    ) !== false
);


retained_ui_check(
    'STOCK_JS_REQUIRES_ZERO_ALLOCATION_FOR_RETAIN',
    strpos(
        $src['stock_js'],
        "decisionAction === 'retain_shared'"
    ) !== false
    &&
    strpos(
        $src['stock_js'],
        'Clear cycle amounts before retaining this consumed stock cost as shared.'
    ) !== false
);


retained_ui_check(
    'STOCK_JS_REQUIRES_RETAIN_REASON',
    strpos(
        $src['stock_js'],
        'Enter a reason for keeping this consumed stock cost at shared-operation level.'
    ) !== false
);


retained_ui_check(
    'STOCK_JS_SENDS_DECISION_ACTION',
    strpos(
        $src['stock_js'],
        "'decision_action'"
    ) !== false
    &&
    strpos(
        $src['stock_js'],
        'decisionAction'
    ) !== false
);


retained_ui_check(
    'STOCK_JS_NORMAL_SAVE_DISABLED_WITH_ZERO_TARGETS',
    strpos(
        $src['stock_js'],
        'const hasAllocationTargets ='
    ) !== false
    &&
    strpos(
        $src['stock_js'],
        '!hasAllocationTargets'
    ) !== false
);


retained_ui_check(
    'STOCK_JS_USES_ACTIVE_SUBMIT_BUTTON',
    strpos(
        $src['stock_js'],
        'const activeButton ='
    ) !== false
    &&
    strpos(
        $src['stock_js'],
        'submitter || saveButton'
    ) !== false
);


retained_ui_check(
    'PROFITABILITY_RETAINED_TOOLTIP_SUPPORTS_COST',
    strpos(
        $src['profit_page'],
        'Shared cost intentionally retained without cycle attribution'
    ) !== false
    &&
    strpos(
        $src['profit_page'],
        '$retainedTitle'
    ) !== false
);


retained_ui_check(
    'PAGES_HAVE_NO_DIRECT_ALLOCATION_SQL',
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?(?:financial_allocations|stock_consumption_allocations|farm_expense_revisions|stock_consumption_allocation_revisions)\b/i',
        $src['expense_page']
        . "\n"
        . $src['stock_page']
        . "\n"
        . $src['profit_page']
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
