<?php
/**
 * V2.3 Billing Stage 2A payment-provider adapter contract.
 *
 * Contract:
 * - provider adapters may initialize checkout, verify a payment, and verify a
 *   webhook signature/event;
 * - this central contract normalizes provider facts before any later billing
 *   application layer can consume them;
 * - amount/currency for checkout come only from a server-authoritative priced
 *   quote produced by billing_pricing_build_payment_quote();
 * - adapters never grant entitlements or write subscription history;
 * - no provider is registered by default, so provider operations fail closed.
 *
 * This file contains no provider SDK, credential, endpoint or network call.
 */

if (!interface_exists('BillingProviderAdapterInterface')) {
    interface BillingProviderAdapterInterface
    {
        public function code(): string;

        /**
         * @param array $request Canonical server-side checkout request.
         * @return array Provider-specific initialization result.
         */
        public function initializeCheckout(array $request): array;

        /**
         * @return array Provider-specific verified payment result.
         */
        public function verifyPayment(string $providerReference): array;

        /**
         * Verify signature/authenticity and normalize the provider event envelope.
         * Raw payload may be inspected in memory but must not be persisted here.
         *
         * @param array $headers Request headers normalized by the route adapter.
         * @return array Provider-specific verified webhook result.
         */
        public function verifyWebhook(string $rawPayload, array $headers): array;
    }
}

if (!function_exists('billing_provider_normalize_code')) {
    function billing_provider_normalize_code(string $provider): string
    {
        if (function_exists('billing_payment_normalize_provider')) {
            return billing_payment_normalize_provider($provider);
        }
        $provider = strtolower(trim($provider));
        if ($provider === '' || strlen($provider) > 40 || !preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $provider)) {
            throw new InvalidArgumentException('A valid billing provider code is required.');
        }
        return $provider;
    }
}

if (!function_exists('billing_provider_normalize_reference')) {
    function billing_provider_normalize_reference(string $reference): string
    {
        if (function_exists('billing_payment_normalize_reference')) {
            return billing_payment_normalize_reference($reference);
        }
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 150 || preg_match('/[\x00-\x1F\x7F]/', $reference)) {
            throw new InvalidArgumentException('A valid provider reference is required.');
        }
        return $reference;
    }
}

if (!function_exists('billing_provider_normalize_amount')) {
    function billing_provider_normalize_amount($amount): string
    {
        if (function_exists('billing_payment_normalize_amount')) {
            return billing_payment_normalize_amount($amount);
        }
        if (is_int($amount)) $amount = (string)$amount;
        if (!is_string($amount)) throw new InvalidArgumentException('Billing amount must be an exact decimal value.');
        $amount = trim($amount);
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $match)) {
            throw new InvalidArgumentException('Billing amount is not a valid DECIMAL(12,2) value.');
        }
        $whole = ltrim($match[1], '0');
        if ($whole === '') $whole = '0';
        $fraction = str_pad((string)($match[2] ?? ''), 2, '0', STR_PAD_RIGHT);
        $normalized = $whole . '.' . $fraction;
        if ($normalized === '0.00') throw new InvalidArgumentException('Billing amount must be greater than zero.');
        return $normalized;
    }
}

if (!function_exists('billing_provider_normalize_currency')) {
    function billing_provider_normalize_currency(string $currency): string
    {
        if (function_exists('billing_payment_normalize_currency')) {
            return billing_payment_normalize_currency($currency);
        }
        $currency = strtoupper(trim($currency));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Billing currency must be a three-letter code.');
        }
        return $currency;
    }
}

if (!function_exists('billing_provider_registry_state')) {
    function &billing_provider_registry_state(): array
    {
        static $registry = [];
        return $registry;
    }
}

if (!function_exists('billing_provider_register_adapter')) {
    function billing_provider_register_adapter(BillingProviderAdapterInterface $adapter): void
    {
        $code = billing_provider_normalize_code($adapter->code());
        $registry =& billing_provider_registry_state();
        if (isset($registry[$code]) && $registry[$code] !== $adapter) {
            throw new RuntimeException('A billing provider adapter is already registered for ' . $code . '.');
        }
        $registry[$code] = $adapter;
    }
}

if (!function_exists('billing_provider_registered_codes')) {
    function billing_provider_registered_codes(): array
    {
        $registry =& billing_provider_registry_state();
        $codes = array_keys($registry);
        sort($codes, SORT_STRING);
        return $codes;
    }
}

