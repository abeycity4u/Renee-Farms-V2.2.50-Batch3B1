<?php
/**
 * V2.3 Billing Stage 2I provider-readiness contract verifier.
 * Database-free and network-free. Temporarily manipulates process environment
 * only and restores every touched variable before exit.
 */

$root = dirname(__DIR__);
$readinessPath = $root . '/includes/billing_provider_readiness.php';
$checkoutPath = $root . '/billing/checkout.php';
if (!is_file($readinessPath) || !is_file($checkoutPath)) {
    fwrite(STDERR, "FAIL: Stage 2I readiness source is missing.\n");
    exit(1);
}
require_once $readinessPath;
$readinessSource = (string)file_get_contents($readinessPath);
$checkoutSource = (string)file_get_contents($checkoutPath);

$checks = 0;
$failures = 0;
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$message}\n";
};

$names = [
    'BILLING_PAYMENT_MODE',
    'BILLING_LIVE_PAYMENTS_ENABLED',
    'BILLING_PUBLIC_BASE_URL',
    'PAYSTACK_SECRET_KEY',
    'FLUTTERWAVE_SECRET_KEY',
    'FLUTTERWAVE_WEBHOOK_HASH',
];
$saved = [];
foreach ($names as $name) {
    $value = getenv($name);
    $saved[$name] = $value === false ? null : (string)$value;
}
$set = static function (string $name, ?string $value): void {
    if ($value === null) putenv($name);
    else putenv($name . '=' . $value);
    unset($_ENV[$name], $_SERVER[$name]);
};
$restore = static function () use (&$saved, $set): void {
    foreach ($saved as $name => $value) $set($name, $value);
};

