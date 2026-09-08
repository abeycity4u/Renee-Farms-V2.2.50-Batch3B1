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

$page = file_get_contents($root . '/poultry/broiler_expenses.php');
$js = file_get_contents($root . '/assets/js/broiler-expenses.js');
$shared = file_get_contents($root . '/assets/js/app-behaviors.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/broiler-expenses.js')"
    ),
    'Broiler Expenses loads external versioned behavior asset'
);

$check(
    str_contains($page, 'id="broilerExpensesConfig"'),
    'Broiler Expenses exposes page configuration element'
);

$check(
    str_contains($page, 'data-csrf-token='),
    'Broiler Expenses exposes CSRF token contract'
);

$check(
    str_contains($page, 'data-can-manage='),
    'Broiler Expenses exposes management capability contract'
);

$check(
    str_contains($page, 'app_attr(csrf_token())'),
    'Broiler Expenses safely escapes CSRF token attribute'
);

$check(
    !str_contains($page, 'function parseJsonResponse'),
    'Broiler Expenses no longer contains inline parseJsonResponse'
);

$check(
    !str_contains($page, 'function deleteExpense'),
    'Broiler Expenses no longer contains inline deleteExpense'
);

$check(
    !str_contains($page, 'attachEditModal({'),
    'Broiler Expenses no longer contains inline edit-modal initialization'
);

$check(
    str_contains($js, 'async function parseJsonResponse(response)'),
    'External Broiler Expenses asset retains JSON response parser'
);

$check(
    str_contains($js, 'async function deleteExpense(expenseId)'),
    'External Broiler Expenses asset retains delete behavior'
);

$check(
    str_contains($js, 'window.deleteExpense = deleteExpense;'),
    'External Broiler Expenses asset exposes shared delete function globally'
);

$check(
    str_contains($js, 'if (!window.BroilerExpensesConfig.canManage)'),
    'Delete behavior enforces management capability'
);

$check(
    str_contains($js, 'if (window.BroilerExpensesConfig.canManage)'),
    'Management-only initialization remains capability-gated'
);

$check(
    str_contains($js, 'attachEditModal({'),
    'External Broiler Expenses asset retains edit-modal initialization'
);

$check(
    str_contains($js, "document.getElementById('broilerExpensesConfig')"),
    'External Broiler Expenses asset reads page configuration'
);

$check(
    str_contains($js, 'config.dataset.csrfToken'),
    'External Broiler Expenses asset reads CSRF token'
);

$check(
    str_contains($js, "config.dataset.canManage === '1'"),
    'External Broiler Expenses asset reads management capability'
);

$check(
    str_contains(
        $js,
        'csrf_token: window.BroilerExpensesConfig.csrfToken'
    ),
    'Delete request uses configured CSRF token'
);

$check(
    str_contains(
        $js,
        "formData.append(\n                'csrf_token',\n                window.BroilerExpensesConfig.csrfToken"
    ),
    'Edit request uses configured CSRF token'
);

$check(
    str_contains($shared, "typeof window.deleteExpense !== 'function'"),
    'Shared behavior validates global deleteExpense contract'
);

$check(
    str_contains($shared, 'window.deleteExpense(expenseId);'),
    'Shared behavior invokes global deleteExpense contract'
);

$check(
    !str_contains($js, '<?php') && !str_contains($js, '<?='),
    'External Broiler Expenses asset contains no PHP'
);

/*
 * Count active inline script blocks.
 */
$active = preg_replace('/<!--.*?-->/s', '', $page);
$inlineCount = 0;

if (preg_match_all(
    '/<script\b([^>]*)>(.*?)<\/script\s*>/is',
    $active,
    $matches,
    PREG_SET_ORDER
)) {
    foreach ($matches as $match) {
        $attrs = $match[1] ?? '';
        $body = trim($match[2] ?? '');

        if (preg_match('/\bsrc\s*=/i', $attrs)) {
            continue;
        }

        if ($body !== '') {
            $inlineCount++;
        }
    }
}

$check(
    $inlineCount === 0,
    'Broiler Expenses contains zero active inline script blocks'
);

$check(
    substr_count($page, 'broiler-expenses.js') === 1,
    'Broiler Expenses loads external behavior asset exactly once'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
