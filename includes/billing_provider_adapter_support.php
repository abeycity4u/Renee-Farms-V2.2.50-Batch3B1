<?php
/**
 * Shared helpers for V2.3 Billing Stage 2E provider adapters.
 * No provider credential is persisted or logged here.
 */

if (!function_exists('billing_adapter_secret')) {
    function billing_adapter_secret(string $secret, string $label): string
    {
        $secret = trim($secret);
        if ($secret === '' || strlen($secret) > 500 || preg_match('/[\x00-\x20\x7F]/', $secret)) {
            throw new InvalidArgumentException($label . ' secret is not configured correctly.');
        }
        return $secret;
    }
}

if (!function_exists('billing_adapter_decimal_to_minor')) {
    function billing_adapter_decimal_to_minor($amount): int
    {
        if (function_exists('billing_pricing_decimal_to_minor')) {
            return billing_pricing_decimal_to_minor($amount);
        }
        if (is_int($amount)) $amount = (string)$amount;
        if (!is_string($amount)) throw new InvalidArgumentException('Billing amount must be an exact decimal string.');
        $amount = trim($amount);
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $match)) {
            throw new InvalidArgumentException('Billing amount is invalid.');
        }
        $whole = (int)$match[1];
        $fraction = str_pad((string)($match[2] ?? ''), 2, '0', STR_PAD_RIGHT);
        $minor = ($whole * 100) + (int)$fraction;
        if ($minor < 1) throw new InvalidArgumentException('Billing amount must be greater than zero.');
        return $minor;
    }
}

if (!function_exists('billing_adapter_minor_to_decimal')) {
    function billing_adapter_minor_to_decimal($minor): string
    {
        if (is_string($minor) && preg_match('/^\d+$/', $minor)) {
            if (strlen($minor) > 15) throw new RuntimeException('Provider amount is outside the supported range.');
            $minor = (int)$minor;
        }
        if (!is_int($minor) || $minor < 1) {
            throw new RuntimeException('Provider returned an invalid minor-unit amount.');
        }
        return intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('billing_adapter_major_to_decimal')) {
    function billing_adapter_major_to_decimal($amount): string
    {
        if (is_int($amount)) return $amount . '.00';
        if (is_string($amount)) {
            $amount = trim($amount);
            if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $match)) {
                throw new RuntimeException('Provider returned an invalid amount.');
            }
            return ltrim($match[1], '0') === ''
                ? '0.' . str_pad((string)($match[2] ?? ''), 2, '0', STR_PAD_RIGHT)
                : ltrim($match[1], '0') . '.' . str_pad((string)($match[2] ?? ''), 2, '0', STR_PAD_RIGHT);
        }
        if (is_float($amount) && is_finite($amount) && $amount >= 0) {
            $formatted = number_format($amount, 2, '.', '');
            if (!preg_match('/^\d{1,10}\.\d{2}$/', $formatted)) {
                throw new RuntimeException('Provider returned an invalid amount.');
            }
            return $formatted;
        }
        throw new RuntimeException('Provider returned an invalid amount.');
    }
}

if (!function_exists('billing_adapter_header')) {
    function billing_adapter_header(array $headers, string $name): ?string
    {
        $needle = strtolower(trim($name));
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $line = (string)$value;
                $pos = strpos($line, ':');
                if ($pos === false) continue;
                $key = substr($line, 0, $pos);
                $value = substr($line, $pos + 1);
            }
            if (strtolower(trim((string)$key)) !== $needle) continue;
            $value = trim((string)$value);
            if ($value === '' || strlen($value) > 1000 || preg_match('/[\r\n]/', $value)) return null;
            return $value;
        }
        return null;
    }
}

if (!function_exists('billing_adapter_decode_json')) {
    function billing_adapter_decode_json(string $rawPayload): array
    {
        if ($rawPayload === '' || strlen($rawPayload) > 1024 * 1024) {
            throw new RuntimeException('Provider webhook payload is empty or too large.');
        }
        try {
            $decoded = json_decode($rawPayload, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $e) {
            throw new RuntimeException('Provider webhook payload is invalid JSON.', 0, $e);
        }
        if (!is_array($decoded)) throw new RuntimeException('Provider webhook payload must be a JSON object.');
        return $decoded;
    }
}

if (!function_exists('billing_adapter_event_id')) {
    function billing_adapter_event_id(string $provider, string $eventType, $providerId, string $rawPayload): string
    {
        $provider = strtolower(trim($provider));
        $eventType = trim($eventType);
        $providerId = trim((string)$providerId);
        $candidate = $provider . ':' . $eventType . ':' . $providerId;
        if ($providerId !== '' && strlen($candidate) <= 150 && !preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
            return $candidate;
        }
        return $provider . ':sha256:' . hash('sha256', $rawPayload);
    }
}

if (!function_exists('billing_adapter_transport_call')) {
    function billing_adapter_transport_call(callable $transport, string $method, string $url, array $headers, ?array $payload = null): array
    {
        $result = $transport($method, $url, $headers, $payload);
        if (!is_array($result) || !is_array($result['json'] ?? null)) {
            throw new RuntimeException('Billing provider transport returned an invalid response envelope.');
        }
        return $result;
    }
}
