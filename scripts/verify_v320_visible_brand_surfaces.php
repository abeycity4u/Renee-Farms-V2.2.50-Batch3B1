<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

require_once
    $root . '/includes/platform_brand.php';

$expectedTitles = [
    'dashboard.php' => 'Dashboard',
    'inventory.php' => 'Inventory',
    'management/billing_refund_reviews.php'
        => 'Billing Refund Reviews',
    'management/expenses.php'
        => 'Expense Report',
    'management/intelligence.php'
        => 'Farm Intelligence',
    'management/investigation.php'
        => 'Poultry Investigation',
    'management/platform_tenant_view.php'
        => 'Tenant View',
    'management/poultry_ruminant_report.php'
        => 'Poultry & Ruminant Report',
    'management/ruminant_investigation.php'
        => 'Ruminant Investigation',
    'management/sales_records.php'
        => 'Sales Records',
    'management/users.php'
        => 'User Management',
    'poultry/broiler_daily_record.php'
        => 'Broiler Daily Record',
    'poultry/broiler_feeds.php'
        => 'Broiler Feeds Record',
    'poultry/expenses.php'
        => 'Poultry Expenses',
    'poultry/layer_feeds.php'
        => 'Layer Feeds Record',
    'poultry/layers_daily_record.php'
        => 'Layer Daily Record',
    'ruminant/ruminant_daily_record.php'
        => 'Ruminant Daily Record',
    'ruminant/ruminant_expenses.php'
        => 'Ruminant Expenses Record',
    'ruminant/ruminant_feeds_record.php'
        => 'Ruminant Feeds Record',
];

foreach ($expectedTitles as $relative => $pageTitle) {
    $content =
        (string)file_get_contents(
            $root . '/' . $relative
        );

    if (
        str_contains(
            $content,
            'platform_brand_document_title('
        )
    ) {
        echo
            'PASS: shared document title | '
            . $relative
            . PHP_EOL;
    } else {
        $failures[] =
            'Missing shared document title: '
            . $relative;
    }

    foreach ([
        $pageTitle . ' - Renee Farms',
        $pageTitle . ' - Renee Farms Platform',
    ] as $oldTitle) {
        if (
            str_contains(
                $content,
                $oldTitle
            )
        ) {
            $failures[] =
                'Old title remains in '
                . $relative
                . ': '
                . $oldTitle;
        }
    }
}

foreach ([
    'errors/403.php',
    'errors/404.php',
] as $relative) {
    $content =
        (string)file_get_contents(
            $root . '/' . $relative
        );

    if (
        str_contains(
            $content,
            'platform_brand_document_title('
        )
        && str_contains(
            $content,
            'platform_brand_product_name()'
        )
    ) {
        echo
            'PASS: shared error-page brand | '
            . $relative
            . PHP_EOL;
    } else {
        $failures[] =
            'Error page does not use shared brand: '
            . $relative;
    }
}

$ownerAccess =
    (string)file_get_contents(
        $root
        . '/includes/platform_owner_tenant_access_message.php'
    );

if (
    str_contains(
        $ownerAccess,
        'platform_brand_document_title('
    )
) {
    echo
        "PASS: Platform Owner access page uses shared brand\n";
} else {
    $failures[] =
        'Platform Owner access title is not shared';
}

$receipt =
    (string)file_get_contents(
        $root . '/billing/receipt.php'
    );

if (
    str_contains(
        $receipt,
        '$receiptPlatformName = platform_brand_product_text();'
    )
) {
    echo
        "PASS: receipt platform name uses shared authority\n";
} else {
    $failures[] =
        'Receipt product identity remains hard-coded';
}

if (
    !str_contains(
        $receipt,
        "\$receiptIssuerName = 'Renee Farms Limited';"
    )
) {
    $failures[] =
        'Legal receipt issuer was altered';
} else {
    echo
        "PASS: legal receipt issuer preserved\n";
}

$index =
    (string)file_get_contents(
        $root . '/index.php'
    );

foreach ([
    '<h1>RENEE FARMS LTD</h1>',
    'Eat Healthy With Renee Farms',
    '&copy; 2026 Renee Farms Ltd.',
] as $corporateIdentity) {
    if (
        !str_contains(
            $index,
            $corporateIdentity
        )
    ) {
        $failures[] =
            'Corporate homepage identity changed: '
            . $corporateIdentity;
    }
}

echo
    "PASS: Renee Farms corporate homepage preserved\n";

$sign =
    (string)file_get_contents(
        $root . '/sign.php'
    );

foreach ([
    "SELECT 'Renee Farms Platform', 'owner', 'platform', 'active'",
    "COALESCE(f.name, 'Renee Farms Platform')",
] as $compatibilityIdentity) {
    if (
        !str_contains(
            $sign,
            $compatibilityIdentity
        )
    ) {
        $failures[] =
            'Platform Owner compatibility identity changed';
    }
}

echo
    "PASS: Platform Owner compatibility identity preserved\n";

if (
    platform_brand_document_title('Dashboard')
    !== 'Dashboard - Renee AgriSuite'
) {
    $failures[] =
        'Shared document-title contract is wrong';
} else {
    echo
        "PASS: shared document-title contract\n";
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(
            STDERR,
            'FAIL: '
            . $failure
            . PHP_EOL
        );
    }

    exit(1);
}

echo
    "PASS: V3.2 final visible-brand surfaces\n";
