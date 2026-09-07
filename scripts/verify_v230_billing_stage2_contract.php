<?php
/**
 * V2.3 Billing Stage 2A pricing/provider contract verifier.
 *
 * Read-only and database-free. It proves that pricing/provider architecture is
 * server-authoritative and fail-closed while commercial prices and the concrete
 * payment provider are still deliberately unconfigured.
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
$check(($book['version'] ?? null) === ''
    && ($book['currency'] ?? null) === ''
    && ($book['packages'] ?? null) === []
    && ($book['seat_unit_prices'] ?? null) === [],
    'Stage 2A ships with no invented price, currency or price-book version');
$check(billing_pricing_is_configured() === false,
    'pricing fails closed while the commercial price book is unconfigured');

$pricingRejected = false;
try {
    billing_pricing_resolve('starter', 'monthly', ['poultry'], []);
} catch (RuntimeException $e) {
    $pricingRejected = str_contains($e->getMessage(), 'Billing pricing is not configured');
}
$check($pricingRejected,
    'price resolution is rejected before commercial pricing is configured');

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

$check(strpos($pricing, "'packages' => []") !== false
    && strpos($pricing, "'seat_unit_prices' => []") !== false,
    'pricing source contains empty package and extra-seat price surfaces only');
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
    'no concrete payment provider is registered');

$providerRejected = false;
try {
    billing_provider_adapter('unconfigured-provider');
} catch (RuntimeException $e) {
    $providerRejected = str_contains($e->getMessage(), 'not configured');
}
$check($providerRejected,
    'provider operations fail closed when no adapter is configured');

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
    'Stage 2A contracts contain no entitlement or subscription-history DML');
$check(!preg_match('/\b(?:curl_init|curl_exec|fsockopen|stream_socket_client)\s*\(/i', $pricing . "\n" . $provider),
    'Stage 2A contracts make no network call');
$check(!preg_match('/\b(?:paystack|flutterwave|stripe)\b/i', $pricing . "\n" . $provider),
    'Stage 2A contracts contain no provider selection');
$check(stripos($composer, 'paystack') === false
    && stripos($composer, 'flutterwave') === false
    && stripos($composer, 'stripe') === false,
    'no payment-provider SDK is installed');
$check(!preg_match('/[\'\"](?:price|monthly_price|annual_price|amount|currency)[\'\"]\s*=>/i', $plans),
    'seat/capacity plan catalog still contains no billing prices');
$check(strpos($init, 'includes/billing_pricing_contract.php') === false
    && strpos($init, 'includes/billing_provider_contract.php') === false,
    'Stage 2A contracts are not globally loaded into runtime');
$check(strpos($provider, 'subscription_record_capture(') === false
    && strpos($provider, 'farm_entitlement_set') === false,
    'provider adapter layer cannot directly apply subscription or entitlement state');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2A pricing/provider contract is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2A is server-price-authoritative, provider-neutral and fail-closed.\n";
echo "NOTE: commercial prices and the concrete payment provider remain intentionally unconfigured.\n";
