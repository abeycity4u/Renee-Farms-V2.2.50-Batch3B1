<?php
/**
 * V2.3 Billing Stage 2E/2I concrete adapter registration.
 *
 * Inclusion is inert. Routes must explicitly call
 * billing_provider_register_configured_adapters() before provider operations.
 * Stage 2I supplies mode-specific test/live credentials; a selected provider
 * never causes another provider to be auto-substituted.
 */

require_once __DIR__ . '/billing_provider_contract.php';
require_once __DIR__ . '/billing_provider_selection.php';
require_once __DIR__ . '/billing_provider_readiness.php';
require_once __DIR__ . '/billing_provider_paystack.php';
require_once __DIR__ . '/billing_provider_flutterwave.php';

if (!function_exists('billing_provider_register_configured_adapters')) {
    function billing_provider_register_configured_adapters(?string $onlyProvider = null): array
    {
        $requested = null;
        if ($onlyProvider !== null && trim($onlyProvider) !== '') {
            $requested = billing_provider_selection_normalize($onlyProvider);
        }

        $targets = $requested !== null
            ? [$requested]
            : billing_provider_selection_codes();

        foreach ($targets as $provider) {
            $registered = billing_provider_registered_codes();
            if (in_array($provider, $registered, true)) continue;

            try {
                $credentials = billing_provider_runtime_credentials($provider, true);
            } catch (Throwable $e) {
                if ($requested !== null) throw $e;
                continue;
            }

            if ($provider === 'paystack') {
                billing_provider_register_adapter(
                    new PaystackBillingProviderAdapter((string)$credentials['secret'])
                );
                continue;
            }

            if ($provider === 'flutterwave') {
                billing_provider_register_adapter(
                    new FlutterwaveBillingProviderAdapter(
                        (string)$credentials['secret'],
                        (string)$credentials['webhook_hash']
                    )
                );
                continue;
            }

            throw new RuntimeException('No concrete adapter exists for selected billing provider.');
        }

        $registered = billing_provider_registered_codes();
        sort($registered, SORT_STRING);
        return $registered;
    }
}
