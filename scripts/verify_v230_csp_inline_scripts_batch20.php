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

$page = file_get_contents($root . '/poultry/layer_expenses.php');
$js = file_get_contents($root . '/assets/js/layer-expenses.js');
$shared = file_get_contents($root . '/assets/js/app-behaviors.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/layer-expenses.js')"
    ),
    'Layer Expenses loads external versioned behavior asset'
);

$check(
    str_contains($page, 'id="layerExpensesConfig"'),
    'Layer Expenses exposes page configuration element'
);

$check(
    str_contains($page, 'data-csrf-token='),
    'Layer Expenses exposes CSRF token contract'
);

$check(
    str_contains($page, 'data-can-manage='),
    'Layer Expenses exposes management capability contract'
);

$check(
    str_contains($page, 'app_attr(csrf_token())'),
    'Layer Expenses safely escapes CSRF token attribute'
);

$check(
    !str_contains($page, 'function parseJsonResponse'),
    'Layer Expenses no longer contains inline parseJsonResponse'
);

$check(
    !str_contains($page, 'function deleteExpense'),
    'Layer Expenses no longer contains inline deleteExpense'
);

$check(
    !str_contains($page, 'attachEditModal({'),
    'Layer Expenses no longer contains inline edit-modal initialization'
);

$check(
    str_contains($js, 'async function parseJsonResponse(response)'),
    'External Layer Expenses asset retains JSON response parser'
);

$check(
    str_contains($js, 'async function deleteExpense(expenseId)'),
    'External Layer Expenses asset retains delete behavior'
);

$check(
    str_contains($js, 'window.deleteExpense = deleteExpense;'),
    'External Layer Expenses asset exposes shared delete function globally'
);

$check(
    str_contains($js, 'if (!window.LayerExpensesConfig.canManage)'),
    'Delete behavior enforces management capability'
);

$check(
    str_contains($js, 'if (window.LayerExpensesConfig.canManage)'),
    'Management-only initialization remains capability-gated'
);

$check(
    str_contains($js, 'attachEditModal({'),
    'External Layer Expenses asset retains edit-modal initialization'
);

$check(
    str_contains(
        $js,
        "document.getElementById('layerExpensesConfig')"
    ),
    'External Layer Expenses asset reads page configuration'
);

$check(
    str_contains($js, 'config.dataset.csrfToken'),
    'External Layer Expenses asset reads CSRF token'
);

$check(
    str_contains($js, "config.dataset.canManage === '1'"),
    'External Layer Expenses asset reads management capability'
);

$check(
    str_contains(
        $js,
        'csrf_token: window.LayerExpensesConfig.csrfToken'
    ),
    'Delete request uses configured CSRF token'
);

$check(
    str_contains(
        $js,
        "formData.append('csrf_token', window.LayerExpensesConfig.csrfToken);"
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
    'External Layer Expenses asset contains no PHP'
);

/*
 * Verify management block closes before globally used functions.
 */
$managePos = strpos(
    $js,
    'if (window.LayerExpensesConfig.canManage)'
);

$parserPos = strpos(
    $js,
    'async function parseJsonResponse(response)'
);

$deletePos = strpos(
    $js,
    'async function deleteExpense(expenseId)'
);

$exportPos = strpos(
    $js,
    'window.deleteExpense = deleteExpense;'
);

$check(
    $managePos !== false &&
    $parserPos !== false &&
    $deletePos !== false &&
    $exportPos !== false &&
    $managePos < $parserPos &&
    $parserPos < $deletePos &&
    $deletePos < $exportPos,
    'Layer Expenses preserves management init before global helper/delete contracts'
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
    'Layer Expenses contains zero active inline script blocks'
);

$check(
    substr_count($page, 'layer-expenses.js') === 1,
    'Layer Expenses loads external behavior asset exactly once'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
