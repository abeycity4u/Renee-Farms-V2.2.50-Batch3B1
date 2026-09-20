<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$bridgePaths = [
    $root
        . '/includes/expense_action_permissions.php',

    $root
        . '/includes/farm_entitlement_runtime.php',

    $root
        . '/includes/functions.php',

    $root
        . '/includes/permission_prepaint.php',

    $root
        . '/includes/permission_runtime.php',

    $root
        . '/navbar_head.php',
];

$bridgeText = '';

foreach ($bridgePaths as $path) {
    $bridgeText .=
        "\n"
        . (string)file_get_contents(
            $path
        );
}

$runtime =
    (string)file_get_contents(
        $root
        . '/includes/permission_runtime.php'
    );

$navbarHead =
    (string)file_get_contents(
        $root
        . '/navbar_head.php'
    );

$layerRoute =
    (string)file_get_contents(
        $root
        . '/poultry/layer_expenses.php'
    );

$broilerRoute =
    (string)file_get_contents(
        $root
        . '/poultry/broiler_expenses.php'
    );

$hub =
    (string)file_get_contents(
        $root
        . '/poultry/expenses.php'
    );

$navbar =
    (string)file_get_contents(
        $root
        . '/navbar.php'
    );

$checks = 0;
$failed = 0;

function product_retirement_check(
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


product_retirement_check(
    'NO_PRODUCT_PERMISSION_BRIDGE_REFERENCES_LEGACY_POULTRY_ROUTES',
    strpos(
        $bridgeText,
        '/poultry/layer_expenses.php'
    ) === false
    &&
    strpos(
        $bridgeText,
        '/poultry/broiler_expenses.php'
    ) === false
);


product_retirement_check(
    'RUMINANT_RUNTIME_PERMISSION_PATH_PRESERVED',
    strpos(
        $runtime,
        '/ruminant/ruminant_expenses.php'
    ) !== false
);


product_retirement_check(
    'RUMINANT_HEAD_PERMISSION_BRIDGE_PRESERVED',
    strpos(
        $navbarHead,
        '/ruminant/ruminant_expenses.php'
    ) !== false
    &&
    strpos(
        $navbarHead,
        'ruminant_expenses_edit'
    ) !== false
    &&
    strpos(
        $navbarHead,
        'ruminant_expenses_delete'
    ) !== false
);


product_retirement_check(
    'LAYER_ROUTE_IS_COMPATIBILITY_ONLY',
    strpos(
        $layerRoute,
        'poultry_expense_compatibility_redirect('
    ) !== false
    &&
    strpos(
        $layerRoute,
        "'layer'"
    ) !== false
);


product_retirement_check(
    'BROILER_ROUTE_IS_COMPATIBILITY_ONLY',
    strpos(
        $broilerRoute,
        'poultry_expense_compatibility_redirect('
    ) !== false
    &&
    strpos(
        $broilerRoute,
        "'broiler'"
    ) !== false
);


product_retirement_check(
    'LAYER_PAGE_SPECIFIC_JS_REMOVED',
    !is_file(
        $root
        . '/assets/js/layer-expenses.js'
    )
);


product_retirement_check(
    'BROILER_PAGE_SPECIFIC_JS_REMOVED',
    !is_file(
        $root
        . '/assets/js/broiler-expenses.js'
    )
);


product_retirement_check(
    'NAVBAR_HAS_ONE_CANONICAL_POULTRY_EXPENSE_LINK',
    substr_count(
        $navbar,
        '/poultry/expenses.php'
    ) === 1
    &&
    strpos(
        $navbar,
        '/poultry/layer_expenses.php'
    ) === false
    &&
    strpos(
        $navbar,
        '/poultry/broiler_expenses.php'
    ) === false
);


product_retirement_check(
    'HUB_RETAINS_CANONICAL_OPERATIONAL_AUTHORITY',
    strpos(
        $hub,
        'permission_catalog_expense_operational_can('
    ) !== false
    &&
    strpos(
        $hub,
        'financial_allocation_workspace_can_access('
    ) !== false
);


product_retirement_check(
    'HUB_RETAINS_SINGLE_CREATE_AUTHORITY',
    substr_count(
        $hub,
        'poultry_expense_entry_create('
    ) === 1
);


product_retirement_check(
    'HUB_RETAINS_SINGLE_PDF_AUTHORITY',
    substr_count(
        $hub,
        'pdf_report_finish('
    ) === 1
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
