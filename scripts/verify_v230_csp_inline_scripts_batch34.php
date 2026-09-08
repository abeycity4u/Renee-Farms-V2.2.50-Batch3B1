<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$php = file_get_contents(
    $root . '/management/expenses.php'
);

$js = file_get_contents(
    $root . '/assets/js/management-expenses.js'
);

$check(
    str_contains($php, 'id="managementExpensesConfig"'),
    'Management Expenses emits centralized page config'
);

$check(
    str_contains($php, 'data-can-manage="'),
    'Management Expenses config exposes permission state'
);

$check(
    str_contains($php, 'data-csrf-token="'),
    'Management Expenses config exposes CSRF token'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/management-expenses.js')"
    ),
    'Management Expenses loads versioned external asset'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Management Expenses safely escapes CSRF config'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Management Expenses has zero literal inline script blocks'
);

$check(
    str_contains(
        $js,
        "document.getElementById('managementExpensesConfig')"
    ),
    'External asset reads centralized config'
);

$check(
    str_contains($js, 'dataset.canManage'),
    'External asset reads permission config'
);

$check(
    str_contains($js, 'dataset.csrfToken'),
    'External asset reads CSRF config'
);

$check(
    str_contains($js, 'if (canManageExpenses)'),
    'External asset preserves permission-gated management behavior'
);

$check(
    str_contains($js, 'applyFilters'),
    'External asset retains report filter behavior'
);

$check(
    str_contains($js, 'farmTypeFilter')
    && str_contains($js, 'productionTypeFilter')
    && str_contains($js, 'categoryFilter'),
    'External asset retains farm, production and category filters'
);

$check(
    str_contains($js, 'reportMode')
    && str_contains($js, 'monthFilter')
    && str_contains($js, 'yearFilter'),
    'External asset retains monthly/yearly report switching'
);

$check(
    str_contains($js, 'printMonthlyBtn')
    && str_contains($js, 'printYearlyBtn'),
    'External asset retains PDF button behavior'
);

$check(
    str_contains($js, 'window.location'),
    'External asset retains report navigation behavior'
);

$check(
    str_contains($js, 'URLSearchParams'),
    'External asset retains encoded delete request body'
);

$check(
    str_contains($js, "fetch('../api/update_expense.php'"),
    'External asset retains expense update endpoint'
);

$check(
    str_contains($js, "fetch('../api/delete_expense.php'"),
    'External asset retains expense delete endpoint'
);

$check(
    substr_count($js, 'csrfToken') >= 3,
    'External asset reuses centralized CSRF token'
);

$check(
    str_contains($js, 'AppConfirm.ask'),
    'External asset retains delete confirmation flow'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'External Management Expenses asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
