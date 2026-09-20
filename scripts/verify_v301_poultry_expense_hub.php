<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$paths = [
    'workspace' =>
        $root
        . '/lib/poultry_expense_workspace.php',

    'page' =>
        $root
        . '/poultry/expenses.php',

    'js' =>
        $root
        . '/assets/js/poultry-expenses.js',

    'navbar' =>
        $root
        . '/navbar.php',

    'allocation' =>
        $root
        . '/lib/financial_allocation_workspace.php',
];

$content = [];

foreach ($paths as $key => $path) {
    $content[$key] =
        is_file($path)
            ? (string)file_get_contents(
                $path
            )
            : '';
}

$checks = 0;
$failed = 0;

function poultry_expense_hub_check(
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


poultry_expense_hub_check(
    'WORKSPACE_EXISTS',
    $content['workspace'] !== ''
);


poultry_expense_hub_check(
    'HUB_PAGE_EXISTS',
    $content['page'] !== ''
);


poultry_expense_hub_check(
    'HUB_JS_EXISTS',
    $content['js'] !== ''
);


poultry_expense_hub_check(
    'WORKSPACE_TENANT_SCOPED',
    strpos(
        $content['workspace'],
        'WHERE e.farm_id = ?'
    ) !== false
);


poultry_expense_hub_check(
    'WORKSPACE_POULTRY_ONLY',
    strpos(
        $content['workspace'],
        "e.farm_type = 'poultry'"
    ) !== false
);


poultry_expense_hub_check(
    'WORKSPACE_CANONICAL_VIEW_FILTER',
    strpos(
        $content['workspace'],
        'permission_catalog_expense_operational_can('
    ) !== false
    &&
    strpos(
        $content['workspace'],
        "'view'"
    ) !== false
);


poultry_expense_hub_check(
    'WORKSPACE_HAS_ALL_LAYER_BROILER_SHARED',
    strpos(
        $content['workspace'],
        "'all'"
    ) !== false
    &&
    strpos(
        $content['workspace'],
        "'layer'"
    ) !== false
    &&
    strpos(
        $content['workspace'],
        "'broiler'"
    ) !== false
    &&
    strpos(
        $content['workspace'],
        "'shared'"
    ) !== false
);


poultry_expense_hub_check(
    'HUB_USES_CANONICAL_CREATE_SERVICE',
    strpos(
        $content['page'],
        'poultry_expense_entry_create('
    ) !== false
);


poultry_expense_hub_check(
    'HUB_OWNS_NO_DIRECT_EXPENSE_INSERT',
    strpos(
        $content['page'],
        'INSERT INTO farm_expenses'
    ) === false
);


poultry_expense_hub_check(
    'SHARED_FORCES_ZERO_CYCLE_ON_CREATE',
    strpos(
        $content['page'],
        "\$requestedProductionType === 'shared'\n                        ? 0"
    ) !== false
);


poultry_expense_hub_check(
    'HUB_SHARED_SCOPE_COPY_IS_EXPLICIT',
    strpos(
        $content['page'],
        'Poultry-wide shared'
    ) !== false
    &&
    strpos(
        $content['page'],
        'No specific production cycle'
    ) !== false
);


poultry_expense_hub_check(
    'HUB_SHARED_NOT_DOUBLE_COUNTED_COPY',
    strpos(
        $content['page'],
        'Not duplicated into Layer or Broiler totals.'
    ) !== false
);


poultry_expense_hub_check(
    'ADD_FORM_HAS_PRODUCTION_TYPE',
    strpos(
        $content['page'],
        'id="addPoultryProductionType"'
    ) !== false
);


poultry_expense_hub_check(
    'ADD_FORM_HAS_DEPENDENT_CYCLE',
    strpos(
        $content['page'],
        'id="addPoultryExpenseCycle"'
    ) !== false
    &&
    strpos(
        $content['js'],
        'refreshCycleOptions'
    ) !== false
);


poultry_expense_hub_check(
    'SHARED_CYCLE_DISABLED_BY_JS',
    strpos(
        $content['js'],
        "productionType === 'shared'"
    ) !== false
    &&
    strpos(
        $content['js'],
        'cycleSelect.disabled'
    ) !== false
);


poultry_expense_hub_check(
    'NAVBAR_LINKS_POULTRY_HUB',
    strpos(
        $content['navbar'],
        '/poultry/expenses.php'
    ) !== false
    &&
    strpos(
        $content['navbar'],
        'Poultry Expenses'
    ) !== false
);


poultry_expense_hub_check(
    'LEGACY_LAYER_LINK_PRESERVED',
    strpos(
        $content['navbar'],
        '/poultry/layer_expenses.php'
    ) !== false
);


poultry_expense_hub_check(
    'LEGACY_BROILER_LINK_PRESERVED',
    strpos(
        $content['navbar'],
        '/poultry/broiler_expenses.php'
    ) !== false
);


poultry_expense_hub_check(
    'SHARED_ALLOCATION_RETURNS_TO_HUB',
    strpos(
        $content['allocation'],
        "/poultry/expenses.php?"
    ) !== false
    &&
    strpos(
        $content['allocation'],
        "'tab' =>\n                    'shared'"
    ) !== false
);


poultry_expense_hub_check(
    'HUB_NO_INLINE_SCRIPT_BLOCK',
    preg_match(
        '/<script(?![^>]*\bsrc=)[^>]*>[\s\S]*?<\/script>/i',
        $content['page']
    ) !== 1
);


poultry_expense_hub_check(
    'WORKSPACE_HAS_NO_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+farm_expenses\b/i',
        $content['workspace']
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
