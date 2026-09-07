<?php
/**
 * V2.3 Billing Stage 2C launch-currency policy.
 *
 * Commercial decision:
 * - NGN is the only supported launch billing currency;
 * - no automatic FX conversion is performed;
 * - adding another currency later requires an explicit, separately approved
 *   server-side price book rather than converting NGN at a live exchange rate.
 *
 * This file contains no prices, provider credentials, network calls, or writes.
 */

if (!function_exists('billing_currency_policy_primary')) {
    function billing_currency_policy_primary(): string
    {
        return 'NGN';
    }
}

if (!function_exists('billing_currency_policy_supported')) {
    function billing_currency_policy_supported(): array
    {
        return ['NGN'];
    }
}

if (!function_exists('billing_currency_policy_multi_currency_enabled')) {
    function billing_currency_policy_multi_currency_enabled(): bool
    {
        return false;
    }
}

if (!function_exists('billing_currency_policy_automatic_fx_enabled')) {
    function billing_currency_policy_automatic_fx_enabled(): bool
    {
        return false;
    }
}

if (!function_exists('billing_currency_policy_normalize')) {
    function billing_currency_policy_normalize(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Billing currency must be a three-letter code.');
        }
        if (!in_array($currency, billing_currency_policy_supported(), true)) {
            throw new InvalidArgumentException(
                'Billing currency is not supported for the V2.3 launch price book.'
            );
        }
        return $currency;
    }
}

if (!function_exists('billing_currency_policy_assert_price_book')) {
    function billing_currency_policy_assert_price_book(array $book): array
    {
        $currency = billing_currency_policy_normalize((string)($book['currency'] ?? ''));
        if (!hash_equals(billing_currency_policy_primary(), $currency)) {
            throw new RuntimeException('Billing price book currency does not match the launch currency policy.');
        }
        return $book;
    }
}

if (!function_exists('billing_currency_policy_requires_explicit_price_book')) {
    function billing_currency_policy_requires_explicit_price_book(string $currency): bool
    {
        $currency = strtoupper(trim($currency));
        return $currency !== billing_currency_policy_primary();
    }
}
