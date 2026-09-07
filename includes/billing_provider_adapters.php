<?php
/**
 * V2.3 Billing Stage 2E concrete adapter registration.
 *
 * Inclusion is inert. Routes must explicitly call
 * billing_provider_register_configured_adapters() before provider operations.
 * A selected provider never causes another provider to be auto-substituted.
 */

require_once __DIR__ . '/billing_provider_contract.php';
require_once __DIR__ . '/billing_provider_selection.php';
require_once __DIR__ . '/billing_provider_paystack.php';
require_once __DIR__ . '/billing_provider_flutterwave.php';

if (!function_exists('billing_provider_register_configured_adapters')) {
    function billing_provider_register_configured_adapters(?string $onlyProvider = null): array
    {
        $requested = null;
        if ($onlyProvider !== null && trim($onlyProvider) !== '') {
            $requested = billing_provider_selection_normalize($onlyProvider);
            billing_provider_selection_assert_configured($requested, true);
        }

        $targets = $requested !== null
            ? [$requested]
            : billing_provider_selection_codes();

        foreach ($targets as $provider) {
            $registered = billing_provider_registered_codes();
            if (in_array($provider, $registered, true)) continue;

            $status = billing_provider_selection_status($provider, true);
            if ($status['configured'] !== true) {
                if ($requested !== null) {
                    billing_provider_selection_assert_configured($provider, true);
                }
                continue;
            }

            if ($provider === 'paystack') {
                $secret = billing_provider_selection_env_value('PAYSTACK_SECRET_KEY');
                if ($secret === null) throw new RuntimeException('Paystack billing secret is unavailable.');
                billing_provider_register_adapter(new PaystackBillingProviderAdapter($secret));
                continue;
            }

            if ($provider === 'flutterwave') {
                $secret = billing_provider_selection_env_value('FLUTTERWAVE_SECRET_KEY');
                $webhookHash = billing_provider_selection_env_value('FLUTTERWAVE_WEBHOOK_HASH');
                if ($secret === null || $webhookHash === null) {
                    throw new RuntimeException('Flutterwave billing credentials are unavailable.');
                }
                billing_provider_register_adapter(new FlutterwaveBillingProviderAdapter($secret, $webhookHash));
                continue;
            }

            throw new RuntimeException('No concrete adapter exists for selected billing provider.');
        }

        $registered = billing_provider_registered_codes();
        sort($registered, SORT_STRING);
        return $registered;
    }
}
