<?php
/**
 * V2.3 dedicated seat-top-up checkout route.
 *
 * Contract:
 * - Farm Admin session only; subscription-recovery checkout is not accepted;
 * - browser input is limited to role, quantity and optional provider;
 * - farm identity, price, currency, commercial product and target seat snapshot
 *   remain server authoritative;
 * - payment attempt plus durable seat-change request are committed before the
 *   provider network call;
 * - provider initialization occurs outside the initiation transaction;
 * - provider initialization failure fails both the payment attempt and its
 *   awaiting durable seat-change request in one cleanup transaction;
 * - this route never grants seats or mutates subscription state directly.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__)
    . '/includes/billing_payment_foundation.php';
require_once dirname(__DIR__)
    . '/includes/billing_currency_policy.php';
require_once dirname(__DIR__)
    . '/includes/billing_provider_contract.php';
require_once dirname(__DIR__)
    . '/includes/billing_provider_selection.php';
require_once dirname(__DIR__)
    . '/includes/billing_provider_adapters.php';
require_once dirname(__DIR__)
    . '/includes/billing_route_request.php';
require_once dirname(__DIR__)
    . '/includes/billing_provider_readiness.php';
require_once dirname(__DIR__)
    . '/includes/billing_payment_audit_state.php';
require_once dirname(__DIR__)
    . '/includes/billing_tenant_actor.php';
require_once dirname(__DIR__)
    . '/includes/billing_seat_topup_reconciliation_launcher.php';
require_once dirname(__DIR__)
    . '/includes/billing_seat_topup_initiation.php';

$actor = billing_require_farm_admin_actor(
    $pdo,
    false
);

$farmId = (int)$actor['farm_id'];

require_valid_csrf_post();

if (!$pdo instanceof PDO
    || !billing_payment_foundation_ready($pdo)
    || !billing_seat_change_ready($pdo)) {
    http_response_code(503);
    exit(
        'Seat top-up service is temporarily unavailable.'
    );
}

try {
    $selection =
        billing_route_normalize_seat_topup_input(
            $_POST
        );

    /*
     * A seat-top-up payment freezes the tenant's complete target seat
     * snapshot. Before preparing another checkout, reconcile any earlier
     * unresolved top-up for this tenant across all seat roles.
     *
     * Historical provider verification remains inside the centralized
     * launcher and outside database transactions.
     */
    $reconciliation =
        billing_seat_topup_reconcile_open_candidates_for_replacement(
            $pdo,
            $farmId
        );

    $stoppedReason =
        (string)(
            $reconciliation['stopped_reason']
                ?? ''
        );

    if ($stoppedReason === 'paid_applied') {
        $_SESSION['success'] =
            'An earlier seat top-up payment was verified and the additional seats were applied. No new checkout was started.';

        header(
            'Location: '
                . BASE_URL
                . '/billing/account.php',
            true,
            303
        );
        exit();
    }

    if (in_array(
        $stoppedReason,
        [
            'initialized_blocked',
            'pending_blocked',
        ],
        true
    )) {
        $_SESSION['error'] =
            $stoppedReason === 'initialized_blocked'
                ? 'An earlier seat top-up checkout is still being prepared. No new checkout was started.'
                : 'An earlier seat top-up payment is still pending verification. No new checkout was started.';

        header(
            'Location: '
                . BASE_URL
                . '/billing/account.php',
            true,
            303
        );
        exit();
    }

    if ($stoppedReason !== 'exhausted'
        || (
            $reconciliation[
                'replacement_allowed'
            ] ?? false
        ) !== true) {
        throw new RuntimeException(
            'Seat-top-up replacement reconciliation did not reach a safe state.'
        );
    }

    /*
     * Resolve the provider selected for the new checkout only after historical
     * seat-top-up reconciliation is safely exhausted. Historical attempts may
     * belong to a different configured provider.
     */
    $provider =
        billing_provider_readiness_resolve_checkout(
            $selection['provider']
        );

    billing_provider_register_configured_adapters(
        $provider
    );

    $farm = billing_tenant_actor_farm(
        $pdo,
        $actor
    );

    if (!$farm
        || (int)($farm['id'] ?? 0) !== $farmId) {
        throw new RuntimeException(
            'Current tenant farm could not be resolved for seat top-up.'
        );
    }

    $customerEmail = strtolower(trim(
        (string)($farm['contact_email'] ?? '')
    ));

    if (!filter_var(
        $customerEmail,
        FILTER_VALIDATE_EMAIL
    )) {
        throw new RuntimeException(
            'A valid farm contact email is required before seat top-up checkout.'
        );
    }

    $returnUrl = billing_route_public_url(
        '/billing/return.php',
        ['provider' => $provider]
    );

    $context = [
        'customer_email' => $customerEmail,
        'customer_name' =>
            trim((string)($farm['name'] ?? ''))
                ?: 'Farm Customer',
        'callback_url' => $returnUrl,
        'redirect_url' => $returnUrl,
    ];

    $providerReference =
        billing_route_provider_reference(
            $farmId,
            $provider
        );

    $prepared = billing_seat_topup_prepare(
        $pdo,
        $farmId,
        $provider,
        $providerReference,
        $selection['role_code'],
        $selection['quantity'],
        (int)$actor['user_id']
    );

    $attemptId = (int)(
        $prepared['attempt_id'] ?? 0
    );

    if ($attemptId < 1) {
        throw new RuntimeException(
            'Seat-top-up payment attempt was not prepared safely.'
        );
    }

    try {
        $checkout =
            billing_provider_initialize_checkout(
                $provider,
                $providerReference,
                $prepared['checkout_quote'],
                $context
            );
    } catch (Throwable $providerError) {
        try {
            $pdo->beginTransaction();

            billing_audit_mark_initialization_failed(
                $pdo,
                $attemptId,
                'seat_topup_checkout_initialization_failed'
            );

            billing_seat_change_mark_payment_failed(
                $pdo,
                $attemptId
            );

            $pdo->commit();
        } catch (Throwable $cleanupError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw new RuntimeException(
                'Seat-top-up checkout cleanup could not be recorded safely.',
                0,
                $cleanupError
            );
        }

        http_response_code(502);
        exit(
            'Payment checkout could not be started. Please try again.'
        );
    }

    try {
        $pdo->beginTransaction();

        billing_audit_mark_pending(
            $pdo,
            $attemptId,
            $checkout[
                'provider_transaction_id'
            ] ?? null,
            $checkout[
                'provider_subscription_id'
            ] ?? null
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500);
        exit(
            'Payment checkout could not be recorded safely. Please try again.'
        );
    }

    header(
        'Location: '
            . $checkout['checkout_url'],
        true,
        303
    );
    exit();
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    exit('Invalid seat top-up checkout request.');
} catch (Throwable $e) {
    http_response_code(503);
    exit(
        'Seat top-up checkout is not available right now.'
    );
}
