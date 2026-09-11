<?php
/**
 * Focused verifier for the V2.3 seat-top-up browser request contract.
 *
 * Database-free and provider-network-free.
 */

$root = dirname(__DIR__);

require_once
    $root . '/includes/subscription_seat_policy.php';
require_once
    $root . '/includes/billing_provider_selection.php';
require_once
    $root . '/includes/billing_route_request.php';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

$check(
    function_exists(
        'billing_route_normalize_seat_topup_input'
    ),
    'centralized seat-top-up request normalizer exists'
);

$check(
    billing_route_allowed_seat_topup_keys()
        === [
            'csrf_token',
            'role_code',
            'quantity',
            'provider',
        ],
    'seat-top-up browser field allow-list is narrow'
);

$normalized =
    billing_route_normalize_seat_topup_input([
        'csrf_token' => 'contract-token',
        'role_code' => ' Viewer ',
        'quantity' => '2',
        'provider' => ' Paystack ',
    ]);

$check(
    ($normalized['role_code'] ?? null)
        === 'viewer'
    && ($normalized['quantity'] ?? null) === 2
    && ($normalized['provider'] ?? null)
        === 'paystack',
    'role, quantity and provider are canonicalized'
);

$withoutProvider =
    billing_route_normalize_seat_topup_input([
        'csrf_token' => 'contract-token',
        'role_code' => 'sales_rep',
        'quantity' => 1,
    ]);

$check(
    array_key_exists(
        'provider',
        $withoutProvider
    )
    && $withoutProvider['provider'] === null,
    'provider remains optional for readiness resolution'
);

$invalidRoleRejected = false;

try {
    billing_route_normalize_seat_topup_input([
        'role_code' => 'farm_admin',
        'quantity' => 1,
    ]);
} catch (InvalidArgumentException $e) {
    $invalidRoleRejected = true;
}

$check(
    $invalidRoleRejected,
    'non-purchasable role is rejected'
);

foreach ([0, 501, '2.5', '', null] as $badQuantity) {
    $rejected = false;

    try {
        billing_route_normalize_seat_topup_input([
            'role_code' => 'viewer',
            'quantity' => $badQuantity,
        ]);
    } catch (InvalidArgumentException $e) {
        $rejected = true;
    }

    $check(
        $rejected,
        'invalid seat quantity is rejected: '
            . var_export($badQuantity, true)
    );
}

foreach (
    [
        'amount' => '1.00',
        'currency' => 'NGN',
        'farm_id' => 999999,
    ]
    as $key => $value
) {
    $rejected = false;

    try {
        billing_route_normalize_seat_topup_input([
            'role_code' => 'viewer',
            'quantity' => 1,
            $key => $value,
        ]);
    } catch (InvalidArgumentException $e) {
        $rejected = str_contains(
            $e->getMessage(),
            'server controlled'
        );
    }

    $check(
        $rejected,
        "browser-controlled {$key} is rejected"
    );
}

$unsupportedRejected = false;

try {
    billing_route_normalize_seat_topup_input([
        'role_code' => 'viewer',
        'quantity' => 1,
        'plan_code' => 'growth',
    ]);
} catch (InvalidArgumentException $e) {
    $unsupportedRejected = true;
}

$check(
    $unsupportedRejected,
    'subscription product fields cannot enter seat-top-up request'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 BILLING SEAT-TOP-UP ROUTE REQUEST: FAILED\n";
    exit(1);
}

echo "V2.3 BILLING SEAT-TOP-UP ROUTE REQUEST: PASSED\n";
