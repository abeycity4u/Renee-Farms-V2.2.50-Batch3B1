<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once
    $root
    . '/includes/billing_commercial_product.php';

require_once
    $root
    . '/includes/subscription_seat_policy.php';

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

$accountPath =
    $root
    . '/billing/account.php';

$account =
    file_get_contents(
        $accountPath
    );

$helper =
    file_get_contents(
        $root
        . '/includes/billing_commercial_product.php'
    );

$check(
    is_string($account),
    'Account & Billing workspace source is readable'
);

$check(
    is_string($helper),
    'commercial-product helper source is readable'
);

$check(
    billing_commercial_product_label(
        ['sales']
    ) === 'Sales',
    'standalone Sales displays as Sales'
);

$check(
    billing_commercial_product_label(
        ['poultry']
    ) === 'Poultry',
    'Poultry product label remains Poultry'
);

$check(
    billing_commercial_product_label(
        ['ruminant']
    ) === 'Ruminant',
    'Ruminant product label remains Ruminant'
);

$check(
    billing_commercial_product_label(
        [
            'poultry',
            'ruminant',
        ]
    ) === 'Poultry + Ruminant',
    'combined livestock product label remains Poultry + Ruminant'
);

$check(
    billing_commercial_product_label(
        [
            'poultry',
            'sales',
        ]
    ) === 'Poultry',
    'shared Sales does not alter Poultry display identity'
);

$check(
    billing_commercial_product_label(
        [
            'ruminant',
            'sales',
        ]
    ) === 'Ruminant',
    'shared Sales does not alter Ruminant display identity'
);

$salesModules = ['sales'];

$check(
    subscription_seat_role_relevant(
        'sales_rep',
        $salesModules
    ),
    'Sales Representative is relevant to Sales-only'
);

$check(
    subscription_seat_role_relevant(
        'viewer',
        $salesModules
    ),
    'Viewer is relevant to Sales-only'
);

$check(
    !subscription_seat_role_relevant(
        'poultry_manager',
        $salesModules
    ),
    'Poultry Manager is not relevant to Sales-only'
);

$check(
    !subscription_seat_role_relevant(
        'ruminant_manager',
        $salesModules
    ),
    'Ruminant Manager is not relevant to Sales-only'
);

if (is_string($account)) {
    $check(
        strpos(
            $account,
            "includes/billing_commercial_product.php"
        ) !== false,
        'Account workspace directly loads shared commercial-product policy'
    );

    $check(
        strpos(
            $account,
            'billing_commercial_product_label'
        ) !== false,
        'Account workspace uses canonical commercial-product labels'
    );

    $check(
        strpos(
            $account,
            'Commercial product'
        ) !== false,
        'Account summary labels the subscription as Commercial product'
    );

    $check(
        strpos(
            $account,
            'current commercial product'
        ) !== false,
        'seat availability message uses commercial-product terminology'
    );

    $check(
        strpos(
            $account,
            'different plan, commercial product or seat package'
        ) !== false,
        'renewal guidance uses commercial-product terminology'
    );

    $check(
        strpos(
            $account,
            '<th>Product</th>'
        ) !== false,
        'subscription history presents Product instead of Modules'
    );

    $check(
        strpos(
            $account,
            "array_intersect(['poultry', 'ruminant']"
        ) === false,
        'subscription history no longer strips standalone Sales'
    );

    $check(
        stripos(
            $account,
            'Livestock bundle'
        ) === false,
        'Account workspace has no Livestock bundle presentation'
    );

    $check(
        strpos(
            $account,
            '$overview[\'seat_summary\']'
        ) !== false,
        'Account seat surfaces still use centralized seat summary'
    );

    $check(
        strpos(
            $account,
            '/billing/checkout.php'
        ) !== false,
        'existing subscription checkout route remains in use'
    );

    $check(
        strpos(
            $account,
            '/billing/seat_topup_checkout.php'
        ) !== false,
        'existing seat top-up checkout route remains in use'
    );

    $check(
        strpos(
            $account,
            '/billing/seat_reduction_schedule.php'
        ) !== false,
        'existing seat reduction route remains in use'
    );
}

if ($failures) {
    fwrite(
        STDERR,
        sprintf(
            "\n%d Sales-only Account & Billing check(s) failed.\n",
            count($failures)
        )
    );

    exit(1);
}

echo "\nSales-only Account & Billing workspace contract passed.\n";
