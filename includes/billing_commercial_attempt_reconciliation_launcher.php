<?php
/**
 * V2.3 commercial subscription-attempt reconciliation launcher.
 *
 * This is the provider-facing orchestration boundary for replacement checkout.
 *
 * Contract:
 * - locate one oldest commercially eligible failed/cancelled subscription
 *   attempt without locking it;
 * - perform provider registration and server-to-server verification while no
 *   database transaction is open;
 * - only after provider verification succeeds, open a short transaction and
 *   delegate the verified fact to the canonical reconciliation service;
 * - the reconciliation service re-locks and revalidates all mutable state;
 * - no entitlement/subscription DML is duplicated here.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_provider_contract.php';
require_once __DIR__ . '/billing_provider_adapters.php';
require_once __DIR__ . '/billing_commercial_attempt_disposition.php';
require_once __DIR__ . '/billing_commercial_attempt_reconciliation.php';

if (!function_exists(
    'billing_commercial_attempt_reconciliation_candidate'
)) {
    function billing_commercial_attempt_reconciliation_candidate(
        PDO $pdo,
        int $farmId
    ): ?array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for commercial reconciliation candidate selection.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Commercial reconciliation candidate selection must occur outside a database transaction.'
            );
        }

        if (!billing_payment_foundation_ready($pdo)
            || !billing_commercial_attempt_disposition_storage_ready(
                $pdo
            )) {
            throw new RuntimeException(
                'Billing payment and commercial disposition storage are not ready for reconciliation candidate selection.'
            );
        }

        /*
         * Deliberately no FOR UPDATE here.
         *
         * Provider verification may involve network I/O. Mutable state is
         * re-locked and revalidated later by the canonical reconciliation
         * service inside its short caller-owned transaction.
         */
        $stmt = $pdo->prepare(
            "SELECT *
             FROM billing_payment_attempts
             WHERE farm_id = ?
               AND purpose = 'subscription'
               AND commercial_disposition = 'eligible'
               AND status IN ('failed', 'cancelled')
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
                'Commercial reconciliation candidate escaped its tenant scope.'
            );
        }

        if (billing_payment_attempt_purpose(
            $attempt
        ) !== 'subscription') {
            throw new RuntimeException(
                'Commercial reconciliation candidate has an invalid payment purpose.'
            );
        }

        $disposition =
            billing_commercial_attempt_disposition_state(
                $attempt
            );

        if (($disposition['disposition'] ?? '')
            !== 'eligible') {
            throw new RuntimeException(
                'Commercial reconciliation candidate is no longer eligible.'
            );
        }

        $status =
            strtolower(trim(
                (string)($attempt['status'] ?? '')
            ));

        if (!in_array(
            $status,
            ['failed', 'cancelled'],
            true
        )) {
            throw new RuntimeException(
                'Commercial reconciliation candidate is not in a terminal replacement-checkout state.'
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
                'Commercial reconciliation candidate already contains paid application evidence.'
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
            'status' => $status,
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
    'billing_commercial_attempt_reconcile_next_for_replacement'
)) {
    function billing_commercial_attempt_reconcile_next_for_replacement(
        PDO $pdo,
        int $farmId,
        int $actorUserId
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for commercial reconciliation.'
            );
        }

        if ($actorUserId < 1) {
            throw new InvalidArgumentException(
                'A valid user is required for commercial reconciliation.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Provider reconciliation launcher must start outside a database transaction.'
            );
        }

        $candidate =
            billing_commercial_attempt_reconciliation_candidate(
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
                'reconciliation' => null,
            ];
        }

        $attemptId =
            (int)$candidate['attempt_id'];

        if ($attemptId < 1) {
            throw new RuntimeException(
                'Commercial reconciliation candidate has an invalid payment identity.'
            );
        }

        $provider =
            (string)$candidate['provider'];

        $providerReference =
            (string)$candidate[
                'provider_reference'
            ];

        /*
         * Network/provider work MUST remain before beginTransaction().
         */
        billing_provider_register_configured_adapters(
            $provider
        );

        $verification =
            billing_provider_verify_payment(
                $provider,
                $providerReference
            );

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Provider verification unexpectedly opened a database transaction.'
            );
        }

        $pdo->beginTransaction();

        try {
            $reconciliation =
                billing_commercial_attempt_reconcile_verified_fact(
                    $pdo,
                    $farmId,
                    $attemptId,
                    $actorUserId,
                    $verification
                );

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
                (string)(
                    $reconciliation[
                        'outcome'
                    ] ?? ''
                ),
            'farm_id' => $farmId,
            'attempt_id' => $attemptId,
            'blocking' =>
                $reconciliation[
                    'blocking'
                ] ?? null,
            'provider' => $provider,
            'provider_reference' =>
                $providerReference,
            'reconciliation' =>
                $reconciliation,
        ];
    }
}
?>
