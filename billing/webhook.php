<?php
/**
 * V2.3 Billing Stage 2H provider webhook route.
 *
 * Provider-authenticated and session-independent. An authentic webhook never
 * becomes payment authority by itself: the route performs a fresh provider
 * verification against the frozen attempt, then applies a verified paid attempt
 * through the proven exactly-once Stage 2G bridge in the same transaction as
 * the audit update and provider-event processing marker.
 */

// Temporary Stage 2I-B ingress diagnostic. Disabled by default and intentionally
// records no payload, signature value, secret, reference, amount, email, IP or
// customer data. Enable only while diagnosing provider webhook delivery.
$billingWebhookIngressDiagnostic = getenv('BILLING_WEBHOOK_INGRESS_DIAGNOSTIC') === '1';
if ($billingWebhookIngressDiagnostic) {
    $diagnosticProvider = strtolower(trim((string)($_GET['provider'] ?? '')));
    $diagnosticProvider = $diagnosticProvider === 'paystack' ? 'paystack' : 'other';
    $diagnosticMethod = strtoupper(trim((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $diagnosticMethod = in_array($diagnosticMethod, ['GET', 'POST'], true) ? $diagnosticMethod : 'OTHER';
    $signaturePresent = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])
        && trim((string)$_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) !== '';
    error_log(sprintf(
        'BILLING_WEBHOOK_INGRESS provider=%s method=%s paystack_signature=%s',
        $diagnosticProvider,
        $diagnosticMethod,
        $signaturePresent ? 'present' : 'absent'
    ));
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/subscription_plan_catalog.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';
require_once dirname(__DIR__) . '/includes/billing_provider_contract.php';
require_once dirname(__DIR__) . '/includes/billing_provider_selection.php';
require_once dirname(__DIR__) . '/includes/billing_provider_adapters.php';
require_once dirname(__DIR__) . '/includes/billing_route_request.php';
require_once dirname(__DIR__) . '/includes/billing_payment_audit_state.php';
require_once dirname(__DIR__) . '/includes/billing_subscription_application.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
if (!$pdo instanceof PDO || !billing_payment_foundation_ready($pdo)) {
    http_response_code(503);
    exit('Billing service unavailable.');
}

$registeredEventId = null;

try {
    $provider = billing_provider_selection_normalize((string)($_GET['provider'] ?? ''));
    billing_provider_register_configured_adapters($provider);
    $rawPayload = billing_route_raw_body(1048576);
    $headers = billing_route_request_headers();

    // Signature/hash verification happens before any provider event is persisted.
    $eventFact = billing_provider_verify_webhook($provider, $rawPayload, $headers);
    if ($billingWebhookIngressDiagnostic) {
        error_log(
            'BILLING_WEBHOOK_AUTH provider=' . ($provider === 'paystack' ? 'paystack' : 'other')
            . ' signature_verified=yes'
        );
    }
    $providerReference = trim((string)($eventFact['provider_reference'] ?? ''));

    $attempt = null;
    if ($providerReference !== '') {
        $providerReference = billing_payment_normalize_reference($providerReference);
        $attempt = billing_audit_attempt_by_reference($pdo, $provider, $providerReference, null, false);
    }

    $eventRegistration = billing_provider_event_register(
        $pdo,
        $provider,
        (string)$eventFact['provider_event_id'],
        (string)$eventFact['event_type'],
        $rawPayload,
        $attempt ? (int)$attempt['id'] : null
    );
    $registeredEventId = (int)$eventRegistration['id'];
    $eventRow = is_array($eventRegistration['event'] ?? null) ? $eventRegistration['event'] : [];

    if ($eventRow && billing_audit_event_terminal($eventRow)) {
        http_response_code(200);
        exit('OK');
    }

    if ($providerReference === '' || !$attempt) {
        billing_audit_event_mark($pdo, $registeredEventId, 'ignored');
        http_response_code(200);
        exit('OK');
    }

    // Never treat webhook status alone as payment authority. Re-query the provider
    // using the already-frozen attempt reference, then cross-check amount/currency.
    try {
        $verification = billing_provider_verify_payment($provider, $providerReference);
    } catch (Throwable $verificationError) {
        billing_audit_event_mark(
            $pdo,
            $registeredEventId,
            'failed',
            'Provider payment verification could not be completed.'
        );
        http_response_code(503);
        exit('Verification unavailable.');
    }

    $pdo->beginTransaction();
    try {
        $locked = billing_audit_attempt_by_reference($pdo, $provider, $providerReference, null, true);
        if (!$locked || (int)$locked['id'] !== (int)$attempt['id']) {
            throw new RuntimeException('Billing payment attempt changed during webhook processing.');
        }
        $updated = billing_audit_apply_verification($pdo, (int)$locked['id'], $verification);
        if ((string)($updated['status'] ?? '') === 'paid') {
            billing_subscription_apply_paid_attempt($pdo, (int)$locked['id']);
        }
        billing_audit_event_mark($pdo, $registeredEventId, 'processed');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        try {
            billing_audit_event_mark(
                $pdo,
                $registeredEventId,
                'failed',
                'Verified provider fact or subscription application could not be applied safely.'
            );
        } catch (Throwable $ignored) {
        }
        http_response_code(500);
        exit('Processing failed.');
    }

    http_response_code(200);
    exit('OK');
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit('Invalid billing webhook request.');
} catch (Throwable $e) {
    if ($registeredEventId !== null) {
        try {
            billing_audit_event_mark(
                $pdo,
                $registeredEventId,
                'failed',
                'Billing webhook processing failed.'
            );
        } catch (Throwable $ignored) {
        }
    }
    http_response_code(401);
    exit('Webhook authentication or processing failed.');
}
