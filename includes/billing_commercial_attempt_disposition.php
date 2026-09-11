<?php
/**
 * V2.3 commercial-attempt disposition foundation.
 *
 * Provider status and commercial applicability are intentionally separate:
 *
 * - status remains the provider payment fact;
 * - commercial_disposition says whether a subscription attempt may still
 *   affect tenant commercial state.
 *
 * Supersession is deliberately narrow:
 *
 * - subscription purpose only;
 * - caller-owned transaction;
 * - payment attempt locked first, then tenant farm;
 * - only provider-verified failed/cancelled attempts may be superseded;
 * - paid/applied/open attempts cannot be superseded;
 * - the exact verification timestamp that justified supersession is retained;
 * - no payment status, entitlement, subscription, provider or network work
 *   occurs here.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_payment_audit_state.php';

if (!function_exists(
    'billing_commercial_attempt_dispositions'
)) {
    function billing_commercial_attempt_dispositions(): array
    {
        return [
            'eligible',
            'superseded',
        ];
    }
}

if (!function_exists(
    'billing_commercial_attempt_normalize_disposition'
)) {
    function billing_commercial_attempt_normalize_disposition(
        string $value
    ): string {
        $value = strtolower(trim($value));

        if (!in_array(
            $value,
            billing_commercial_attempt_dispositions(),
            true
        )) {
            throw new RuntimeException(
                'Unsupported commercial payment-attempt disposition.'
            );
        }

        return $value;
    }
}

if (!function_exists(
    'billing_commercial_attempt_supersession_reason'
)) {
    function billing_commercial_attempt_supersession_reason(
        string $reason
    ): string {
        $reason = strtolower(trim($reason));

        if ($reason === ''
            || strlen($reason) > 80
            || !preg_match(
                '/^[a-z0-9][a-z0-9_]*$/',
                $reason
            )) {
            throw new InvalidArgumentException(
                'A valid commercial supersession reason is required.'
            );
        }

        return $reason;
    }
}

if (!function_exists(
    'billing_commercial_attempt_disposition_columns'
)) {
    function billing_commercial_attempt_disposition_columns(): array
    {
        return [
            'commercial_disposition',
            'commercial_superseded_at',
            'commercial_supersession_verified_at',
            'commercial_superseded_by_user_id',
            'commercial_supersession_reason',
        ];
    }
}

if (!function_exists(
    'billing_commercial_attempt_disposition_storage_ready'
)) {
    function billing_commercial_attempt_disposition_storage_ready(
        PDO $pdo
    ): bool {
        if (!billing_payment_foundation_ready($pdo)) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?'
        );

        $stmt->execute([
            'billing_payment_attempts',
        ]);

        $columns = array_fill_keys(
            $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [],
            true
        );

        foreach (
            billing_commercial_attempt_disposition_columns()
            as $column
        ) {
            if (!isset($columns[$column])) {
                return false;
            }
        }

        $index = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?'
        );

        $index->execute([
            'billing_payment_attempts',
            'idx_billing_attempt_farm_purpose_disposition_status',
        ]);

        return (int)$index->fetchColumn() > 0;
    }
}

if (!function_exists(
    'billing_commercial_attempt_disposition_state'
)) {
    function billing_commercial_attempt_disposition_state(
        array $attempt
    ): array {
        if (!array_key_exists(
            'commercial_disposition',
            $attempt
        )) {
            throw new RuntimeException(
                'Commercial payment-attempt disposition is unavailable.'
            );
        }

        $disposition =
            billing_commercial_attempt_normalize_disposition(
                (string)$attempt[
                    'commercial_disposition'
                ]
            );

        $supersededAt =
            billing_audit_datetime(
                $attempt[
                    'commercial_superseded_at'
                ] ?? null
            );

        $supersessionVerifiedAt =
            billing_audit_datetime(
                $attempt[
                    'commercial_supersession_verified_at'
                ] ?? null
            );

        $supersededByUserId =
            (int)(
                $attempt[
                    'commercial_superseded_by_user_id'
                ] ?? 0
            );

        $reasonRaw =
            trim((string)(
                $attempt[
                    'commercial_supersession_reason'
                ] ?? ''
            ));

        if ($disposition === 'eligible') {
            if ($supersededAt !== null
                || $supersessionVerifiedAt !== null
                || $supersededByUserId > 0
                || $reasonRaw !== '') {
                throw new RuntimeException(
                    'Eligible commercial payment attempt contains supersession metadata.'
                );
            }

            return [
                'disposition' => 'eligible',
                'superseded_at' => null,
                'supersession_verified_at' => null,
                'superseded_by_user_id' => null,
                'supersession_reason' => null,
            ];
        }

        if ($supersededAt === null
            || $supersessionVerifiedAt === null
            || $supersededByUserId < 1
            || $reasonRaw === '') {
            throw new RuntimeException(
                'Superseded commercial payment attempt is missing audit evidence.'
            );
        }

        $reason =
            billing_commercial_attempt_supersession_reason(
                $reasonRaw
            );

        if ((int)(
            $attempt[
                'applied_subscription_record_id'
            ] ?? 0
        ) > 0) {
            throw new RuntimeException(
                'An applied subscription payment attempt cannot be superseded.'
            );
        }

        return [
            'disposition' => 'superseded',
            'superseded_at' => $supersededAt,
            'supersession_verified_at' =>
                $supersessionVerifiedAt,
            'superseded_by_user_id' =>
                $supersededByUserId,
            'supersession_reason' => $reason,
        ];
    }
}

if (!function_exists(
    'billing_commercial_attempt_mark_superseded_after_verified_terminal'
)) {
    function billing_commercial_attempt_mark_superseded_after_verified_terminal(
        PDO $pdo,
        int $attemptId,
        int $actorUserId,
        string $expectedVerifiedAt,
        string $reason = 'customer_replaced_checkout'
    ): array {
        if ($attemptId < 1) {
            throw new InvalidArgumentException(
                'A valid subscription payment attempt is required for supersession.'
            );
        }

        if ($actorUserId < 1) {
            throw new InvalidArgumentException(
                'A valid user is required for commercial supersession.'
            );
        }

        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Commercial attempt supersession requires an active caller transaction.'
            );
        }

        if (!billing_commercial_attempt_disposition_storage_ready(
            $pdo
        )) {
            throw new RuntimeException(
                'Commercial attempt disposition storage is not ready.'
            );
        }

        $expectedVerifiedAt =
            billing_audit_datetime(
                $expectedVerifiedAt
            );

        if ($expectedVerifiedAt === null) {
            throw new InvalidArgumentException(
                'Commercial supersession requires an exact provider verification timestamp.'
            );
        }

        $reason =
            billing_commercial_attempt_supersession_reason(
                $reason
            );

        /*
         * Lock order deliberately matches verified paid application:
         *
         * payment attempt -> tenant farm.
         *
         * Farm-first commercial coordination never locks attempt rows.
         */
        $attempt =
            billing_audit_attempt_by_id(
                $pdo,
                $attemptId,
                true
            );

        if (!is_array($attempt)) {
            throw new RuntimeException(
                'Subscription payment attempt could not be found for supersession.'
            );
        }

        if (billing_payment_attempt_purpose(
            $attempt
        ) !== 'subscription') {
            throw new RuntimeException(
                'Only subscription payment attempts may be commercially superseded.'
            );
        }

        $farmId =
            (int)($attempt['farm_id'] ?? 0);

        if ($farmId < 1) {
            throw new RuntimeException(
                'Subscription payment attempt has an invalid tenant identity.'
            );
        }

        $state =
            billing_commercial_attempt_disposition_state(
                $attempt
            );

        if (($state['disposition'] ?? '')
            === 'superseded') {
            if (!hash_equals(
                (string)$state[
                    'supersession_verified_at'
                ],
                $expectedVerifiedAt
            )) {
                throw new RuntimeException(
                    'Existing commercial supersession belongs to a different provider verification.'
                );
            }

            return [
                'superseded' => false,
                'idempotent' => true,
                'attempt_id' => $attemptId,
                'farm_id' => $farmId,
                'disposition' => 'superseded',
                'attempt' => $attempt,
            ];
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
                'Only provider-verified failed or cancelled subscription attempts may be superseded.'
            );
        }

        if ((int)(
            $attempt[
                'applied_subscription_record_id'
            ] ?? 0
        ) > 0) {
            throw new RuntimeException(
                'An applied subscription payment attempt cannot be superseded.'
            );
        }

        if (billing_audit_datetime(
            $attempt['paid_at'] ?? null
        ) !== null) {
            throw new RuntimeException(
                'A payment attempt with paid evidence cannot be superseded.'
            );
        }

        $actualVerifiedAt =
            billing_audit_datetime(
                $attempt['verified_at'] ?? null
            );

        if ($actualVerifiedAt === null
            || !hash_equals(
                $actualVerifiedAt,
                $expectedVerifiedAt
            )) {
            throw new RuntimeException(
                'Commercial supersession must bind to the exact provider verification that produced the terminal state.'
            );
        }

        $farmStmt = $pdo->prepare(
            "SELECT id
             FROM farms
             WHERE id = ?
               AND slug <> 'owner'
             LIMIT 1
             FOR UPDATE"
        );

        $farmStmt->execute([
            $farmId,
        ]);

        if (!$farmStmt->fetchColumn()) {
            throw new RuntimeException(
                'Tenant farm could not be locked for commercial supersession.'
            );
        }

        $supersededAt =
            date('Y-m-d H:i:s');

        $update = $pdo->prepare(
            "UPDATE billing_payment_attempts
             SET commercial_disposition = 'superseded',
                 commercial_superseded_at = ?,
                 commercial_supersession_verified_at = ?,
                 commercial_superseded_by_user_id = ?,
                 commercial_supersession_reason = ?
             WHERE id = ?
               AND farm_id = ?
               AND purpose = 'subscription'
               AND commercial_disposition = 'eligible'
               AND status IN ('failed', 'cancelled')
               AND verified_at = ?
               AND paid_at IS NULL
               AND applied_subscription_record_id IS NULL"
        );

        $update->execute([
            $supersededAt,
            $expectedVerifiedAt,
            $actorUserId,
            $reason,
            $attemptId,
            $farmId,
            $expectedVerifiedAt,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Commercial payment-attempt supersession lost its verified terminal state.'
            );
        }

        $reloaded =
            billing_audit_attempt_by_id(
                $pdo,
                $attemptId,
                false
            );

        if (!is_array($reloaded)) {
            throw new RuntimeException(
                'Superseded payment attempt could not be reloaded.'
            );
        }

        $reloadedState =
            billing_commercial_attempt_disposition_state(
                $reloaded
            );

        if (($reloadedState['disposition'] ?? '')
                !== 'superseded'
            || !hash_equals(
                (string)$reloadedState[
                    'superseded_at'
                ],
                $supersededAt
            )
            || !hash_equals(
                (string)$reloadedState[
                    'supersession_verified_at'
                ],
                $expectedVerifiedAt
            )
            || (int)$reloadedState[
                'superseded_by_user_id'
            ] !== $actorUserId
            || !hash_equals(
                (string)$reloadedState[
                    'supersession_reason'
                ],
                $reason
            )) {
            throw new RuntimeException(
                'Commercial supersession audit evidence was not persisted exactly.'
            );
        }

        return [
            'superseded' => true,
            'idempotent' => false,
            'attempt_id' => $attemptId,
            'farm_id' => $farmId,
            'disposition' => 'superseded',
            'superseded_at' => $supersededAt,
            'supersession_verified_at' =>
                $expectedVerifiedAt,
            'superseded_by_user_id' =>
                $actorUserId,
            'supersession_reason' =>
                $reason,
            'attempt' => $reloaded,
        ];
    }
}
?>
