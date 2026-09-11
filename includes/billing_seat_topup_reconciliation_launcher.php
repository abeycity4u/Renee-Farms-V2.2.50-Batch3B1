<?php
/**
 * V2.3 seat-top-up replacement reconciliation launcher.
 *
 * This is the provider-facing orchestration boundary used before another
 * seat-top-up checkout may be started.
 *
 * Seat-top-up payment attempts freeze the tenant's complete target seat-add-on
 * snapshot. Therefore unresolved paid-seat changes are serialized per tenant,
 * not merely per role. A concurrent top-up for a different role could otherwise
 * make an earlier immutable target snapshot stale.
 *
 * Contract:
 * - inspect one tenant's oldest durable add-seat request awaiting payment;
 * - serialize unresolved seat-top-up payment work across all seat roles;
 * - an initialized checkout remains blocking without guessing provider state;
 * - provider verification occurs only while no database transaction is open;
 * - verified paid attempts are applied through the central paid dispatcher;
 * - verified terminal attempts are reconciled through the durable seat-change
 *   terminal-payment service;
 * - verified pending attempts remain blocking;
 * - no entitlement, subscription or seat-change DML is duplicated here.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_provider_contract.php';
require_once __DIR__ . '/billing_provider_adapters.php';
require_once __DIR__ . '/billing_payment_audit_state.php';
require_once __DIR__ . '/billing_seat_change_request.php';
require_once __DIR__ . '/billing_paid_attempt_dispatcher.php';

if (!function_exists(
    'billing_seat_topup_reconciliation_candidate'
)) {
    function billing_seat_topup_reconciliation_candidate(
        PDO $pdo,
        int $farmId
    ): ?array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for seat-top-up reconciliation.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Seat-top-up reconciliation candidate selection must occur outside a database transaction.'
            );
        }

        if (!billing_payment_foundation_ready($pdo)
            || !billing_seat_change_ready($pdo)) {
            throw new RuntimeException(
                'Billing payment and seat-change storage are not ready for seat-top-up reconciliation.'
            );
        }

        /*
         * Deliberately no FOR UPDATE.
         *
         * Provider/network work must never occur while this candidate row is
         * locked. Mutable state is re-locked inside the short reconciliation
         * transaction after provider verification.
         */
        $stmt = $pdo->prepare(
            "SELECT
                a.*,
                r.id AS seat_change_request_id,
                r.status AS seat_change_request_status,
                r.role_code AS seat_change_role_code,
                r.change_kind AS seat_change_kind
             FROM billing_payment_attempts a
             INNER JOIN billing_seat_change_requests r
                ON r.payment_attempt_id = a.id
             WHERE a.farm_id = ?
               AND a.purpose = 'seat_topup'
               AND r.farm_id = a.farm_id
               AND r.change_kind = 'add'
               AND r.status = 'awaiting_payment'
               AND a.status IN (
                   'initialized',
                   'pending',
                   'paid',
                   'failed',
                   'cancelled',
                   'refunded'
               )
             ORDER BY a.id ASC
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
                'Seat-top-up reconciliation candidate escaped its tenant scope.'
            );
        }

        if (billing_payment_attempt_purpose(
            $attempt
        ) !== 'seat_topup') {
            throw new RuntimeException(
                'Seat-top-up reconciliation candidate has an invalid payment purpose.'
            );
        }

        $roleCode =
            billing_seat_change_normalize_role(
                (string)(
                    $attempt[
                        'seat_change_role_code'
                    ] ?? ''
                )
            );

        if (($attempt['seat_change_kind'] ?? '')
                !== 'add'
            || ($attempt[
                'seat_change_request_status'
            ] ?? '') !== 'awaiting_payment'
            || (int)(
                $attempt['seat_change_request_id']
                    ?? 0
            ) < 1) {
            throw new RuntimeException(
                'Seat-top-up reconciliation candidate has an invalid durable request.'
            );
        }

        $status = strtolower(trim(
            (string)($attempt['status'] ?? '')
        ));

        if (!in_array(
            $status,
            [
                'initialized',
                'pending',
                'paid',
                'failed',
                'cancelled',
                'refunded',
            ],
            true
        )) {
            throw new RuntimeException(
                'Seat-top-up reconciliation candidate has an unsupported payment state.'
            );
        }

        $provider =
            billing_payment_normalize_provider(
                (string)($attempt['provider'] ?? '')
            );

        $providerReference =
            billing_payment_normalize_reference(
                (string)(
                    $attempt['provider_reference']
                        ?? ''
                )
            );

        return [
            'attempt_id' =>
                (int)($attempt['id'] ?? 0),
            'request_id' =>
                (int)(
                    $attempt[
                        'seat_change_request_id'
                    ] ?? 0
                ),
            'farm_id' => $farmId,
            'role_code' => $roleCode,
            'status' => $status,
            'provider' => $provider,
            'provider_reference' =>
                $providerReference,
            'attempt' => $attempt,
        ];
    }
}

