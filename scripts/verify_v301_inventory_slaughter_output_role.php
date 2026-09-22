<?php

declare(strict_types=1);

/**
 * V3.0.1 — Inventory slaughter-output role verifier.
 * Source-only. No database connection or write.
 */

$root = dirname(__DIR__);

$paths = [
    'migration' =>
        $root . '/migrations/070_inventory_slaughter_output_role.sql',

    'role' =>
        $root . '/lib/inventory_category_role.php',

    'inventory' =>
        $root . '/inventory.php',

    'service' =>
        $root . '/lib/ruminant_slaughter_processing.php',

    'page' =>
        $root . '/ruminant/slaughter_processing.php',
];

$sources = [];

foreach ($paths as $key => $path) {
    $sources[$key] =
        is_file($path)
        && is_readable($path)
            ? (string)file_get_contents($path)
            : '';
}

$checks = 0;
$failures = 0;

$check = static function (
    string $label,
    bool $ok
) use (
    &$checks,
    &$failures
): void {
    $checks++;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    'Required Inventory-role sources are readable',
    !in_array('', $sources, true)
);

$check(
    'Migration adds explicit inventory_role with safe operational default',
    str_contains(
        $sources['migration'],
        'ADD COLUMN inventory_role VARCHAR(40)'
    )
    && str_contains(
        $sources['migration'],
        "DEFAULT 'operational'"
    )
);

$check(
    'Migration does not guess historical slaughter-output categories',
    !preg_match(
        '/UPDATE\s+inventory_categories/i',
        $sources['migration']
    )
);

$check(
    'Central role policy defines operational and slaughter output',
    str_contains(
        $sources['role'],
        "'operational'"
    )
    && str_contains(
        $sources['role'],
        "'slaughter_output'"
    )
);

$check(
    'Slaughter Output category is constrained to Ruminant and Other Stock',
    str_contains(
        $sources['role'],
        "categoryFarmType !== 'ruminant'"
    )
    && str_contains(
        $sources['role'],
        "financialType !== 'other_stock'"
    )
);

$check(
    'Slaughter Output item is constrained to Ruminant general stock',
    str_contains(
        $sources['role'],
        "itemFarmType !== 'ruminant'"
    )
    && str_contains(
        $sources['role'],
        "feedCategory !== 'general'"
    )
);

$check(
    'Inventory category creation persists canonical inventory role',
    str_contains(
        $sources['inventory'],
        'category_inventory_role'
    )
    && str_contains(
        $sources['inventory'],
        'inventory_role'
    )
    && str_contains(
        $sources['inventory'],
        'inventory_category_role_contract_errors('
    )
);

$check(
    'Inventory item creation validates category role boundary',
    str_contains(
        $sources['inventory'],
        'inventory_category_role_item_contract_errors('
    )
);

$check(
    'Inventory UI exposes category role without overloading Financial Type',
    str_contains(
        $sources['inventory'],
        'Inventory Role'
    )
    && str_contains(
        $sources['inventory'],
        'inventory_category_roles()'
    )
);

$check(
    'Central helper owns the slaughter-output role key',
    str_contains(
        $sources['role'],
        'function inventory_category_slaughter_output_role'
    )
);

$check(
    'Slaughter service resolves output role through shared policy',
    str_contains(
        $sources['service'],
        'inventory_category_slaughter_output_role()'
    )
    && str_contains(
        $sources['service'],
        'ic.inventory_role=?'
    )
);

$check(
    'Slaughter page dropdown resolves output role through shared policy',
    str_contains(
        $sources['page'],
        'inventory_category_slaughter_output_role()'
    )
    && str_contains(
        $sources['page'],
        'ic.inventory_role=?'
    )
);

$check(
    'Slaughter writer still delegates physical stock to canonical stock service',
    str_contains(
        $sources['service'],
        'stock_apply_movement('
    )
    && !preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+stock_transactions/i',
        $sources['service']
    )
);

$check(
    'Migration records checkpoint 070',
    str_contains(
        $sources['migration'],
        '070_inventory_slaughter_output_role.sql'
    )
);

echo PHP_EOL;
echo 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . ($failures === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
