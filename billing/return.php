<?php
/**
 * V2.3 provider return route.
 *
 * Browser query status/amount/currency values are never trusted. The route uses
 * only the selected provider/reference to locate the tenant's existing attempt,
 * performs a fresh server-to-server provider verification, and applies a paid
 * attempt through the exactly-once subscription bridge in one DB transaction.
 * Restricted recovery auth is promoted to a normal login only after active state
 * has been established by that verified payment application.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';
require_once dirname(__DIR__) . '/includes/billing_provider_contract.php';
require_once dirname(__DIR__) . '/includes/billing_provider_selection.php';
require_once dirname(__DIR__) . '/includes/billing_provider_adapters.php';
require_once dirname(__DIR__) . '/includes/billing_payment_audit_state.php';
require_once dirname(__DIR__) . '/includes/billing_paid_attempt_dispatcher.php';
require_once dirname(__DIR__) . '/includes/billing_tenant_actor.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Active is allowed here only for a still-valid recovery context. That covers the
// race where an authenticated webhook applies the same attempt before the browser
// returns; exactly-once application remains authoritative.
$recoveryReturnStatuses = array_values(array_unique(array_merge(
    subscription_recovery_target_statuses(),
    ['active']
)));
$actor = billing_require_farm_admin_actor($pdo, true, $recoveryReturnStatuses);
$farmId = (int)$actor['farm_id'];

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
    if (billing_tenant_actor_is_recovery($actor)
        && (int)($attempt['initiated_by_user_id'] ?? 0) !== (int)$actor['user_id']) {
        http_response_code(404);
        exit('Billing payment attempt could not be found.');
    }

    $verification = billing_provider_verify_payment($provider, $providerReference);

    $application = null;
    $pdo->beginTransaction();
    try {
        $locked = billing_audit_attempt_by_reference($pdo, $provider, $providerReference, $farmId, true);
        if (!$locked || (int)$locked['id'] !== (int)$attempt['id']) {
            throw new RuntimeException('Billing payment attempt changed during verification.');
        }
        if (billing_tenant_actor_is_recovery($actor)
            && (int)($locked['initiated_by_user_id'] ?? 0) !== (int)$actor['user_id']) {
            throw new RuntimeException('Recovery payment ownership changed during verification.');
        }

        $updated = billing_audit_apply_verification($pdo, (int)$locked['id'], $verification);
        if ((string)($updated['status'] ?? '') === 'paid') {
            $application = billing_paid_attempt_dispatch($pdo, (int)$locked['id']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $status = (string)($updated['status'] ?? 'pending');
    $recoveryMode = billing_tenant_actor_is_recovery($actor);
    $target = $recoveryMode ? '/billing/recover.php' : '/dashboard.php';

    if ($status === 'paid') {
        if ($recoveryMode) {
            subscription_recovery_promote_to_login($pdo);
            $target = '/dashboard.php';
        }
        $_SESSION['success'] = 'Payment verified and subscription activated successfully.';
    } elseif ($status === 'refunded') {
        $_SESSION['error'] = 'This payment has been recorded as refunded.';
    } elseif (in_array($status, ['failed', 'cancelled'], true)) {
        $_SESSION['error'] = 'Payment was not completed. You can start a new checkout when ready.';
    } else {
        $_SESSION['success'] = 'Payment verification is still pending. No subscription change has been applied.';
    }

    header('Location: ' . BASE_URL . $target, true, 303);
    exit();
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit('Invalid billing return request.');
} catch (Throwable $e) {
    http_response_code(502);
    exit('Payment could not be verified and applied right now. Please try again later.');
}
