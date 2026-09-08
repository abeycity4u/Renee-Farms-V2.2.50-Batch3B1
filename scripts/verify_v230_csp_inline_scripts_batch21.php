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

$page = file_get_contents($root . '/ruminant/ruminant_expenses.php');
$allocationJs = file_get_contents(
    $root . '/assets/js/ruminant-expenses-allocation.js'
);
$behaviorJs = file_get_contents(
    $root . '/assets/js/ruminant-expenses.js'
);
$shared = file_get_contents(
    $root . '/assets/js/app-behaviors.js'
);

/*
 * Page asset/config contracts.
 */
$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/ruminant-expenses-allocation.js')"
    ),
    'Ruminant Expenses loads external allocation asset'
);

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/ruminant-expenses.js')"
    ),
    'Ruminant Expenses loads external behavior asset'
);

$check(
    str_contains($page, 'id="ruminantExpensesAllocationConfig"'),
    'Ruminant Expenses exposes centralized configuration element'
);

$check(
    str_contains($page, 'data-cycles='),
    'Ruminant Expenses exposes cycle data contract'
);

$check(
    str_contains($page, 'data-animals='),
    'Ruminant Expenses exposes animal data contract'
);

$check(
    str_contains($page, 'data-csrf-token='),
    'Ruminant Expenses exposes CSRF token contract'
);

$check(
    str_contains($page, 'data-can-manage='),
    'Ruminant Expenses exposes management capability contract'
);

$check(
    str_contains(
        $page,
        'app_attr(json_encode($expenseCycles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE))'
    ),
    'Cycle JSON is safely escaped for attribute context'
);

$check(
    str_contains(
        $page,
        'app_attr(json_encode($ruminantAnimals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE))'
    ),
    'Animal JSON is safely escaped for attribute context'
);

$check(
    str_contains($page, 'app_attr(csrf_token())'),
    'CSRF token is safely escaped for attribute context'
);

/*
 * Allocation asset contracts.
 */
$check(
    str_contains(
        $allocationJs,
        "document.getElementById('ruminantExpensesAllocationConfig')"
    ),
    'Allocation asset reads centralized configuration element'
);

$check(
    str_contains(
        $allocationJs,
        "JSON.parse(config.dataset.cycles || '[]')"
    ),
    'Allocation asset parses cycle dataset'
);

$check(
    str_contains(
        $allocationJs,
        "JSON.parse(config.dataset.animals || '[]')"
    ),
    'Allocation asset parses animal dataset'
);

$check(
    str_contains(
        $allocationJs,
        'const ruminantExpenseCycles = window.RuminantExpensesAllocationConfig.cycles;'
    ),
    'Allocation asset sources cycles from centralized config'
);

$check(
    str_contains(
        $allocationJs,
        'const ruminantExpenseAnimals = window.RuminantExpensesAllocationConfig.animals;'
    ),
    'Allocation asset sources animals from centralized config'
);

$check(
    str_contains(
        $allocationJs,
        'function refreshRuminantExpenseCycles()'
    ),
    'Allocation asset retains add-cycle filtering'
);

$check(
    str_contains(
        $allocationJs,
        'function refreshEditRuminantExpenseCycles(selectedCycle = 0)'
    ),
    'Allocation asset retains edit-cycle filtering'
);

$check(
    str_contains(
        $allocationJs,
        'function expenseTotal(prefix)'
    ),
    'Allocation asset retains expense-total helper'
);

$check(
    str_contains(
        $allocationJs,
        'function renderAnimalAllocation(prefix, selectedRows = null)'
    ),
    'Allocation asset retains animal-allocation renderer'
);

/*
 * Behavior asset contracts.
 */
$check(
    str_contains(
        $behaviorJs,
        "document.getElementById(\n        'ruminantExpensesAllocationConfig'\n    )"
    ),
    'Behavior asset reads same centralized config element'
);

$check(
    str_contains(
        $behaviorJs,
        'config.dataset.csrfToken'
    ),
    'Behavior asset reads CSRF token'
);

$check(
    str_contains(
        $behaviorJs,
        "config.dataset.canManage === '1'"
    ),
    'Behavior asset reads management capability'
);

$check(
    str_contains(
        $behaviorJs,
        'if (window.RuminantExpensesConfig.canManage)'
    ),
    'Edit initialization remains capability-gated'
);

$check(
    str_contains(
        $behaviorJs,
        'attachEditModal({'
    ),
    'Behavior asset retains edit-modal initialization'
);

$check(
    str_contains(
        $behaviorJs,
        'refreshEditRuminantExpenseCycles(parseInt(btn.dataset.cycle'
    ),
    'Behavior asset retains edit-cycle synchronization'
);

$check(
    str_contains(
        $behaviorJs,
        "renderAnimalAllocation('edit', rows);"
    ),
    'Behavior asset retains edit animal-allocation rendering'
);

$check(
    str_contains(
        $behaviorJs,
        'async function parseJsonResponse(response)'
    ),
    'Behavior asset retains JSON response parser'
);

$check(
    str_contains(
        $behaviorJs,
        'async function deleteExpense(expenseId)'
    ),
    'Behavior asset retains delete behavior'
);

$check(
    str_contains(
        $behaviorJs,
        'if (!window.RuminantExpensesConfig.canManage)'
    ),
    'Delete behavior enforces management capability'
);

$check(
    str_contains(
        $behaviorJs,
        'csrf_token: window.RuminantExpensesConfig.csrfToken'
    ),
    'Delete request uses configured CSRF token'
);

$check(
    str_contains(
        $behaviorJs,
        "formData.append('csrf_token', window.RuminantExpensesConfig.csrfToken);"
    ),
    'Edit request uses configured CSRF token'
);

$check(
    str_contains(
        $behaviorJs,
        'window.deleteExpense = deleteExpense;'
    ),
    'Behavior asset exposes deleteExpense globally'
);

$check(
    str_contains(
        $shared,
        "typeof window.deleteExpense !== 'function'"
    ),
    'Shared behavior validates global deleteExpense contract'
);

$check(
    str_contains(
        $shared,
        'window.deleteExpense(expenseId);'
    ),
    'Shared behavior invokes global deleteExpense contract'
);

/*
 * PHP leakage.
 */
$check(
    !str_contains($allocationJs, '<?php') &&
    !str_contains($allocationJs, '<?='),
    'Allocation asset contains no PHP'
);

$check(
    !str_contains($behaviorJs, '<?php') &&
    !str_contains($behaviorJs, '<?='),
    'Behavior asset contains no PHP'
);

/*
 * Ensure global helper/delete functions are outside management block.
 */
$managePos = strpos(
    $behaviorJs,
    'if (window.RuminantExpensesConfig.canManage)'
);

$parserPos = strpos(
    $behaviorJs,
    'async function parseJsonResponse(response)'
);

$deletePos = strpos(
    $behaviorJs,
    'async function deleteExpense(expenseId)'
);

$exportPos = strpos(
    $behaviorJs,
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
    'Ruminant Expenses preserves management init before global helper/delete contracts'
);

/*
 * Active inline script count.
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
    'Ruminant Expenses contains zero active inline script blocks'
);

$check(
    substr_count(
        $page,
        'ruminant-expenses-allocation.js'
    ) === 1,
    'Ruminant Expenses loads allocation asset exactly once'
);

$check(
    substr_count(
        $page,
        'ruminant-expenses.js'
    ) === 1,
    'Ruminant Expenses loads behavior asset exactly once'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
