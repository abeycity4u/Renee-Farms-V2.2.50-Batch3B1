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
    . '/includes/billing_provider_selection.php';

require_once
    $root
    . '/includes/billing_route_request.php';

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

$routeSource =
    file_get_contents(
        $root
        . '/includes/billing_route_request.php'
    );

$sandboxSource =
    file_get_contents(
        $root
        . '/billing/sandbox_checkout.php'
    );

$sales =
    billing_route_normalize_checkout_input([
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => ['sales'],
        'seat_addons' => [
            'sales_rep' => 0,
            'viewer' => 0,
        ],
    ]);

$check(
    ($sales['modules'] ?? null)
        === ['sales'],
    'Sales-only browser assertion normalizes to Sales commercial product'
);

$check(
    ($sales['plan_code'] ?? null)
        === 'starter',
    'Sales-only route preserves selected plan assertion'
);

$check(
    ($sales['billing_interval'] ?? null)
        === 'monthly',
    'Sales-only route preserves billing interval assertion'
);

$sharedSales =
    billing_route_normalize_checkout_input([
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => [
            'sales',
            'poultry',
        ],
    ]);

$check(
    ($sharedSales['modules'] ?? null)
        === ['poultry'],
    'Poultry plus shared Sales canonicalizes to Poultry product'
);

$combined =
    billing_route_normalize_checkout_input([
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => [
            'ruminant',
            'poultry',
            'sales',
        ],
    ]);

$check(
    ($combined['modules'] ?? null)
        === [
            'poultry',
            'ruminant',
        ],
    'combined livestock plus shared Sales canonicalizes to livestock bundle'
);

$unknownRejected = false;

try {
    billing_route_normalize_checkout_input([
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => ['unknown'],
    ]);
} catch (InvalidArgumentException $e) {
    $unknownRejected = true;
}

$check(
    $unknownRejected,
    'unsupported commercial module still fails closed'
);

$emptyRejected = false;

try {
    billing_route_normalize_checkout_input([
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => [],
    ]);
} catch (InvalidArgumentException $e) {
    $emptyRejected = true;
}

$check(
    $emptyRejected,
    'missing commercial product still fails closed'
);

if (is_string($routeSource)) {
    $check(
        strpos(
            $routeSource,
            'billing_commercial_product.php'
        ) !== false,
        'checkout route loads shared commercial-product policy'
    );

    $check(
        strpos(
            $routeSource,
            'billing_commercial_product_require_modules'
        ) !== false,
        'checkout route delegates commercial module normalization'
    );

    $check(
        strpos(
            $routeSource,
            "['poultry', 'ruminant']"
        ) === false,
        'checkout route contains no livestock-only product whitelist'
    );
}

if (is_string($sandboxSource)) {
    $check(
        strpos(
            $sandboxSource,
            'billing_commercial_product.php'
        ) !== false,
        'sandbox launcher loads shared commercial-product policy'
    );

    $check(
        strpos(
            $sandboxSource,
            'billing_commercial_product_require_modules'
        ) !== false,
        'sandbox launcher delegates current product normalization'
    );

    $check(
        strpos(
            $sandboxSource,
            "array_intersect(['poultry', 'ruminant']"
        ) === false,
        'sandbox launcher no longer strips standalone Sales'
    );

    $check(
        strpos(
            $sandboxSource,
            'Commercial product'
        ) !== false,
        'sandbox launcher uses commercial-product terminology'
    );

    $check(
        strpos(
            $sandboxSource,
            '/billing/checkout.php'
        ) !== false,
        'sandbox launcher still delegates to hardened checkout route'
    );
}

if ($failures) {
    fwrite(
        STDERR,
        sprintf(
            "\n%d Sales-only checkout-route check(s) failed.\n",
            count($failures)
        )
    );

    exit(1);
}

echo "\nSales-only checkout-route contract passed.\n";
