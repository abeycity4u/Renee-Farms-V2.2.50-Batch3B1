<?php
/**
 * V2.3 Billing Stage 2I provider-readiness contract verifier.
 * Database-free and network-free. Temporarily manipulates process environment
 * only and restores every touched variable before exit.
 */

$root = dirname(__DIR__);
$readinessPath = $root . '/includes/billing_provider_readiness.php';
$checkoutPath = $root . '/billing/checkout.php';
$adaptersPath = $root . '/includes/billing_provider_adapters.php';
foreach ([$readinessPath, $checkoutPath, $adaptersPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: Stage 2I readiness source is missing.\n");
        exit(1);
    }
}
require_once $readinessPath;
$readinessSource = (string)file_get_contents($readinessPath);
$checkoutSource = (string)file_get_contents($checkoutPath);
$adaptersSource = (string)file_get_contents($adaptersPath);

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
    'PAYSTACK_TEST_SECRET_KEY',
    'PAYSTACK_LIVE_SECRET_KEY',
    'FLUTTERWAVE_TEST_SECRET_KEY',
    'FLUTTERWAVE_TEST_WEBHOOK_HASH',
    'FLUTTERWAVE_LIVE_SECRET_KEY',
    'FLUTTERWAVE_LIVE_WEBHOOK_HASH',
    // Legacy generic names are included only to prove Stage 2I runtime ignores them.
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

    $check(billing_provider_mode_required_env('paystack', 'test', true) === ['PAYSTACK_TEST_SECRET_KEY'],
        'Paystack test checkout/webhook uses only the Paystack test secret slot');
    $check(billing_provider_mode_required_env('paystack', 'live', true) === ['PAYSTACK_LIVE_SECRET_KEY'],
        'Paystack live checkout/webhook uses only the Paystack live secret slot');
    $check(billing_provider_mode_required_env('flutterwave', 'test', true)
        === ['FLUTTERWAVE_TEST_SECRET_KEY', 'FLUTTERWAVE_TEST_WEBHOOK_HASH'],
        'Flutterwave test mode uses separate test secret and webhook-hash slots');
    $check(billing_provider_mode_required_env('flutterwave', 'live', true)
        === ['FLUTTERWAVE_LIVE_SECRET_KEY', 'FLUTTERWAVE_LIVE_WEBHOOK_HASH'],
        'Flutterwave live mode uses separate live secret and webhook-hash slots');

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
        && array_key_exists('url', $publicBad)
        && $publicBad['url'] === null,
        'non-HTTPS public billing URL is reported not ready');
    $set('BILLING_PUBLIC_BASE_URL', 'https://billing.example.test');

    $set('PAYSTACK_SECRET_KEY', 'legacy-generic-secret-must-not-enable-stage2i');
    $genericIgnored = billing_provider_readiness_status('paystack', true);
    $check(($genericIgnored['provider_configured'] ?? null) === false
        && in_array('PAYSTACK_TEST_SECRET_KEY', $genericIgnored['missing_env'] ?? [], true),
        'legacy generic Paystack secret cannot enable Stage 2I test runtime');

    $set('PAYSTACK_LIVE_SECRET_KEY', 'live-slot-secret-must-not-enable-test');
    $liveSlotIgnored = billing_provider_readiness_status('paystack', true);
    $check(($liveSlotIgnored['provider_configured'] ?? null) === false,
        'Paystack live credential slot cannot satisfy test-mode readiness');

    $dummyPaystack = 'stage2i-paystack-test-secret-do-not-expose';
    $set('PAYSTACK_TEST_SECRET_KEY', $dummyPaystack);
    $paystack = billing_provider_readiness_status('paystack', true);
    $check(($paystack['ready'] ?? null) === true
        && ($paystack['mode'] ?? null) === 'test'
        && ($paystack['provider_configured'] ?? null) === true
        && ($paystack['public_url_configured'] ?? null) === true,
        'Paystack readiness becomes true only with test slot, test mode and HTTPS public URL');
    $check(strpos((string)json_encode($paystack), $dummyPaystack) === false,
        'readiness status never exposes Paystack secret values');
    $paystackRuntime = billing_provider_runtime_credentials('paystack', true);
    $check(($paystackRuntime['mode'] ?? null) === 'test'
        && ($paystackRuntime['secret'] ?? null) === $dummyPaystack,
        'adapter-only runtime credential resolver selects the test Paystack slot');

    $set('PAYSTACK_TEST_SECRET_KEY', null);
    $paystackMissing = billing_provider_readiness_status('paystack', true);
    $check(($paystackMissing['ready'] ?? null) === false
        && in_array('PAYSTACK_TEST_SECRET_KEY', $paystackMissing['missing_env'] ?? [], true),
        'Paystack test readiness reports the missing test environment name without a value');

    $dummyFlutter = 'stage2i-flutterwave-test-secret-do-not-expose';
    $set('FLUTTERWAVE_TEST_SECRET_KEY', $dummyFlutter);
    $set('FLUTTERWAVE_TEST_WEBHOOK_HASH', null);
    $flutterWebhook = billing_provider_readiness_status('flutterwave', true);
    $check(($flutterWebhook['ready'] ?? null) === false
        && in_array('FLUTTERWAVE_TEST_WEBHOOK_HASH', $flutterWebhook['missing_env'] ?? [], true),
        'Flutterwave test full readiness requires its test webhook hash');
    $flutterCheckout = billing_provider_readiness_status('flutterwave', false);
    $check(($flutterCheckout['provider_configured'] ?? null) === true,
        'Flutterwave test checkout readiness distinguishes checkout secret from webhook configuration');
    $check(strpos((string)json_encode($flutterWebhook), $dummyFlutter) === false,
        'readiness status never exposes Flutterwave secret values');

    $set('PAYSTACK_TEST_SECRET_KEY', $dummyPaystack);
    $check(billing_provider_readiness_resolve_checkout('paystack') === 'paystack',
        'explicit Paystack checkout resolves only from current test-mode credentials');
    $set('PAYSTACK_TEST_SECRET_KEY', null);
    $set('FLUTTERWAVE_TEST_WEBHOOK_HASH', 'stage2i-flutterwave-test-webhook');
    $check(billing_provider_readiness_resolve_checkout(null) === 'flutterwave',
        'provider preference can select the next configured test provider without cross-attempt fallback');

    $check(strpos($readinessSource, 'curl_') === false
        && !preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i', $readinessSource),
        'readiness helper performs no provider network call or database mutation');
    $check(strpos($readinessSource, "return 'disabled';") !== false
        && strpos($readinessSource, "['disabled', 'test', 'live']") !== false,
        'readiness policy source locks the three explicit operating modes');
    $check(strpos($readinessSource, "=== '1'") !== false,
        'live-payment opt-in is exact in source');
    $check(strpos($readinessSource, 'PAYSTACK_TEST_SECRET_KEY') !== false
        && strpos($readinessSource, 'PAYSTACK_LIVE_SECRET_KEY') !== false
        && strpos($readinessSource, 'FLUTTERWAVE_TEST_SECRET_KEY') !== false
        && strpos($readinessSource, 'FLUTTERWAVE_LIVE_SECRET_KEY') !== false,
        'source keeps test and live provider credential slots physically distinct');

    $check(strpos($checkoutSource, 'billing_provider_readiness.php') !== false
        && strpos($checkoutSource, 'billing_provider_assert_new_checkout_allowed();') !== false,
        'checkout explicitly loads and invokes the Stage 2I readiness gate');
    $modePos = strpos($checkoutSource, 'billing_provider_assert_new_checkout_allowed();');
    $resolvePos = strpos($checkoutSource, 'billing_provider_readiness_resolve_checkout(');
    $attemptPos = strpos($checkoutSource, 'billing_payment_attempt_create(');
    $networkPos = strpos($checkoutSource, 'billing_provider_initialize_checkout(');
    $check($modePos !== false && $resolvePos !== false && $attemptPos !== false && $networkPos !== false
        && $modePos < $resolvePos && $modePos < $attemptPos && $modePos < $networkPos,
        'deployment mode is asserted before mode-aware provider resolution, attempt creation and network initialization');
    $check(strpos($checkoutSource, 'billing_provider_selection_resolve_checkout(') === false,
        'checkout no longer resolves runtime credentials through the generic Stage 2B readiness path');
    $check(strpos($adaptersSource, 'billing_provider_runtime_credentials($provider, true)') !== false,
        'adapter registration consumes only the Stage 2I mode-specific credential resolver');
    $check(strpos($adaptersSource, "billing_provider_selection_env_value('PAYSTACK_SECRET_KEY')") === false
        && strpos($adaptersSource, "billing_provider_selection_env_value('FLUTTERWAVE_SECRET_KEY')") === false,
        'adapter registration no longer reads generic provider secret variables');
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

echo "PASS: V2.3 Billing Stage 2I readiness is disabled-by-default, test/live credential-separated, live-double-gated and secret-safe.\n";
echo "NOTE: this verifier performs no provider network call and does not prove current external provider API compatibility.\n";
