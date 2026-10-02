<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$failures = 0;

$check = static function (
    bool $condition,
    string $label
) use (&$failures): void {
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    echo "FAIL: {$label}\n";
    $failures++;
};

$catalog = file_get_contents(
    $root . '/includes/permission_catalog.php'
);

$permissionsPage = file_get_contents(
    $root . '/admin/permissions.php'
);

$permissionsSave = file_get_contents(
    $root . '/admin/permissions_save.php'
);

$functions = file_get_contents(
    $root . '/includes/functions.php'
);

$migration = file_get_contents(
    $root . '/migrations/091_permission_architecture_alignment.sql'
);

$check(
    substr_count(
        $catalog,
        "'poultry_expenses' =>"
    ) === 1,
    'catalog exposes one Poultry Expenses View permission'
);

foreach (
    [
        'poultry_expenses_add',
        'poultry_expenses_edit',
        'poultry_expenses_delete',
    ]
    as $code
) {
    $check(
        str_contains(
            $catalog,
            "'" . $code . "' =>"
        ),
        'catalog contains ' . $code
    );
}

foreach (
    [
        'poultry_layer_expenses',
        'poultry_broiler_expenses',
    ]
    as $legacyCode
) {
    $check(
        !str_contains(
            $catalog,
            "'" . $legacyCode . "' =>"
        ),
        'catalog retires legacy ' . $legacyCode
    );
}

$check(
    str_contains(
        $catalog,
        "'Poultry Slaughter Processing' => ["
    ),
    'Poultry slaughter has dedicated permission group'
);

$check(
    str_contains(
        $catalog,
        "'Ruminant Slaughter Processing' => ["
    ),
    'Ruminant slaughter has dedicated permission group'
);

$check(
    substr_count(
        $catalog,
        "'roles' => ['poultry_manager','ruminant_manager','sales_rep']]"
    ) >= 3,
    'Inventory permissions include Sales Representative role'
);

$check(
    str_contains(
        $catalog,
        'function permission_catalog_applicable_for_farm('
    ),
    'farm-aware permission applicability helper exists'
);

$check(
    str_contains(
        $catalog,
        "farm_entitlement_is_sales_only("
    ),
    'Sales Rep Inventory applicability is Sales-only scoped'
);

$check(
    str_contains(
        $permissionsPage,
        'permission_catalog_applicable_for_farm($pdo, $permissionFarmId, $role, $module)'
    ),
    'Permissions UI uses farm-aware applicability'
);

$check(
    str_contains(
        $permissionsSave,
        'permission_catalog_applicable_for_farm('
    ),
    'Permissions save uses farm-aware applicability'
);

$check(
    str_contains(
        $functions,
        "require_once __DIR__ . '/permission_catalog.php';"
    ),
    'runtime loads canonical permission catalog'
);

$check(
    str_contains(
        $functions,
        'permission_catalog_applicable_for_farm('
    ),
    'runtime permission resolution uses farm-aware applicability'
);

$check(
    !str_contains(
        $functions,
        "in_array(\$module, ['inventory', 'inventory_add_new_item', 'update_stock', 'farm_intelligence'], true)"
    ),
    'stale dedicated Sales Rep Inventory hard-block is retired'
);

$check(
    str_contains(
        $catalog,
        "'poultry_expenses'"
        . "\n        . \$suffixMap[\$action]"
    ),
    'Poultry expense operational authority resolves canonical family'
);

$check(
    str_contains(
        $migration,
        '091_permission_architecture_alignment.sql'
    ),
    'migration registers its schema marker'
);

$check(
    str_contains(
        $migration,
        'COUNT(DISTINCT module) = 2'
    )
    && str_contains(
        $migration,
        'MIN(allowed) = 1'
    ),
    'legacy Poultry expense migration is privilege-conservative'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "PERMISSION_ARCHITECTURE_ALIGNMENT=PASS\n";
    exit(0);
}

echo "PERMISSION_ARCHITECTURE_ALIGNMENT=FAIL\n";
exit(1);
