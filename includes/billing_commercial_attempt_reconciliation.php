<?php
/**
 * V2.3 commercial subscription-attempt reconciliation foundation.
 *
 * A caller performs the provider/network verification before entering this
 * service. This service consumes only the normalized verified provider fact
 * inside a caller-owned transaction.
 *
 * Starting scope is deliberately narrow:
 * - subscription purpose only;
 * - commercially eligible failed/cancelled attempts only;
 * - payment attempt lock remains first;
 * - authoritative provider fact is applied through the audit layer;
 * - still-failed/cancelled -> durable commercial supersession;
 * - paid -> central paid dispatcher;
 * - refunded -> settled without commercial application;
 * - pending -> remains blocking;
 * - no provider/network call occurs here.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_payment_audit_state.php';
require_once __DIR__ . '/billing_commercial_attempt_disposition.php';
require_once __DIR__ . '/billing_paid_attempt_dispatcher.php';

if (!function_exists(
    'billing_commercial_attempt_reconcile_verified_fact'
)) {
    function billing_commercial_attempt_reconcile_verified_fact(
        PDO $pdo,
        int $farmId,
        int $attemptId,
        int $actorUserId,
        array $verification,
        string $supersessionReason =
            'customer_replaced_checkout'
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for commercial reconciliation.'
            );
        }

        if ($attemptId < 1) {
            throw new InvalidArgumentException(
                'A valid subscription payment attempt is required for commercial reconciliation.'
            );
        }

        if ($actorUserId < 1) {
            throw new InvalidArgumentException(
                'A valid user is required for commercial reconciliation.'
            );
        }

        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Commercial attempt reconciliation requires an active caller transaction.'
            );
        }

        if (!billing_payment_foundation_ready($pdo)
            || !billing_commercial_attempt_disposition_storage_ready(
                $pdo
            )) {
            throw new RuntimeException(
                'Billing payment and commercial disposition storage are not ready for reconciliation.'
            );
        }

        /*
         * Preserve verified-payment/application lock order:
         *
         * payment attempt -> tenant farm.
         *
         * The supersession helper and paid dispatcher use the same ordering.
         */
        $attempt =
            billing_audit_attempt_by_id(
                $pdo,
                $attemptId,
                true
            );

        if (!is_array($attempt)) {
            throw new RuntimeException(
                'Subscription payment attempt could not be found for commercial reconciliation.'
            );
        }

        if ((int)($attempt['farm_id'] ?? 0) !== $farmId) {
            throw new RuntimeException(
                'Subscription payment attempt does not belong to the reconciliation tenant.'
            );
        }

        if (billing_payment_attempt_purpose(
            $attempt
        ) !== 'subscription') {
            throw new RuntimeException(
                'Only subscription payment attempts may enter commercial reconciliation.'
            );
        }

        $disposition =
            billing_commercial_attempt_disposition_state(
                $attempt
            );

        if (($disposition['disposition'] ?? '')
            !== 'eligible') {
            throw new RuntimeException(
                'Only commercially eligible payment attempts may enter replacement-checkout reconciliation.'
            );
        }

        $startingStatus =
            strtolower(trim(
                (string)($attempt['status'] ?? '')
            ));

        if (!in_array(
            $startingStatus,
            ['failed', 'cancelled'],
            true
        )) {
            throw new RuntimeException(
                'Only failed or cancelled subscription attempts may enter replacement-checkout reconciliation.'
            );
        }

        if ((int)(
            $attempt[
                'applied_subscription_record_id'
            ] ?? 0
        ) > 0) {
            throw new RuntimeException(
                'An already-applied subscription attempt cannot enter commercial reconciliation.'
            );
        }

        if (billing_audit_datetime(
            $attempt['paid_at'] ?? null
        ) !== null) {
            throw new RuntimeException(
                'A payment attempt with existing paid evidence cannot enter replacement-checkout reconciliation.'
            );
        }

        if (($verification['verified'] ?? null) !== true) {
            throw new RuntimeException(
                'Commercial reconciliation requires a server-verified provider payment fact.'
            );
        }

        $incomingStatus =
            strtolower(trim(
                (string)($verification['status'] ?? '')
            ));

        if (!in_array(
            $incomingStatus,
            [
                'pending',
                'paid',
                'failed',
                'cancelled',
                'refunded',
            ],
            true
        )) {
            throw new RuntimeException(
                'Commercial reconciliation received an unsupported provider payment status.'
            );
        }

        /*
         * This re-locks the same attempt row in the same transaction and
         * performs the canonical provider identity, amount and currency checks.
         */
        $updated =
            billing_audit_apply_verification(
                $pdo,
                $attemptId,
                $verification
            );

        if ((int)($updated['id'] ?? 0) !== $attemptId
            || (int)($updated['farm_id'] ?? 0) !== $farmId) {
            throw new RuntimeException(
                'Reconciled payment attempt identity changed unexpectedly.'
            );
        }

        if (billing_payment_attempt_purpose(
            $updated
        ) !== 'subscription') {
            throw new RuntimeException(
                'Reconciled payment attempt purpose changed unexpectedly.'
            );
        }

        $updatedDisposition =
            billing_commercial_attempt_disposition_state(
                $updated
            );

        if (($updatedDisposition['disposition'] ?? '')
            !== 'eligible') {
            throw new RuntimeException(
                'Payment attempt disposition changed unexpectedly during provider reconciliation.'
            );
        }

        $status =
            strtolower(trim(
                (string)($updated['status'] ?? '')
            ));

        if (in_array(
            $status,
            ['failed', 'cancelled'],
            true
        )) {
            $verifiedAt =
                billing_audit_datetime(
                    $updated['verified_at'] ?? null
                );

            if ($verifiedAt === null) {
                throw new RuntimeException(
                    'Verified terminal payment attempt is missing its reconciliation timestamp.'
                );
            }

            $supersession =
                billing_commercial_attempt_mark_superseded_after_verified_terminal(
                    $pdo,
                    $attemptId,
                    $actorUserId,
                    $verifiedAt,
                    $supersessionReason
                );

            if (($supersession['disposition'] ?? '')
                !== 'superseded') {
                throw new RuntimeException(
                    'Verified terminal payment attempt was not commercially superseded.'
                );
            }

            return [
                'outcome' => 'superseded',
                'farm_id' => $farmId,
                'attempt_id' => $attemptId,
                'status' => $status,
                'commercial_disposition' =>
                    'superseded',
                'blocking' => false,
                'application' => null,
                'supersession' => $supersession,
                'attempt' =>
                    $supersession['attempt']
                    ?? $updated,
            ];
        }

        if ($status === 'paid') {
            $application =
                billing_paid_attempt_dispatch(
                    $pdo,
                    $attemptId
                );

            if (($application['audit_only'] ?? false)
                === true) {
                throw new RuntimeException(
                    'Eligible reconciled payment unexpectedly entered audit-only paid dispatch.'
                );
            }

            $finalAttempt =
                billing_audit_attempt_by_id(
                    $pdo,
                    $attemptId,
                    false
                );

            if (!is_array($finalAttempt)
                || (int)($finalAttempt['id'] ?? 0)
                    !== $attemptId
                || (int)($finalAttempt['farm_id'] ?? 0)
                    !== $farmId
                || strtolower(trim(
                    (string)(
                        $finalAttempt['status']
                        ?? ''
                    )
                )) !== 'paid'
                || (int)(
                    $finalAttempt[
                        'applied_subscription_record_id'
                    ] ?? 0
                ) < 1) {
                throw new RuntimeException(
                    'Reconciled paid subscription application did not persist its final attempt linkage.'
                );
            }

            $finalDisposition =
                billing_commercial_attempt_disposition_state(
                    $finalAttempt
                );

            if (($finalDisposition['disposition'] ?? '')
                !== 'eligible') {
                throw new RuntimeException(
                    'Reconciled paid subscription attempt lost commercial eligibility after application.'
                );
            }

            return [
                'outcome' => 'paid_applied',
                'farm_id' => $farmId,
                'attempt_id' => $attemptId,
                'status' => 'paid',
                'commercial_disposition' =>
                    'eligible',
                'blocking' => false,
                'application' => $application,
                'supersession' => null,
                'attempt' => $finalAttempt,
            ];
        }

        if ($status === 'refunded') {
            return [
                'outcome' => 'refunded_settled',
                'farm_id' => $farmId,
                'attempt_id' => $attemptId,
                'status' => 'refunded',
                'commercial_disposition' =>
                    'eligible',
                'blocking' => false,
                'application' => null,
                'supersession' => null,
                'attempt' => $updated,
            ];
        }

        if ($status === 'pending') {
            return [
                'outcome' => 'pending_blocked',
                'farm_id' => $farmId,
                'attempt_id' => $attemptId,
                'status' => 'pending',
                'commercial_disposition' =>
                    'eligible',
                'blocking' => true,
                'application' => null,
                'supersession' => null,
                'attempt' => $updated,
            ];
        }

        throw new RuntimeException(
            'Provider reconciliation produced an unsupported effective payment state.'
        );
    }
}
?>
