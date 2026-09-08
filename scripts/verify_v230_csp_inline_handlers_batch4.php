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

$behaviors = file_get_contents($root . '/assets/js/app-behaviors.js');

$targets = [
    'management/expenses.php',
    'poultry/layer_expenses.php',
    'poultry/broiler_expenses.php',
    'ruminant/ruminant_expenses.php',
];

$check(
    str_contains($behaviors, "[data-delete-expense-id]"),
    'Shared behavior layer recognizes data-delete-expense-id contract'
);

$check(
    str_contains($behaviors, "typeof window.deleteExpense !== 'function'"),
    'Shared behavior safely guards missing deleteExpense function'
);

$check(
    str_contains($behaviors, 'Number.parseInt(deleteExpenseTarget.dataset.deleteExpenseId, 10)'),
    'Shared behavior parses delete-expense id from data attribute'
);

$check(
    str_contains($behaviors, 'Number.isInteger(expenseId) || expenseId <= 0'),
    'Shared behavior rejects invalid delete-expense ids'
);

$check(
    str_contains($behaviors, 'window.deleteExpense(expenseId);'),
    'Shared behavior delegates deletion to the page-owned function'
);

foreach ($targets as $relative) {
    $content = file_get_contents($root . '/' . $relative);

    $check(
        !str_contains($content, 'onclick="deleteExpense('),
        $relative . ' no longer uses inline deleteExpense handler'
    );

    $check(
        str_contains($content, 'data-delete-expense-id='),
        $relative . ' opts into centralized delete-expense behavior'
    );

    $check(
        str_contains($content, 'function deleteExpense('),
        $relative . ' retains its page-owned deleteExpense implementation'
    );
}

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