try {
    foreach ($names as $name) $set($name, null);

    $check(billing_provider_payment_mode() === 'disabled',
        'billing payment mode defaults to disabled when deployment mode is absent');
    $check(billing_provider_new_checkout_allowed() === false,
        'disabled mode never permits a new provider checkout');
    $disabledRejected = false;
    try { billing_provider_assert_new_checkout_allowed(); }
    catch (RuntimeException $e) { $disabledRejected = str_contains($e->getMessage(), 'disabled'); }
    $check($disabledRejected,
        'disabled mode fails closed through the checkout assertion');

    $set('BILLING_PAYMENT_MODE', 'banana');
    $invalidRejected = false;
    try { billing_provider_payment_mode(); }
    catch (RuntimeException $e) { $invalidRejected = true; }
    $check($invalidRejected,
        'unknown billing payment mode is rejected rather than coerced');

    $set('BILLING_PAYMENT_MODE', 'test');
    $set('BILLING_LIVE_PAYMENTS_ENABLED', null);
    $check(billing_provider_payment_mode() === 'test'
        && billing_provider_new_checkout_allowed() === true,
        'explicit test mode permits test checkout without the live opt-in');
    $check(billing_provider_assert_new_checkout_allowed() === 'test',
        'test mode checkout assertion returns the canonical test mode');

    $set('BILLING_PAYMENT_MODE', 'live');
    $set('BILLING_LIVE_PAYMENTS_ENABLED', null);
    $check(billing_provider_new_checkout_allowed() === false,
        'live mode remains blocked without the second explicit live opt-in');
    $liveRejected = false;
    try { billing_provider_assert_new_checkout_allowed(); }
    catch (RuntimeException $e) { $liveRejected = str_contains($e->getMessage(), 'BILLING_LIVE_PAYMENTS_ENABLED=1'); }
    $check($liveRejected,
        'live checkout assertion explains the required explicit opt-in');

    $set('BILLING_LIVE_PAYMENTS_ENABLED', 'true');
    $check(billing_provider_live_payments_enabled() === false,
        'live opt-in is strict and does not accept truthy aliases');
    $set('BILLING_LIVE_PAYMENTS_ENABLED', '1');
    $check(billing_provider_live_payments_enabled() === true
        && billing_provider_new_checkout_allowed() === true
        && billing_provider_assert_new_checkout_allowed() === 'live',
        'live checkout requires and honors exact BILLING_LIVE_PAYMENTS_ENABLED=1');

    $set('BILLING_PAYMENT_MODE', 'test');
    $set('BILLING_LIVE_PAYMENTS_ENABLED', null);
    $set('BILLING_PUBLIC_BASE_URL', 'https://billing.example.test/app/');
    $public = billing_provider_public_url_status();
    $check(($public['configured'] ?? null) === true
        && ($public['url'] ?? null) === 'https://billing.example.test/app',
        'readiness reuses canonical HTTPS public billing URL validation');

    $set('BILLING_PUBLIC_BASE_URL', 'http://billing.example.test');
    $publicBad = billing_provider_public_url_status();
    $check(($publicBad['configured'] ?? null) === false
        && ($publicBad['url'] ?? 'x') === null,
        'non-HTTPS public billing URL is reported not ready');

    $set('BILLING_PUBLIC_BASE_URL', 'https://billing.example.test');
    $dummyPaystack = 'stage2i-paystack-secret-do-not-expose';
    $set('PAYSTACK_SECRET_KEY', $dummyPaystack);
    $paystack = billing_provider_readiness_status('paystack', true);
    $check(($paystack['ready'] ?? null) === true
        && ($paystack['mode'] ?? null) === 'test'
        && ($paystack['provider_configured'] ?? null) === true
        && ($paystack['public_url_configured'] ?? null) === true,
        'Paystack readiness becomes true only when test mode, provider config and public URL are ready');
    $check(strpos(json_encode($paystack), $dummyPaystack) === false,
        'readiness status never exposes Paystack secret values');

    $set('PAYSTACK_SECRET_KEY', null);
    $paystackMissing = billing_provider_readiness_status('paystack', true);
    $check(($paystackMissing['ready'] ?? null) === false
        && in_array('PAYSTACK_SECRET_KEY', $paystackMissing['missing_env'] ?? [], true),
        'Paystack readiness reports missing environment names without values');

    $dummyFlutter = 'stage2i-flutterwave-secret-do-not-expose';
    $set('FLUTTERWAVE_SECRET_KEY', $dummyFlutter);
    $set('FLUTTERWAVE_WEBHOOK_HASH', null);
    $flutterWebhook = billing_provider_readiness_status('flutterwave', true);
    $check(($flutterWebhook['ready'] ?? null) === false
        && in_array('FLUTTERWAVE_WEBHOOK_HASH', $flutterWebhook['missing_env'] ?? [], true),
        'Flutterwave full readiness requires its separate webhook hash');
    $flutterCheckout = billing_provider_readiness_status('flutterwave', false);
    $check(($flutterCheckout['provider_configured'] ?? null) === true,
        'Flutterwave checkout-only readiness can distinguish checkout credentials from webhook configuration');
    $check(strpos(json_encode($flutterWebhook), $dummyFlutter) === false,
        'readiness status never exposes Flutterwave secret values');

    $check(strpos($readinessSource, 'curl_') === false
        && !preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i', $readinessSource),
        'readiness helper performs no provider network call or database mutation');
    $check(strpos($readinessSource, "return 'disabled';") !== false
        && strpos($readinessSource, "['disabled', 'test', 'live']") !== false,
        'readiness policy source locks the three explicit operating modes');
    $check(strpos($readinessSource, "=== '1'") !== false,
        'live-payment opt-in is exact in source');

    $check(strpos($checkoutSource, 'billing_provider_readiness.php') !== false
        && strpos($checkoutSource, 'billing_provider_assert_new_checkout_allowed();') !== false,
        'checkout explicitly loads and invokes the Stage 2I readiness gate');
    $modePos = strpos($checkoutSource, 'billing_provider_assert_new_checkout_allowed();');
    $resolvePos = strpos($checkoutSource, 'billing_provider_selection_resolve_checkout(');
    $attemptPos = strpos($checkoutSource, 'billing_payment_attempt_create(');
    $networkPos = strpos($checkoutSource, 'billing_provider_initialize_checkout(');
    $check($modePos !== false && $resolvePos !== false && $attemptPos !== false && $networkPos !== false
        && $modePos < $resolvePos && $modePos < $attemptPos && $modePos < $networkPos,
        'deployment mode is asserted before provider resolution, attempt creation and network initialization');

    $check(strpos($checkoutSource, 'BILLING_LIVE_PAYMENTS_ENABLED') === false,
        'checkout route delegates live-mode policy to the centralized readiness helper');
} finally {
    $restore();
}

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2I provider readiness contract is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2I provider readiness is disabled-by-default, test-explicit, live-double-gated and secret-safe.\n";
echo "NOTE: this verifier performs no provider network call and does not prove current external provider API compatibility.\n";
