<?php
/**
 * V2.3 Billing Stage 2I provider runtime-readiness policy.
 *
 * Provider credentials being present is not enough to permit a new network
 * checkout. New checkout is fail-closed unless BILLING_PAYMENT_MODE explicitly
 * enables test/live operation. Live mode additionally requires an explicit
 * BILLING_LIVE_PAYMENTS_ENABLED=1 opt-in.
 *
 * This helper never exposes credential values and performs no network or DB I/O.
 */

require_once __DIR__ . '/billing_provider_selection.php';
require_once __DIR__ . '/billing_route_request.php';

if (!function_exists('billing_provider_readiness_env_value')) {
    function billing_provider_readiness_env_value(string $name): ?string
    {
        return billing_provider_selection_env_value($name);
    }
}

if (!function_exists('billing_provider_payment_mode')) {
    function billing_provider_payment_mode(): string
    {
        $raw = billing_provider_readiness_env_value('BILLING_PAYMENT_MODE');
        if ($raw === null) return 'disabled';

        $mode = strtolower(trim($raw));
        if (!in_array($mode, ['disabled', 'test', 'live'], true)) {
            throw new RuntimeException('BILLING_PAYMENT_MODE must be disabled, test, or live.');
        }
        return $mode;
    }
}

if (!function_exists('billing_provider_live_payments_enabled')) {
    function billing_provider_live_payments_enabled(): bool
    {
        return billing_provider_readiness_env_value('BILLING_LIVE_PAYMENTS_ENABLED') === '1';
    }
}

if (!function_exists('billing_provider_new_checkout_allowed')) {
    function billing_provider_new_checkout_allowed(): bool
    {
        $mode = billing_provider_payment_mode();
        if ($mode === 'test') return true;
        if ($mode === 'live') return billing_provider_live_payments_enabled();
        return false;
    }
}

if (!function_exists('billing_provider_assert_new_checkout_allowed')) {
    function billing_provider_assert_new_checkout_allowed(): string
    {
        $mode = billing_provider_payment_mode();
        if ($mode === 'disabled') {
            throw new RuntimeException('Billing payment checkout is disabled by deployment policy.');
        }
        if ($mode === 'live' && !billing_provider_live_payments_enabled()) {
            throw new RuntimeException('Live billing checkout requires BILLING_LIVE_PAYMENTS_ENABLED=1.');
        }
        return $mode;
    }
}

if (!function_exists('billing_provider_public_url_status')) {
    function billing_provider_public_url_status(): array
    {
        try {
            $url = billing_route_public_base_url();
            return ['configured' => true, 'url' => $url, 'error' => null];
        } catch (Throwable $e) {
            return ['configured' => false, 'url' => null, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('billing_provider_readiness_status')) {
    function billing_provider_readiness_status(string $provider, bool $includeWebhook = true): array
    {
        $provider = billing_provider_selection_normalize($provider);
        $providerStatus = billing_provider_selection_status($provider, $includeWebhook);
        $public = billing_provider_public_url_status();

        try {
            $mode = billing_provider_payment_mode();
            $modeValid = true;
            $modeError = null;
        } catch (Throwable $e) {
            $mode = null;
            $modeValid = false;
            $modeError = $e->getMessage();
        }

        $liveOptIn = billing_provider_live_payments_enabled();
        $checkoutAllowed = false;
        if ($modeValid) {
            $checkoutAllowed = $mode === 'test' || ($mode === 'live' && $liveOptIn);
        }

        return [
            'provider' => $provider,
            'label' => (string)$providerStatus['label'],
            'mode' => $mode,
            'mode_valid' => $modeValid,
            'mode_error' => $modeError,
            'live_opt_in' => $liveOptIn,
            'provider_configured' => $providerStatus['configured'] === true,
            'required_env' => $providerStatus['required_env'],
            'missing_env' => $providerStatus['missing_env'],
            'public_url_configured' => $public['configured'] === true,
            'public_url' => $public['url'],
            'public_url_error' => $public['error'],
            'new_checkout_allowed' => $checkoutAllowed,
            'ready' => $modeValid
                && $checkoutAllowed
                && $providerStatus['configured'] === true
                && $public['configured'] === true,
        ];
    }
}
