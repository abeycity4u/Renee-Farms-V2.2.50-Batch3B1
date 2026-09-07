<?php
/**
 * V2.3 Billing Stage 2B provider selection/configuration contract.
 *
 * Commercial decision:
 * - Paystack is the primary payment provider;
 * - Flutterwave is the secondary payment provider;
 * - provider credentials live only in deployment environment variables;
 * - readiness/status helpers never expose credential values;
 * - provider fallback is never automatic after a checkout attempt starts.
 *
 * This file does not register network adapters, initialize checkout, charge a
 * customer, verify a webhook, or mutate subscription/entitlement state.
 */

if (!function_exists('billing_provider_selection_catalog')) {
    function billing_provider_selection_catalog(): array
    {
        return [
            'paystack' => [
                'label' => 'Paystack',
                'priority' => 1,
                'role' => 'primary',
                'required_env' => [
                    'PAYSTACK_SECRET_KEY',
                ],
                'webhook_env' => [
                    // Paystack webhook verification uses the server secret.
                    'PAYSTACK_SECRET_KEY',
                ],
            ],
            'flutterwave' => [
                'label' => 'Flutterwave',
                'priority' => 2,
                'role' => 'secondary',
                'required_env' => [
                    'FLUTTERWAVE_SECRET_KEY',
                ],
                'webhook_env' => [
                    // Deployment convention for the webhook verification hash.
                    'FLUTTERWAVE_WEBHOOK_HASH',
                ],
            ],
        ];
    }
}

if (!function_exists('billing_provider_selection_codes')) {
    function billing_provider_selection_codes(): array
    {
        $catalog = billing_provider_selection_catalog();
        uasort($catalog, static function (array $a, array $b): int {
            return ((int)($a['priority'] ?? 999)) <=> ((int)($b['priority'] ?? 999));
        });
        return array_keys($catalog);
    }
}

if (!function_exists('billing_provider_selection_primary')) {
    function billing_provider_selection_primary(): string
    {
        return 'paystack';
    }
}

if (!function_exists('billing_provider_selection_secondary')) {
    function billing_provider_selection_secondary(): array
    {
        return ['flutterwave'];
    }
}

if (!function_exists('billing_provider_selection_normalize')) {
    function billing_provider_selection_normalize(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (!array_key_exists($provider, billing_provider_selection_catalog())) {
            throw new InvalidArgumentException('Unsupported billing provider.');
        }
        return $provider;
    }
}

if (!function_exists('billing_provider_selection_definition')) {
    function billing_provider_selection_definition(string $provider): array
    {
        $provider = billing_provider_selection_normalize($provider);
        return billing_provider_selection_catalog()[$provider];
    }
}

if (!function_exists('billing_provider_selection_env_value')) {
    function billing_provider_selection_env_value(string $name): ?string
    {
        $name = trim($name);
        if ($name === '' || !preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Invalid billing environment variable name.');
        }

        $value = getenv($name);
        if ($value === false && array_key_exists($name, $_ENV)) $value = $_ENV[$name];
        if ($value === false && array_key_exists($name, $_SERVER)) $value = $_SERVER[$name];
        if ($value === false || $value === null) return null;

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}

if (!function_exists('billing_provider_selection_required_env')) {
    function billing_provider_selection_required_env(string $provider, bool $includeWebhook = true): array
    {
        $definition = billing_provider_selection_definition($provider);
        $names = is_array($definition['required_env'] ?? null) ? $definition['required_env'] : [];
        if ($includeWebhook) {
            $names = array_merge(
                $names,
                is_array($definition['webhook_env'] ?? null) ? $definition['webhook_env'] : []
            );
        }
        $names = array_values(array_unique(array_map('strval', $names)));
        sort($names, SORT_STRING);
        return $names;
    }
}

if (!function_exists('billing_provider_selection_status')) {
    function billing_provider_selection_status(string $provider, bool $includeWebhook = true): array
    {
        $provider = billing_provider_selection_normalize($provider);
        $definition = billing_provider_selection_definition($provider);
        $required = billing_provider_selection_required_env($provider, $includeWebhook);
        $missing = [];

        foreach ($required as $name) {
            if (billing_provider_selection_env_value($name) === null) $missing[] = $name;
        }

        return [
            'provider' => $provider,
            'label' => (string)($definition['label'] ?? ucfirst($provider)),
            'priority' => (int)($definition['priority'] ?? 999),
            'role' => (string)($definition['role'] ?? 'secondary'),
            'configured' => $missing === [],
            'required_env' => $required,
            'missing_env' => $missing,
        ];
    }
}

if (!function_exists('billing_provider_selection_configured_codes')) {
    function billing_provider_selection_configured_codes(bool $includeWebhook = true): array
    {
        $configured = [];
        foreach (billing_provider_selection_codes() as $provider) {
            $status = billing_provider_selection_status($provider, $includeWebhook);
            if ($status['configured'] === true) $configured[] = $provider;
        }
        return $configured;
    }
}

if (!function_exists('billing_provider_selection_assert_configured')) {
    function billing_provider_selection_assert_configured(string $provider, bool $includeWebhook = true): array
    {
        $status = billing_provider_selection_status($provider, $includeWebhook);
        if ($status['configured'] !== true) {
            throw new RuntimeException(
                $status['label'] . ' billing is not configured. Missing deployment environment: '
                . implode(', ', $status['missing_env']) . '.'
            );
        }
        return $status;
    }
}

if (!function_exists('billing_provider_selection_checkout_options')) {
    function billing_provider_selection_checkout_options(): array
    {
        $options = [];
        foreach (billing_provider_selection_codes() as $provider) {
            $status = billing_provider_selection_status($provider, false);
            $options[] = [
                'provider' => $provider,
                'label' => $status['label'],
                'priority' => $status['priority'],
                'role' => $status['role'],
                'available' => $status['configured'],
            ];
        }
        return $options;
    }
}

if (!function_exists('billing_provider_selection_resolve_checkout')) {
    function billing_provider_selection_resolve_checkout(?string $requestedProvider = null): string
    {
        if ($requestedProvider !== null && trim($requestedProvider) !== '') {
            $provider = billing_provider_selection_normalize($requestedProvider);
            billing_provider_selection_assert_configured($provider, false);
            return $provider;
        }

        foreach (billing_provider_selection_codes() as $provider) {
            $status = billing_provider_selection_status($provider, false);
            if ($status['configured'] === true) return $provider;
        }

        throw new RuntimeException('No billing payment provider is configured for checkout.');
    }
}

if (!function_exists('billing_provider_selection_no_automatic_fallback')) {
    function billing_provider_selection_no_automatic_fallback(): bool
    {
        // Once a provider reference/payment attempt exists, any switch to another
        // provider must create a new explicit attempt. Never retry a charge across
        // providers behind the user's back.
        return true;
    }
}