if (!function_exists('billing_provider_adapter')) {
    function billing_provider_adapter(string $provider): BillingProviderAdapterInterface
    {
        $provider = billing_provider_normalize_code($provider);
        $registry =& billing_provider_registry_state();
        $adapter = $registry[$provider] ?? null;
        if (!$adapter instanceof BillingProviderAdapterInterface) {
            throw new RuntimeException('Billing provider adapter is not configured for ' . $provider . '.');
        }
        return $adapter;
    }
}

if (!function_exists('billing_provider_optional_identifier')) {
    function billing_provider_optional_identifier($value, int $maxLength = 150): ?string
    {
        if ($value === null) return null;
        $value = trim((string)$value);
        if ($value === '') return null;
        if (strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new RuntimeException('Provider returned an invalid identifier.');
        }
        return $value;
    }
}

if (!function_exists('billing_provider_normalize_checkout_result')) {
    function billing_provider_normalize_checkout_result(string $provider, array $result): array
    {
        $provider = billing_provider_normalize_code($provider);
        $reference = billing_provider_normalize_reference((string)($result['provider_reference'] ?? ''));
        $checkoutUrl = trim((string)($result['checkout_url'] ?? ''));
        $parts = $checkoutUrl !== '' ? parse_url($checkoutUrl) : false;
        if ($parts === false || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('Provider returned an invalid HTTPS checkout URL.');
        }

        return [
            'provider' => $provider,
            'provider_reference' => $reference,
            'checkout_url' => $checkoutUrl,
            'provider_transaction_id' => billing_provider_optional_identifier($result['provider_transaction_id'] ?? null),
            'provider_subscription_id' => billing_provider_optional_identifier($result['provider_subscription_id'] ?? null),
        ];
    }
}

if (!function_exists('billing_provider_normalize_payment_status')) {
    function billing_provider_normalize_payment_status(string $status): string
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['pending', 'paid', 'failed', 'cancelled', 'refunded'], true)) {
            throw new RuntimeException('Provider returned an unsupported payment status.');
        }
        return $status;
    }
}

