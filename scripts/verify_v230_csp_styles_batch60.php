<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$helpers = [
    'includes/production_cycle_view_permissions.php' => [
        '/assets/css/prepaint-production-cycle-readonly.css',
    ],
    'includes/dashboard_action_permissions.php' => [
        '/assets/css/prepaint-dashboard-stock-readonly.css',
    ],
    'includes/ruminant_feed_permissions.php' => [
        '/assets/css/prepaint-ruminant-feed-readonly.css',
    ],
    'includes/legacy_authorization_closure.php' => [
        '/assets/css/prepaint-animal-profile-readonly.css',
        '/assets/css/prepaint-membership-repair-readonly.css',
    ],
];

foreach ($helpers as $relative => $assets) {
    $path = $root . '/' . $relative;
    $page = is_file($path) ? file_get_contents($path) : '';

    $check(
        $page !== '',
        "{$relative} exists and is readable"
    );

    $check(
        preg_match_all(
            '/<style\b[^>]*>.*?<\/style\s*>/is',
            $page
        ) === 0,
        "{$relative} contains zero inline style blocks"
    );

    foreach ($assets as $asset) {
        $check(
            substr_count($page, $asset) === 2,
            "{$relative} keeps fallback and versioned reference for {$asset}"
        );
    }
}

$cssContracts = [
    'assets/css/prepaint-production-cycle-readonly.css' => [
        'form[method="post"],form[method="POST"]{display:none!important}',
    ],
    'assets/css/prepaint-dashboard-stock-readonly.css' => [
        '#stockTable button[onclick^="quickStockUpdate("]{display:none!important}',
    ],
    'assets/css/prepaint-ruminant-feed-readonly.css' => [
        'button[data-bs-target="#addTransactionModal"]{display:none!important;}',
    ],
    'assets/css/prepaint-animal-profile-readonly.css' => [
        'a[href*="animal_registry.php?edit="]',
        'button[data-bs-target="#weightModal"]',
        'button[data-bs-target="#healthModal"]',
        'button[data-bs-target="#membershipModal"]',
        'button[onclick^="closeMembership"]',
        '#cycle-membership form[method="post"]',
        '#weightModal,#healthModal,#membershipModal,#closeMembershipModal',
        '{display:none!important}',
    ],
    'assets/css/prepaint-membership-repair-readonly.css' => [
        'form[method="post"]{display:none!important}',
    ],
];

foreach ($cssContracts as $relative => $tokens) {
    $path = $root . '/' . $relative;
    $css = is_file($path) ? file_get_contents($path) : '';

    $check(
        $css !== '',
        "{$relative} exists and is readable"
    );

    $check(
        !str_contains($css, '<?php')
        && !str_contains($css, '<?='),
        "{$relative} contains no PHP"
    );

    foreach ($tokens as $token) {
        $check(
            str_contains($css, $token),
            "{$relative} preserves {$token}"
        );
    }
}

$production = file_get_contents(
    $root . '/includes/production_cycle_view_permissions.php'
);

$check(
    str_contains(
        $production,
        "http_response_code(403);"
    ),
    'Production Cycle POST authorization boundary remains'
);

$dashboard = file_get_contents(
    $root . '/includes/dashboard_action_permissions.php'
);

$check(
    str_contains(
        $dashboard,
        '$showInventoryDashboard && !$canUpdateStock'
    ),
    'Dashboard stock prepaint remains permission-scoped'
);

$ruminant = file_get_contents(
    $root . '/includes/ruminant_feed_permissions.php'
);

$check(
    str_contains(
        $ruminant,
        "\$method === 'GET' && !\$canAddRuminantFeed"
    ),
    'Ruminant feed prepaint remains GET and permission scoped'
);

$check(
    str_contains(
        $ruminant,
        "\$method === 'POST' && isset(\$_POST['add_transaction']) && !\$canAddRuminantFeed"
    ),
    'Ruminant feed POST authorization boundary remains'
);

$legacy = file_get_contents(
    $root . '/includes/legacy_authorization_closure.php'
);

$check(
    str_contains(
        $legacy,
        "legacy_authorization_closure_require('ruminant_animals_edit');"
    ),
    'Animal Profile mutation authorization remains'
);

$check(
    str_contains(
        $legacy,
        "legacy_authorization_closure_deny('Farm Admin access is required to repair historical membership boundaries.');"
    ),
    'Membership repair authorization boundary remains'
);

foreach ([
    'navbar_head.php' => 2,
    'includes/permission_prepaint.php' => 1,
    'includes/expense_action_permissions.php' => 1,
] as $relative => $expected) {
    $text = file_get_contents($root . '/' . $relative);

    $check(
        preg_match_all(
            '/<style\b[^>]*>.*?<\/style\s*>/is',
            $text
        ) === $expected,
        "{$relative} retains expected dynamic/generated style-block count"
    );
}

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
