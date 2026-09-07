<?php
/**
 * V2.3 Billing Stage 2E provider-adapter verifier.
 * Read-only, database-free and network-free. Provider HTTP is fully mocked.
 */

$root = dirname(__DIR__);
$paths = [
    'plans' => $root . '/includes/subscription_plan_catalog.php',
    'payment' => $root . '/includes/billing_payment_foundation.php',
    'currency' => $root . '/includes/billing_currency_policy.php',
    'pricing' => $root . '/includes/billing_pricing_contract.php',
    'contract' => $root . '/includes/billing_provider_contract.php',
    'selection' => $root . '/includes/billing_provider_selection.php',
    'transport' => $root . '/includes/billing_http_transport.php',
    'context' => $root . '/includes/billing_provider_context.php',
    'support' => $root . '/includes/billing_provider_adapter_support.php',
    'paystack' => $root . '/includes/billing_provider_paystack.php',
    'flutterwave' => $root . '/includes/billing_provider_flutterwave.php',
    'adapters' => $root . '/includes/billing_provider_adapters.php',
    'init' => $root . '/init.php',
];

$source = [];
foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: missing {$path}\n");
        exit(1);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }
    $source[$key] = $content;
}

require_once $paths['plans'];
require_once $paths['payment'];
require_once $paths['currency'];
require_once $paths['pricing'];
require_once $paths['contract'];
require_once $paths['selection'];
require_once $paths['transport'];
require_once $paths['context'];
require_once $paths['support'];
require_once $paths['paystack'];
require_once $paths['flutterwave'];
require_once $paths['adapters'];

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

$check(billing_provider_registered_codes() === [],
    'including concrete adapter files is inert and registers no provider');
$check(billing_provider_selection_primary() === 'paystack'
    && billing_provider_selection_secondary() === ['flutterwave'],
    'Paystack remains primary and Flutterwave remains secondary');
$check(billing_http_allowed_hosts() === ['api.paystack.co', 'api.flutterwave.com'],
    'billing HTTPS transport allowlists only the two selected provider API hosts');
$check(billing_http_assert_url('https://api.paystack.co/transaction/initialize') !== '',
    'exact Paystack HTTPS host is accepted');
$check(billing_http_assert_url('https://api.flutterwave.com/v3/payments') !== '',
    'exact Flutterwave HTTPS host is accepted');

$evilHostRejected = false;
try {
    billing_http_assert_url('https://api.paystack.co.evil.example/transaction/initialize');
} catch (Throwable $e) {
    $evilHostRejected = true;
}
$check($evilHostRejected,
    'lookalike provider host is rejected');

$plainHttpRejected = false;
try {
    billing_http_assert_url('http://api.paystack.co/transaction/initialize');
} catch (Throwable $e) {
    $plainHttpRejected = true;
}
$check($plainHttpRejected,
    'plain HTTP provider endpoint is rejected');

$context = billing_provider_sanitize_checkout_context([
    'customer_email' => ' Billing.QA@Example.Test ',
    'customer_name' => 'Stage 2E Farm',
    'callback_url' => 'https://example.test/billing/paystack/callback',
    'redirect_url' => 'https://example.test/billing/flutterwave/return',
]);
$check(($context['customer_email'] ?? null) === 'billing.qa@example.test'
    && ($context['customer_name'] ?? null) === 'Stage 2E Farm',
    'checkout context normalizes approved customer metadata');

$reservedRejected = false;
try {
    billing_provider_sanitize_checkout_context([
        'customer_email' => 'billing.qa@example.test',
        'amount' => '1.00',
    ]);
} catch (InvalidArgumentException $e) {
    $reservedRejected = str_contains($e->getMessage(), 'Billing-sensitive');
}
$check($reservedRejected,
    'browser context cannot inject amount or another reserved billing key');

$unknownContextRejected = false;
try {
    billing_provider_sanitize_checkout_context([
        'customer_email' => 'billing.qa@example.test',
        'custom_payload' => 'x',
    ]);
} catch (InvalidArgumentException $e) {
    $unknownContextRejected = str_contains($e->getMessage(), 'Unsupported');
}
$check($unknownContextRejected,
    'unknown checkout context keys fail closed');

$check(billing_adapter_decimal_to_minor('10000.00') === 1000000
    && billing_adapter_minor_to_decimal(1000000) === '10000.00',
    'exact NGN decimal/minor-unit conversion is reversible');

