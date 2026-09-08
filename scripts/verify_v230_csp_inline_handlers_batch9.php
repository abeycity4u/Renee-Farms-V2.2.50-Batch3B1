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
$inventory = file_get_contents($root . '/inventory.php');

$check(
    str_contains($behaviors, "[data-inventory-action][data-inventory-item-id]"),
    'Shared behavior recognizes inventory action contract'
);

$check(
    str_contains($behaviors, 'dataset.inventoryItemId'),
    'Shared behavior reads inventory item id'
);

$check(
    str_contains($behaviors, "action === 'history'"),
    'Shared behavior recognizes inventory history action'
);

$check(
    str_contains($behaviors, "action === 'delete'"),
    'Shared behavior recognizes inventory delete action'
);

$check(
    str_contains($behaviors, 'window.viewHistory(itemId);'),
    'Shared behavior delegates inventory history to page-owned function'
);

$check(
    str_contains($behaviors, 'window.deleteItem(itemId);'),
    'Shared behavior delegates inventory delete to page-owned function'
);

$check(
    str_contains($behaviors, "[data-inventory-update-item]"),
    'Shared behavior recognizes inventory item-info change contract'
);

$check(
    str_contains($behaviors, 'window.updateItemInfo(itemInfoTarget.value);'),
    'Shared behavior delegates item-info refresh to page-owned function'
);

$check(
    str_contains($behaviors, "[data-inventory-quantity-label]"),
    'Shared behavior recognizes inventory quantity-label change contract'
);

$check(
    str_contains($behaviors, 'window.updateQuantityLabel();'),
    'Shared behavior delegates quantity-label refresh to page-owned function'
);

$check(
    !preg_match('/onclick="(?:viewHistory|deleteItem)\(/', $inventory),
    'Inventory no longer uses inline history/delete handlers'
);

$check(
    !preg_match('/onchange="(?:updateItemInfo|updateQuantityLabel)\(/', $inventory),
    'Inventory no longer uses inline change handlers'
);

$check(
    substr_count($inventory, 'data-inventory-action="history"') === 1,
    'Inventory has exactly one centralized history action contract'
);

$check(
    substr_count($inventory, 'data-inventory-action="delete"') >= 2,
    'Inventory delete action contract is used by button and row lookup'
);

$check(
    substr_count($inventory, 'data-inventory-update-item') === 1,
    'Inventory has exactly one centralized item-info change contract'
);

$check(
    substr_count($inventory, 'data-inventory-quantity-label') === 1,
    'Inventory has exactly one centralized quantity-label change contract'
);

$check(
    str_contains(
        $inventory,
        '`[data-inventory-action="delete"][data-inventory-item-id="${itemId}"]`'
    ),
    'Inventory deleteItem row lookup uses the new declarative delete contract'
);

$check(
    str_contains($inventory, 'function viewHistory(itemId)'),
    'Inventory retains page-owned viewHistory implementation'
);

$check(
    str_contains($inventory, 'async function deleteItem(itemId)'),
    'Inventory retains page-owned deleteItem implementation'
);

$check(
    str_contains($inventory, 'function updateItemInfo(itemId)'),
    'Inventory retains page-owned updateItemInfo implementation'
);

$check(
    str_contains($inventory, 'function updateQuantityLabel()'),
    'Inventory retains page-owned updateQuantityLabel implementation'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
