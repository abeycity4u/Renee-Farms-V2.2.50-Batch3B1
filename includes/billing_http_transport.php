<?php
/**
 * V2.3 Billing Stage 2E HTTPS transport.
 *
 * Security contract:
 * - HTTPS only;
 * - exact provider API host allowlist;
 * - TLS peer/host verification enabled;
 * - redirects disabled;
 * - bounded response body;
 * - provider credentials/request bodies are never written to logs here;
 * - JSON only.
 */

if (!class_exists('BillingHttpResponseException')) {
    final class BillingHttpResponseException extends RuntimeException
    {
        private int $httpStatus;
        private ?array $responseJson;

        public function __construct(int $httpStatus, ?array $responseJson = null)
        {
            if ($httpStatus < 100 || $httpStatus > 599) {
                throw new InvalidArgumentException('Billing HTTP response status is invalid.');
            }

            $this->httpStatus = $httpStatus;
            $this->responseJson = $responseJson;

            parent::__construct('Billing provider returned HTTP ' . $httpStatus . '.');
        }

        public function httpStatus(): int
        {
            return $this->httpStatus;
        }

        public function responseJson(): ?array
        {
            return $this->responseJson;
        }
    }
}

if (!function_exists('billing_http_non_success_exception')) {
    function billing_http_non_success_exception(
        int $status,
        string $responseBody
    ): BillingHttpResponseException {
        $decoded = null;

        if ($responseBody !== '') {
            try {
                $candidate = json_decode(
                    $responseBody,
                    true,
                    64,
                    JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
                );

                if (is_array($candidate)) {
                    $decoded = $candidate;
                }
            } catch (JsonException $e) {
                // Non-2xx provider bodies are optional evidence only.
                // Invalid JSON must not hide the authoritative HTTP status.
            }
        }

        return new BillingHttpResponseException($status, $decoded);
    }
}

if (!function_exists('billing_http_allowed_hosts')) {
    function billing_http_allowed_hosts(): array
    {
        return [
            'api.paystack.co',
            'api.flutterwave.com',
        ];
    }
}

if (!function_exists('billing_http_assert_url')) {
    function billing_http_assert_url(string $url): string
    {
        $url = trim($url);
        $parts = $url !== '' ? parse_url($url) : false;
        if ($parts === false
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Billing provider endpoint must be a valid HTTPS URL.');
        }

        $host = strtolower((string)$parts['host']);
        if (!in_array($host, billing_http_allowed_hosts(), true)) {
            throw new RuntimeException('Billing provider endpoint host is not allowed.');
        }
        if (isset($parts['port']) && (int)$parts['port'] !== 443) {
            throw new RuntimeException('Billing provider endpoint must use the standard HTTPS port.');
        }
        return $url;
    }
}

if (!function_exists('billing_http_normalize_headers')) {
    function billing_http_normalize_headers(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $line = trim((string)$value);
                if ($line === '' || strlen($line) > 4096 || preg_match('/[\r\n]/', $line)) {
                    throw new InvalidArgumentException('Invalid billing HTTP header.');
                }
                $normalized[] = $line;
                continue;
            }

            $name = trim((string)$name);
            $value = trim((string)$value);
            if (!preg_match('/^[A-Za-z0-9-]{1,80}$/', $name)
                || strlen($value) > 4000
                || preg_match('/[\r\n]/', $value)) {
                throw new InvalidArgumentException('Invalid billing HTTP header.');
            }
            $normalized[] = $name . ': ' . $value;
        }
        return $normalized;
    }
}

if (!function_exists('billing_http_json_request')) {
    function billing_http_json_request(
        string $method,
        string $url,
        array $headers = [],
        ?array $payload = null,
        int $timeoutSeconds = 20
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for billing provider communication.');
        }

        $method = strtoupper(trim($method));
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new InvalidArgumentException('Unsupported billing HTTP method.');
        }
        $url = billing_http_assert_url($url);
        if ($timeoutSeconds < 1 || $timeoutSeconds > 30) {
            throw new InvalidArgumentException('Billing HTTP timeout is outside the allowed range.');
        }

        $headers = billing_http_normalize_headers($headers);
        $hasAccept = false;
        $hasContentType = false;
        foreach ($headers as $line) {
            $lower = strtolower($line);
            if (str_starts_with($lower, 'accept:')) $hasAccept = true;
            if (str_starts_with($lower, 'content-type:')) $hasContentType = true;
        }
        if (!$hasAccept) $headers[] = 'Accept: application/json';

        $body = null;
        if ($payload !== null) {
            if ($method !== 'POST') {
                throw new InvalidArgumentException('Billing HTTP payload is only supported for POST requests.');
            }
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (!$hasContentType) $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init();
        if ($ch === false) throw new RuntimeException('Unable to initialize billing HTTP transport.');

        $responseBody = '';
        $maxBytes = 1024 * 1024;
        $write = static function ($curl, string $chunk) use (&$responseBody, $maxBytes): int {
            if (strlen($responseBody) + strlen($chunk) > $maxBytes) return 0;
            $responseBody .= $chunk;
            return strlen($chunk);
        };

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => min(7, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'ReneeFarmsBilling/2.3',
            CURLOPT_WRITEFUNCTION => $write,
        ];
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($ok === false || $errno !== 0) {
            throw new RuntimeException('Billing provider network request failed.');
        }
        if ($status < 100 || $status > 599) {
            throw new RuntimeException('Billing provider returned an invalid HTTP status.');
        }
        if ($status < 200 || $status >= 300) {
            throw billing_http_non_success_exception($status, $responseBody);
        }
        if ($responseBody === '') {
            throw new RuntimeException('Billing provider returned an empty response.');
        }

        try {
            $decoded = json_decode($responseBody, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $e) {
            throw new RuntimeException('Billing provider returned invalid JSON.', 0, $e);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Billing provider returned an invalid JSON object.');
        }

        return [
            'status' => $status,
            'json' => $decoded,
        ];
    }
}
