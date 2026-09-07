<?php
/**
 * V2.3 Billing Stage 2E concrete adapter registration.
 *
 * Inclusion is inert. Routes must explicitly call
 * billing_provider_register_configured_adapters() before provider operations.
 */

require_once __DIR__ . '/billing_provider_contract.php';
require_once __DIR__ . '/billing_provider_selection.php';
require_once __DIR__ . '/billing_provider_paystack.php';
require_once __DIR__ . '/billing_provider_flutterwave.php';

if (!function_exists('billing_provider_register_configured_adapters')) {
    function billing_provider_register_configured_adapters(): array
    {
        $registered = billing_provider_registered_codes();

        if (!in_array('paystack', $registered, true)) {
            billing_provider_selection_assert_configured('paystack', true);
            $secret = billing_provider_selection_env_value('PAYSTACK_SECRET_KEY');
            if ($secret === null) throw new RuntimeException('Paystack billing secret is unavailable.');
            billing_provider_register_adapter(new PaystackBillingProviderAdapter($secret));
        }

        $registered = billing_provider_registered_codes();
        if (!in_array('flutterwave', $registered, true)) {
            billing_provider_selection_assert_configured('flutterwave', true);
            $secret = billing_provider_selection_env_value('FLUTTERWAVE_SECRET_KEY');
            $webhookHash = billing_provider_selection_env_value('FLUTTERWAVE_WEBHOOK_HASH');
            if ($secret === null || $webhookHash === null) {
                throw new RuntimeException('Flutterwave billing credentials are unavailable.');
            }
            billing_provider_register_adapter(new FlutterwaveBillingProviderAdapter($secret, $webhookHash));
        }

        $registered = billing_provider_registered_codes();
        sort($registered, SORT_STRING);
        return $registered;
    }
}
