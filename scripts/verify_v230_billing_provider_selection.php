<?php
/**
 * V2.3 Billing Stage 2B provider-selection verifier.
 * Read-only, database-free and network-free.
 */

$root = dirname(__DIR__);
$selectionPath = $root . '/includes/billing_provider_selection.php';
$providerContractPath = $root . '/includes/billing_provider_contract.php';
$initPath = $root . '/init.php';

foreach ([$selectionPath, $providerContractPath, $initPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: missing {$path}\n");
        exit(1);
    }
}

$selection = file_get_contents($selectionPath);
$providerContract = file_get_contents($providerContractPath);
$init = file_get_contents($initPath);
if ($selection === false || $providerContract === false || $init === false) {
    fwrite(STDERR, "FAIL: unable to read provider-selection source files.\n");
    exit(1);
}

require_once $selectionPath;

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

$catalog = billing_provider_selection_catalog();
$check(array_keys($catalog) === ['paystack', 'flutterwave'],
    'supported billing providers are exactly Paystack and Flutterwave');
$check(billing_provider_selection_codes() === ['paystack', 'flutterwave'],
    'provider preference order is Paystack first, Flutterwave second');
$check(billing_provider_selection_primary() === 'paystack',
    'Paystack is the canonical primary provider');
$check(billing_provider_selection_secondary() === ['flutterwave'],
    'Flutterwave is the canonical secondary provider');
$check(($catalog['paystack']['priority'] ?? null) === 1
    && ($catalog['paystack']['role'] ?? null) === 'primary',
    'Paystack catalog metadata is primary priority 1');
$check(($catalog['flutterwave']['priority'] ?? null) === 2
    && ($catalog['flutterwave']['role'] ?? null) === 'secondary',
    'Flutterwave catalog metadata is secondary priority 2');

$check(billing_provider_selection_required_env('paystack', false) === ['PAYSTACK_SECRET_KEY'],
    'Paystack server credential comes from PAYSTACK_SECRET_KEY environment only');
$check(billing_provider_selection_required_env('flutterwave', false) === ['FLUTTERWAVE_SECRET_KEY'],
    'Flutterwave server credential comes from FLUTTERWAVE_SECRET_KEY environment only');
$check(billing_provider_selection_required_env('flutterwave', true) === ['FLUTTERWAVE_SECRET_KEY', 'FLUTTERWAVE_WEBHOOK_HASH'],
    'Flutterwave webhook verification requires the deployment webhook hash');

$unsupportedRejected = false;
try {
    billing_provider_selection_normalize('other-gateway');
} catch (InvalidArgumentException $e) {
    $unsupportedRejected = true;
}
$check($unsupportedRejected,
    'unsupported provider codes are rejected');
$check(billing_provider_selection_no_automatic_fallback() === true,
    'cross-provider fallback is explicitly never automatic after checkout begins');

$check(strpos($selection, "'PAYSTACK_SECRET_KEY'") !== false
    && strpos($selection, "'FLUTTERWAVE_SECRET_KEY'") !== false
    && strpos($selection, "'FLUTTERWAVE_WEBHOOK_HASH'") !== false,
    'provider secrets are referenced only by deployment environment-variable names');
$check(strpos($selection, "'secret' =>") === false
    && strpos($selection, "'secret_key' =>") === false
    && strpos($selection, "'webhook_hash' =>") === false,
    'provider catalog contains no embedded secret values');
$check(strpos($selection, '\'missing_env\' => $missing') !== false
    && strpos($selection, '\'configured\' => $missing === []') !== false,
    'configuration status exposes readiness/missing names rather than credentials');

$protectedWritePattern = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions|billing_payment_attempts|billing_provider_events)\b/i';
$check(!preg_match($protectedWritePattern, $selection),
    'provider selection contains no billing, subscription or entitlement DML');
$check(!preg_match('/\b(?:curl_init|curl_exec|fsockopen|stream_socket_client)\s*\(/i', $selection),
    'provider selection makes no network call');
$check(strpos($selection, 'billing_provider_register_adapter(') === false,
    'provider selection does not register a concrete network adapter');
$check(stripos($providerContract, 'paystack') === false
    && stripos($providerContract, 'flutterwave') === false,
    'provider-neutral adapter contract remains provider-neutral');
$check(strpos($init, 'includes/billing_provider_selection.php') === false,
    'Stage 2B provider selection is not globally loaded into runtime yet');

$check(strpos($selection, 'Never retry a charge across') !== false,
    'source documents the no-silent-cross-provider-retry rule');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2B provider selection is not closed.\n");
    exit(1);
}

echo "PASS: Paystack is primary, Flutterwave is secondary, and provider selection remains credential-safe and fail-closed.\n";
echo "NOTE: this stage selects provider order only; it does not make live provider API calls.\n";
