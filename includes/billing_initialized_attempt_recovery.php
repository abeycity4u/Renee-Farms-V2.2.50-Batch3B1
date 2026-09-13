<?php
/**
 * V2.3 initialized subscription-attempt recovery.
 *
 * Purpose:
 * Recover a subscription checkout that was durably persisted as initialized
 * but whose provider result was not safely recorded before execution stopped.
 *
 * Safety contract:
 * - subscription purpose only;
 * - commercially eligible initialized attempts only;
 * - provider verification occurs outside database transactions;
 * - provider/network failure is not interpreted as payment failure;
 * - provider facts are revalidated against the locked durable attempt;
 * - paid application stays centralized through billing_paid_attempt_dispatch();
 * - verified pending remains blocking;
 * - verified failed/cancelled may be commercially superseded;
 * - verified refunded settles without commercial application;
 * - no age-only expiry and no guessed provider state;
 * - no direct entitlement or subscription-state DML.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_provider_contract.php';
require_once __DIR__ . '/billing_provider_adapters.php';
require_once __DIR__ . '/billing_payment_audit_state.php';
require_once __DIR__ . '/billing_initialized_attempt_recovery_core.php';
require_once __DIR__ . '/billing_commercial_attempt_disposition.php';
require_once __DIR__ . '/billing_paid_attempt_dispatcher.php';

if (!function_exists(
    'billing_initialized_attempt_recovery_candidate'
)) {
    function billing_initialized_attempt_recovery_candidate(
        PDO $pdo,
        int $farmId
    ): ?array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for initialized checkout recovery.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Initialized checkout recovery candidate selection must occur outside a database transaction.'
            );
        }

        if (!billing_payment_foundation_ready($pdo)
            || !billing_commercial_attempt_disposition_storage_ready(
                $pdo
            )) {
            throw new RuntimeException(
                'Billing payment and commercial disposition storage are not ready for initialized checkout recovery.'
            );
        }

        /*
         * Deliberately no FOR UPDATE here.
         *
         * Provider verification follows candidate selection and must occur
         * outside any transaction. Mutable state is locked and revalidated
         * only after authoritative provider verification succeeds.
         */
        $stmt = $pdo->prepare(
            "SELECT *
             FROM billing_payment_attempts
             WHERE farm_id = ?
               AND purpose = 'subscription'
               AND commercial_disposition = 'eligible'
               AND status = 'initialized'
             ORDER BY id ASC
             LIMIT 1"
        );

        $stmt->execute([
            $farmId,
        ]);

        $attempt =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if ($attempt === null) {
            return null;
        }

        if ((int)($attempt['farm_id'] ?? 0)
            !== $farmId) {
            throw new RuntimeException(
                'Initialized checkout recovery candidate escaped its tenant scope.'
            );
        }

        if (billing_payment_attempt_purpose(
            $attempt
        ) !== 'subscription') {
            throw new RuntimeException(
                'Initialized checkout recovery candidate has an invalid payment purpose.'
            );
        }

        $disposition =
            billing_commercial_attempt_disposition_state(
                $attempt
            );

        if (($disposition['disposition'] ?? '')
            !== 'eligible') {
            throw new RuntimeException(
                'Initialized checkout recovery candidate is no longer commercially eligible.'
            );
        }

        if (strtolower(trim(
            (string)($attempt['status'] ?? '')
        )) !== 'initialized') {
            throw new RuntimeException(
                'Initialized checkout recovery candidate changed payment status.'
            );
        }

        if ((int)(
            $attempt[
                'applied_subscription_record_id'
            ] ?? 0
        ) > 0
            || billing_audit_datetime(
                $attempt['paid_at'] ?? null
            ) !== null) {
            throw new RuntimeException(
                'Initialized checkout recovery candidate contains conflicting paid evidence.'
            );
        }

        $provider =
            billing_payment_normalize_provider(
                (string)(
                    $attempt['provider']
                    ?? ''
                )
            );

        $providerReference =
            billing_payment_normalize_reference(
                (string)(
                    $attempt[
                        'provider_reference'
                    ] ?? ''
                )
            );

        return [
            'attempt_id' =>
                (int)($attempt['id'] ?? 0),
            'farm_id' => $farmId,
            'status' => 'initialized',
            'provider' => $provider,
            'provider_reference' =>
                $providerReference,
            'commercial_disposition' =>
                'eligible',
            'attempt' => $attempt,
        ];
    }
}

