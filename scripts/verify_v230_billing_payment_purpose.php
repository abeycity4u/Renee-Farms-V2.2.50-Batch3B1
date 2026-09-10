<?php
/**
 * Static V2.3 billing payment-purpose contract verifier.
 *
 * Confirms migration 045's purpose discriminator is understood by the shared
 * billing foundation without changing existing subscription quote hashes or
 * exposing seat-top-up checkout prematurely.
 *
 * No database connection, provider call or mutation.
 */

$root = dirname(__DIR__);

$servicePath = $root . '/includes/billing_payment_foundation.php';
$migrationPath = $root . '/migrations/045_billing_seat_change_foundation.sql';
$checkoutPath = $root . '/billing/checkout.php';

foreach ([$servicePath, $migrationPath, $checkoutPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: missing {$path}\n");
        exit(1);
    }
}

$service = (string)file_get_contents($servicePath);
$migration = (string)file_get_contents($migrationPath);
$checkout = (string)file_get_contents($checkoutPath);

$checks = 0;
$failures = 0;

$check = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
};

$check(
    strpos($service, "'id', 'farm_id', 'purpose', 'status'") !== false,
    'billing readiness requires the migration 045 purpose column'
);

$check(
    strpos($service, "return ['subscription', 'seat_topup'];") !== false,
    'payment purposes are centrally allow-listed'
);

$check(
    strpos($service, "function billing_payment_normalize_purpose") !== false
    && strpos($service, 'Unsupported billing payment purpose.') !== false,
    'unknown payment purposes fail closed'
);

$check(
    strpos($service, "function billing_payment_attempt_purpose") !== false
    && strpos($service, "?? 'subscription'") !== false,
    'legacy attempt arrays remain subscription-compatible'
);

$check(
    strpos(
        $service,
        "string \$purpose = 'subscription'"
    ) !== false,
    'existing attempt-create callers default to subscription purpose'
);

$check(
    strpos(
        $service,
        'farm_id, purpose, status, provider, provider_reference, plan_code'
    ) !== false,
    'new payment attempts persist their purpose explicitly'
);

$check(
    strpos(
        $service,
        "\$purpose = billing_payment_normalize_purpose(\$purpose);"
    ) !== false,
    'purpose is normalized before payment-attempt persistence'
);

$check(
    strpos(
        $service,
        'Provider reference already belongs to a different billing payment purpose.'
    ) !== false,
    'idempotent provider-reference replay rejects purpose mismatch'
);

$check(
    strpos(
        $service,
        'Provider reference collision detected across billing payment purposes.'
    ) !== false,
    'duplicate-key race recovery also rejects purpose mismatch'
);

$buildStart = strpos(
    $service,
    "if (!function_exists('billing_payment_build_quote'))"
);

$attemptStart = strpos(
    $service,
    "if (!function_exists('billing_payment_attempt_by_reference'))"
);

$buildQuoteSurface = '';

if ($buildStart !== false
    && $attemptStart !== false
    && $attemptStart > $buildStart) {
    $buildQuoteSurface = substr(
        $service,
        $buildStart,
        $attemptStart - $buildStart
    );
}

$check(
    $buildQuoteSurface !== ''
    && strpos($buildQuoteSurface, "'purpose'") === false
    && strpos($buildQuoteSurface, '"purpose"') === false,
    'existing commercial quote hash contract is not rewritten with payment purpose'
);

$check(
    strpos($migration, "DEFAULT ''subscription''") !== false,
    'database default agrees with source default subscription purpose'
);

$check(
    strpos($migration, 'billing_seat_change_requests') !== false,
    'purpose support remains paired with migration 045 seat-change storage'
);

$check(
    strpos($checkout, 'billing_current_product_assert_selection') !== false,
    'normal checkout still enforces current-product renewal'
);

$check(
    strpos($checkout, 'seat_topup') === false,
    'this checkpoint does not expose seat-top-up checkout'
);

$protectedWritePattern =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions)\b/i';

$check(
    !preg_match($protectedWritePattern, $service),
    'payment foundation remains entitlement and subscription-state inert'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    exit(1);
}

echo "V2.3 BILLING PAYMENT-PURPOSE CONTRACT: PASSED\n";
