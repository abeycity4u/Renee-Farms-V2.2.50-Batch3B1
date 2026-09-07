<?php
/**
 * V2.3 Billing Stage 2E Flutterwave secondary adapter.
 *
 * V3-compatible hosted-payment contract selected for launch. Network behavior
 * is isolated behind an injectable transport callable for offline verification.
 */

if (!interface_exists('BillingProviderAdapterInterface')) {
    require_once __DIR__ . '/billing_provider_contract.php';
}
require_once __DIR__ . '/billing_provider_context.php';
require_once __DIR__ . '/billing_provider_adapter_support.php';
require_once __DIR__ . '/billing_http_transport.php';

if (!class_exists('FlutterwaveBillingProviderAdapter')) {
    final class FlutterwaveBillingProviderAdapter implements BillingProviderAdapterInterface
    {
        private string $secretKey;
        private string $webhookHash;
        /** @var callable */
        private $transport;

        public function __construct(string $secretKey, string $webhookHash, ?callable $transport = null)
        {
            $this->secretKey = billing_adapter_secret($secretKey, 'Flutterwave');
            $this->webhookHash = billing_adapter_secret($webhookHash, 'Flutterwave webhook');
            $this->transport = $transport ?? 'billing_http_json_request';
        }

        public function code(): string
        {
            return 'flutterwave';
        }

        private function headers(): array
        {
            return [
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
        }

        private function mapStatus(string $status): string
        {
            $status = strtolower(trim($status));
            return match ($status) {
                'successful', 'success' => 'paid',
                'failed' => 'failed',
                'cancelled', 'canceled' => 'cancelled',
                'refunded', 'reversed' => 'refunded',
                'pending', 'processing' => 'pending',
                default => throw new RuntimeException('Flutterwave returned an unsupported transaction status.'),
            };
        }

        public function initializeCheckout(array $request): array
        {
            $reference = billing_provider_normalize_reference((string)($request['provider_reference'] ?? ''));
            $amount = billing_provider_normalize_amount($request['amount'] ?? null);
            $currency = billing_provider_normalize_currency((string)($request['currency'] ?? ''));
            $context = billing_provider_sanitize_checkout_context(
                is_array($request['context'] ?? null) ? $request['context'] : []
            );
            $redirectUrl = $context['redirect_url'] ?? $context['callback_url'] ?? null;
            if ($redirectUrl === null) {
                throw new InvalidArgumentException('Flutterwave checkout requires an HTTPS redirect URL.');
            }

            $customer = ['email' => $context['customer_email']];
            if (!empty($context['customer_name'])) $customer['name'] = $context['customer_name'];

            $payload = [
                'tx_ref' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'redirect_url' => $redirectUrl,
                'customer' => $customer,
            ];

            $response = billing_adapter_transport_call(
                $this->transport,
                'POST',
                'https://api.flutterwave.com/v3/payments',
                $this->headers(),
                $payload
            );
            $json = $response['json'];
            if (strtolower(trim((string)($json['status'] ?? ''))) !== 'success'
                || !is_array($json['data'] ?? null)) {
                throw new RuntimeException('Flutterwave checkout initialization was not accepted.');
            }
            $data = $json['data'];

            return [
                'provider_reference' => $reference,
                'checkout_url' => (string)($data['link'] ?? ''),
                'provider_transaction_id' => null,
                'provider_subscription_id' => null,
            ];
        }

        public function verifyPayment(string $providerReference): array
        {
            $providerReference = billing_provider_normalize_reference($providerReference);
            $response = billing_adapter_transport_call(
                $this->transport,
                'GET',
                'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=' . rawurlencode($providerReference),
                $this->headers(),
                null
            );
            $json = $response['json'];
            if (strtolower(trim((string)($json['status'] ?? ''))) !== 'success'
                || !is_array($json['data'] ?? null)) {
                throw new RuntimeException('Flutterwave payment verification was not accepted.');
            }
            $data = $json['data'];
            $status = $this->mapStatus((string)($data['status'] ?? ''));
            $providerId = trim((string)($data['id'] ?? ''));
            $failure = null;
            if ($status !== 'paid') {
                $failure = trim((string)($data['processor_response'] ?? $data['status'] ?? ''));
                if ($failure === '') $failure = null;
            }

            return [
                'verified' => true,
                'status' => $status,
                'provider_reference' => (string)($data['tx_ref'] ?? $providerReference),
                'amount' => billing_adapter_major_to_decimal($data['amount'] ?? null),
                'currency' => strtoupper(trim((string)($data['currency'] ?? ''))),
                'provider_transaction_id' => $providerId === '' ? null : $providerId,
                'provider_subscription_id' => null,
                'paid_at' => $data['created_at'] ?? null,
                'failure_code' => $failure,
            ];
        }

        public function verifyWebhook(string $rawPayload, array $headers): array
        {
            $signature = billing_adapter_header($headers, 'verif-hash');
            if ($signature === null || !hash_equals($this->webhookHash, $signature)) {
                throw new RuntimeException('Flutterwave webhook verification hash is invalid.');
            }

            $payload = billing_adapter_decode_json($rawPayload);
            $eventType = trim((string)($payload['event'] ?? $payload['type'] ?? ''));
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            if ($eventType === '' || !$data) {
                throw new RuntimeException('Flutterwave webhook event is incomplete.');
            }

            $statusRaw = trim((string)($data['status'] ?? ''));
            $paymentStatus = $statusRaw === '' ? null : $this->mapStatus($statusRaw);
            $reference = trim((string)($data['tx_ref'] ?? ''));

            return [
                'verified_signature' => true,
                'provider_event_id' => billing_adapter_event_id('flutterwave', $eventType, $data['id'] ?? null, $rawPayload),
                'event_type' => $eventType,
                'provider_reference' => $reference === '' ? null : $reference,
                'payment_status' => $paymentStatus,
            ];
        }
    }
}
