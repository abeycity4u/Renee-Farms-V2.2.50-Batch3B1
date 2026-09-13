<?php
/**
 * V2.3 shared initialized payment-attempt recovery core.
 *
 * Owns only the mechanism common to interrupted provider checkout recovery:
 * - provider verification occurs outside any database transaction;
 * - provider/network ambiguity remains blocking;
 * - the durable payment attempt is re-locked after provider I/O;
 * - tenant, purpose and initialized state are revalidated under lock;
 * - authoritative provider facts enter through billing_audit_apply_verification();
 * - purpose-specific locked-context validation and commercial settlement are
 *   supplied by narrow callbacks;
 * - the caller-visible transaction is committed only after settlement succeeds.
 *
 * This service does not contain subscription or seat-top-up business policy.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_provider_contract.php';
require_once __DIR__ . '/billing_provider_adapters.php';
require_once __DIR__ . '/billing_payment_audit_state.php';

if (!function_exists(
    'billing_initialized_attempt_recovery_core'
)) {
    function billing_initialized_attempt_recovery_core(
        PDO $pdo,
        array $candidate,
        string $expectedPurpose,
        callable $validateLockedContext,
        callable $settleVerified
    ): array {
        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Initialized payment recovery must start outside a database transaction.'
            );
        }

        $attemptId = (int)($candidate['attempt_id'] ?? 0);
        $farmId = (int)($candidate['farm_id'] ?? 0);

        if ($attemptId < 1 || $farmId < 1) {
            throw new InvalidArgumentException(
                'Initialized payment recovery requires a valid tenant and payment attempt.'
            );
        }

        $expectedPurpose = strtolower(trim($expectedPurpose));

        if (!in_array(
            $expectedPurpose,
            ['subscription', 'seat_topup'],
            true
        )) {
            throw new InvalidArgumentException(
                'Initialized payment recovery received an unsupported payment purpose.'
            );
        }

        $provider = billing_payment_normalize_provider(
            (string)($candidate['provider'] ?? '')
        );

        $providerReference =
            billing_payment_normalize_reference(
                (string)(
                    $candidate['provider_reference']
                        ?? ''
                )
            );

        /*
         * Provider work must remain completely outside the transaction.
         * Any exception is ambiguous, so the durable initialized attempt
         * remains blocking and no terminal provider fact is invented.
         */
        try {
            billing_provider_register_configured_adapters(
                $provider
            );

            $verification =
                billing_provider_verify_payment(
                    $provider,
                    $providerReference
                );
        } catch (Throwable $providerError) {
            if ($pdo->inTransaction()) {
                throw new RuntimeException(
                    'Initialized payment provider failure unexpectedly overlapped a database transaction.',
                    0,
                    $providerError
                );
            }

            return [
                'attempt_found' => true,
                'outcome' => 'initialized_blocked',
                'farm_id' => $farmId,
                'attempt_id' => $attemptId,
                'blocking' => true,
                'provider' => $provider,
                'provider_reference' =>
                    $providerReference,
                'verification' => null,
                'settlement' => null,
            ];
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Initialized payment provider verification unexpectedly opened a database transaction.'
            );
        }

        $pdo->beginTransaction();

        try {
            /*
             * Lock order always begins with the payment attempt. Purpose
             * callbacks may acquire their additional durable rows afterwards.
             */
            $lockedAttempt =
                billing_audit_attempt_by_id(
                    $pdo,
                    $attemptId,
                    true
                );

            if (!is_array($lockedAttempt)
                || (int)($lockedAttempt['id'] ?? 0)
                    !== $attemptId
                || (int)($lockedAttempt['farm_id'] ?? 0)
                    !== $farmId
                || billing_payment_attempt_purpose(
                    $lockedAttempt
                ) !== $expectedPurpose) {
                throw new RuntimeException(
                    'Initialized payment attempt changed identity during provider verification.'
                );
            }

            $lockedStatus = strtolower(trim(
                (string)($lockedAttempt['status'] ?? '')
            ));

            /*
             * A webhook/return may have settled the attempt while provider
             * verification was in flight. Never apply a stale provider fact
             * to a row that has left the selected initialized state.
             */
            if ($lockedStatus !== 'initialized') {
                $pdo->rollBack();

                return [
                    'attempt_found' => true,
                    'outcome' => 'initialized_blocked',
                    'farm_id' => $farmId,
                    'attempt_id' => $attemptId,
                    'blocking' => true,
                    'provider' => $provider,
                    'provider_reference' =>
                        $providerReference,
                    'verification' => null,
                    'settlement' => null,
                ];
            }

            $lockedContext =
                $validateLockedContext(
                    $pdo,
                    $lockedAttempt,
                    $candidate
                );

            if (!is_array($lockedContext)) {
                throw new RuntimeException(
                    'Initialized payment locked-context validator returned an invalid result.'
                );
            }

            $staleBlocked =
                $lockedContext['stale_blocked']
                    ?? false;

            if (!is_bool($staleBlocked)) {
                throw new RuntimeException(
                    'Initialized payment locked-context validator returned an invalid stale-state decision.'
                );
            }

            /*
             * Purpose-specific durable state may also have changed while
             * provider verification was in flight. Preserve the same
             * fail-closed replacement behavior without applying a stale
             * provider fact.
             */
            if ($staleBlocked) {
                $pdo->rollBack();

                return [
                    'attempt_found' => true,
                    'outcome' => 'initialized_blocked',
                    'farm_id' => $farmId,
                    'attempt_id' => $attemptId,
                    'blocking' => true,
                    'provider' => $provider,
                    'provider_reference' =>
                        $providerReference,
                    'verification' => null,
                    'settlement' => null,
                ];
            }

            /*
             * Canonical audit application re-locks the same payment attempt
             * and validates provider identity, reference, amount and currency.
             */
            $updated =
                billing_audit_apply_verification(
                    $pdo,
                    $attemptId,
                    $verification
                );

            if ((int)($updated['id'] ?? 0)
                    !== $attemptId
                || (int)($updated['farm_id'] ?? 0)
                    !== $farmId
                || billing_payment_attempt_purpose(
                    $updated
                ) !== $expectedPurpose) {
                throw new RuntimeException(
                    'Recovered payment attempt identity changed unexpectedly.'
                );
            }

            $settlement =
                $settleVerified(
                    $pdo,
                    $updated,
                    $candidate,
                    $lockedContext
                );

            if (!is_array($settlement)
                || trim((string)(
                    $settlement['outcome'] ?? ''
                )) === ''
                || !array_key_exists(
                    'blocking',
                    $settlement
                )
                || !is_bool(
                    $settlement['blocking']
                )) {
                throw new RuntimeException(
                    'Initialized payment settlement returned an invalid canonical result.'
                );
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        return [
            'attempt_found' => true,
            'outcome' =>
                (string)$settlement['outcome'],
            'farm_id' => $farmId,
            'attempt_id' => $attemptId,
            'blocking' =>
                (bool)$settlement['blocking'],
            'provider' => $provider,
            'provider_reference' =>
                $providerReference,
            'verification' => $verification,
            'settlement' => $settlement,
        ];
    }
}

?>
