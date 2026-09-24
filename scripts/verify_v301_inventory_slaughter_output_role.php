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

    'shared_output' =>
        $root . '/lib/slaughter_output_inventory.php',

    'inventory' =>
        $root . '/inventory.php',

    'bridge' =>
        $root . '/includes/inventory_permission_hardening.php',

    'stock' =>
        $root . '/lib/stock_service.php',

    'js' =>
        $root . '/assets/js/inventory.js',

    'service' =>
        $root . '/lib/ruminant_slaughter_processing.php',

    'page' =>
        $root . '/ruminant/slaughter_processing.php',

    'dashboard' =>
        $root . '/dashboard.php',

    'summary_api' =>
        $root . '/api/get_stock_summary.php',
];

$sources = [];

foreach ($paths as $key => $path) {
    $sources[$key] =
        is_file($path)
        && is_readable($path)
            ? (string)file_get_contents($path)
            : '';
}

if ($sources['role'] !== '') {
    require_once $paths['role'];
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
    'Slaughter Output category accepts only Poultry/Ruminant Other Stock',
    inventory_category_role_contract_errors(
        inventory_category_slaughter_output_role(),
        'ruminant',
        'other_stock'
    ) === []
    && inventory_category_role_contract_errors(
        inventory_category_slaughter_output_role(),
        'poultry',
        'other_stock'
    ) === []
    && inventory_category_role_contract_errors(
        inventory_category_slaughter_output_role(),
        'both',
        'other_stock'
    ) !== []
    && inventory_category_role_contract_errors(
        inventory_category_slaughter_output_role(),
        'poultry',
        'feed'
    ) !== []
);

$check(
    'Slaughter Output item accepts Poultry/Ruminant/Shared general stock',
    inventory_category_role_item_contract_errors(
        inventory_category_slaughter_output_role(),
        'ruminant',
        'general'
    ) === []
    && inventory_category_role_item_contract_errors(
        inventory_category_slaughter_output_role(),
        'poultry',
        'general'
    ) === []
    && inventory_category_role_item_contract_errors(
        inventory_category_slaughter_output_role(),
        'both',
        'general'
    ) === []
    && inventory_category_role_item_contract_errors(
        inventory_category_slaughter_output_role(),
        'poultry',
        'feed'
    ) !== []
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
    'Slaughter service resolves output through shared Inventory policy',
    str_contains(
        $sources['service'],
        'slaughter_output_inventory_lock_item('
    )
    && str_contains(
        $sources['shared_output'],
        'inventory_category_slaughter_output_role()'
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
    'Slaughter writer delegates physical stock through shared canonical boundary',
    str_contains(
        $sources['service'],
        'slaughter_output_inventory_receive('
    )
    && str_contains(
        $sources['shared_output'],
        'stock_apply_movement('
    )
    && !preg_match(
        '/(?:INSERT\\s+INTO|UPDATE|DELETE\\s+FROM)\\s+stock_transactions/i',
        $sources['service']
    )
    && !preg_match(
        '/(?:INSERT\\s+INTO|UPDATE|DELETE\\s+FROM)\\s+stock_transactions/i',
        $sources['shared_output']
    )
);

$check(
    'Delegated Add Item loads and validates Inventory role',
    str_contains(
        $sources['bridge'],
        'financial_type, inventory_role'
    )
    && str_contains(
        $sources['bridge'],
        'inventory_category_role_item_contract_errors('
    )
);

$check(
    'Delegated Slaughter Output Add Item must begin at zero',
    str_contains(
        $sources['bridge'],
        'inventory_category_role_initial_stock_errors('
    )
);

$check(
    'Slaughter Output Add Item must begin at zero',
    str_contains(
        $sources['role'],
        'function inventory_category_role_initial_stock_errors'
    )
    && str_contains(
        $sources['inventory'],
        'inventory_category_role_initial_stock_errors('
    )
    && str_contains(
        $sources['js'],
        'refreshAddItemInitialStockPolicy'
    )
);

$check(
    'Central stock writer owns slaughter-output provenance gate',
    str_contains(
        $sources['stock'],
        'inventory_category_role_stock_movement_errors('
    )
    && str_contains(
        $sources['stock'],
        'category_inventory_role'
    )
);

$check(
    'Shared role policy permits only sourced slaughter movements',
    function_exists(
        'inventory_category_role_stock_movement_errors'
    )
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'received',
        'ruminant_slaughter_output',
        1
    ) === []
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'received',
        'ruminant_slaughter_sale_reversal',
        1
    ) === []
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'used',
        'ruminant_slaughter_sale',
        1
    ) === []
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'received',
        'inventory_update',
        1
    ) !== []
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'used',
        'inventory_update',
        1
    ) !== []
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'received',
        'ruminant_slaughter_output',
        null
    ) !== []
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'used',
        'ruminant_slaughter_sale',
        null
    ) !== []
);

