<?php
/**
 * V2.3 Billing Stage 2F provider return route.
 *
 * Browser query status/amount/currency values are never trusted. The route uses
 * only the selected provider/reference to locate the current tenant's existing
 * attempt, then performs a fresh server-to-server provider verification.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';
require_once dirname(__DIR__) . '/includes/billing_provider_contract.php';
require_once dirname(__DIR__) . '/includes/billing_provider_selection.php';
require_once dirname(__DIR__) . '/includes/billing_provider_adapters.php';
require_once dirname(__DIR__) . '/includes/billing_payment_audit_state.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    exit('Method not allowed.');
}

requireLogin();
$farmId = requireCurrentFarmId();
if (isPlatformOwner() || !hasRole('farm_admin')) {
    http_response_code(403);
    exit('Farm Admin access is required to verify subscription payment.');
}

try {
    $provider = billing_provider_selection_normalize((string)($_GET['provider'] ?? ''));
    billing_provider_register_configured_adapters($provider);

    if ($provider === 'paystack') {
        $providerReference = trim((string)($_GET['reference'] ?? ($_GET['trxref'] ?? '')));
    } elseif ($provider === 'flutterwave') {
        $providerReference = trim((string)($_GET['tx_ref'] ?? ''));
    } else {
        throw new InvalidArgumentException('Unsupported billing provider.');
    }
    $providerReference = billing_payment_normalize_reference($providerReference);

    $attempt = billing_audit_attempt_by_reference($pdo, $provider, $providerReference, $farmId, false);
    if (!$attempt) {
        http_response_code(404);
        exit('Billing payment attempt could not be found.');
    }

    $verification = billing_provider_verify_payment($provider, $providerReference);

    $pdo->beginTransaction();
    try {
        // Re-read under a row lock and tenant scope before applying the provider fact.
        $locked = billing_audit_attempt_by_reference($pdo, $provider, $providerReference, $farmId, true);
        if (!$locked || (int)$locked['id'] !== (int)$attempt['id']) {
            throw new RuntimeException('Billing payment attempt changed during verification.');
        }
        $updated = billing_audit_apply_verification($pdo, (int)$locked['id'], $verification);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $status = (string)($updated['status'] ?? 'pending');
    if ($status === 'paid') {
        $_SESSION['success'] = 'Payment verified and recorded. Subscription activation is pending final billing application.';
    } elseif ($status === 'refunded') {
        $_SESSION['error'] = 'This payment has been recorded as refunded.';
    } elseif (in_array($status, ['failed', 'cancelled'], true)) {
        $_SESSION['error'] = 'Payment was not completed. You can start a new checkout when ready.';
    } else {
        $_SESSION['success'] = 'Payment verification is still pending. No subscription change has been applied.';
    }

    header('Location: ' . BASE_URL . '/dashboard.php', true, 303);
    exit();
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit('Invalid billing return request.');
} catch (Throwable $e) {
    http_response_code(502);
    exit('Payment could not be verified right now. Please try again later.');
}
