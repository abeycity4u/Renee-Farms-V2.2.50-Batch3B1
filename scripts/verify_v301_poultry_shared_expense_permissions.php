<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

require_once
    $root
    . '/includes/permission_catalog.php';

$paths = [
    'catalog' =>
        $root
        . '/includes/permission_catalog.php',

    'update' =>
        $root
        . '/api/update_expense.php',

    'delete' =>
        $root
        . '/api/delete_expense.php',

    'allocation' =>
        $root
        . '/lib/financial_allocation_workspace.php',
];

$content = [];

foreach ($paths as $key => $path) {
    $content[$key] =
        is_file($path)
            ? (string)file_get_contents($path)
            : '';
}

$checks = 0;
$failed = 0;

function poultry_shared_permission_check(
    string $name,
    bool $ok
): void {
    global $checks, $failed;

    $checks++;

    echo
        $name
        . '='
        . (
            $ok
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$ok) {
        $failed++;
    }
}


$shared = [
    'farm_type' =>
        'poultry',

    'production_type' =>
        'shared',

    'poultry_category' =>
        null,
];

$layer = [
    'farm_type' =>
        'poultry',

    'production_type' =>
        'layer',

    'poultry_category' =>
        'layer',
];

$legacyBroiler = [
    'farm_type' =>
        'poultry',

    'production_type' =>
        '',

    'poultry_category' =>
        'broiler',
];


poultry_shared_permission_check(
    'SHARED_VIEW_IS_LAYER_AND_BROILER_VIEW',
    permission_catalog_expense_required_permissions(
        $shared,
        'view'
    ) === [
        'poultry_layer_expenses',
        'poultry_broiler_expenses',
    ]
);


poultry_shared_permission_check(
    'SHARED_EDIT_IS_LAYER_AND_BROILER_EDIT',
    permission_catalog_expense_required_permissions(
        $shared,
        'edit'
    ) === [
        'poultry_layer_expenses_edit',
        'poultry_broiler_expenses_edit',
    ]
);


poultry_shared_permission_check(
    'SHARED_DELETE_IS_LAYER_AND_BROILER_DELETE',
    permission_catalog_expense_required_permissions(
        $shared,
        'delete'
    ) === [
        'poultry_layer_expenses_delete',
        'poultry_broiler_expenses_delete',
    ]
);


poultry_shared_permission_check(
    'LAYER_ROW_AUTHORITY_PRESERVED',
    permission_catalog_expense_required_permissions(
        $layer,
        'edit'
    ) === [
        'poultry_layer_expenses_edit',
    ]
);


poultry_shared_permission_check(
    'LEGACY_BROILER_CATEGORY_FALLBACK_PRESERVED',
    permission_catalog_expense_required_permissions(
        $legacyBroiler,
        'delete'
    ) === [
        'poultry_broiler_expenses_delete',
    ]
);


poultry_shared_permission_check(
    'RUMINANT_AUTHORITY_PRESERVED',
    permission_catalog_expense_required_permissions(
        [
            'farm_type' =>
                'ruminant',
        ],
        'edit'
    ) === [
        'ruminant_expenses_edit',
    ]
);


poultry_shared_permission_check(
    'GENERAL_AUTHORITY_PRESERVED',
    permission_catalog_expense_required_permissions(
        [
            'farm_type' =>
                'general',
        ],
        'delete'
    ) === [
        'expenses_delete',
    ]
);


poultry_shared_permission_check(
    'NO_SYNTHETIC_SHARED_PERMISSION_CODE',
    strpos(
        $content['catalog'],
        'poultry_shared_expenses'
    ) === false
);


poultry_shared_permission_check(
    'OPERATIONAL_HELPER_REQUIRES_VIEW_PLUS_ACTION',
    strpos(
        $content['catalog'],
        "permission_catalog_expense_required_permissions(\n            \$expense,\n            'view'"
    ) !== false
    &&
    strpos(
        $content['catalog'],
        'array_merge('
    ) !== false
    &&
    strpos(
        $content['catalog'],
        'array_unique('
    ) !== false
);


poultry_shared_permission_check(
    'UPDATE_EXISTING_AND_TARGET_USE_CANONICAL_AUTHORITY',
    substr_count(
        $content['update'],
        'permission_catalog_expense_operational_can('
    ) >= 2
    &&
    strpos(
        $content['update'],
        'permission_catalog_expense_action_code('
    ) === false
);


poultry_shared_permission_check(
    'DELETE_USES_CANONICAL_AUTHORITY',
    strpos(
        $content['delete'],
        'permission_catalog_expense_operational_can('
    ) !== false
    &&
    strpos(
        $content['delete'],
        'permission_catalog_expense_action_code('
    ) === false
);


poultry_shared_permission_check(
    'ALLOCATION_USES_CANONICAL_AUTHORITY',
    strpos(
        $content['allocation'],
        'permission_catalog_expense_operational_can('
    ) !== false
    &&
    strpos(
        $content['allocation'],
        'permission_catalog_expense_action_code('
    ) === false
);


poultry_shared_permission_check(
    'EXPENSE_REPORT_SCOPE_REMAINS_SEPARATE',
    strpos(
        $content['update'],
        "'expenses_edit'"
    ) !== false
    &&
    strpos(
        $content['delete'],
        "'expenses_delete'"
    ) !== false
    &&
    strpos(
        $content['allocation'],
        "'expense_report'"
    ) !== false
);


poultry_shared_permission_check(
    'CATALOG_HAS_NO_SCHEMA_DDL',
    preg_match(
        '/\b(?:ALTER|CREATE|DROP)\s+TABLE\b/i',
        $content['catalog']
    ) !== 1
);


echo
    'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . $failed
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