$check(
    'Shared role policy permits sourced Poultry slaughter movements',
    inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'received',
        'poultry_slaughter_output',
        1
    ) === []
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'received',
        'poultry_slaughter_sale_reversal',
        1
    ) === []
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'used',
        'poultry_slaughter_sale',
        1
    ) === []
    && inventory_category_role_stock_movement_errors(
        inventory_category_slaughter_output_role(),
        'received',
        'poultry_slaughter_output',
        null
    ) !== []
);

$check(
    'Generic stock reversal is blocked for slaughter-output lots',
    str_contains(
        $sources['stock'],
        'inventory_category_role_reversal_errors('
    )
);

$check(
    'Inventory generic Update Stock UI excludes slaughter-output items',
    str_contains(
        $sources['inventory'],
        'Slaughter Processing'
    )
    && str_contains(
        $sources['inventory'],
        'inventory_category_slaughter_output_role()'
    )
);

$check(
    'Category role changes cannot reclassify categories with items',
    str_contains(
        $sources['role'],
        'function inventory_category_role_transition_errors'
    )
    && str_contains(
        $sources['inventory'],
        'inventory_category_role_transition_errors('
    )
);

$check(
    'Slaughter Output minimum stock is centrally fixed at zero',
    str_contains(
        $sources['role'],
        'function inventory_category_role_min_stock_errors'
    )
    && str_contains(
        $sources['inventory'],
        'inventory_category_role_min_stock_errors('
    )
    && str_contains(
        $sources['bridge'],
        'inventory_category_role_min_stock_errors('
    )
    && str_contains(
        $sources['js'],
        'addItemMinStock'
    )
);

$check(
    'Shared role policy excludes slaughter output from reorder workflows',
    str_contains(
        $sources['role'],
        'function inventory_category_role_uses_reorder_policy'
    )
    && str_contains(
        $sources['inventory'],
        'inventory_category_role_uses_reorder_policy('
    )
    && str_contains(
        $sources['dashboard'],
        'inventory_category_role_uses_reorder_policy('
    )
);

$check(
    'Inventory renders slaughter output without fake minimum or reorder status',
    str_contains(
        $sources['inventory'],
        "'info' => 'Output Stock'"
    )
    && str_contains(
        $sources['inventory'],
        'Batch-controlled'
    )
    && !str_contains(
        $sources['inventory'],
        "\$minStockLevel = max(1, (\$item['min_stock_level'] ?? 0));"
    )
);

$check(
    'Dashboard routes slaughter output to processing instead of generic Update',
    str_contains(
        $sources['dashboard'],
        'Output Stock'
    )
    && str_contains(
        $sources['dashboard'],
        '/ruminant/slaughter_processing.php'
    )
    && str_contains(
        $sources['dashboard'],
        'if ($usesReorderPolicy)'
    )
);

$check(
    'Stock summary excludes slaughter output from low-stock count',
    str_contains(
        $sources['summary_api'],
        'inventory_category_slaughter_output_role()'
    )
    && str_contains(
        $sources['summary_api'],
        "inventory_role,''),'operational')<>?"
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
