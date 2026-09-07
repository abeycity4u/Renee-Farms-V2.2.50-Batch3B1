<?php
/**
 * V2.3 Billing Stage 2D approved NGN launch-price verifier.
 * Read-only, database-free and network-free.
 */

$root = dirname(__DIR__);
$paths = [
    'plans' => $root . '/includes/subscription_plan_catalog.php',
    'currency' => $root . '/includes/billing_currency_policy.php',
    'pricing' => $root . '/includes/billing_pricing_contract.php',
    'book' => $root . '/includes/billing_price_book_ngn.php',
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
require_once $paths['currency'];
require_once $paths['pricing'];

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

$book = billing_pricing_price_book();
$packages = $book['packages'] ?? [];
$seatPrices = $book['seat_unit_prices'] ?? [];

$expectedMonthly = [
    'starter' => ['poultry' => '10000.00', 'ruminant' => '10000.00', 'poultry+ruminant' => '15000.00'],
    'growth' => ['poultry' => '20000.00', 'ruminant' => '20000.00', 'poultry+ruminant' => '30000.00'],
    'pro' => ['poultry' => '35000.00', 'ruminant' => '35000.00', 'poultry+ruminant' => '50000.00'],
];
$expectedSeatMonthly = [
    'poultry_manager' => '2000.00',
    'ruminant_manager' => '2000.00',
    'sales_rep' => '1500.00',
    'viewer' => '1000.00',
];

$check(($book['version'] ?? null) === 'ngn-launch-v1',
    'price book version is ngn-launch-v1');
$check(($book['currency'] ?? null) === 'NGN',
    'price book currency is NGN');
$check(billing_currency_policy_supported() === ['NGN'],
    'launch currency policy still permits NGN only');
$check(billing_pricing_is_configured() === true,
    'server-authoritative pricing reports configured');

$planKeys = array_keys($packages);
sort($planKeys, SORT_STRING);
$check($planKeys === ['growth', 'pro', 'starter'],
    'price book covers exactly Starter, Growth and Pro');

$matrixExact = true;
foreach ($expectedMonthly as $plan => $bundles) {
    foreach ($bundles as $bundle => $monthly) {
        if (($packages[$plan][$bundle]['monthly'] ?? null) !== $monthly) {
            $matrixExact = false;
        }
    }
}
$check($matrixExact,
    'monthly package matrix matches the approved launch prices');

$check(($packages['starter']['poultry']['monthly'] ?? null) === '10000.00'
    && ($packages['starter']['ruminant']['monthly'] ?? null) === '10000.00'
    && ($packages['starter']['poultry+ruminant']['monthly'] ?? null) === '15000.00',
    'Starter monthly pricing is ₦10,000 single-module / ₦15,000 both');
$check(($packages['growth']['poultry']['monthly'] ?? null) === '20000.00'
    && ($packages['growth']['ruminant']['monthly'] ?? null) === '20000.00'
    && ($packages['growth']['poultry+ruminant']['monthly'] ?? null) === '30000.00',
    'Growth monthly pricing is ₦20,000 single-module / ₦30,000 both');
$check(($packages['pro']['poultry']['monthly'] ?? null) === '35000.00'
    && ($packages['pro']['ruminant']['monthly'] ?? null) === '35000.00'
    && ($packages['pro']['poultry+ruminant']['monthly'] ?? null) === '50000.00',
    'Pro monthly pricing is ₦35,000 single-module / ₦50,000 both');

$annualRuleOk = true;
foreach ($expectedMonthly as $plan => $bundles) {
    foreach ($bundles as $bundle => $monthly) {
        $monthlyMinor = billing_pricing_decimal_to_minor($monthly);
        $annualMinor = billing_pricing_decimal_to_minor((string)($packages[$plan][$bundle]['annual'] ?? ''));
        if ($annualMinor !== $monthlyMinor * 10) {
            $annualRuleOk = false;
        }
    }
}
$check($annualRuleOk,
    'all annual package prices equal exactly 10× monthly price');
$check(($packages['starter']['poultry+ruminant']['annual'] ?? null) === '150000.00'
    && ($packages['growth']['poultry+ruminant']['annual'] ?? null) === '300000.00'
    && ($packages['pro']['poultry+ruminant']['annual'] ?? null) === '500000.00',
    'annual both-module prices are ₦150k / ₦300k / ₦500k');

$singleParity = true;
$bundleDiscount = true;
foreach (['starter', 'growth', 'pro'] as $plan) {
    $poultry = billing_pricing_decimal_to_minor((string)$packages[$plan]['poultry']['monthly']);
    $ruminant = billing_pricing_decimal_to_minor((string)$packages[$plan]['ruminant']['monthly']);
    $both = billing_pricing_decimal_to_minor((string)$packages[$plan]['poultry+ruminant']['monthly']);
    if ($poultry !== $ruminant) $singleParity = false;
    if ($both >= ($poultry + $ruminant)) $bundleDiscount = false;
}
$check($singleParity,
    'Poultry-only and Ruminant-only have equal price within each plan');
$check($bundleDiscount,
    'both-module bundle is discounted versus buying both single-module prices');

$seatMonthlyExact = true;
$seatAnnualRuleOk = true;
$seatSameAcrossPlans = true;
foreach (['starter', 'growth', 'pro'] as $plan) {
    foreach ($expectedSeatMonthly as $role => $monthly) {
        if (($seatPrices[$plan][$role]['monthly'] ?? null) !== $monthly) {
            $seatMonthlyExact = false;
        }
        $monthlyMinor = billing_pricing_decimal_to_minor($monthly, true);
        $annualMinor = billing_pricing_decimal_to_minor((string)($seatPrices[$plan][$role]['annual'] ?? ''), true);
        if ($annualMinor !== $monthlyMinor * 10) {
            $seatAnnualRuleOk = false;
        }
        if (($seatPrices[$plan][$role] ?? null) !== ($seatPrices['starter'][$role] ?? null)) {
            $seatSameAcrossPlans = false;
        }
    }
}
$check($seatMonthlyExact,
    'extra-seat monthly prices are ₦2,000/₦2,000/₦1,500/₦1,000 by approved role');
$check($seatAnnualRuleOk,
    'all annual extra-seat prices equal exactly 10× monthly price');
$check($seatSameAcrossPlans,
    'extra-seat unit pricing is consistent across Starter, Growth and Pro');
$check(!isset($seatPrices['starter']['farm_admin']),
    'Farm Admin remains included/protected and is not sold as an extra seat');

$salesNotStandalone = true;
foreach (['starter', 'growth', 'pro'] as $plan) {
    if (isset($packages[$plan]['sales'])) $salesNotStandalone = false;
}
$check($salesNotStandalone,
    'shared Sales remains included and has no standalone package price');

$starter = billing_pricing_resolve('starter', 'monthly', ['poultry'], []);
$check(($starter['amount'] ?? null) === '10000.00'
    && ($starter['currency'] ?? null) === 'NGN'
    && ($starter['pricing_version'] ?? null) === 'ngn-launch-v1',
    'Starter Poultry monthly resolution returns ₦10,000 NGN with version identity');

$proBothAnnual = billing_pricing_resolve('pro', 'annual', ['poultry', 'ruminant'], []);
$check(($proBothAnnual['amount'] ?? null) === '500000.00',
    'Pro both-module annual resolution returns ₦500,000');

$growthWithSeats = billing_pricing_resolve('growth', 'monthly', ['poultry', 'ruminant'], [
    'poultry_manager' => 1,
    'ruminant_manager' => 1,
    'sales_rep' => 1,
    'viewer' => 1,
]);
$check(($growthWithSeats['package_amount'] ?? null) === '30000.00'
    && ($growthWithSeats['seat_addon_amount'] ?? null) === '6500.00'
    && ($growthWithSeats['amount'] ?? null) === '36500.00',
    'Growth both + one of each extra seat resolves to ₦36,500');
$check(($growthWithSeats['seat_components']['poultry_manager']['unit_amount'] ?? null) === '2000.00'
    && ($growthWithSeats['seat_components']['ruminant_manager']['unit_amount'] ?? null) === '2000.00'
    && ($growthWithSeats['seat_components']['sales_rep']['unit_amount'] ?? null) === '1500.00'
    && ($growthWithSeats['seat_components']['viewer']['unit_amount'] ?? null) === '1000.00',
    'resolved extra-seat components preserve approved per-role unit amounts');

$validated = billing_pricing_validate_price_book($book);
$hash = (string)($validated['price_book_hash'] ?? '');
$check(preg_match('/^[a-f0-9]{64}$/', $hash) === 1,
    'price book has a canonical SHA-256 audit identity');
$check(hash_equals($hash, billing_pricing_price_book_hash([
        'version' => $validated['version'],
        'currency' => $validated['currency'],
        'packages' => $validated['packages'],
        'seat_unit_prices' => $validated['seat_unit_prices'],
    ])),
    'price-book hash is deterministic for the approved commercial snapshot');

$combinedSource = $source['book'] . "\n" . $source['pricing'];
$protectedWritePattern = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions|billing_payment_attempts|billing_provider_events)\b/i';
$check(!preg_match($protectedWritePattern, $combinedSource),
    'price-book/pricing code contains no billing, subscription or entitlement DML');
$check(!preg_match('/\b(?:curl_init|curl_exec|fsockopen|stream_socket_client)\s*\(/i', $combinedSource),
    'price-book/pricing code makes no network or FX call');
$check(!preg_match('/\b(?:paystack|flutterwave|stripe)\b/i', $combinedSource),
    'approved price book is payment-provider independent');
$check(strpos($source['init'], 'includes/billing_price_book_ngn.php') === false
    && strpos($source['init'], 'includes/billing_pricing_contract.php') === false,
    'approved pricing remains unwired from global application runtime');
$check(stripos($source['book'], 'two months free') !== false
    && stripos($source['book'], 'new price-book version') !== false,
    'price-book source documents annual discount and versioning policy');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2D approved NGN price book is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2D NGN launch pricing is exact, versioned, server-authoritative and provider-independent.\n";
echo "NOTE: pricing is approved but checkout/provider network routes are still intentionally unwired.\n";
