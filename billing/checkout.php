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
require_once dirname(__DIR__) . '/includes/billing_current_product.php';
require_once dirname(__DIR__) . '/includes/billing_commercial_attempt_reconciliation_launcher.php';
require_once dirname(__DIR__) . '/includes/billing_initialized_attempt_recovery.php';
require_once dirname(__DIR__) . '/includes/billing_subscription_checkout_initiation.php';
require_once dirname(__DIR__) . '/includes/notifications.php';

$actor = billing_require_farm_admin_actor($pdo, true, subscription_recovery_target_statuses());
$farmId = (int)$actor['farm_id'];
require_valid_csrf_post();

if (!$pdo instanceof PDO || !billing_payment_foundation_ready($pdo)) {
    if (!billing_tenant_actor_is_recovery(
        $actor
    )) {
        redirectWithNotification(
            'error',
            'Billing service is temporarily unavailable. Please try again later.',
            '/billing/account.php'
        );
    }

    http_response_code(503);
    exit('Billing service is temporarily unavailable.');
}

try {
    $selection = billing_route_normalize_checkout_input($_POST);

    $allowedStatuses =
        billing_tenant_actor_is_recovery($actor)
            ? subscription_recovery_target_statuses()
            : billing_current_product_normal_statuses();

    /*
     * First recover any subscription attempt that was durably persisted as
     * initialized but whose provider result was not safely recorded.
     *
     * Provider verification occurs outside database transactions inside the
     * recovery helper. Ambiguous provider/network failure remains blocking;
     * it is never guessed to mean declined, cancelled or absent.
     */
    $initializedRecovery =
        billing_initialized_attempt_recover_next(
            $pdo,
            $farmId,
            (int)$actor['user_id']
        );

    $initializedOutcome =
        (string)(
            $initializedRecovery['outcome']
                ?? ''
        );

    $initializedFound =
        ($initializedRecovery['attempt_found'] ?? false)
        === true;

    if (!$initializedFound) {
        if ($initializedOutcome !== 'none'
            || array_key_exists(
                'blocking',
                $initializedRecovery
            )
                && $initializedRecovery['blocking']
                    !== null) {
            throw new RuntimeException(
                'Initialized checkout recovery returned an invalid empty-candidate result.'
            );
        }
    } elseif (!in_array(
        $initializedOutcome,
        [
            'initialized_blocked',
            'pending_blocked',
            'paid_applied',
            'superseded',
            'refunded_settled',
        ],
        true
    )) {
        throw new RuntimeException(
            'Initialized checkout recovery returned an unsupported outcome.'
        );
    }

    if ($initializedOutcome === 'paid_applied') {
        /*
         * The interrupted checkout has now been authoritatively verified paid
         * and applied. Never create another payment in this request.
         */
        if (billing_tenant_actor_is_recovery(
            $actor
        )) {
            subscription_recovery_promote_to_login(
                $pdo
            );
        }

        $_SESSION['success'] =
            'An earlier payment was verified and your subscription is active. No replacement checkout was started.';

        header(
            'Location: ' . BASE_URL . '/dashboard.php',
            true,
            303
        );
        exit();
    }

    if ($initializedOutcome === 'pending_blocked') {
        http_response_code(409);
        exit(
            'An earlier subscription payment is still pending verification. No new checkout was started.'
        );
    }

    if ($initializedOutcome === 'initialized_blocked') {
        http_response_code(409);
        exit(
            'An earlier subscription checkout could not yet be verified safely. No new checkout was started.'
        );
    }

    if (in_array(
        $initializedOutcome,
        [
            'superseded',
            'refunded_settled',
        ],
        true
    )
        && ($initializedRecovery['blocking'] ?? null)
            !== false) {
        throw new RuntimeException(
            'Settled initialized checkout recovery unexpectedly remained blocking.'
        );
    }

    /*
     * Before creating a replacement checkout, re-verify every commercially
     * eligible terminal subscription attempt within the bounded canonical
     * reconciliation flow. Provider/network work remains outside DB
     * transactions inside that launcher.
     */
    $reconciliation =
        billing_commercial_attempt_reconcile_terminal_candidates_for_replacement(
            $pdo,
            $farmId,
            (int)$actor['user_id']
        );

    $stoppedReason =
        (string)(
            $reconciliation['stopped_reason']
                ?? ''
        );

    if ($stoppedReason === 'paid_applied') {
        /*
         * The earlier checkout has now been authoritatively verified paid and
         * applied. Never open another provider checkout in this request.
         */
        if (billing_tenant_actor_is_recovery(
            $actor
        )) {
            subscription_recovery_promote_to_login(
                $pdo
            );
        }

        $_SESSION['success'] =
            'An earlier payment was verified and your subscription is active. No replacement checkout was started.';

        header(
            'Location: ' . BASE_URL . '/dashboard.php',
            true,
            303
        );
        exit();
    }

    if ($stoppedReason === 'pending_blocked') {
        http_response_code(409);
        exit(
            'An earlier subscription payment is still pending verification. No new checkout was started.'
        );
    }

    if ($stoppedReason !== 'exhausted'
        || (
            $reconciliation[
                'terminal_candidates_exhausted'
            ] ?? false
        ) !== true) {
        throw new RuntimeException(
            'Replacement checkout reconciliation did not reach a safe terminal state.'
        );
    }

    /*
     * Only after prior-attempt reconciliation is safely exhausted do we
     * validate the customer's selected provider for a brand-new checkout.
     * The reconciliation launcher independently registers each historical
     * attempt's own provider before verifying it.
     */
    $provider =
        billing_provider_readiness_resolve_checkout(
            $selection['provider']
        );

    billing_provider_register_configured_adapters(
        $provider
    );

    /*
     * Contact information is preflighted before the centralized prepare
     * transaction creates a fresh attempt. Commercial product fields are not
     * resolved here; prepare() remains the final server-authoritative gate.
     */
    $farm =
        billing_tenant_actor_farm(
            $pdo,
            $actor
        );

    if (!is_array($farm)
        || (int)($farm['id'] ?? 0)
            !== $farmId) {
        throw new RuntimeException(
            'Current tenant farm could not be resolved for billing.'
        );
    }

    $customerEmail =
        strtolower(trim(
            (string)(
                $farm['contact_email']
                    ?? ''
            )
        ));

    if (!filter_var(
        $customerEmail,
        FILTER_VALIDATE_EMAIL
    )) {
        throw new RuntimeException(
            'A valid farm contact email is required before subscription checkout.'
        );
    }

    $returnUrl =
        billing_route_public_url(
            '/billing/return.php',
            ['provider' => $provider]
        );

    $context = [
        'customer_email' =>
            $customerEmail,
        'customer_name' =>
            trim((string)(
                $farm['name'] ?? ''
            )) ?: 'Farm Customer',
        'callback_url' =>
            $returnUrl,
        'redirect_url' =>
            $returnUrl,
    ];

    $providerReference =
        billing_route_provider_reference(
            $farmId,
            $provider
        );

    /*
     * This service owns farm serialization, authoritative renewal resolution,
     * browser-field assertion, frozen quote creation and fresh attempt
     * persistence. It commits before any provider/network initialization.
     */
    $prepared =
        billing_subscription_checkout_prepare(
            $pdo,
            $farmId,
            $provider,
            $providerReference,
            $selection,
            (int)$actor['user_id'],
            $allowedStatuses
        );

    $attemptId =
        (int)$prepared['attempt_id'];

    $pricedQuote =
        $prepared['payment_quote'];

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
    if (!billing_tenant_actor_is_recovery(
        $actor
    )) {
        redirectWithNotification(
            'error',
            'Subscription checkout could not be validated. Please refresh Billing & Subscription and try again.',
            '/billing/account.php'
        );
    }

    http_response_code(422);
    exit('Invalid subscription checkout request.');
} catch (Throwable $e) {
    error_log(
        'Billing checkout unavailable for farm '
        . $farmId
        . ': '
        . $e->getMessage()
    );

    if (!billing_tenant_actor_is_recovery(
        $actor
    )) {
        redirectWithNotification(
            'error',
            'Payment checkout is currently unavailable. Please try again later.',
            '/billing/account.php'
        );
    }

    http_response_code(503);
    exit('Billing checkout is not available right now.');
}
