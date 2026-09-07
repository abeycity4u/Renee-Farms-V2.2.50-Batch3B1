<?php
/**
 * V2.3 Billing Stage 2E checkout-context sanitizer.
 *
 * Only non-pricing customer/callback metadata may reach provider adapters.
 * Billing-sensitive values must come from the frozen server-authoritative quote.
 */

if (!function_exists('billing_provider_context_https_url')) {
    function billing_provider_context_https_url($value, string $field): ?string
    {
        if ($value === null) return null;
        $value = trim((string)$value);
        if ($value === '') return null;
        if (strlen($value) > 1000) {
            throw new InvalidArgumentException($field . ' is too long.');
        }
        $parts = parse_url($value);
        if ($parts === false
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            throw new InvalidArgumentException($field . ' must be a valid HTTPS URL.');
        }
        return $value;
    }
}

if (!function_exists('billing_provider_context_customer_email')) {
    function billing_provider_context_customer_email($value): string
    {
        $value = trim((string)$value);
        if ($value === '' || strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid billing customer email is required.');
        }
        return strtolower($value);
    }
}

if (!function_exists('billing_provider_context_customer_name')) {
    function billing_provider_context_customer_name($value): ?string
    {
        if ($value === null) return null;
        $value = trim((string)$value);
        if ($value === '') return null;
        if (strlen($value) > 160 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('Billing customer name is invalid.');
        }
        return $value;
    }
}

if (!function_exists('billing_provider_context_reserved_keys')) {
    function billing_provider_context_reserved_keys(): array
    {
        return [
            'amount',
            'currency',
            'plan',
            'plan_code',
            'billing_interval',
            'modules',
            'module_bundle',
            'seat_addons',
            'seat_unit_prices',
            'pricing_version',
            'pricing_hash',
            'quote_hash',
            'provider_reference',
            'provider_transaction_id',
            'provider_subscription_id',
        ];
    }
}

if (!function_exists('billing_provider_sanitize_checkout_context')) {
    function billing_provider_sanitize_checkout_context(array $context): array
    {
        foreach (billing_provider_context_reserved_keys() as $reserved) {
            if (array_key_exists($reserved, $context)) {
                throw new InvalidArgumentException('Billing-sensitive checkout context key is not allowed: ' . $reserved . '.');
            }
        }

        $allowed = [
            'customer_email',
            'customer_name',
            'callback_url',
            'redirect_url',
        ];
        foreach ($context as $key => $_value) {
            if (!in_array((string)$key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported billing checkout context key: ' . (string)$key . '.');
            }
        }

        $sanitized = [
            'customer_email' => billing_provider_context_customer_email($context['customer_email'] ?? null),
        ];

        $name = billing_provider_context_customer_name($context['customer_name'] ?? null);
        if ($name !== null) $sanitized['customer_name'] = $name;

        $callback = billing_provider_context_https_url($context['callback_url'] ?? null, 'Billing callback URL');
        if ($callback !== null) $sanitized['callback_url'] = $callback;

        $redirect = billing_provider_context_https_url($context['redirect_url'] ?? null, 'Billing redirect URL');
        if ($redirect !== null) $sanitized['redirect_url'] = $redirect;

        return $sanitized;
    }
}