$paystackCalls = [];
$paystackTransport = static function (string $method, string $url, array $headers, ?array $payload = null) use (&$paystackCalls): array {
    $paystackCalls[] = compact('method', 'url', 'headers', 'payload');
    if ($method === 'POST') {
        return [
            'status' => 200,
            'json' => [
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/stage2e',
                    'reference' => (string)($payload['reference'] ?? ''),
                ],
            ],
        ];
    }
    return [
        'status' => 200,
        'json' => [
            'status' => true,
            'data' => [
                'id' => '901001',
                'status' => 'success',
                'reference' => 'rf-stage2e-paystack',
                'amount' => 1000000,
                'currency' => 'NGN',
                'paid_at' => '2026-09-07T03:00:00.000Z',
            ],
        ],
    ];
};

$paystackSecret = 'stage2e-paystack-secret';
$paystack = new PaystackBillingProviderAdapter($paystackSecret, $paystackTransport);
billing_provider_register_adapter($paystack);
$paystackQuote = billing_pricing_build_payment_quote('starter', 'monthly', ['poultry'], []);
$paystackCheckout = billing_provider_initialize_checkout(
    'paystack',
    'rf-stage2e-paystack',
    $paystackQuote,
    [
        'customer_email' => 'billing.qa@example.test',
        'customer_name' => 'Stage 2E Farm',
        'callback_url' => 'https://example.test/billing/paystack/callback',
    ]
);
$check(($paystackCheckout['provider'] ?? null) === 'paystack'
    && ($paystackCheckout['provider_reference'] ?? null) === 'rf-stage2e-paystack'
    && ($paystackCheckout['checkout_url'] ?? null) === 'https://checkout.paystack.com/stage2e',
    'Paystack checkout normalizes the provider redirect without a network call');

$paystackInit = $paystackCalls[0] ?? [];
$paystackInitPayload = is_array($paystackInit['payload'] ?? null) ? $paystackInit['payload'] : [];
$check(($paystackInit['method'] ?? null) === 'POST'
    && ($paystackInit['url'] ?? null) === 'https://api.paystack.co/transaction/initialize',
    'Paystack checkout targets the expected HTTPS initialization endpoint');
$check(($paystackInitPayload['amount'] ?? null) === 1000000
    && ($paystackInitPayload['currency'] ?? null) === 'NGN'
    && ($paystackInitPayload['reference'] ?? null) === 'rf-stage2e-paystack'
    && ($paystackInitPayload['email'] ?? null) === 'billing.qa@example.test',
    'Paystack receives server-derived NGN minor units, reference and customer email');
$check(($paystackInitPayload['callback_url'] ?? null) === 'https://example.test/billing/paystack/callback',
    'Paystack receives only the sanitized HTTPS callback URL');

$paystackVerified = billing_provider_verify_payment('paystack', 'rf-stage2e-paystack');
$check(($paystackVerified['verified'] ?? null) === true
    && ($paystackVerified['status'] ?? null) === 'paid'
    && ($paystackVerified['amount'] ?? null) === '10000.00'
    && ($paystackVerified['currency'] ?? null) === 'NGN',
    'Paystack verification normalizes an authenticated success to paid ₦10,000');

$paystackVerifyCall = $paystackCalls[1] ?? [];
$check(($paystackVerifyCall['method'] ?? null) === 'GET'
    && ($paystackVerifyCall['url'] ?? null) === 'https://api.paystack.co/transaction/verify/rf-stage2e-paystack',
    'Paystack verification uses the frozen provider reference');

$paystackWebhookRaw = json_encode([
    'event' => 'charge.success',
    'data' => [
        'id' => 901001,
        'status' => 'success',
        'reference' => 'rf-stage2e-paystack',
    ],
], JSON_UNESCAPED_SLASHES);
$paystackSignature = hash_hmac('sha512', $paystackWebhookRaw, $paystackSecret);
$paystackEvent = billing_provider_verify_webhook('paystack', $paystackWebhookRaw, [
    'x-paystack-signature' => $paystackSignature,
]);
$check(($paystackEvent['verified_signature'] ?? null) === true
    && ($paystackEvent['payment_status'] ?? null) === 'paid'
    && ($paystackEvent['provider_reference'] ?? null) === 'rf-stage2e-paystack',
    'Paystack webhook requires valid HMAC-SHA512 and normalizes charge success');
$check(str_starts_with((string)($paystackEvent['provider_event_id'] ?? ''), 'paystack:charge.success:'),
    'Paystack webhook produces a deterministic provider event id');

$badPaystackWebhookRejected = false;
try {
    billing_provider_verify_webhook('paystack', $paystackWebhookRaw, [
        'x-paystack-signature' => str_repeat('0', 128),
    ]);
} catch (RuntimeException $e) {
    $badPaystackWebhookRejected = true;
}
$check($badPaystackWebhookRejected,
    'Paystack webhook with invalid signature is rejected');

