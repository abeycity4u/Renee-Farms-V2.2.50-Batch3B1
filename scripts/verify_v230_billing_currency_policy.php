<?php
/**
 * V2.3 Billing Stage 2C NGN launch-currency verifier.
 * Read-only, database-free and network-free.
 */

$root = dirname(__DIR__);
$policyPath = $root . '/includes/billing_currency_policy.php';
$pricingPath = $root . '/includes/billing_pricing_contract.php';
$initPath = $root . '/init.php';

foreach ([$policyPath, $pricingPath, $initPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: missing {$path}\n");
        exit(1);
    }
}

$policySource = file_get_contents($policyPath);
$pricingSource = file_get_contents($pricingPath);
$initSource = file_get_contents($initPath);
if ($policySource === false || $pricingSource === false || $initSource === false) {
    fwrite(STDERR, "FAIL: unable to read Stage 2C source files.\n");
    exit(1);
}

require_once $policyPath;
require_once $pricingPath;

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

$check(billing_currency_policy_primary() === 'NGN',
    'NGN is the canonical V2.3 launch billing currency');
$check(billing_currency_policy_supported() === ['NGN'],
    'NGN is the only supported launch billing currency');
$check(billing_currency_policy_multi_currency_enabled() === false,
    'multi-currency billing is disabled for launch');
$check(billing_currency_policy_automatic_fx_enabled() === false,
    'automatic FX conversion is disabled');
$check(billing_currency_policy_normalize(' ngn ') === 'NGN',
    'NGN currency input is normalized canonically');

$usdRejected = false;
try {
    billing_currency_policy_normalize('USD');
} catch (InvalidArgumentException $e) {
    $usdRejected = str_contains($e->getMessage(), 'not supported');
}
$check($usdRejected,
    'USD is rejected by the V2.3 launch currency policy');
$check(billing_currency_policy_requires_explicit_price_book('NGN') === false
    && billing_currency_policy_requires_explicit_price_book('USD') === true,
    'any future non-NGN currency requires a separate explicit price book');

$ngnBookAccepted = false;
try {
    $validated = billing_pricing_validate_price_book([
        'version' => 'stage2c-contract-test',
        'currency' => 'NGN',
        'packages' => ['contract-test' => ['configured' => true]],
        'seat_unit_prices' => [],
    ]);
    $ngnBookAccepted = ($validated['currency'] ?? null) === 'NGN';
} catch (Throwable $e) {
    $ngnBookAccepted = false;
}
$check($ngnBookAccepted,
    'pricing validator accepts an NGN price-book currency when policy is loaded');

$usdBookRejected = false;
try {
    billing_pricing_validate_price_book([
        'version' => 'stage2c-contract-test',
        'currency' => 'USD',
        'packages' => ['contract-test' => ['configured' => true]],
        'seat_unit_prices' => [],
    ]);
} catch (InvalidArgumentException $e) {
    $usdBookRejected = str_contains($e->getMessage(), 'not supported');
}
$check($usdBookRejected,
    'pricing validator rejects a USD price book when launch policy is loaded');

$currentBook = billing_pricing_price_book();
$check(($currentBook['version'] ?? null) === ''
    && ($currentBook['currency'] ?? null) === ''
    && ($currentBook['packages'] ?? null) === []
    && ($currentBook['seat_unit_prices'] ?? null) === [],
    'actual commercial price amounts remain deliberately unconfigured');
$check(billing_pricing_is_configured() === false,
    'checkout pricing still fails closed until real NGN prices are approved');

$check(strpos($pricingSource, "function_exists('billing_currency_policy_normalize')") !== false
    && strpos($pricingSource, 'billing_currency_policy_normalize($currency)') !== false,
    'server-side price-book validation is wired to the launch currency policy');
$check(strpos($policySource, "return ['NGN'];") !== false
    && strpos($policySource, "return 'NGN';") !== false,
    'currency policy source has one explicit launch currency');
$check(strpos($policySource, "'amount' =>") === false
    && strpos($policySource, "'price' =>") === false,
    'currency policy contains no invented subscription prices');

$protectedWritePattern = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions|billing_payment_attempts|billing_provider_events)\b/i';
$check(!preg_match($protectedWritePattern, $policySource . "\n" . $pricingSource),
    'Stage 2C contains no billing, subscription or entitlement DML');
$check(!preg_match('/\b(?:curl_init|curl_exec|fsockopen|stream_socket_client)\s*\(/i', $policySource . "\n" . $pricingSource),
    'Stage 2C makes no network or exchange-rate call');
$check(strpos($initSource, 'includes/billing_currency_policy.php') === false,
    'Stage 2C policy is not globally loaded into application runtime yet');

$check(stripos($policySource, 'exchange rate') !== false
    && stripos($policySource, 'explicit') !== false,
    'source documents explicit future price books instead of automatic FX conversion');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2C NGN launch-currency policy is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2C is NGN-only, no-FX, server-enforced and price-amount-neutral.\n";
echo "NOTE: Starter/Growth/Pro and extra-seat NGN amounts are intentionally still unconfigured.\n";