if (!function_exists(
    'billing_initialized_attempt_recover_next'
)) {
    function billing_initialized_attempt_recover_next(
        PDO $pdo,
        int $farmId,
        int $actorUserId
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for initialized checkout recovery.'
            );
        }

        if ($actorUserId < 1) {
            throw new InvalidArgumentException(
                'A valid user is required for initialized checkout recovery.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Initialized checkout recovery must start outside a database transaction.'
            );
        }

        $candidate =
            billing_initialized_attempt_recovery_candidate(
                $pdo,
                $farmId
            );

        if ($candidate === null) {
            return [
                'attempt_found' => false,
                'outcome' => 'none',
                'farm_id' => $farmId,
                'attempt_id' => null,
                'blocking' => null,
                'provider' => null,
                'provider_reference' => null,
                'verification' => null,
                'application' => null,
                'supersession' => null,
            ];
        }

        $core =
            billing_initialized_attempt_recovery_core(
                $pdo,
                $candidate,
                'subscription',
                static function (
                    PDO $pdo,
                    array $lockedAttempt,
                    array $candidate
                ): array {
                    $disposition =
                        billing_commercial_attempt_disposition_state(
                            $lockedAttempt
                        );

                    if (($disposition['disposition'] ?? '')
                        !== 'eligible') {
                        return [
                            'stale_blocked' => true,
                        ];
                    }

                    if ((int)(
                        $lockedAttempt[
                            'applied_subscription_record_id'
                        ] ?? 0
                    ) > 0
                        || billing_audit_datetime(
                            $lockedAttempt['paid_at']
                                ?? null
                        ) !== null) {
                        throw new RuntimeException(
                            'Initialized subscription attempt gained conflicting paid evidence during recovery.'
                        );
                    }

                    return [
                        'stale_blocked' => false,
                    ];
                },
                static function (
                    PDO $pdo,
                    array $updated,
                    array $candidate,
                    array $lockedContext
                ) use ($actorUserId): array {
                    $attemptId =
                        (int)($updated['id'] ?? 0);

                    $updatedDisposition =
                        billing_commercial_attempt_disposition_state(
                            $updated
                        );

                    if (($updatedDisposition['disposition'] ?? '')
                        !== 'eligible') {
                        throw new RuntimeException(
                            'Recovered subscription payment lost commercial eligibility unexpectedly.'
                        );
                    }

                    $status = strtolower(trim(
                        (string)($updated['status'] ?? '')
                    ));

                    $application = null;
                    $supersession = null;

                    if ($status === 'paid') {
                        $application =
                            billing_paid_attempt_dispatch(
                                $pdo,
                                $attemptId
                            );

                        if (($application['audit_only'] ?? false)
                            === true) {
                            throw new RuntimeException(
                                'Eligible recovered subscription payment unexpectedly entered audit-only dispatch.'
                            );
                        }

                        $finalAttempt =
                            billing_audit_attempt_by_id(
                                $pdo,
                                $attemptId,
                                false
                            );

                        if (!is_array($finalAttempt)
                            || (int)(
                                $finalAttempt[
                                    'applied_subscription_record_id'
                                ] ?? 0
                            ) < 1
                            || strtolower(trim(
                                (string)(
                                    $finalAttempt['status']
                                    ?? ''
                                )
                            )) !== 'paid') {
                            throw new RuntimeException(
                                'Recovered paid subscription did not persist its exactly-once application linkage.'
                            );
                        }

                        return [
                            'outcome' => 'paid_applied',
                            'blocking' => false,
                            'application' => $application,
                            'supersession' => null,
                        ];
                    }

                    if ($status === 'pending') {
                        return [
                            'outcome' => 'pending_blocked',
                            'blocking' => true,
                            'application' => null,
                            'supersession' => null,
                        ];
                    }

                    if (in_array(
                        $status,
                        ['failed', 'cancelled'],
                        true
                    )) {
                        $verifiedAt =
                            billing_audit_datetime(
                                $updated['verified_at']
                                    ?? null
                            );

                        if ($verifiedAt === null) {
                            throw new RuntimeException(
                                'Recovered terminal subscription payment is missing its provider verification timestamp.'
                            );
                        }

                        $supersession =
                            billing_commercial_attempt_mark_superseded_after_verified_terminal(
                                $pdo,
                                $attemptId,
                                $actorUserId,
                                $verifiedAt,
                                'initialized_recovery_terminal'
                            );

                        if (($supersession['disposition'] ?? '')
                            !== 'superseded') {
                            throw new RuntimeException(
                                'Recovered terminal subscription attempt was not commercially superseded.'
                            );
                        }

                        return [
                            'outcome' => 'superseded',
                            'blocking' => false,
                            'application' => null,
                            'supersession' => $supersession,
                        ];
                    }

                    if ($status === 'refunded') {
                        return [
                            'outcome' => 'refunded_settled',
                            'blocking' => false,
                            'application' => null,
                            'supersession' => null,
                        ];
                    }

                    throw new RuntimeException(
                        'Initialized checkout recovery produced an unsupported provider payment state.'
                    );
                }
            );

        $settlement =
            is_array($core['settlement'] ?? null)
                ? $core['settlement']
                : [];

        return [
            'attempt_found' => true,
            'outcome' =>
                (string)($core['outcome'] ?? ''),
            'farm_id' => $farmId,
            'attempt_id' =>
                (int)($core['attempt_id'] ?? 0),
            'blocking' =>
                $core['blocking'] ?? true,
            'provider' =>
                $core['provider'] ?? null,
            'provider_reference' =>
                $core['provider_reference']
                    ?? null,
            'verification' =>
                $core['verification'] ?? null,
            'application' =>
                $settlement['application']
                    ?? null,
            'supersession' =>
                $settlement['supersession']
                    ?? null,
        ];
    }
}

?>
