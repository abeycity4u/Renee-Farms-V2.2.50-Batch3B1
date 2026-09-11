<?php
/**
 * V2.3 verified-paid-attempt purpose dispatcher.
 *
 * This is the single route-facing authority that decides which application
 * service may consume a provider-verified paid billing attempt.
 *
 * Current contract:
 * - subscription -> existing exactly-once subscription application service;
 * - seat_topup -> dedicated exactly-once seat-top-up application service;
 * - every other purpose -> fail closed.
 *
 * Provider verification and audit-state transitions remain owned by the
 * billing routes/audit layer. This dispatcher performs no provider call and
 * contains no direct commercial-state SQL.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_payment_audit_state.php';
require_once __DIR__ . '/billing_commercial_attempt_disposition.php';
require_once __DIR__ . '/billing_subscription_application.php';
require_once __DIR__ . '/billing_seat_topup_application.php';

if (!function_exists('billing_paid_attempt_dispatch')) {
    function billing_paid_attempt_dispatch(
        PDO $pdo,
        int $attemptId
    ): array {
        if ($attemptId < 1) {
            throw new InvalidArgumentException(
                'A valid paid billing attempt is required.'
            );
        }

        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Paid billing application dispatch requires an active database transaction.'
            );
        }

        $attempt = billing_audit_attempt_by_id(
            $pdo,
            $attemptId,
            true
        );

        if (!$attempt) {
            throw new RuntimeException(
                'Paid billing attempt could not be found for application dispatch.'
            );
        }

        if (strtolower(trim(
            (string)($attempt['status'] ?? '')
        )) !== 'paid') {
            throw new RuntimeException(
                'Only a provider-verified paid billing attempt can be dispatched.'
            );
        }

        try {
            $purpose = billing_payment_attempt_purpose(
                $attempt
            );
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException(
                'Paid billing attempt has an unsupported application purpose.',
                0,
                $e
            );
        }

        if ($purpose === 'subscription') {
            if (!billing_commercial_attempt_disposition_storage_ready(
                $pdo
            )) {
                throw new RuntimeException(
                    'Commercial payment-attempt disposition storage is unavailable for paid subscription dispatch.'
                );
            }

            $disposition =
                billing_commercial_attempt_disposition_state(
                    $attempt
                );

            if (($disposition['disposition'] ?? '')
                === 'superseded') {
                /*
                 * The provider payment remains fully audited as paid, but an
                 * explicitly superseded checkout must never overwrite newer
                 * tenant commercial state.
                 */
                return [
                    'applied' => false,
                    'idempotent' => true,
                    'audit_only' => true,
                    'attempt_id' => (int)$attempt['id'],
                    'farm_id' =>
                        (int)($attempt['farm_id'] ?? 0),
                    'purpose' => 'subscription',
                    'commercial_disposition' =>
                        'superseded',
                ];
            }

            return billing_subscription_apply_paid_attempt(
                $pdo,
                $attemptId
            );
        }

        if ($purpose === 'seat_topup') {
            return billing_seat_topup_apply_paid_attempt(
                $pdo,
                $attemptId
            );
        }

        throw new RuntimeException(
            'Paid billing attempt has no supported application service.'
        );
    }
}
