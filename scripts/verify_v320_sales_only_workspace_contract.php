<?php

require_once dirname(__DIR__)
    . '/includes/farm_entitlements.php';

$cases = [
    [
        'standalone Sales is Sales-only',
        ['sales'],
        true,
    ],
    [
        'Poultry plus Sales is not Sales-only',
        ['poultry', 'sales'],
        false,
    ],
    [
        'Ruminant plus Sales is not Sales-only',
        ['ruminant', 'sales'],
        false,
    ],
    [
        'combined livestock plus Sales is not Sales-only',
        ['poultry', 'ruminant', 'sales'],
        false,
    ],
    [
        'Poultry without standalone Sales is not Sales-only',
        ['poultry'],
        false,
    ],
    [
        'empty entitlement set is not Sales-only',
        [],
        false,
    ],
];

$fail = 0;

foreach ($cases as [$label, $modules, $expected]) {
    $actual =
        farm_entitlement_modules_are_sales_only(
            $modules
        );

    if ($actual === $expected) {
        echo "PASS: {$label}\n";
    } else {
        echo "FAIL: {$label}\n";
        $fail = 1;
    }
}

if (function_exists('farm_entitlement_is_sales_only')) {
    echo "PASS: tenant-pinned Sales-only helper exists\n";
} else {
    echo "FAIL: tenant-pinned Sales-only helper missing\n";
    $fail = 1;
}

if (function_exists('current_farm_is_sales_only')) {
    echo "PASS: current-tenant Sales-only helper exists\n";
} else {
    echo "FAIL: current-tenant Sales-only helper missing\n";
    $fail = 1;
}

if ($fail) {
    exit(1);
}

echo "SALES_ONLY_WORKSPACE_CONTRACT=PASS\n";
