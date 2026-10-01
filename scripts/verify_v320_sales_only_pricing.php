<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once
    $root
    . '/includes/subscription_plan_catalog.php';

require_once
    $root
    . '/includes/subscription_seat_policy.php';

require_once
    $root
    . '/includes/billing_commercial_product.php';

require_once
    $root
    . '/includes/billing_payment_foundation.php';

require_once
    $root
    . '/includes/billing_price_book_ngn.php';

require_once
    $root
    . '/includes/billing_pricing_contract.php';

$failures = [];

$check =
    static function (
        bool $condition,
        string $label
    ) use (&$failures): void {
        echo ($condition ? 'PASS: ' : 'FAIL: ')
            . $label
            . PHP_EOL;

        if (!$condition) {
            $failures[] = $label;
        }
    };

$book = billing_price_book_ngn();

$check(
    ($book['version'] ?? null) === 'ngn-launch-v2',
    'Sales-only package catalog uses new price-book version'
);

$expected = [
    'starter' => [
        'monthly' => '10000.00',
        'annual' => '100000.00',
    ],
    'growth' => [
        'monthly' => '20000.00',
        'annual' => '200000.00',
    ],
    'pro' => [
        'monthly' => '35000.00',
        'annual' => '350000.00',
    ],
];

foreach ($expected as $plan => $intervals) {
    foreach ($intervals as $interval => $amount) {
        $actual =
            $book['packages'][$plan]['sales'][$interval]
            ?? null;

        $check(
            $actual === $amount,
            sprintf(
                '%s Sales-only %s package is %s NGN',
                ucfirst($plan),
                $interval,
                $amount
            )
        );
    }
}

$starterMonthly =
    billing_pricing_resolve(
        'starter',
        'monthly',
        ['sales'],
        []
    );

$check(
    ($starterMonthly['module_bundle'] ?? null) === 'sales',
    'Sales-only quote resolves sales bundle key'
);

$check(
    ($starterMonthly['amount'] ?? null) === '10000.00',
    'Starter Sales-only monthly quote is server-authoritative at 10000.00 NGN'
);

$growthAnnual =
    billing_pricing_resolve(
        'growth',
        'annual',
        ['sales'],
        []
    );

$check(
    ($growthAnnual['amount'] ?? null) === '200000.00',
    'Growth Sales-only annual quote is 200000.00 NGN'
);

$proMonthly =
    billing_pricing_resolve(
        'pro',
        'monthly',
        ['sales'],
        []
    );

$check(
    ($proMonthly['amount'] ?? null) === '35000.00',
    'Pro Sales-only monthly quote is 35000.00 NGN'
);

$starterWithSalesRep =
    billing_pricing_resolve(
        'starter',
        'monthly',
        ['sales'],
        [
            'sales_rep' => 1,
        ]
    );

$check(
    ($starterWithSalesRep['amount'] ?? null) === '11500.00',
    'Starter Sales-only plus one extra Sales Rep is 11500.00 NGN'
);

$starterWithViewer =
    billing_pricing_resolve(
        'starter',
        'monthly',
        ['sales'],
        [
            'viewer' => 1,
        ]
    );

$check(
    ($starterWithViewer['amount'] ?? null) === '11000.00',
    'Starter Sales-only plus one extra Viewer is 11000.00 NGN'
);

$poultryManagerRejected = false;

try {
    billing_pricing_resolve(
        'starter',
        'monthly',
        ['sales'],
        [
            'poultry_manager' => 1,
        ]
    );
} catch (InvalidArgumentException $e) {
    $poultryManagerRejected = true;
}

$check(
    $poultryManagerRejected,
    'Sales-only rejects Poultry Manager extra-seat pricing'
);

$ruminantManagerRejected = false;

try {
    billing_pricing_resolve(
        'starter',
        'monthly',
        ['sales'],
        [
            'ruminant_manager' => 1,
        ]
    );
} catch (InvalidArgumentException $e) {
    $ruminantManagerRejected = true;
}

$check(
    $ruminantManagerRejected,
    'Sales-only rejects Ruminant Manager extra-seat pricing'
);

$poultry =
    billing_pricing_resolve(
        'starter',
        'monthly',
        ['poultry'],
        []
    );

$check(
    ($poultry['amount'] ?? null) === '10000.00',
    'existing Starter Poultry monthly price remains unchanged'
);

$ruminant =
    billing_pricing_resolve(
        'starter',
        'monthly',
        ['ruminant'],
        []
    );

$check(
    ($ruminant['amount'] ?? null) === '10000.00',
    'existing Starter Ruminant monthly price remains unchanged'
);

$combined =
    billing_pricing_resolve(
        'starter',
        'monthly',
        [
            'poultry',
            'ruminant',
        ],
        []
    );

$check(
    ($combined['amount'] ?? null) === '15000.00',
    'existing Starter Poultry plus Ruminant monthly price remains unchanged'
);

$sharedSales =
    billing_pricing_resolve(
        'starter',
        'monthly',
        [
            'poultry',
            'sales',
        ],
        []
    );

$check(
    ($sharedSales['module_bundle'] ?? null) === 'poultry'
    &&
    ($sharedSales['amount'] ?? null) === '10000.00',
    'shared Sales does not create a second charge on Poultry'
);

if ($failures) {
    fwrite(
        STDERR,
        sprintf(
            "\n%d Sales-only pricing check(s) failed.\n",
            count($failures)
        )
    );

    exit(1);
}

echo "\nSales-only server pricing contract passed.\n";