if (!function_exists(
    'billing_seat_topup_reconcile_next_for_replacement'
)) {
    function billing_seat_topup_reconcile_next_for_replacement(
        PDO $pdo,
        int $farmId
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for seat-top-up replacement reconciliation.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Seat-top-up provider reconciliation must start outside a database transaction.'
            );
        }

        $candidate =
            billing_seat_topup_reconciliation_candidate(
                $pdo,
                $farmId
            );

        if ($candidate === null) {
            return [
                'attempt_found' => false,
                'outcome' => 'none',
                'farm_id' => $farmId,
                'role_code' => null,
                'attempt_id' => null,
                'request_id' => null,
                'blocking' => false,
                'verification' => null,
                'application' => null,
                'terminal_reconciliation' => null,
            ];
        }

        $attemptId =
            (int)$candidate['attempt_id'];

        $requestId =
            (int)$candidate['request_id'];

        $roleCode =
            (string)$candidate['role_code'];

        if ($attemptId < 1
            || $requestId < 1
            || $roleCode === '') {
            throw new RuntimeException(
                'Seat-top-up reconciliation candidate has an invalid durable identity.'
            );
        }

        /*
         * An initialized attempt means initiation committed but provider
         * initialization has not yet been safely recorded as pending.
         *
         * Do not race another checkout and do not guess provider state here.
         */
        if (($candidate['status'] ?? '')
            === 'initialized') {
            return [
                'attempt_found' => true,
                'outcome' => 'initialized_blocked',
                'farm_id' => $farmId,
                'role_code' => $roleCode,
                'attempt_id' => $attemptId,
                'request_id' => $requestId,
                'blocking' => true,
                'provider' =>
                    (string)$candidate['provider'],
                'provider_reference' =>
                    (string)$candidate[
                        'provider_reference'
                    ],
                'verification' => null,
                'application' => null,
                'terminal_reconciliation' => null,
            ];
        }

        $provider =
            (string)$candidate['provider'];

        $providerReference =
            (string)$candidate[
                'provider_reference'
            ];

        /*
         * Provider/network work MUST remain outside any DB transaction.
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
                'Seat-top-up provider verification unexpectedly opened a database transaction.'
            );
        }

        $pdo->beginTransaction();

        try {
            /*
             * Preserve canonical application lock order:
             *
             * payment attempt -> durable request -> tenant farm
             *
             * The paid dispatcher acquires the tenant farm only when verified
             * payment actually reaches seat-top-up commercial application.
             */
            $lockedAttempt =
                billing_audit_attempt_by_id(
                    $pdo,
                    $attemptId,
                    true
                );

            if (!$lockedAttempt
                || (int)(
                    $lockedAttempt['farm_id']
                        ?? 0
                ) !== $farmId
                || billing_payment_attempt_purpose(
                    $lockedAttempt
                ) !== 'seat_topup') {
                throw new RuntimeException(
                    'Seat-top-up payment attempt changed during provider verification.'
                );
            }

            $lockedRequest =
                billing_seat_change_request_by_payment(
                    $pdo,
                    $attemptId,
                    true
                );

            if (!$lockedRequest) {
                throw new RuntimeException(
                    'Seat-top-up payment attempt lost its durable seat-change request.'
                );
            }

            $requestState =
                billing_seat_change_row_contract(
                    $lockedRequest
                );

            $requestContract =
                $requestState['contract'];

            /*
             * Candidate selection occurred before provider/network I/O.
             * Revalidate that another return/webhook path has not already
             * settled or applied this durable request while verification was
             * in flight. Never apply a stale provider fact to a request that
             * has left awaiting_payment.
             */
            if (($requestState['status'] ?? '')
                    !== 'awaiting_payment'
                || (int)$requestState['id']
                    !== $requestId
                || (int)(
                    $requestContract['farm_id']
                        ?? 0
                ) !== $farmId
                || ($requestContract[
                    'change_kind'
                ] ?? '') !== 'add'
                || ($requestContract[
                    'role_code'
                ] ?? '') !== $roleCode
                || (int)(
                    $requestContract[
                        'payment_attempt_id'
                    ] ?? 0
                ) !== $attemptId) {
                throw new RuntimeException(
                    'Seat-top-up durable request changed during provider verification.'
                );
            }

            $updated =
                billing_audit_apply_verification(
                    $pdo,
                    $attemptId,
                    $verification
                );

            if ((int)($updated['farm_id'] ?? 0)
                    !== $farmId
                || billing_payment_attempt_purpose(
                    $updated
                ) !== 'seat_topup') {
                throw new RuntimeException(
                    'Verified seat-top-up payment identity changed unexpectedly.'
                );
            }

            $status = strtolower(trim(
                (string)($updated['status'] ?? '')
            ));

            $application = null;
            $terminal = null;
            $outcome = '';
            $blocking = false;

            if ($status === 'paid') {
                $application =
                    billing_paid_attempt_dispatch(
                        $pdo,
                        $attemptId
                    );

                $outcome = 'paid_applied';
                $blocking = true;
            } elseif ($status === 'pending') {
                $outcome = 'pending_blocked';
                $blocking = true;
            } elseif (in_array(
                $status,
                ['failed', 'cancelled'],
                true
            )) {
                $terminal =
                    billing_seat_change_reconcile_terminal_payment(
                        $pdo,
                        $attemptId
                    );

                if (($terminal['handled'] ?? false)
                    !== true) {
                    throw new RuntimeException(
                        'Terminal seat-top-up payment was not reconciled to its durable request.'
                    );
                }

                $outcome = 'terminal_settled';
                $blocking = false;
            } elseif ($status === 'refunded') {
                $terminal =
                    billing_seat_change_reconcile_terminal_payment(
                        $pdo,
                        $attemptId
                    );

                if (($terminal['handled'] ?? false)
                    !== true) {
                    throw new RuntimeException(
                        'Refunded seat-top-up payment was not reconciled to its durable request.'
                    );
                }

                $outcome = 'refunded_settled';
                $blocking = false;
            } else {
                throw new RuntimeException(
                    'Provider reconciliation produced an unsupported seat-top-up payment state.'
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
            'outcome' => $outcome,
            'farm_id' => $farmId,
            'role_code' => $roleCode,
            'attempt_id' => $attemptId,
            'request_id' => $requestId,
            'blocking' => $blocking,
            'provider' => $provider,
            'provider_reference' =>
                $providerReference,
            'verification' => $verification,
            'application' => $application,
            'terminal_reconciliation' =>
                $terminal,
        ];
    }
}

