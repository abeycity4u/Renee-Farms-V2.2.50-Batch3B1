<?php
/**
 * V2.3 controlled checkout route.
 *
 * Creates/updates billing_payment_attempts, then redirects to the explicitly
 * selected provider. Subscription/entitlement application is never performed
 * here. Normal Farm Admin sessions and the restricted subscription-recovery
 * actor share the same centralized billing path.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';
require_once dirname(__DIR__) . '/includes/billing_currency_policy.php';
require_once dirname(__DIR__) . '/includes/billing_pricing_contract.php';
require_once dirname(__DIR__) . '/includes/billing_provider_contract.php';
require_once dirname(__DIR__) . '/includes/billing_provider_selection.php';
require_once dirname(__DIR__) . '/includes/billing_provider_adapters.php';
require_once dirname(__DIR__) . '/includes/billing_route_request.php';
require_once dirname(__DIR__) . '/includes/billing_provider_readiness.php';
require_once dirname(__DIR__) . '/includes/billing_payment_audit_state.php';
require_once dirname(__DIR__) . '/includes/billing_tenant_actor.php';
require_once dirname(__DIR__) . '/includes/billing_reactivation_quote.php';

$actor = billing_require_farm_admin_actor($pdo, true, ['suspended', 'cancelled']);
$farmId = (int)$actor['farm_id'];
require_valid_csrf_post();

if (!$pdo instanceof PDO || !billing_payment_foundation_ready($pdo)) {
    http_response_code(503);
    exit('Billing service is temporarily unavailable.');
}

try {
    $selection = billing_route_normalize_checkout_input($_POST);

    // Recovery is a same-product renewal only. The browser may carry the fields
    // needed by checkout, but it cannot use recovery auth to switch the tenant's
    // plan, interval, modules or purchased seats.
    if (billing_tenant_actor_is_recovery($actor)) {
        billing_reactivation_assert_selection($pdo, $farmId, $selection);
    }

    billing_provider_assert_new_checkout_allowed();
    $provider = billing_provider_readiness_resolve_checkout($selection['provider']);
    billing_provider_register_configured_adapters($provider);

    $farm = billing_tenant_actor_farm($pdo, $actor);
    if (!$farm || (int)($farm['id'] ?? 0) !== $farmId) {
        throw new RuntimeException('Current tenant farm could not be resolved for billing.');
    }
    $customerEmail = strtolower(trim((string)($farm['contact_email'] ?? '')));
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid farm contact email is required before subscription checkout.');
    }

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
            (int)$actor['user_id']
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
