<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

require_once $root
    . '/includes/billing_commercial_product.php';

require_once $root
    . '/includes/farm_entitlements.php';

$failures = 0;

$check =
    static function (
        bool $condition,
        string $message
    ) use (&$failures): void {
        echo ($condition ? 'PASS: ' : 'FAIL: ')
            . $message
            . PHP_EOL;

        if (!$condition) {
            $failures++;
        }
    };

$check(
    billing_commercial_product_selection_modules(
        ['sales']
    ) === ['sales'],
    'Sales is a standalone commercial product'
);

$check(
    billing_commercial_product_selection_modules(
        ['poultry']
    ) === ['poultry'],
    'Poultry is a valid commercial product'
);

$check(
    billing_commercial_product_selection_modules(
        ['ruminant']
    ) === ['ruminant'],
    'Ruminant is a valid commercial product'
);

$check(
    billing_commercial_product_selection_modules(
        [
            'ruminant',
            'poultry',
        ]
    ) === [
        'poultry',
        'ruminant',
    ],
    'Poultry + Ruminant remains valid'
);

$mixedRejected = false;

try {
    billing_commercial_product_selection_modules(
        [
            'sales',
            'poultry',
        ]
    );
} catch (InvalidArgumentException $exception) {
    $mixedRejected = true;
}

$check(
    $mixedRejected,
    'new Sales + livestock selection is rejected'
);

$check(
    billing_commercial_product_modules(
        [
            'sales',
            'poultry',
        ]
    ) === ['poultry'],
    'legacy mixed rows remain readable through canonical product identity'
);

$check(
    farm_entitlement_subscribed_modules(
        ['poultry']
    ) === [
        'poultry',
        'sales',
    ],
    'Poultry subscribed modules display includes Sales'
);

$check(
    farm_entitlement_subscribed_modules(
        ['ruminant']
    ) === [
        'ruminant',
        'sales',
    ],
    'Ruminant subscribed modules display includes Sales'
);

$check(
    farm_entitlement_subscribed_modules(
        [
            'poultry',
            'ruminant',
        ]
    ) === [
        'poultry',
        'ruminant',
        'sales',
    ],
    'combined livestock display includes Sales exactly once'
);

$check(
    farm_entitlement_subscribed_modules(
        ['sales']
    ) === ['sales'],
    'Sales-only display remains Sales only'
);

$check(
    farm_entitlement_commercial_product_for_display(
        [
            'poultry',
            'sales',
        ]
    ) === ['poultry'],
    'legacy mixed row still displays correct livestock commercial product'
);

$files = [
    'intake' =>
        '/includes/trial_onboarding_intake.php',
    'review' =>
        '/includes/trial_onboarding_review.php',
    'provisioning' =>
        '/includes/trial_onboarding_provisioning.php',
    'tenant' =>
        '/includes/tenant_provisioning.php',
    'farms' =>
        '/management/farms.php',
    'bridge' =>
        '/includes/subscription_plan_farms.php',
    'tenant_view' =>
        '/includes/platform_owner_tenant_view.php',
    'trial' =>
        '/trial.php',
    'review_ui' =>
        '/management/trial_onboarding_reviews.php',
    'browser' =>
        '/assets/js/commercial-product-selection.js',
];

$sources = [];

foreach ($files as $name => $suffix) {
    $path =
        $root
        . $suffix;

    $sources[$name] =
        is_file($path)
            ? (string)file_get_contents($path)
            : '';

    $check(
        $sources[$name] !== '',
        $name . ' source exists'
    );
}

foreach (
    [
        'intake',
        'review',
        'provisioning',
        'tenant',
        'farms',
    ]
    as $name
) {
    $check(
        str_contains(
            $sources[$name],
            'billing_commercial_product_selection_modules'
        ),
        $name
        . ' consumes canonical commercial-product selection authority'
    );
}

$check(
    !str_contains(
        $sources['bridge'],
        '$_POST[\'modules\'] = $livestockModules'
    ),
    'Platform Farms bridge no longer discards standalone Sales'
);

$check(
    !str_contains(
        $sources['bridge'],
        'Remove only its commercial module'
    ),
    'Platform Farms bridge no longer strips Sales selector'
);

$check(
    str_contains(
        $sources['tenant_view'],
        'farm_entitlement_subscribed_modules'
    ),
    'Platform Owner tenant view includes effective Sales capability'
);

$check(
    str_contains(
        $sources['farms'],
        '>Commercial Product</label>'
    ),
    'Platform Owner edit form separates commercial product selection'
);

$check(
    str_contains(
        $sources['farms'],
        '<th>Subscribed Modules</th>'
    ),
    'Platform Owner farm table labels effective subscribed modules'
);

$check(
    str_contains(
        $sources['browser'],
        'sales.disabled'
    )
    && str_contains(
        $sources['browser'],
        'sales.checked'
    ),
    'browser prevents simultaneous Sales and livestock selection'
);

$check(
    str_contains(
        $sources['trial'],
        'shared Sales capability'
    )
    && str_contains(
        $sources['trial'],
        'commercial-product-selection.js'
    ),
    'public trial explains and loads the shared selection UX'
);

$check(
    str_contains(
        $sources['review_ui'],
        'commercial-product-selection.js'
    ),
    'trial review loads shared selection UX'
);

$check(
    !str_contains(
        (string)file_get_contents(
            $root
            . '/includes/billing_commercial_product.php'
        ),
        'hasRole('
    ),
    'commercial product remains independent of user roles'
);

echo
    'RESULT='
    . (
        $failures === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
