<?php
/**
 * V2.3 Billing Stage 2F controlled checkout route.
 *
 * Audit-only: creates/updates billing_payment_attempts, then redirects to the
 * selected provider. Subscription/entitlement application is intentionally not
 * performed here.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';
require_once dirname(__DIR__) . '/includes/billing_currency_policy.php';
require_once dirname(__DIR__) . '/includes/billing_pricing_contract.php';
require_once dirname(__DIR__) . '/includes/billing_provider_contract.php';
require_once dirname(__DIR__) . '/includes/billing_provider_selection.php';
require_once dirname(__DIR__) . '/includes/billing_provider_adapters.php';
require_once dirname(__DIR__) . '/includes/billing_route_request.php';
require_once dirname(__DIR__) . '/includes/billing_payment_audit_state.php';

requireLogin();
$farmId = requireCurrentFarmId();
if (isPlatformOwner() || !hasRole('farm_admin')) {
    http_response_code(403);
    exit('Farm Admin access is required for subscription checkout.');
}
require_valid_csrf_post();

if (!$pdo instanceof PDO || !billing_payment_foundation_ready($pdo)) {
    http_response_code(503);
    exit('Billing service is temporarily unavailable.');
}

try {
    $selection = billing_route_normalize_checkout_input($_POST);
    $provider = billing_provider_selection_resolve_checkout($selection['provider']);
    // A checkout route registers only its explicitly selected provider; there is
    // never a silent retry through the secondary provider.
    billing_provider_register_configured_adapters($provider);

    $farm = currentFarm();
    if (!$farm || (int)($farm['id'] ?? 0) !== $farmId) {
        throw new RuntimeException('Current tenant farm could not be resolved for billing.');
    }
    $customerEmail = strtolower(trim((string)($farm['contact_email'] ?? '')));
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid farm contact email is required before subscription checkout.');
    }

    // Resolve the deployment callback URL before any billing row is written. A
    // missing/malformed BILLING_PUBLIC_BASE_URL therefore fails with zero DB writes.
    $returnUrl = billing_route_public_url('/billing/return.php', ['provider' => $provider]);
    $context = [
        'customer_email' => $customerEmail,
        'customer_name' => trim((string)($farm['name'] ?? '')) ?: 'Farm Customer',
        'callback_url' => $returnUrl,
        'redirect_url' => $returnUrl,
    ];

    $pricedQuote = billing_pricing_build_payment_quote(
        $selection['plan_code'],
        $selection['billing_interval'],
        $selection['modules'],
        $selection['seat_addons']
    );
    $pricing = $pricedQuote['pricing'];

    // Never take payment for a plan/seat combination that cannot contain the
    // tenant's current users. The Stage 2G bridge repeats this check at apply
    // time, but checkout must fail before attempt creation/provider activity.
    subscription_seat_assert_capacity(
        $pdo,
        $farmId,
        $pricing['plan_code'],
        $pricing['modules'],
        $pricing['seat_addons']
    );

    $providerReference = billing_route_provider_reference($farmId, $provider);

    $pdo->beginTransaction();
    try {
        $created = billing_payment_attempt_create(
            $pdo,
            $farmId,
            $provider,
            $providerReference,
            $pricing['plan_code'],
            $pricing['billing_interval'],
            $pricing['amount'],
            $pricing['currency'],
            $pricing['modules'],
            $pricing['seat_addons'],
            (int)($_SESSION['user_id'] ?? 0)
        );
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $attemptId = (int)$created['id'];

    try {
        $checkout = billing_provider_initialize_checkout(
            $provider,
            $providerReference,
            $pricedQuote,
            $context
        );
    } catch (Throwable $providerError) {
        $pdo->beginTransaction();
        try {
            billing_audit_mark_initialization_failed($pdo, $attemptId, 'checkout_initialization_failed');
            $pdo->commit();
        } catch (Throwable $auditError) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
        http_response_code(502);
        exit('Payment checkout could not be started. Please try again.');
    }

    $pdo->beginTransaction();
    try {
        billing_audit_mark_pending(
            $pdo,
            $attemptId,
            $checkout['provider_transaction_id'] ?? null,
            $checkout['provider_subscription_id'] ?? null
        );
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        exit('Payment checkout could not be recorded safely. Please try again.');
    }

    header('Location: ' . $checkout['checkout_url'], true, 303);
    exit();
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    exit('Invalid subscription checkout request.');
} catch (Throwable $e) {
    http_response_code(503);
    exit('Billing checkout is not available right now.');
}