$paystackCallsBeforeInjection = count($paystackCalls);
$paystackContextInjectionRejected = false;
try {
    billing_provider_initialize_checkout(
        'paystack',
        'rf-stage2e-paystack-injection',
        $paystackQuote,
        [
            'customer_email' => 'billing.qa@example.test',
            'currency' => 'USD',
        ]
    );
} catch (InvalidArgumentException $e) {
    $paystackContextInjectionRejected = true;
}
$check($paystackContextInjectionRejected && count($paystackCalls) === $paystackCallsBeforeInjection,
    'reserved context injection is rejected before Paystack transport is called');

$flutterwaveCalls = [];
$flutterwaveTransport = static function (string $method, string $url, array $headers, ?array $payload = null) use (&$flutterwaveCalls): array {
    $flutterwaveCalls[] = compact('method', 'url', 'headers', 'payload');
    if ($method === 'POST') {
        return [
            'status' => 200,
            'json' => [
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/stage2e'],
            ],
        ];
    }
    return [
        'status' => 200,
        'json' => [
            'status' => 'success',
            'data' => [
                'id' => '902002',
                'status' => 'successful',
                'tx_ref' => 'rf-stage2e-flutterwave',
                'amount' => '15000',
                'currency' => 'NGN',
                'created_at' => '2026-09-07T03:00:00.000Z',
            ],
        ],
    ];
};

$flutterwaveSecret = 'stage2e-flutterwave-secret';
$flutterwaveHash = 'stage2e-flutterwave-webhook-hash';
$flutterwave = new FlutterwaveBillingProviderAdapter($flutterwaveSecret, $flutterwaveHash, $flutterwaveTransport);
billing_provider_register_adapter($flutterwave);
$flutterwaveQuote = billing_pricing_build_payment_quote('starter', 'monthly', ['poultry', 'ruminant'], []);
$flutterwaveCheckout = billing_provider_initialize_checkout(
    'flutterwave',
    'rf-stage2e-flutterwave',
    $flutterwaveQuote,
    [
        'customer_email' => 'billing.qa@example.test',
        'customer_name' => 'Stage 2E Farm',
        'redirect_url' => 'https://example.test/billing/flutterwave/return',
    ]
);
$check(($flutterwaveCheckout['provider'] ?? null) === 'flutterwave'
    && ($flutterwaveCheckout['provider_reference'] ?? null) === 'rf-stage2e-flutterwave'
    && ($flutterwaveCheckout['checkout_url'] ?? null) === 'https://checkout.flutterwave.com/stage2e',
    'Flutterwave checkout normalizes the provider redirect without a network call');

$flutterwaveInit = $flutterwaveCalls[0] ?? [];
$flutterwaveInitPayload = is_array($flutterwaveInit['payload'] ?? null) ? $flutterwaveInit['payload'] : [];
$check(($flutterwaveInit['method'] ?? null) === 'POST'
    && ($flutterwaveInit['url'] ?? null) === 'https://api.flutterwave.com/v3/payments',
    'Flutterwave checkout targets the selected V3 HTTPS payments endpoint');
$check(($flutterwaveInitPayload['amount'] ?? null) === '15000.00'
    && ($flutterwaveInitPayload['currency'] ?? null) === 'NGN'
    && ($flutterwaveInitPayload['tx_ref'] ?? null) === 'rf-stage2e-flutterwave',
    'Flutterwave receives the exact server-derived NGN amount and reference');
$check(($flutterwaveInitPayload['customer']['email'] ?? null) === 'billing.qa@example.test'
    && ($flutterwaveInitPayload['redirect_url'] ?? null) === 'https://example.test/billing/flutterwave/return',
    'Flutterwave receives sanitized customer email and HTTPS redirect URL');

$flutterwaveVerified = billing_provider_verify_payment('flutterwave', 'rf-stage2e-flutterwave');
$check(($flutterwaveVerified['verified'] ?? null) === true
    && ($flutterwaveVerified['status'] ?? null) === 'paid'
    && ($flutterwaveVerified['amount'] ?? null) === '15000.00'
    && ($flutterwaveVerified['currency'] ?? null) === 'NGN',
    'Flutterwave verification normalizes an authenticated success to paid ₦15,000');

$flutterwaveVerifyCall = $flutterwaveCalls[1] ?? [];
$check(($flutterwaveVerifyCall['method'] ?? null) === 'GET'
    && str_contains((string)($flutterwaveVerifyCall['url'] ?? ''), '/v3/transactions/verify_by_reference?tx_ref=rf-stage2e-flutterwave'),
    'Flutterwave verification uses the frozen transaction reference');