if (!function_exists('billing_provider_normalize_payment_result')) {
    function billing_provider_normalize_payment_result(string $provider, array $result): array
    {
        $provider = billing_provider_normalize_code($provider);
        $verified = filter_var($result['verified'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($verified === null) throw new RuntimeException('Provider verification result must include a boolean verified flag.');

        $status = billing_provider_normalize_payment_status((string)($result['status'] ?? ''));
        if ($status === 'paid' && $verified !== true) {
            throw new RuntimeException('An unverified provider result cannot be normalized as paid.');
        }

        return [
            'provider' => $provider,
            'verified' => $verified,
            'status' => $status,
            'provider_reference' => billing_provider_normalize_reference((string)($result['provider_reference'] ?? '')),
            'amount' => billing_provider_normalize_amount($result['amount'] ?? null),
            'currency' => billing_provider_normalize_currency((string)($result['currency'] ?? '')),
            'provider_transaction_id' => billing_provider_optional_identifier($result['provider_transaction_id'] ?? null),
            'provider_subscription_id' => billing_provider_optional_identifier($result['provider_subscription_id'] ?? null),
            'paid_at' => billing_provider_optional_identifier($result['paid_at'] ?? null, 40),
            'failure_code' => billing_provider_optional_identifier($result['failure_code'] ?? null, 80),
        ];
    }
}

if (!function_exists('billing_provider_normalize_webhook_result')) {
    function billing_provider_normalize_webhook_result(
        string $provider,
        string $rawPayload,
        array $result
    ): array {
        $provider = billing_provider_normalize_code($provider);
        $verifiedSignature = filter_var(
            $result['verified_signature'] ?? null,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        if ($verifiedSignature !== true) {
            throw new RuntimeException('Provider webhook signature could not be verified.');
        }

        $eventId = billing_provider_normalize_reference((string)($result['provider_event_id'] ?? ''));
        $eventType = trim((string)($result['event_type'] ?? ''));
        if ($eventType === '' || strlen($eventType) > 100 || preg_match('/[\x00-\x1F\x7F]/', $eventType)) {
            throw new RuntimeException('Provider returned an invalid webhook event type.');
        }

        $reference = billing_provider_optional_identifier($result['provider_reference'] ?? null);
        $paymentStatus = $result['payment_status'] ?? null;
        $paymentStatus = ($paymentStatus === null || trim((string)$paymentStatus) === '')
            ? null
            : billing_provider_normalize_payment_status((string)$paymentStatus);

        return [
            'provider' => $provider,
            'verified_signature' => true,
            'provider_event_id' => $eventId,
            'event_type' => $eventType,
            'provider_reference' => $reference,
            'payment_status' => $paymentStatus,
            // Persist only this identity hash through Stage 1 event storage.
            'payload_hash' => hash('sha256', $rawPayload),
        ];
    }
}

if (!function_exists('billing_provider_checkout_request')) {
    function billing_provider_checkout_request(
        string $providerReference,
        array $pricedQuote,
        array $context = []
    ): array {
        $providerReference = billing_provider_normalize_reference($providerReference);
        $pricing = $pricedQuote['pricing'] ?? null;
        $paymentQuote = $pricedQuote['payment_quote'] ?? null;
        if (!is_array($pricing) || !is_array($paymentQuote)) {
            throw new InvalidArgumentException('A server-authoritative priced payment quote is required.');
        }

        $quote = $paymentQuote['quote'] ?? null;
        $quoteHash = trim((string)($paymentQuote['quote_hash'] ?? ''));
        $pricingHash = trim((string)($pricing['pricing_hash'] ?? ''));
        $pricingVersion = trim((string)($pricing['pricing_version'] ?? ''));
        if (!is_array($quote)
            || !preg_match('/^[a-f0-9]{64}$/', $quoteHash)
            || !preg_match('/^[a-f0-9]{64}$/', $pricingHash)
            || $pricingVersion === '') {
            throw new RuntimeException('Priced payment quote is missing canonical pricing/quote identity.');
        }

        // Cross-check the amount/currency copied from pricing into the frozen quote.
        $amount = billing_provider_normalize_amount($pricing['amount'] ?? null);
        $currency = billing_provider_normalize_currency((string)($pricing['currency'] ?? ''));
        if (!hash_equals($amount, billing_provider_normalize_amount($quote['amount'] ?? null))
            || !hash_equals($currency, billing_provider_normalize_currency((string)($quote['currency'] ?? '')))) {
            throw new RuntimeException('Priced quote amount/currency does not match the frozen payment quote.');
        }

        return [
            'provider_reference' => $providerReference,
            'amount' => $amount,
            'currency' => $currency,
            'plan_code' => (string)($quote['plan_code'] ?? ''),
            'billing_interval' => (string)($quote['billing_interval'] ?? ''),
            'modules' => is_array($quote['modules'] ?? null) ? $quote['modules'] : [],
            'seat_addons' => is_array($quote['seat_addons'] ?? null) ? $quote['seat_addons'] : [],
            'pricing_version' => $pricingVersion,
            'pricing_hash' => $pricingHash,
            'quote_hash' => $quoteHash,
            'context' => $context,
        ];
    }
}

if (!function_exists('billing_provider_initialize_checkout')) {
    function billing_provider_initialize_checkout(
        string $provider,
        string $providerReference,
        array $pricedQuote,
        array $context = []
    ): array {
        $provider = billing_provider_normalize_code($provider);
        $adapter = billing_provider_adapter($provider);
        $request = billing_provider_checkout_request($providerReference, $pricedQuote, $context);
        $result = $adapter->initializeCheckout($request);
        $normalized = billing_provider_normalize_checkout_result($provider, $result);
        if (!hash_equals($providerReference, $normalized['provider_reference'])) {
            throw new RuntimeException('Provider initialization returned a different provider reference.');
        }
        return $normalized;
    }
}

if (!function_exists('billing_provider_verify_payment')) {
    function billing_provider_verify_payment(string $provider, string $providerReference): array
    {
        $provider = billing_provider_normalize_code($provider);
        $providerReference = billing_provider_normalize_reference($providerReference);
        $adapter = billing_provider_adapter($provider);
        $normalized = billing_provider_normalize_payment_result(
            $provider,
            $adapter->verifyPayment($providerReference)
        );
        if (!hash_equals($providerReference, $normalized['provider_reference'])) {
            throw new RuntimeException('Provider verification returned a different provider reference.');
        }
        return $normalized;
    }
}

if (!function_exists('billing_provider_verify_webhook')) {
    function billing_provider_verify_webhook(
        string $provider,
        string $rawPayload,
        array $headers = []
    ): array {
        $provider = billing_provider_normalize_code($provider);
        $adapter = billing_provider_adapter($provider);
        return billing_provider_normalize_webhook_result(
            $provider,
            $rawPayload,
            $adapter->verifyWebhook($rawPayload, $headers)
        );
    }
}