if (!function_exists(
    'billing_seat_topup_reconcile_open_candidates_for_replacement'
)) {
    function billing_seat_topup_reconcile_open_candidates_for_replacement(
        PDO $pdo,
        int $farmId,
        int $maxAttempts = 10
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for bounded seat-top-up reconciliation.'
            );
        }

        if ($maxAttempts < 1
            || $maxAttempts > 25) {
            throw new InvalidArgumentException(
                'Seat-top-up reconciliation attempt limit must be between 1 and 25.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Bounded seat-top-up reconciliation must start outside a database transaction.'
            );
        }

        $reconciliations = [];
        $seenAttemptIds = [];

        for (
            $iteration = 0;
            $iteration < $maxAttempts;
            $iteration++
        ) {
            $result =
                billing_seat_topup_reconcile_next_for_replacement(
                    $pdo,
                    $farmId
                );

            if ($pdo->inTransaction()) {
                throw new RuntimeException(
                    'Seat-top-up replacement reconciliation unexpectedly left a database transaction open.'
                );
            }

            if (($result['attempt_found'] ?? false)
                !== true) {
                if (($result['outcome'] ?? '')
                    !== 'none') {
                    throw new RuntimeException(
                        'Seat-top-up replacement reconciliation returned an invalid empty-candidate outcome.'
                    );
                }

                return [
                    'farm_id' => $farmId,
                    'reconciled_count' =>
                        count($reconciliations),
                    'replacement_allowed' => true,
                    'stopped_reason' =>
                        'exhausted',
                    'stopped_attempt_id' => null,
                    'stopped_role_code' => null,
                    'reconciliations' =>
                        $reconciliations,
                ];
            }

            $attemptId =
                (int)($result['attempt_id'] ?? 0);

            if ($attemptId < 1) {
                throw new RuntimeException(
                    'Seat-top-up replacement reconciliation returned an invalid attempt identity.'
                );
            }

            if (isset(
                $seenAttemptIds[$attemptId]
            )) {
                throw new RuntimeException(
                    'Seat-top-up replacement reconciliation repeated the same payment attempt.'
                );
            }

            $seenAttemptIds[$attemptId] = true;
            $reconciliations[] = $result;

            $outcome =
                (string)($result['outcome'] ?? '');

            $blocking =
                $result['blocking'] ?? null;

            if (!in_array(
                $outcome,
                [
                    'initialized_blocked',
                    'pending_blocked',
                    'paid_applied',
                    'terminal_settled',
                    'refunded_settled',
                ],
                true
            )
                || !is_bool($blocking)) {
                throw new RuntimeException(
                    'Seat-top-up replacement reconciliation returned an unsupported canonical outcome.'
                );
            }

            if ($blocking === true) {
                if (!in_array(
                    $outcome,
                    [
                        'initialized_blocked',
                        'pending_blocked',
                        'paid_applied',
                    ],
                    true
                )) {
                    throw new RuntimeException(
                        'Settled seat-top-up outcome unexpectedly remained blocking.'
                    );
                }

                return [
                    'farm_id' => $farmId,
                    'reconciled_count' =>
                        count($reconciliations),
                    'replacement_allowed' => false,
                    'stopped_reason' => $outcome,
                    'stopped_attempt_id' =>
                        $attemptId,
                    'stopped_role_code' =>
                        (string)(
                            $result['role_code']
                                ?? ''
                        ),
                    'reconciliations' =>
                        $reconciliations,
                ];
            }

            if (!in_array(
                $outcome,
                [
                    'terminal_settled',
                    'refunded_settled',
                ],
                true
            )) {
                throw new RuntimeException(
                    'Seat-top-up replacement reconciliation cannot continue after this outcome.'
                );
            }
        }

        /*
         * Re-check without provider work before declaring the finite safety
         * bound exhausted.
         */
        $remaining =
            billing_seat_topup_reconciliation_candidate(
                $pdo,
                $farmId
            );

        if ($remaining !== null) {
            throw new RuntimeException(
                'Seat-top-up replacement reconciliation reached its bounded attempt limit before open candidates were exhausted.'
            );
        }

        return [
            'farm_id' => $farmId,
            'reconciled_count' =>
                count($reconciliations),
            'replacement_allowed' => true,
            'stopped_reason' => 'exhausted',
            'stopped_attempt_id' => null,
            'stopped_role_code' => null,
            'reconciliations' => $reconciliations,
        ];
    }
}

?>
