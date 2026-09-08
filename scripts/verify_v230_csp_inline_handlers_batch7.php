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
$dashboard = file_get_contents($root . '/dashboard.php');

$check(
    str_contains($behaviors, "[data-quick-stock-id]"),
    'Shared behavior recognizes data-quick-stock-id contract'
);

$check(
    str_contains($behaviors, "typeof window.quickStockUpdate !== 'function'"),
    'Shared behavior safely guards missing quickStockUpdate function'
);

$check(
    str_contains($behaviors, 'Number.parseInt(quickStockTarget.dataset.quickStockId, 10)'),
    'Shared behavior parses quick-stock item id from data attribute'
);

$check(
    str_contains($behaviors, 'Number.isInteger(itemId) || itemId <= 0'),
    'Shared behavior rejects invalid quick-stock item ids'
);

$check(
    str_contains($behaviors, 'window.quickStockUpdate(itemId);'),
    'Shared behavior delegates quick-stock action to dashboard-owned function'
);

$check(
    !str_contains($dashboard, 'onclick="quickStockUpdate('),
    'Dashboard no longer uses inline quickStockUpdate handlers'
);

$check(
    substr_count($dashboard, 'data-quick-stock-id=') === 2,
    'Dashboard has exactly two centralized quick-stock controls'
);

$check(
    str_contains($dashboard, 'function quickStockUpdate(itemId)'),
    'Dashboard retains its page-owned quickStockUpdate implementation'
);

$check(
    str_contains($dashboard, 'api/get_item_details.php?id=${itemId}'),
    'Dashboard quick-stock implementation still loads item details'
);

$check(
    str_contains($dashboard, "document.getElementById('quickStockModal')"),
    'Dashboard quick-stock implementation still opens the existing modal'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
