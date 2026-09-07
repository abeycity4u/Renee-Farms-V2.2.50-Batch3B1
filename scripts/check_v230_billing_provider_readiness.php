<?php
/**
 * V2.3 Billing Stage 2I deployment readiness check.
 *
 * Usage:
 *   php scripts/check_v230_billing_provider_readiness.php [paystack|flutterwave]
 *
 * Reports only configuration presence/status. Secret values are never printed.
 * Performs no database mutation and no provider network call.
 */

require_once dirname(__DIR__) . '/includes/billing_provider_readiness.php';

$provider = strtolower(trim((string)($argv[1] ?? billing_provider_selection_primary())));
try {
    $provider = billing_provider_selection_normalize($provider);
    $status = billing_provider_readiness_status($provider, true);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$yesNo = static fn(bool $value): string => $value ? 'YES' : 'NO';
$mode = $status['mode_valid'] ? strtoupper((string)$status['mode']) : 'INVALID';

echo 'Provider:              ' . $status['label'] . ' (' . $status['provider'] . ')' . PHP_EOL;
echo 'Payment mode:          ' . $mode . PHP_EOL;
echo 'Provider configured:    ' . $yesNo((bool)$status['provider_configured']) . PHP_EOL;
echo 'Public URL configured:  ' . $yesNo((bool)$status['public_url_configured']) . PHP_EOL;
echo 'New checkout allowed:   ' . $yesNo((bool)$status['new_checkout_allowed']) . PHP_EOL;
echo 'Live explicit opt-in:   ' . $yesNo((bool)$status['live_opt_in']) . PHP_EOL;
echo 'Overall ready:          ' . $yesNo((bool)$status['ready']) . PHP_EOL;

if ($status['public_url_configured'] && is_string($status['public_url'])) {
    echo 'Public billing URL:     ' . $status['public_url'] . PHP_EOL;
}
if (!$status['mode_valid'] && is_string($status['mode_error'])) {
    echo 'Mode issue:             ' . $status['mode_error'] . PHP_EOL;
}
if (!$status['public_url_configured'] && is_string($status['public_url_error'])) {
    echo 'Public URL issue:       ' . $status['public_url_error'] . PHP_EOL;
}
if (!empty($status['missing_env'])) {
    echo 'Missing environment:    ' . implode(', ', $status['missing_env']) . PHP_EOL;
}

echo PHP_EOL;
if ($status['ready']) {
    echo 'PASS: billing provider deployment prerequisites are present for ' . $status['label'] . '.' . PHP_EOL;
    if ($status['mode'] === 'test') {
        echo 'PASS: checkout is explicitly constrained to Stage 2I test mode policy.' . PHP_EOL;
    } elseif ($status['mode'] === 'live') {
        echo 'WARNING: LIVE payment mode is explicitly enabled.' . PHP_EOL;
    }
    exit(0);
}

echo 'FAIL: billing provider deployment is not ready for a new checkout.' . PHP_EOL;
echo 'NOTE: this check does not contact the provider and never prints credential values.' . PHP_EOL;
exit(1);
