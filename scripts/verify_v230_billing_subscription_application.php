<?php
/**
 * V2.3 Billing Stage 2G subscription-application contract verifier.
 * Read-only, database-free and network-free.
 */

$root = dirname(__DIR__);
$paths = [
    'plans' => $root . '/includes/subscription_plan_catalog.php',
    'payment' => $root . '/includes/billing_payment_foundation.php',
    'currency' => $root . '/includes/billing_currency_policy.php',
    'pricing' => $root . '/includes/billing_pricing_contract.php',
    'selection' => $root . '/includes/billing_provider_selection.php',
    'audit' => $root . '/includes/billing_payment_audit_state.php',
    'application' => $root . '/includes/billing_subscription_application.php',
    'record' => $root . '/includes/subscription_record.php',
    'checkout' => $root . '/billing/checkout.php',
    'return' => $root . '/billing/return.php',
    'webhook' => $root . '/billing/webhook.php',
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
require_once $paths['selection'];
require_once $paths['audit'];
require_once $paths['application'];

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

$irrelevantRuminantSeatRejected = false;
try {
    billing_pricing_build_payment_quote(
        'starter',
        'monthly',
        ['poultry'],
        ['ruminant_manager' => 1]
    );
} catch (InvalidArgumentException $e) {
    $irrelevantRuminantSeatRejected = str_contains($e->getMessage(), 'Ruminant Manager');
}
$check($irrelevantRuminantSeatRejected,
    'Poultry-only checkout rejects an unusable Ruminant Manager extra seat before charging');

$irrelevantPoultrySeatRejected = false;
try {
    billing_pricing_build_payment_quote(
        'starter',
        'monthly',
        ['ruminant'],
        ['poultry_manager' => 1]
    );
} catch (InvalidArgumentException $e) {
    $irrelevantPoultrySeatRejected = str_contains($e->getMessage(), 'Poultry Manager');
}
$check($irrelevantPoultrySeatRejected,
    'Ruminant-only checkout rejects an unusable Poultry Manager extra seat before charging');

$priced = billing_pricing_build_payment_quote(
    'starter',
    'monthly',
    ['poultry'],
    ['poultry_manager' => 1, 'sales_rep' => 1, 'viewer' => 1]
);
$check(($priced['pricing']['currency'] ?? null) === 'NGN'
    && ($priced['pricing']['modules'] ?? null) === ['poultry']
    && ($priced['pricing']['seat_addons']['poultry_manager'] ?? null) === 1,
    'valid relevant extra seats still resolve through server-authoritative NGN pricing');

$quote = $priced['payment_quote']['quote'];
$attempt = [
    'id' => 101,
    'farm_id' => 7,
    'status' => 'paid',
    'provider' => 'paystack',
    'provider_reference' => 'rf-stage2g-contract',
    'provider_transaction_id' => 'stage2g-contract-tx',
    'provider_subscription_id' => null,
    'plan_code' => $quote['plan_code'],
    'billing_interval' => $quote['billing_interval'],
    'amount' => $quote['amount'],
    'currency' => $quote['currency'],
    'modules_snapshot' => json_encode($quote['modules'], JSON_UNESCAPED_SLASHES),
    'seat_addons_snapshot' => json_encode($quote['seat_addons'], JSON_UNESCAPED_SLASHES),
    'quote_hash' => $priced['payment_quote']['quote_hash'],
    'initiated_by_user_id' => null,
    'verified_at' => '2026-09-07 04:00:00',
    'paid_at' => '2026-09-07 04:00:00',
    'applied_subscription_record_id' => null,
];
$contract = billing_subscription_attempt_contract($attempt);
$check(($contract['provider'] ?? null) === 'paystack'
    && ($contract['plan_code'] ?? null) === 'starter'
    && ($contract['amount'] ?? null) === $quote['amount']
    && ($contract['currency'] ?? null) === 'NGN',
    'canonical verified paid attempt resolves to an immutable application contract');
$check(($contract['modules'] ?? null) === ['poultry']
    && ($contract['seat_addons']['poultry_manager'] ?? null) === 1,
    'application contract preserves frozen module and seat snapshots exactly');

$pending = $attempt;
$pending['status'] = 'pending';
$pendingRejected = false;
try { billing_subscription_attempt_contract($pending); }
catch (RuntimeException $e) { $pendingRejected = str_contains($e->getMessage(), 'verified paid'); }
$check($pendingRejected,
    'non-paid billing attempt cannot enter subscription application');

$missingVerification = $attempt;
$missingVerification['verified_at'] = null;
$missingVerificationRejected = false;
try { billing_subscription_attempt_contract($missingVerification); }
catch (RuntimeException $e) { $missingVerificationRejected = str_contains($e->getMessage(), 'verification timestamps'); }
$check($missingVerificationRejected,
    'paid row without provider verification timestamps is rejected');

$missingTransaction = $attempt;
$missingTransaction['provider_transaction_id'] = null;
$missingTransactionRejected = false;
try { billing_subscription_attempt_contract($missingTransaction); }
catch (RuntimeException $e) { $missingTransactionRejected = str_contains($e->getMessage(), 'transaction id'); }
$check($missingTransactionRejected,
    'paid row without provider transaction identity is rejected');

$unsupportedProvider = $attempt;
$unsupportedProvider['provider'] = 'other-provider';
$unsupportedProviderRejected = false;
try { billing_subscription_attempt_contract($unsupportedProvider); }
catch (RuntimeException $e) { $unsupportedProviderRejected = str_contains($e->getMessage(), 'unsupported provider'); }
$check($unsupportedProviderRejected,
    'application accepts only the selected Paystack/Flutterwave providers');

$tamperedHash = $attempt;
$tamperedHash['quote_hash'] = str_repeat('0', 64);
$tamperedHashRejected = false;
try { billing_subscription_attempt_contract($tamperedHash); }
catch (RuntimeException $e) { $tamperedHashRejected = str_contains($e->getMessage(), 'integrity'); }
$check($tamperedHashRejected,
    'frozen quote hash tampering is rejected before commercial state mutation');

$nonCanonicalModules = $attempt;
$nonCanonicalModules['modules_snapshot'] = json_encode(['poultry', 'poultry']);
$nonCanonicalModulesRejected = false;
try { billing_subscription_attempt_contract($nonCanonicalModules); }
catch (RuntimeException $e) { $nonCanonicalModulesRejected = str_contains($e->getMessage(), 'not canonical'); }
$check($nonCanonicalModulesRejected,
    'non-canonical frozen module snapshot is rejected');

$irrelevantBuilt = billing_payment_build_quote(
    'starter', 'monthly', '12000.00', 'NGN', ['poultry'], ['ruminant_manager' => 1]
);
$irrelevantFrozen = $attempt;
$irrelevantFrozen['amount'] = $irrelevantBuilt['quote']['amount'];
$irrelevantFrozen['seat_addons_snapshot'] = json_encode($irrelevantBuilt['quote']['seat_addons']);
$irrelevantFrozen['quote_hash'] = $irrelevantBuilt['quote_hash'];
$irrelevantFrozenRejected = false;
try { billing_subscription_attempt_contract($irrelevantFrozen); }
catch (RuntimeException $e) { $irrelevantFrozenRejected = str_contains($e->getMessage(), 'unsubscribed livestock module'); }
$check($irrelevantFrozenRejected,
    'legacy/tampered paid attempt with unusable livestock seats is rejected at application too');

$renewContract = $contract;
$renewContract['plan_code'] = 'starter';
$renewContract['modules'] = ['poultry'];
$renewContract['billing_interval'] = 'monthly';
$renewContract['paid_at'] = '2026-09-01 12:00:00';
$renewTerm = billing_subscription_term([
    'subscription_plan' => 'starter',
    'subscription_starts_at' => '2026-01-01 12:00:00',
    'subscription_ends_at' => '2026-10-01 12:00:00',
], ['poultry'], $renewContract);
$check(($renewTerm['extended_existing_term'] ?? null) === true
    && ($renewTerm['subscription_starts_at'] ?? null) === '2026-01-01 12:00:00'
    && ($renewTerm['subscription_ends_at'] ?? null) === '2026-11-01 12:00:00',
    'same-product early monthly renewal preserves remaining time and extends one month');

$annualContract = $contract;
$annualContract['plan_code'] = 'growth';
$annualContract['modules'] = ['poultry', 'ruminant'];
$annualContract['billing_interval'] = 'annual';
$annualContract['paid_at'] = '2026-09-01 12:00:00';
$annualTerm = billing_subscription_term([
    'subscription_plan' => 'starter',
    'subscription_starts_at' => '2026-01-01 12:00:00',
    'subscription_ends_at' => '2026-10-01 12:00:00',
], ['poultry'], $annualContract);
$check(($annualTerm['extended_existing_term'] ?? null) === false
    && ($annualTerm['subscription_starts_at'] ?? null) === '2026-09-01 12:00:00'
    && ($annualTerm['subscription_ends_at'] ?? null) === '2027-09-01 12:00:00',
    'plan/module change starts immediately and grants exactly one annual term');

$app = $source['application'];
$pricingSource = $source['pricing'];
$combinedRoutes = $source['checkout'] . "\n" . $source['return'] . "\n" . $source['webhook'];

$check(strpos($app, "strtolower(trim((string)(\$attempt['status'] ?? ''))) !== 'paid'") !== false,
    'application source explicitly gates on paid billing status');
$check(strpos($app, "\$attempt['verified_at']") !== false
    && strpos($app, "\$attempt['paid_at']") !== false,
    'application source requires provider verification and paid timestamps');
$check(strpos($app, 'billing_provider_selection_codes()') !== false,
    'application source restricts paid attempts to selected providers');
$check(strpos($app, 'hash_equals($storedHash, (string)$rebuilt[\'quote_hash\'])') !== false,
    'application source revalidates the frozen quote hash');
$check(strpos($app, "'farms',") !== false
    && strpos($app, "'farm_modules',") !== false
    && strpos($app, "'farm_role_limits',") !== false
    && strpos($app, "'farm_subscription_seat_addons',") !== false
    && strpos($app, "'subscriptions',") !== false
    && strpos($app, "'billing_payment_attempts',") !== false
    && strpos($app, "strcasecmp(\$engine, 'InnoDB')") !== false,
    'application fails closed unless every commercial mutation table is InnoDB');

$capacityPos = strpos($app, 'subscription_seat_assert_capacity(');
$farmUpdatePos = strpos($app, 'UPDATE farms');
$check($capacityPos !== false && $farmUpdatePos !== false && $capacityPos < $farmUpdatePos,
    'seat capacity is validated before current subscription state changes');
$check(strpos($app, 'sync_farm_entitlements(') !== false
    && strpos($app, 'subscription_seat_save_addons(') !== false
    && strpos($app, 'billing_subscription_save_effective_limits(') !== false,
    'application updates modules, durable extra seats and effective seat limits through canonical policy');
$check(strpos($app, "subscription_status = 'active'") !== false,
    'verified paid application activates the farm current subscription snapshot');
$check(strpos($app, 'INSERT INTO subscriptions') !== false
    && strpos($app, "'billing_payment_applied'") !== false,
    'each applied payment appends dedicated immutable subscription history');
$check(strpos($app, "\$contract['billing_interval']") !== false
    && strpos($app, "\$contract['amount']") !== false
    && strpos($app, "\$contract['currency']") !== false
    && strpos($app, "\$contract['provider']") !== false,
    'billing history receives interval, amount, currency and provider from the frozen paid contract');
$check(strpos($app, 'applied_subscription_record_id = ?') !== false
    && strpos($app, 'applied_subscription_record_id IS NULL') !== false,
    'billing attempt is linked to history through an exactly-once application marker');
$check(strpos($app, 'billing_subscription_existing_application(') !== false
    && strpos($app, "'idempotent' => true") !== false,
    'repeated application of an already-linked paid attempt returns idempotently');
$check(strpos($app, 'FOR UPDATE') !== false,
    'application locks payment/farm state during transactional application');

$protectedOperationalWrite = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:users|user_roles|roles|permissions|sales_records|farm_expenses|production_cycles|stock_items|stock_transactions)\b/i';
$check(!preg_match($protectedOperationalWrite, $app),
    'Stage 2G application does not mutate users, permissions or operational farm records');
