<?php
/**
 * V2.3 Billing Stage 2 architecture regression verifier.
 * Read-only, database-free and network-free.
 *
 * Stage 2D now supplies an approved NGN price book. This verifier continues to
 * protect the original Stage 2A invariants: server-authoritative pricing,
 * provider-neutral adapter boundaries, no browser-controlled amount/currency,
 * no entitlement writes, and no network/provider SDK coupling.
 */

$root = dirname(__DIR__);
$paths = [
    'pricing' => $root . '/includes/billing_pricing_contract.php',
    'provider' => $root . '/includes/billing_provider_contract.php',
    'plans' => $root . '/includes/subscription_plan_catalog.php',
    'composer' => $root . '/composer.json',
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

$pricing = $source['pricing'];
$provider = $source['provider'];
$plans = $source['plans'];
$composer = $source['composer'];
$init = $source['init'];

require_once $paths['plans'];
require_once $paths['pricing'];
require_once $paths['provider'];

$book = billing_pricing_price_book();
$check(($book['version'] ?? '') !== ''
    && ($book['currency'] ?? null) === 'NGN'
    && !empty($book['packages'])
    && !empty($book['seat_unit_prices']),
    'approved versioned NGN commercial price book is installed');
$check(billing_pricing_is_configured() === true,
    'server pricing reports configured after Stage 2D approval');

$starter = billing_pricing_resolve('starter', 'monthly', ['poultry'], []);
$check(($starter['currency'] ?? null) === 'NGN'
    && ($starter['pricing_version'] ?? '') !== ''
    && preg_match('/^\d+\.\d{2}$/', (string)($starter['amount'] ?? '')) === 1,
    'price resolution returns canonical server-derived NGN pricing');

$check(billing_pricing_bundle_key(['poultry']) === 'poultry'
    && billing_pricing_bundle_key(['ruminant']) === 'ruminant'
    && billing_pricing_bundle_key(['ruminant', 'poultry']) === 'poultry+ruminant',
    'priced module bundle supports Poultry, Ruminant, or both');

$salesRejected = false;
try {
    billing_pricing_bundle_key(['sales']);
} catch (InvalidArgumentException $e) {
    $salesRejected = true;
}
$check($salesRejected,
    'shared Sales cannot become a separately priced module');

$check(strpos($pricing, 'billing_price_book_ngn.php') !== false
    && strpos($pricing, 'billing_price_book_ngn()') !== false,
    'pricing contract loads the dedicated approved price book rather than embedding browser pricing');
$check(strpos($pricing, 'billing_pricing_price_book_hash') !== false
    && strpos($pricing, "hash('sha256'") !== false,
    'price book has deterministic SHA-256 identity');
$check(strpos($pricing, "'pricing_version' =>") !== false
    && strpos($pricing, "'pricing_hash' =>") !== false,
    'resolved pricing carries version and hash identity');
$check(strpos($pricing, "'package_amount' =>") !== false
    && strpos($pricing, "'seat_addon_amount' =>") !== false
    && strpos($pricing, "'amount' =>") !== false,
    'resolved pricing separates package, extra-seat and total amounts');
$check(strpos($pricing, '$packageRaw = $book[\'packages\'][$planCode][$bundleKey][$billingInterval]') !== false,
    'package price is keyed by plan, module bundle and interval');
$check(strpos($pricing, '$book[\'seat_unit_prices\'][$planCode][$role][$billingInterval]') !== false,
    'extra-seat unit price is keyed by plan, role and interval');

$pricedQuoteSignature = preg_match(
    '/function\s+billing_pricing_build_payment_quote\s*\(\s*string\s+\$planCode\s*,\s*string\s+\$billingInterval\s*,\s*array\s+\$modules\s*,\s*array\s+\$seatAddOns\s*=\s*\[\]\s*\)/s',
    $pricing
) === 1;
$check($pricedQuoteSignature,
    'authoritative priced-quote entry point accepts no caller amount or currency');
$check(strpos($pricing, '$pricing = billing_pricing_resolve($planCode, $billingInterval, $modules, $seatAddOns);') !== false
    && strpos($pricing, '$pricing[\'amount\']') !== false
    && strpos($pricing, '$pricing[\'currency\']') !== false,
    'payment quote amount/currency come from server-side pricing resolution');

$check(interface_exists('BillingProviderAdapterInterface'),
    'provider-neutral adapter interface is available');
$check(method_exists('BillingProviderAdapterInterface', 'initializeCheckout')
    && method_exists('BillingProviderAdapterInterface', 'verifyPayment')
    && method_exists('BillingProviderAdapterInterface', 'verifyWebhook'),
    'adapter surface is checkout, payment verification and webhook verification only');
$check(billing_provider_registered_codes() === [],
    'no concrete network provider adapter is registered by the neutral contract');

$providerRejected = false;
try {
    billing_provider_adapter('unconfigured-provider');
} catch (RuntimeException $e) {
    $providerRejected = str_contains($e->getMessage(), 'not configured');
}
$check($providerRejected,
    'provider operations fail closed when an adapter is not configured');

$paidWithoutVerificationRejected = false;
try {
    billing_provider_normalize_payment_result('contract-test', [
        'verified' => false,
        'status' => 'paid',
        'provider_reference' => 'ref-1',
        'amount' => '1.00',
        'currency' => 'NGN',
    ]);
} catch (RuntimeException $e) {
    $paidWithoutVerificationRejected = str_contains($e->getMessage(), 'unverified');
}
$check($paidWithoutVerificationRejected,
    'unverified provider result can never normalize to paid');

$unsignedWebhookRejected = false;
try {
    billing_provider_normalize_webhook_result('contract-test', '{}', [
        'verified_signature' => false,
        'provider_event_id' => 'evt-1',
        'event_type' => 'payment.test',
    ]);
} catch (RuntimeException $e) {
    $unsignedWebhookRejected = str_contains($e->getMessage(), 'signature');
}
$check($unsignedWebhookRejected,
    'webhook event is rejected unless signature verification succeeds');

$httpCheckoutRejected = false;
try {
    billing_provider_normalize_checkout_result('contract-test', [
        'provider_reference' => 'ref-1',
        'checkout_url' => 'http://example.invalid/checkout',
    ]);
} catch (RuntimeException $e) {
    $httpCheckoutRejected = str_contains($e->getMessage(), 'HTTPS');
}
$check($httpCheckoutRejected,
    'provider checkout redirect must use HTTPS');

$check(strpos($provider, 'billing_provider_checkout_request') !== false
    && strpos($provider, '$pricing = $pricedQuote[\'pricing\'] ?? null;') !== false
    && strpos($provider, '$paymentQuote = $pricedQuote[\'payment_quote\'] ?? null;') !== false,
    'provider checkout requires the server-authoritative priced quote envelope');
$check(strpos($provider, 'hash_equals($amount, billing_provider_normalize_amount($quote[\'amount\'] ?? null))') !== false
    && strpos($provider, 'hash_equals($currency, billing_provider_normalize_currency((string)($quote[\'currency\'] ?? \'\')))') !== false,
    'provider checkout cross-checks amount/currency against the frozen quote');
$check(strpos($provider, '\'payload_hash\' => hash(\'sha256\', $rawPayload)') !== false,
    'webhook persistence surface exposes only SHA-256 payload identity');
$check(strpos($provider, "['pending', 'paid', 'failed', 'cancelled', 'refunded']") !== false,
    'provider status normalizes to the canonical Stage 1 vocabulary');

$protectedWritePattern = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions)\b/i';
$check(!preg_match($protectedWritePattern, $pricing . "\n" . $provider),
    'pricing/provider contracts contain no entitlement or subscription-history DML');