$flutterwaveWebhookRaw = json_encode([
    'event' => 'charge.completed',
    'data' => [
        'id' => 902002,
        'status' => 'successful',
        'tx_ref' => 'rf-stage2e-flutterwave',
    ],
], JSON_UNESCAPED_SLASHES);
$flutterwaveEvent = billing_provider_verify_webhook('flutterwave', $flutterwaveWebhookRaw, [
    'verif-hash' => $flutterwaveHash,
]);
$check(($flutterwaveEvent['verified_signature'] ?? null) === true
    && ($flutterwaveEvent['payment_status'] ?? null) === 'paid'
    && ($flutterwaveEvent['provider_reference'] ?? null) === 'rf-stage2e-flutterwave',
    'Flutterwave webhook requires the configured verification hash and normalizes success');
$check(str_starts_with((string)($flutterwaveEvent['provider_event_id'] ?? ''), 'flutterwave:charge.completed:'),
    'Flutterwave webhook produces a deterministic provider event id');

$badFlutterwaveWebhookRejected = false;
try {
    billing_provider_verify_webhook('flutterwave', $flutterwaveWebhookRaw, [
        'verif-hash' => 'wrong-stage2e-hash',
    ]);
} catch (RuntimeException $e) {
    $badFlutterwaveWebhookRejected = true;
}
$check($badFlutterwaveWebhookRejected,
    'Flutterwave webhook with invalid verification hash is rejected');

$registered = billing_provider_registered_codes();
$check($registered === ['flutterwave', 'paystack'],
    'both concrete adapters satisfy and register through the provider-neutral interface');

$combined = $source['transport'] . "\n"
    . $source['context'] . "\n"
    . $source['support'] . "\n"
    . $source['paystack'] . "\n"
    . $source['flutterwave'] . "\n"
    . $source['adapters'];
$protectedWritePattern = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions|billing_payment_attempts|billing_provider_events)\b/i';
$check(!preg_match($protectedWritePattern, $combined),
    'Stage 2E transport/adapters contain no billing, subscription or entitlement DML');
$check(strpos($combined, 'subscription_record_capture(') === false
    && strpos($combined, 'farm_entitlement_set') === false,
    'provider adapters cannot apply subscription or entitlement state');
$check(strpos($combined, 'error_log(') === false,
    'provider transport/adapters do not log credentials or provider payloads');
$check(strpos($source['transport'], 'CURLOPT_FOLLOWLOCATION => false') !== false
    && strpos($source['transport'], 'CURLOPT_SSL_VERIFYPEER => true') !== false
    && strpos($source['transport'], 'CURLOPT_SSL_VERIFYHOST => 2') !== false,
    'real transport disables redirects and enforces TLS peer/host verification');
$check(strpos($source['transport'], '1024 * 1024') !== false,
    'real transport bounds provider response bodies to one MiB');
$check(strpos($source['paystack'], "hash_hmac('sha512'") !== false,
    'Paystack webhook authenticity uses raw-body HMAC-SHA512');
$check(strpos($source['flutterwave'], 'billing_adapter_header($headers, \'verif-hash\')') !== false,
    'Flutterwave webhook authenticity uses the configured V3 verification hash header');
$check(strpos($source['init'], 'includes/billing_provider_adapters.php') === false
    && strpos($source['init'], 'includes/billing_provider_paystack.php') === false
    && strpos($source['init'], 'includes/billing_provider_flutterwave.php') === false,
    'Stage 2E concrete adapters remain unwired from global application runtime');
$check(strpos($source['adapters'], 'billing_provider_register_configured_adapters(?string $onlyProvider = null)') !== false,
    'routes can register one explicitly selected provider without requiring the other');
$check(strpos($source['adapters'], 'billing_provider_runtime_credentials($provider, true)') !== false,
    'explicit provider registration requires current mode checkout/webhook credentials before use');

$secretPattern = '/\b(?:sk_live_|sk_test_|FLWSECK-|Bearer\s+[A-Za-z0-9_-]{20,})/';
$check(!preg_match($secretPattern, $source['paystack'] . "\n" . $source['flutterwave'] . "\n" . $source['adapters']),
    'no live/test provider credential value is embedded in adapter source');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2E provider adapters are not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2E adapters remain provider-authenticated, server-price-bound, offline-verifiable and entitlement-inert under Stage 2I credential separation.\n";
echo "NOTE: provider HTTP behavior is unchanged; Stage 2I controls which deployment credential slot can instantiate an adapter.\n";
