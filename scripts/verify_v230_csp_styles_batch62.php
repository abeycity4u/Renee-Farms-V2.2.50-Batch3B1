<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$helperPath = $root . '/includes/permission_prepaint.php';
$cssPath = $root . '/assets/css/permission-prepaint.css';

$helper = is_file($helperPath) ? file_get_contents($helperPath) : '';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';

$check($helper !== '', 'Permission prepaint helper exists');
$check($css !== '', 'Permission prepaint stylesheet exists');

$check(
    preg_match_all('/<style\b[^>]*>.*?<\/style\s*>/is', $helper) === 0,
    'Permission helper contains zero inline style blocks'
);

$check(
    substr_count($helper, '/assets/css/permission-prepaint.css') === 2,
    'Permission stylesheet keeps fallback and versioned references'
);

$check(
    !str_contains($helper, '$rules'),
    'Legacy generated CSS rule array is removed'
);

$check(
    str_contains($helper, 'array_unique($tokens)'),
    'Duplicate permission tokens are normalized'
);

$check(
    str_contains($helper, 'data-permission-prepaint'),
    'Permission tokens are rendered on the HTML element'
);

$check(
    str_contains($helper, "'/<html\\b/i'")
    && str_contains($helper, "'<html' . $attribute"),
    'HTML permission attribute is injected before document delivery'
);

$check(
    !str_contains($css, '<?php') && !str_contains($css, '<?='),
    'Permission stylesheet contains no PHP'
);

$tokenContracts = [
    'daily-add' => 'button[onclick*="openRecordModal"]',
    'daily-edit' => 'table .edit-record-btn',
    'daily-delete' => 'button[onclick*="deleteLayerDailyRecord"]',

    'expense-edit' => '.edit-expense-btn',
    'expense-delete' => 'button[onclick*="deleteExpense"]',
    'expense-add' => 'button[data-bs-target="#addExpenseModal"]',

    'feed-add' => 'button[data-bs-target="#addTransactionModal"]',

    'sales-add' => 'button[data-bs-target="#addSaleModal"]',
    'sales-payment' => 'form button[name="record_payment"]',
    'sales-edit' => '.edit-sale-btn',
    'sales-delete' => 'button[onclick*="deleteSale"]',

    'animal-add' => 'button[onclick*="newAnimal"]',
    'animal-edit' => 'button[onclick*="editAnimal"]',
    'animal-exit' => 'button[onclick*="exitAnimal"]',

    'production-cycle-readonly' => 'form[method="post"]',

    'hide-poultry-menu' => '#poultryMenu',
    'hide-ruminant-menu' => '#ruminantMenu',
    'hide-manage-menu' => '#manageMenu',
];

foreach ($tokenContracts as $token => $selector) {
    $check(
        str_contains($helper, "'{$token}'"),
        "Helper can emit {$token}"
    );

    $check(
        str_contains($css, "data-permission-prepaint~=\"{$token}\""),
        "Stylesheet handles {$token}"
    );

    $check(
        str_contains($css, $selector),
        "{$token} preserves selector {$selector}"
    );
}

$navContracts = [
    'inventory' => '/inventory.php',
    'poultry-layer-expenses' => '/poultry/layer_expenses.php',
    'poultry-broiler-expenses' => '/poultry/broiler_expenses.php',
    'ruminant-animals' => '/ruminant/animal_registry.php',
    'ruminant-expenses' => '/ruminant/ruminant_expenses.php',
    'sales' => '/management/sales_records.php',
    'expenses' => '/management/expenses.php',
    'reports' => '/management/reports.php',
    'farm-intelligence' => '/management/intelligence.php',
    'profitability' => '/management/profitability.php',
    'production-cycles' => '/management/production_cycles.php',
    'users' => '/management/users.php',
];

foreach ($navContracts as $token => $href) {
    $check(
        str_contains(
            $css,
            'data-permission-prepaint~="hide-nav-' . $token . '"'
        ),
        "Stylesheet handles hide-nav-{$token}"
    );

    $check(
        str_contains($css, 'href$="' . $href . '"]'),
        "Navigation prepaint preserves {$href}"
    );
}

$check(
    str_contains(
        $css,
        'href$="/management/poultry_ruminant_report.php"]'
    ),
    'Reports token also hides combined Poultry/Ruminant report link'
);

$check(
    str_contains(
        $helper,
        "\$navToken = 'hide-nav-' . str_replace('_', '-', \$permission);"
    ),
    'Navigation token generation remains derived from permission key'
);

$check(
    str_contains(
        $helper,
        "if (!permission_prepaint_has(\$addPermission)) \$tokens[] = 'daily-add';"
    ),
    'Daily Add token remains permission scoped'
);

$check(
    str_contains(
        $helper,
        "if (!permission_prepaint_has(\$editPermission)) \$tokens[] = 'daily-edit';"
    ),
    'Daily Edit token remains permission scoped'
);

$check(
    str_contains(
        $helper,
        "if (!permission_prepaint_has(\$deletePermission)) \$tokens[] = 'daily-delete';"
    ),
    'Daily Delete token remains permission scoped'
);

$check(
    str_contains(
        $helper,
        "if (!\$allowPoultryMenu)"
    ),
    'Poultry menu token remains entitlement scoped'
);

$check(
    str_contains(
        $helper,
        "if (!\$allowRuminantMenu)"
    ),
    'Ruminant menu token remains entitlement scoped'
);

$check(
    str_contains(
        $helper,
        "if (!\$anyManagement && !permission_prepaint_privileged())"
    ),
    'Manage menu hiding remains privilege scoped'
);

$check(
    substr_count($helper, '$tokens[]') === 21,
    'Expected permission token assignment sites remain'
);

$navbar = file_get_contents($root . '/navbar_head.php');

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $navbar
    ) === 0,
    'navbar_head.php contains zero inline style blocks'
);

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
