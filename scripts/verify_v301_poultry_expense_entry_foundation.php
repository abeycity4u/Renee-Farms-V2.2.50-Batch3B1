<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

require_once
    $root
    . '/includes/permission_catalog.php';

require_once
    $root
    . '/lib/attribution.php';

$servicePath =
    $root
    . '/lib/poultry_expense_entry.php';

$service =
    is_file(
        $servicePath
    )
        ? (string)file_get_contents(
            $servicePath
        )
        : '';

$checks = 0;
$failed = 0;

function poultry_expense_foundation_check(
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


poultry_expense_foundation_check(
    'LAYER_VIEW_REQUIRES_LAYER_VIEW',
    permission_catalog_poultry_expense_required_permissions(
        'layer',
        'view'
    ) === [
        'poultry_layer_expenses',
    ]
);


poultry_expense_foundation_check(
    'BROILER_ADD_REQUIRES_BROILER_ADD',
    permission_catalog_poultry_expense_required_permissions(
        'broiler',
        'add'
    ) === [
        'poultry_broiler_expenses_add',
    ]
);


poultry_expense_foundation_check(
    'SHARED_VIEW_REQUIRES_BOTH_VIEWS',
    permission_catalog_poultry_expense_required_permissions(
        'shared',
        'view'
    ) === [
        'poultry_layer_expenses',
        'poultry_broiler_expenses',
    ]
);


poultry_expense_foundation_check(
    'SHARED_ADD_REQUIRES_BOTH_ADDS',
    permission_catalog_poultry_expense_required_permissions(
        'shared',
        'add'
    ) === [
        'poultry_layer_expenses_add',
        'poultry_broiler_expenses_add',
    ]
);


poultry_expense_foundation_check(
    'SHARED_EDIT_REQUIRES_BOTH_EDITS',
    permission_catalog_poultry_expense_required_permissions(
        'shared',
        'edit'
    ) === [
        'poultry_layer_expenses_edit',
        'poultry_broiler_expenses_edit',
    ]
);


poultry_expense_foundation_check(
    'SHARED_DELETE_REQUIRES_BOTH_DELETES',
    permission_catalog_poultry_expense_required_permissions(
        'shared',
        'delete'
    ) === [
        'poultry_layer_expenses_delete',
        'poultry_broiler_expenses_delete',
    ]
);


poultry_expense_foundation_check(
    'INVALID_PRODUCTION_HAS_NO_PERMISSION',
    permission_catalog_poultry_expense_required_permissions(
        'invalid',
        'view'
    ) === []
);


poultry_expense_foundation_check(
    'ATTRIBUTION_ALREADY_SUPPORTS_POULTRY_SHARED',
    array_key_exists(
        'shared',
        attribution_production_types(
            'poultry'
        )
    )
);


poultry_expense_foundation_check(
    'POULTRY_SHARED_SCOPE_IS_FARM',
    attribution_scope(
        null,
        'poultry',
        'shared'
    ) === 'farm'
);


poultry_expense_foundation_check(
    'SERVICE_SUPPORTS_LAYER_BROILER_SHARED_ONLY',
    strpos(
        $service,
        "'layer',"
    ) !== false
    &&
    strpos(
        $service,
        "'broiler',"
    ) !== false
    &&
    strpos(
        $service,
        "'shared',"
    ) !== false
);


poultry_expense_foundation_check(
    'SERVICE_SHARED_HAS_NO_DIRECT_CYCLE',
    strpos(
        $service,
        "Poultry-wide shared expenses cannot be assigned directly to a production cycle."
    ) !== false
);


poultry_expense_foundation_check(
    'SERVICE_SHARED_CATEGORY_IS_NULL',
    strpos(
        $service,
        "\$productionType === 'shared'\n            ? null"
    ) !== false
);


poultry_expense_foundation_check(
    'SERVICE_USES_CANONICAL_CYCLE_VALIDATION',
    strpos(
        $service,
        'attribution_validate_cycle('
    ) !== false
);


poultry_expense_foundation_check(
    'SERVICE_USES_CANONICAL_SCOPE',
    strpos(
        $service,
        'attribution_scope('
    ) !== false
);


poultry_expense_foundation_check(
    'SERVICE_ASSIGN_REFERENCE',
    strpos(
        $service,
        'record_reference_persistence_assign_existing('
    ) !== false
);


poultry_expense_foundation_check(
    'SERVICE_WRITES_CREATE_REVISION',
    strpos(
        $service,
        'expense_revision_service_record_created('
    ) !== false
);


poultry_expense_foundation_check(
    'SERVICE_REQUIRES_OUTER_TRANSACTION',
    strpos(
        $service,
        'poultry_expense_entry_require_transaction('
    ) !== false
    &&
    strpos(
        $service,
        'beginTransaction('
    ) === false
    &&
    strpos(
        $service,
        '->commit('
    ) === false
);


poultry_expense_foundation_check(
    'SERVICE_HAS_SINGLE_EXPENSE_INSERT',
    substr_count(
        $service,
        'INSERT INTO farm_expenses'
    ) === 1
);


poultry_expense_foundation_check(
    'SERVICE_HAS_NO_SCHEMA_DDL',
    preg_match(
        '/\b(?:ALTER|CREATE|DROP)\s+TABLE\b/i',
        $service
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
