<?php
/**
 * V2.3 Billing Stage 2I provider runtime-readiness policy.
 *
 * Provider credentials being present is not enough to permit a new network
 * checkout. New checkout is fail-closed unless BILLING_PAYMENT_MODE explicitly
 * enables test/live operation. Live mode additionally requires an explicit
 * BILLING_LIVE_PAYMENTS_ENABLED=1 opt-in.
 *
 * Test/live credentials are stored in separate deployment variable names so
 * test-mode routes cannot read the live credential slot and vice versa. Status
 * helpers never expose credential values. This file performs no network or DB I/O.
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

if (!function_exists('billing_provider_mode_env_catalog')) {
    function billing_provider_mode_env_catalog(): array
    {
        return [
            'paystack' => [
                'test' => [
                    'secret' => 'PAYSTACK_TEST_SECRET_KEY',
                    'webhook' => 'PAYSTACK_TEST_SECRET_KEY',
                ],
                'live' => [
                    'secret' => 'PAYSTACK_LIVE_SECRET_KEY',
                    'webhook' => 'PAYSTACK_LIVE_SECRET_KEY',
                ],
            ],
            'flutterwave' => [
                'test' => [
                    'secret' => 'FLUTTERWAVE_TEST_SECRET_KEY',
                    'webhook' => 'FLUTTERWAVE_TEST_WEBHOOK_HASH',
                ],
                'live' => [
                    'secret' => 'FLUTTERWAVE_LIVE_SECRET_KEY',
                    'webhook' => 'FLUTTERWAVE_LIVE_WEBHOOK_HASH',
                ],
            ],
        ];
    }
}

if (!function_exists('billing_provider_mode_env_definition')) {
    function billing_provider_mode_env_definition(string $provider, string $mode): array
    {
        $provider = billing_provider_selection_normalize($provider);
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['test', 'live'], true)) {
            throw new InvalidArgumentException('Provider credentials require test or live billing mode.');
        }
        $catalog = billing_provider_mode_env_catalog();
        $definition = $catalog[$provider][$mode] ?? null;
        if (!is_array($definition)) {
            throw new RuntimeException('Billing provider mode credential mapping is unavailable.');
        }
        return $definition;
    }
}

if (!function_exists('billing_provider_mode_required_env')) {
    function billing_provider_mode_required_env(string $provider, string $mode, bool $includeWebhook = true): array
    {
        $definition = billing_provider_mode_env_definition($provider, $mode);
        $required = [(string)$definition['secret']];
        if ($includeWebhook) $required[] = (string)$definition['webhook'];
        $required = array_values(array_unique($required));
        sort($required, SORT_STRING);
        return $required;
    }
}

if (!function_exists('billing_provider_mode_status')) {
    function billing_provider_mode_status(string $provider, string $mode, bool $includeWebhook = true): array
    {
        $provider = billing_provider_selection_normalize($provider);
        $definition = billing_provider_selection_definition($provider);
        $required = billing_provider_mode_required_env($provider, $mode, $includeWebhook);
        $missing = [];
        foreach ($required as $name) {
            if (billing_provider_readiness_env_value($name) === null) $missing[] = $name;
        }
        return [
            'provider' => $provider,
            'label' => (string)($definition['label'] ?? ucfirst($provider)),
            'mode' => strtolower(trim($mode)),
            'configured' => $missing === [],
            'required_env' => $required,
            'missing_env' => $missing,
        ];
    }
}

if (!function_exists('billing_provider_runtime_credentials')) {
    function billing_provider_runtime_credentials(string $provider, bool $includeWebhook = true): array
    {
        $provider = billing_provider_selection_normalize($provider);
        $mode = billing_provider_payment_mode();
        if (!in_array($mode, ['test', 'live'], true)) {
            throw new RuntimeException('Billing provider credentials are unavailable while payment mode is disabled.');
        }
        if ($mode === 'live' && !billing_provider_live_payments_enabled()) {
            throw new RuntimeException('Live billing provider credentials are blocked until live payments are explicitly enabled.');
        }

        $status = billing_provider_mode_status($provider, $mode, $includeWebhook);
        if ($status['configured'] !== true) {
            throw new RuntimeException(
                $status['label'] . ' ' . $mode . ' billing is not configured. Missing deployment environment: '
                . implode(', ', $status['missing_env']) . '.'
            );
        }
        $definition = billing_provider_mode_env_definition($provider, $mode);
        $secret = billing_provider_readiness_env_value((string)$definition['secret']);
        $webhook = $includeWebhook
            ? billing_provider_readiness_env_value((string)$definition['webhook'])
            : null;
        if ($secret === null || ($includeWebhook && $webhook === null)) {
            throw new RuntimeException('Billing provider credentials became unavailable during registration.');
        }
        return [
            'provider' => $provider,
            'mode' => $mode,
            'secret' => $secret,
            'webhook_hash' => $webhook,
        ];
    }
}

if (!function_exists('billing_provider_readiness_resolve_checkout')) {
    function billing_provider_readiness_resolve_checkout(?string $requestedProvider = null): string
    {
        $mode = billing_provider_assert_new_checkout_allowed();
        if ($requestedProvider !== null && trim($requestedProvider) !== '') {
            $provider = billing_provider_selection_normalize($requestedProvider);
            $status = billing_provider_mode_status($provider, $mode, false);
            if ($status['configured'] !== true) {
                throw new RuntimeException(
                    $status['label'] . ' ' . $mode . ' checkout is not configured. Missing deployment environment: '
                    . implode(', ', $status['missing_env']) . '.'
                );
            }
            return $provider;
        }

        foreach (billing_provider_selection_codes() as $provider) {
            $status = billing_provider_mode_status($provider, $mode, false);
            if ($status['configured'] === true) return $provider;
        }
        throw new RuntimeException('No billing payment provider is configured for ' . $mode . ' checkout.');
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
        $definition = billing_provider_selection_definition($provider);
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
        $providerStatus = null;
        if ($modeValid) {
            $checkoutAllowed = $mode === 'test' || ($mode === 'live' && $liveOptIn);
            if (in_array($mode, ['test', 'live'], true)) {
                $providerStatus = billing_provider_mode_status($provider, $mode, $includeWebhook);
            }
        }
        $providerConfigured = is_array($providerStatus) && ($providerStatus['configured'] ?? false) === true;

        return [
            'provider' => $provider,
            'label' => (string)($definition['label'] ?? ucfirst($provider)),
            'mode' => $mode,
            'mode_valid' => $modeValid,
            'mode_error' => $modeError,
            'live_opt_in' => $liveOptIn,
            'provider_configured' => $providerConfigured,
            'required_env' => is_array($providerStatus) ? $providerStatus['required_env'] : [],
            'missing_env' => is_array($providerStatus) ? $providerStatus['missing_env'] : [],
            'public_url_configured' => $public['configured'] === true,
            'public_url' => $public['url'],
            'public_url_error' => $public['error'],
            'new_checkout_allowed' => $checkoutAllowed,
            'ready' => $modeValid
                && $checkoutAllowed
                && $providerConfigured
                && $public['configured'] === true,
        ];
    }
}
