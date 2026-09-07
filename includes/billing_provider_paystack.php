<?php
/**
 * V2.3 Billing Stage 2E Paystack adapter.
 *
 * Network behavior is isolated behind an injectable transport callable so the
 * adapter can be verified without live credentials or external requests.
 */

if (!interface_exists('BillingProviderAdapterInterface')) {
    require_once __DIR__ . '/billing_provider_contract.php';
}
require_once __DIR__ . '/billing_provider_context.php';
require_once __DIR__ . '/billing_provider_adapter_support.php';
require_once __DIR__ . '/billing_http_transport.php';

if (!class_exists('PaystackBillingProviderAdapter')) {
    final class PaystackBillingProviderAdapter implements BillingProviderAdapterInterface
    {
        private string $secretKey;
        /** @var callable */
        private $transport;

        public function __construct(string $secretKey, ?callable $transport = null)
        {
            $this->secretKey = billing_adapter_secret($secretKey, 'Paystack');
            $this->transport = $transport ?? 'billing_http_json_request';
        }

        public function code(): string
        {
            return 'paystack';
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
                'success' => 'paid',
                'failed' => 'failed',
                'abandoned' => 'cancelled',
                'reversed' => 'refunded',
                'pending', 'ongoing', 'processing', 'queued' => 'pending',
                default => throw new RuntimeException('Paystack returned an unsupported transaction status.'),
            };
        }

        private function subscriptionId(array $data): ?string
        {
            $subscription = $data['subscription'] ?? null;
            if (is_array($subscription)) {
                $code = trim((string)($subscription['subscription_code'] ?? $subscription['code'] ?? ''));
                if ($code !== '') return $code;
            }
            $code = trim((string)($data['subscription_code'] ?? ''));
            return $code === '' ? null : $code;
        }

        public function initializeCheckout(array $request): array
        {
            $reference = billing_provider_normalize_reference((string)($request['provider_reference'] ?? ''));
            $amount = billing_provider_normalize_amount($request['amount'] ?? null);
            $currency = billing_provider_normalize_currency((string)($request['currency'] ?? ''));
            $context = billing_provider_sanitize_checkout_context(
                is_array($request['context'] ?? null) ? $request['context'] : []
            );

            $payload = [
                'email' => $context['customer_email'],
                'amount' => billing_adapter_decimal_to_minor($amount),
                'currency' => $currency,
                'reference' => $reference,
            ];
            if (!empty($context['callback_url'])) {
                $payload['callback_url'] = $context['callback_url'];
            }

            $response = billing_adapter_transport_call(
                $this->transport,
                'POST',
                'https://api.paystack.co/transaction/initialize',
                $this->headers(),
                $payload
            );
            $json = $response['json'];
            if (($json['status'] ?? null) !== true || !is_array($json['data'] ?? null)) {
                throw new RuntimeException('Paystack checkout initialization was not accepted.');
            }
            $data = $json['data'];
            $returnedReference = trim((string)($data['reference'] ?? $reference));
            if ($returnedReference === '') $returnedReference = $reference;

            return [
                'provider_reference' => $returnedReference,
                'checkout_url' => (string)($data['authorization_url'] ?? ''),
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
                'https://api.paystack.co/transaction/verify/' . rawurlencode($providerReference),
                $this->headers(),
                null
            );
            $json = $response['json'];
            if (($json['status'] ?? null) !== true || !is_array($json['data'] ?? null)) {
                throw new RuntimeException('Paystack payment verification was not accepted.');
            }
            $data = $json['data'];
            $status = $this->mapStatus((string)($data['status'] ?? ''));
            $providerId = trim((string)($data['id'] ?? ''));
            $failure = null;
            if ($status !== 'paid') {
                $failure = trim((string)($data['gateway_response'] ?? $data['status'] ?? ''));
                if ($failure === '') $failure = null;
            }

            return [
                'verified' => true,
                'status' => $status,
                'provider_reference' => (string)($data['reference'] ?? $providerReference),
                'amount' => billing_adapter_minor_to_decimal($data['amount'] ?? null),
                'currency' => strtoupper(trim((string)($data['currency'] ?? ''))),
                'provider_transaction_id' => $providerId === '' ? null : $providerId,
                'provider_subscription_id' => $this->subscriptionId($data),
                'paid_at' => $data['paid_at'] ?? $data['paidAt'] ?? null,
                'failure_code' => $failure,
            ];
        }

        public function verifyWebhook(string $rawPayload, array $headers): array
        {
            $signature = billing_adapter_header($headers, 'x-paystack-signature');
            if ($signature === null || !preg_match('/^[a-fA-F0-9]{128}$/', $signature)) {
                throw new RuntimeException('Paystack webhook signature is missing or invalid.');
            }
            $expected = hash_hmac('sha512', $rawPayload, $this->secretKey);
            if (!hash_equals($expected, strtolower($signature))) {
                throw new RuntimeException('Paystack webhook signature is invalid.');
            }

            $payload = billing_adapter_decode_json($rawPayload);
            $eventType = trim((string)($payload['event'] ?? ''));
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            if ($eventType === '' || !$data) {
                throw new RuntimeException('Paystack webhook event is incomplete.');
            }

            $statusRaw = trim((string)($data['status'] ?? ''));
            $paymentStatus = $statusRaw === '' ? null : $this->mapStatus($statusRaw);
            $reference = trim((string)($data['reference'] ?? ''));

            return [
                'verified_signature' => true,
                'provider_event_id' => billing_adapter_event_id('paystack', $eventType, $data['id'] ?? null, $rawPayload),
                'event_type' => $eventType,
                'provider_reference' => $reference === '' ? null : $reference,
                'payment_status' => $paymentStatus,
            ];
        }
    }
}