$check(!preg_match('/\b(?:curl_init|curl_exec|fsockopen|stream_socket_client)\s*\(/i', $pricing . "\n" . $provider),
    'pricing/provider contracts make no network call');
$check(!preg_match('/\b(?:paystack|flutterwave|stripe)\b/i', $pricing . "\n" . $provider),
    'provider-neutral contract remains free of provider selection');
$check(stripos($composer, 'paystack') === false
    && stripos($composer, 'flutterwave') === false
    && stripos($composer, 'stripe') === false,
    'no payment-provider SDK is installed');
$check(!preg_match('/[\'\"](?:price|monthly_price|annual_price|amount|currency)[\'\"]\s*=>/i', $plans),
    'seat/capacity plan catalog still contains no billing prices');
$check(strpos($init, 'includes/billing_pricing_contract.php') === false
    && strpos($init, 'includes/billing_provider_contract.php') === false,
    'billing pricing/provider contracts are not globally loaded into runtime yet');
$check(strpos($provider, 'subscription_record_capture(') === false
    && strpos($provider, 'farm_entitlement_set') === false,
    'provider adapter layer cannot directly apply subscription or entitlement state');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2 architecture regression detected.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2 remains server-price-authoritative, provider-neutral and fail-closed.\n";
echo "NOTE: NGN prices are approved; concrete network adapters and checkout routes remain unwired.\n";
