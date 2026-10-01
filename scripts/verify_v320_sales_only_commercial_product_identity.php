<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

require_once
    $root
    . '/includes/billing_commercial_product.php';

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
            $failures[] =
                $label;
        }
    };

$check(
    billing_commercial_product_modules(
        ['sales']
    ) === ['sales'],
    'standalone Sales entitlement is the Sales commercial product'
);

$check(
    billing_commercial_product_bundle_key(
        ['sales']
    ) === 'sales',
    'standalone Sales resolves to sales bundle key'
);

$check(
    billing_commercial_product_modules(
        ['poultry']
    ) === ['poultry'],
    'Poultry product remains Poultry'
);

$check(
    billing_commercial_product_modules(
        ['ruminant']
    ) === ['ruminant'],
    'Ruminant product remains Ruminant'
);

$check(
    billing_commercial_product_modules(
        [
            'poultry',
            'ruminant',
        ]
    )
        === [
            'poultry',
            'ruminant',
        ],
    'combined livestock product remains Poultry plus Ruminant'
);

$check(
    billing_commercial_product_modules(
        [
            'poultry',
            'sales',
        ]
    ) === ['poultry'],
    'shared Sales never converts Poultry to Sales-only'
);

$check(
    billing_commercial_product_modules(
        [
            'ruminant',
            'sales',
        ]
    ) === ['ruminant'],
    'shared Sales never converts Ruminant to Sales-only'
);

$check(
    billing_commercial_product_modules(
        [
            'poultry',
            'ruminant',
            'sales',
        ]
    )
        === [
            'poultry',
            'ruminant',
        ],
    'shared Sales never changes combined livestock product identity'
);

$check(
    billing_commercial_product_is_sales_only(
        ['sales']
    ),
    'Sales-only classifier accepts standalone Sales'
);

$check(
    !billing_commercial_product_is_sales_only(
        [
            'poultry',
            'sales',
        ]
    ),
    'Sales-only classifier rejects Poultry plus shared Sales'
);

$check(
    !billing_commercial_product_is_sales_only(
        [
            'ruminant',
            'sales',
        ]
    ),
    'Sales-only classifier rejects Ruminant plus shared Sales'
);

$emptyRejected = false;

try {
    billing_commercial_product_require_modules(
        []
    );
} catch (InvalidArgumentException $e) {
    $emptyRejected =
        true;
}

$check(
    $emptyRejected,
    'missing commercial entitlement still fails closed'
);

$sourceChecks = [
    'includes/billing_payment_foundation.php'
        => [
            "billing_commercial_product.php",
            "billing_commercial_product_require_modules",
        ],

    'includes/billing_pricing_contract.php'
        => [
            "billing_commercial_product.php",
            "billing_commercial_product_bundle_key",
        ],

    'includes/subscription_record.php'
        => [
            "billing_commercial_product.php",
            "billing_commercial_product_modules",
        ],

    'includes/billing_subscription_application.php'
        => [
            "billing_commercial_product_modules",
            "billing_commercial_product_require_modules",
        ],
];

foreach (
    $sourceChecks
    as $relative => $needles
) {
    $source =
        file_get_contents(
            $root . '/' . $relative
        );

    foreach ($needles as $needle) {
        $check(
            is_string($source)
            &&
            str_contains(
                $source,
                $needle
            ),
            $relative
            . ' uses shared commercial-product policy: '
            . $needle
        );
    }
}

$pricingSource =
    file_get_contents(
        $root
        . '/includes/billing_price_book_ngn.php'
    );

$check(
    is_string($pricingSource)
    &&
    !preg_match(
        "/^[[:space:]]*'sales'[[:space:]]*=>/m",
        $pricingSource
    ),
    'K2 does not invent a Sales-only monetary package'
);

if ($failures) {
    fwrite(
        STDERR,
        sprintf(
            "\n%d commercial-product identity check(s) failed.\n",
            count($failures)
        )
    );

    exit(1);
}

echo
    "\nSales-only commercial-product identity contract passed.\n";