$check(strpos($app, 'DELETE FROM subscriptions') === false
    && strpos($app, 'UPDATE subscriptions') === false,
    'subscription history is append-only inside the payment bridge');
$check(strpos($app, 'curl_') === false
    && strpos($app, 'billing_provider_verify_payment(') === false
    && strpos($app, 'billing_provider_initialize_checkout(') === false,
    'application bridge makes no provider/network call and consumes only already-verified audit state');
$check(strpos($app, 'automatic renewal') !== false
    && strpos($app, 'proration') !== false,
    'launch term policy explicitly excludes automatic renewal and proration');

$check(strpos($pricingSource, 'Extra Poultry Manager seats require a Poultry subscription.') !== false
    && strpos($pricingSource, 'Extra Ruminant Manager seats require a Ruminant subscription.') !== false,
    'server pricing contains the matching specialist-seat preflight guards');
$check(strpos($source['record'], "'inserted' => false") !== false
    && strpos($source['record'], 'hash_equals((string)($latest[\'snapshot_hash\'] ?? \'\'), $hash)') !== false,
    'ordinary subscription-record no-op suppression remains unchanged for non-payment captures');
$check(strpos($combinedRoutes, 'billing_subscription_apply_paid_attempt(') === false,
    'Stage 2G application remains deliberately unwired from checkout/return/webhook while independently verified');
$check(strpos($source['init'], 'billing_subscription_application.php') === false,
    'Stage 2G application is not globally loaded through init.php');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2G subscription application contract is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2G application is paid-only, quote-bound, transactional, exactly-once and independently unwired.\n";
echo "NOTE: monthly/annual paid terms are non-recurring; live provider routes still do not invoke entitlement application.\n";
