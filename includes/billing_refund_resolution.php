<?php
/**
 * V2.3 post-application refund-resolution foundation.
 *
 * Provider payment fact and commercial refund response remain separate:
 *
 * - billing_payment_attempts.status='refunded' is provider/audit fact;
 * - billing_refund_resolutions records what commercial review must do next.
 *
 * Provider calls remain forbidden here. Explicit commercial review may
 * preserve or compensate already-applied entitlement, but provider/payment
 * audit fact is never rewritten by this service.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_payment_audit_state.php';
require_once __DIR__ . '/billing_seat_change_request.php';
require_once __DIR__ . '/subscription_plan_catalog.php';
require_once __DIR__ . '/farm_entitlements.php';
require_once __DIR__ . '/subscription_seat_policy.php';
require_once __DIR__ . '/subscription_record.php';

if (!function_exists(
    'billing_refund_resolution_statuses'
)) {
    function billing_refund_resolution_statuses(): array
    {
        return [
            'pending_review',
            'resolved',
        ];
    }
}

if (!function_exists(
    'billing_refund_resolution_actions'
)) {
    function billing_refund_resolution_actions(): array
    {
        return [
            'preserve_entitlement',
            'reverse_entitlement',
        ];
    }
}

if (!function_exists(
    'billing_refund_resolution_required_columns'
)) {
    function billing_refund_resolution_required_columns(): array
    {
        return [
            'id',
            'farm_id',
            'payment_attempt_id',
            'purpose',
            'status',
            'resolution_action',
            'refund_verified_at',
            'applied_subscription_record_id',
            'seat_change_request_id',
            'reversal_subscription_record_id',
            'resolved_at',
            'resolved_by_user_id',
            'resolution_reason',
            'created_at',
            'updated_at',
        ];
    }
}

if (!function_exists(
    'billing_refund_resolution_table_exists'
)) {
    function billing_refund_resolution_table_exists(
        PDO $pdo
    ): bool {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name =
                   'billing_refund_resolutions'"
        );

        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists(
    'billing_refund_resolution_table_ready'
)) {
    function billing_refund_resolution_table_ready(
        PDO $pdo
    ): bool {
        if (!billing_refund_resolution_table_exists($pdo)) {
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name =
                   'billing_refund_resolutions'"
        );

        $stmt->execute();

        $columns = array_fill_keys(
            $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [],
            true
        );

        foreach (
            billing_refund_resolution_required_columns()
            as $column
        ) {
            if (!isset($columns[$column])) {
                return false;
            }
        }

        $engineStmt = $pdo->prepare(
            "SELECT engine
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name =
                   'billing_refund_resolutions'
             LIMIT 1"
        );

        $engineStmt->execute();

        $engine = $engineStmt->fetchColumn();

        return is_string($engine)
            && strcasecmp($engine, 'InnoDB') === 0;
    }
}

if (!function_exists(
    'billing_refund_resolution_foreign_keys_ready'
)) {
    function billing_refund_resolution_foreign_keys_ready(
        PDO $pdo
    ): bool {
        $expected = [
            'fk_billing_refund_farm' => [
                'billing_refund_resolutions',
                'farms',
            ],
            'fk_billing_refund_payment_attempt' => [
                'billing_refund_resolutions',
                'billing_payment_attempts',
            ],
            'fk_billing_refund_subscription' => [
                'billing_refund_resolutions',
                'subscriptions',
            ],
            'fk_billing_refund_seat_request' => [
                'billing_refund_resolutions',
                'billing_seat_change_requests',
            ],
            'fk_billing_refund_reversal_subscription' => [
                'billing_refund_resolutions',
                'subscriptions',
            ],
        ];

        $stmt = $pdo->prepare(
            "SELECT
                 table_name,
                 referenced_table_name,
                 delete_rule
             FROM information_schema.referential_constraints
             WHERE constraint_schema = DATABASE()
               AND constraint_name = ?
             LIMIT 1"
        );

        foreach ($expected as $name => $definition) {
            $stmt->execute([$name]);

            $row =
                $stmt->fetch(PDO::FETCH_ASSOC)
                ?: null;

            $deleteRule = $row
                ? strtoupper(trim(
                    (string)($row['delete_rule'] ?? '')
                ))
                : '';

            if (!$row
                || (string)$row['table_name']
                    !== $definition[0]
                || (string)$row['referenced_table_name']
                    !== $definition[1]
                || !in_array(
                    $deleteRule,
                    ['RESTRICT', 'NO ACTION'],
                    true
                )) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists(
    'billing_refund_resolution_unique_key_ready'
)) {
    function billing_refund_resolution_unique_key_ready(
        PDO $pdo
    ): bool {
        $stmt = $pdo->prepare(
            "SELECT
                 column_name,
                 seq_in_index
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name =
                   'billing_refund_resolutions'
               AND index_name = ?
               AND non_unique = 0
             ORDER BY seq_in_index ASC"
        );

        $stmt->execute([
            'uniq_billing_refund_payment_attempt',
        ]);

        $rows =
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            ?: [];

        return count($rows) === 1
            && (string)(
                $rows[0]['column_name'] ?? ''
            ) === 'payment_attempt_id'
            && (int)(
                $rows[0]['seq_in_index'] ?? 0
            ) === 1;
    }
}

if (!function_exists(
    'billing_refund_resolution_migration_ready'
)) {
    function billing_refund_resolution_migration_ready(
        PDO $pdo
    ): bool {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM schema_migrations
             WHERE filename IN (?, ?)"
        );

        $stmt->execute([
            '050_billing_refund_resolution.sql',
            '052_billing_refund_reversal_history_link.sql',
        ]);

        return (int)$stmt->fetchColumn() === 2;
    }
}

if (!function_exists(
    'billing_refund_resolution_ready'
)) {
    function billing_refund_resolution_ready(
        PDO $pdo
    ): bool {
        return billing_payment_foundation_ready($pdo)
            && billing_refund_resolution_table_ready($pdo)
            && billing_refund_resolution_foreign_keys_ready(
                $pdo
            )
            && billing_refund_resolution_unique_key_ready(
                $pdo
            )
            && billing_refund_resolution_migration_ready(
                $pdo
            );
    }
}

if (!function_exists(
    'billing_refund_resolution_normalize_status'
)) {
    function billing_refund_resolution_normalize_status(
        string $status
    ): string {
        $status = strtolower(trim($status));

        if (!in_array(
            $status,
            billing_refund_resolution_statuses(),
            true
        )) {
            throw new RuntimeException(
                'Unsupported billing refund-resolution status.'
            );
        }

        return $status;
    }
}

if (!function_exists(
    'billing_refund_resolution_normalize_action'
)) {
    function billing_refund_resolution_normalize_action(
        string $action
    ): string {
        $action = strtolower(trim($action));

        if (!in_array(
            $action,
            billing_refund_resolution_actions(),
            true
        )) {
            throw new RuntimeException(
                'Unsupported billing refund-resolution action.'
            );
        }

        return $action;
    }
}

if (!function_exists(
    'billing_refund_resolution_reason'
)) {
    function billing_refund_resolution_reason(
        string $reason
    ): string {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'A refund-resolution reason is required.'
            );
        }

        if (function_exists('mb_strlen')) {
            $length = mb_strlen(
                $reason,
                'UTF-8'
            );
        } else {
            $matchCount = preg_match_all(
                '/./us',
                $reason,
                $matches
            );

            if ($matchCount === false) {
                throw new InvalidArgumentException(
                    'Refund-resolution reason must be valid UTF-8.'
                );
            }

            $length = $matchCount;
        }

        if ($length > 160) {
            throw new InvalidArgumentException(
                'Refund-resolution reason cannot exceed 160 characters.'
            );
        }

        return $reason;
    }
}

if (!function_exists(
    'billing_refund_resolution_row_contract'
)) {
    function billing_refund_resolution_row_contract(
        array $row
    ): array {
        $id = (int)($row['id'] ?? 0);
        $farmId = (int)($row['farm_id'] ?? 0);
        $attemptId =
            (int)($row['payment_attempt_id'] ?? 0);

        if ($id < 1 || $farmId < 1 || $attemptId < 1) {
            throw new RuntimeException(
                'Refund-resolution identity is invalid.'
            );
        }

        $purpose = billing_payment_normalize_purpose(
            (string)($row['purpose'] ?? '')
        );

        $status =
            billing_refund_resolution_normalize_status(
                (string)($row['status'] ?? '')
            );

        $refundVerifiedAt = billing_audit_datetime(
            $row['refund_verified_at'] ?? null
        );

        if ($refundVerifiedAt === null) {
            throw new RuntimeException(
                'Refund resolution is missing provider verification evidence.'
            );
        }

        $subscriptionRecordId =
            (int)(
                $row['applied_subscription_record_id']
                ?? 0
            );

        $seatChangeRequestId =
            (int)(
                $row['seat_change_request_id']
                ?? 0
            );

        $reversalSubscriptionRecordId =
            (int)(
                $row['reversal_subscription_record_id']
                ?? 0
            );

        if ($purpose === 'subscription') {
            if ($subscriptionRecordId < 1
                || $seatChangeRequestId > 0) {
                throw new RuntimeException(
                    'Subscription refund resolution must bind only to its applied subscription record.'
                );
            }
        } elseif ($purpose === 'seat_topup') {
            if ($seatChangeRequestId < 1
                || $subscriptionRecordId > 0) {
                throw new RuntimeException(
                    'Seat-top-up refund resolution must bind only to its applied seat request.'
                );
            }
        } else {
            throw new RuntimeException(
                'Unsupported refund-resolution payment purpose.'
            );
        }

        $actionRaw = trim((string)(
            $row['resolution_action'] ?? ''
        ));

        $resolvedAt = billing_audit_datetime(
            $row['resolved_at'] ?? null
        );

        $resolvedByUserId =
            (int)($row['resolved_by_user_id'] ?? 0);

        $reasonRaw = trim((string)(
            $row['resolution_reason'] ?? ''
        ));

        if ($status === 'pending_review') {
            if ($actionRaw !== ''
                || $resolvedAt !== null
                || $resolvedByUserId > 0
                || $reasonRaw !== ''
                || $reversalSubscriptionRecordId > 0) {
                throw new RuntimeException(
                    'Pending refund resolution contains resolved metadata.'
                );
            }

            $action = null;
            $reason = null;
        } else {
            $action =
                billing_refund_resolution_normalize_action(
                    $actionRaw
                );

            if ($resolvedAt === null
                || $resolvedByUserId < 1) {
                throw new RuntimeException(
                    'Resolved refund resolution is missing audit evidence.'
                );
            }

            if (
                $action === 'preserve_entitlement'
                && $reversalSubscriptionRecordId > 0
            ) {
                throw new RuntimeException(
                    'Preserved refund resolution cannot reference compensating subscription history.'
                );
            }

            if (
                $action === 'reverse_entitlement'
                && $reversalSubscriptionRecordId < 1
            ) {
                throw new RuntimeException(
                    'Reversed refund resolution is missing compensating subscription history.'
                );
            }

            $reason =
                billing_refund_resolution_reason(
                    $reasonRaw
                );
        }

        return [
            'id' => $id,
            'farm_id' => $farmId,
            'payment_attempt_id' => $attemptId,
            'purpose' => $purpose,
            'status' => $status,
            'resolution_action' => $action,
            'refund_verified_at' => $refundVerifiedAt,
            'applied_subscription_record_id' =>
                $subscriptionRecordId > 0
                    ? $subscriptionRecordId
                    : null,
            'seat_change_request_id' =>
                $seatChangeRequestId > 0
                    ? $seatChangeRequestId
                    : null,
            'reversal_subscription_record_id' =>
                $reversalSubscriptionRecordId > 0
                    ? $reversalSubscriptionRecordId
                    : null,
            'resolved_at' => $resolvedAt,
            'resolved_by_user_id' =>
                $resolvedByUserId > 0
                    ? $resolvedByUserId
                    : null,
            'resolution_reason' => $reason,
        ];
    }
}

if (!function_exists(
    'billing_refund_resolution_by_payment'
)) {
    function billing_refund_resolution_by_payment(
        PDO $pdo,
        int $paymentAttemptId,
        bool $forUpdate = false
    ): ?array {
        if ($paymentAttemptId < 1) {
            throw new InvalidArgumentException(
                'A valid billing payment attempt is required.'
            );
        }

        if ($forUpdate && !$pdo->inTransaction()) {
            throw new RuntimeException(
                'Refund-resolution row locking requires an active database transaction.'
            );
        }

        $sql =
            "SELECT *
             FROM billing_refund_resolutions
             WHERE payment_attempt_id = ?
             LIMIT 1";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$paymentAttemptId]);

        $row =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if ($row === null) {
            return null;
        }

        billing_refund_resolution_row_contract($row);

        return $row;
    }
}

if (!function_exists(
    'billing_refund_resolution_capture_verified'
)) {
    /**
     * Record the separate commercial review required when a
     * provider-verified refund belongs to commercial state that
     * was already applied.
     *
     * This function never calls a provider and never changes
     * subscription, entitlement, seat or payment state.
     */
    function billing_refund_resolution_capture_verified(
        PDO $pdo,
        int $paymentAttemptId
    ): array {
        if ($paymentAttemptId < 1) {
            throw new InvalidArgumentException(
                'A valid billing payment attempt is required for refund capture.'
            );
        }

        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Refund-resolution capture requires an active database transaction.'
            );
        }

        if (!billing_refund_resolution_ready($pdo)) {
            throw new RuntimeException(
                'Refund-resolution storage is not ready.'
            );
        }

        /*
         * Canonical lock order begins with the payment attempt.
         */
        $attempt =
            billing_audit_attempt_by_id(
                $pdo,
                $paymentAttemptId,
                true
            );

        if (!$attempt) {
            throw new RuntimeException(
                'Billing payment attempt could not be found for refund capture.'
            );
        }

        $paymentStatus = strtolower(trim(
            (string)($attempt['status'] ?? '')
        ));

        if ($paymentStatus !== 'refunded') {
            return [
                'handled' => false,
                'captured' => false,
                'idempotent' => true,
                'reason' => 'payment_not_refunded',
                'resolution' => null,
            ];
        }

        $farmId =
            (int)($attempt['farm_id'] ?? 0);

        if ($farmId < 1) {
            throw new RuntimeException(
                'Refunded billing attempt has an invalid tenant.'
            );
        }

        $purpose =
            billing_payment_attempt_purpose(
                $attempt
            );

        $refundVerifiedAt =
            billing_audit_datetime(
                $attempt['verified_at'] ?? null
            );

        if ($refundVerifiedAt === null) {
            throw new RuntimeException(
                'Refunded billing attempt is missing provider verification evidence.'
            );
        }

        $subscriptionRecordId = null;
        $seatChangeRequestId = null;

        /*
         * Lock the durable refund-resolution row or unique-key gap
         * second, before purpose-specific commercial context.
         *
         * Canonical order:
         * payment attempt -> refund resolution -> purpose context.
         */
        $existing =
            billing_refund_resolution_by_payment(
                $pdo,
                $paymentAttemptId,
                true
            );

        if ($purpose === 'subscription') {
            $subscriptionRecordId =
                (int)(
                    $attempt[
                        'applied_subscription_record_id'
                    ] ?? 0
                );

            /*
             * No application means there is no commercial state
             * requiring refund resolution.
             */
            if ($subscriptionRecordId < 1) {
                return [
                    'handled' => true,
                    'captured' => false,
                    'idempotent' => true,
                    'reason' =>
                        'refund_not_post_application',
                    'resolution' => null,
                ];
            }

            if (billing_audit_datetime(
                $attempt['paid_at'] ?? null
            ) === null) {
                throw new RuntimeException(
                    'Applied refunded subscription attempt is missing its paid evidence.'
                );
            }

            $subscriptionStmt =
                $pdo->prepare(
                    "SELECT id, farm_id
                     FROM subscriptions
                     WHERE id = ?
                       AND farm_id = ?
                     LIMIT 1"
                );

            $subscriptionStmt->execute([
                $subscriptionRecordId,
                $farmId,
            ]);

            $subscription =
                $subscriptionStmt->fetch(
                    PDO::FETCH_ASSOC
                ) ?: null;

            if (!$subscription
                || (int)$subscription['id']
                    !== $subscriptionRecordId
                || (int)$subscription['farm_id']
                    !== $farmId) {
                throw new RuntimeException(
                    'Refunded subscription attempt points to missing or cross-tenant application history.'
                );
            }
        } elseif ($purpose === 'seat_topup') {
            /*
             * Provider routes call refund capture before terminal seat
             * reconciliation so transaction-wide locking remains:
             * payment attempt -> refund resolution -> seat request.
             */
            $requestRow =
                billing_seat_change_request_by_payment(
                    $pdo,
                    $paymentAttemptId,
                    true
                );

            if (!$requestRow) {
                throw new RuntimeException(
                    'Refunded seat-top-up attempt has no durable seat-change request.'
                );
            }

            $requestState =
                billing_seat_change_row_contract(
                    $requestRow
                );

            $requestContract =
                $requestState['contract'];

            if (($requestContract['change_kind'] ?? '')
                    !== 'add'
                || (int)(
                    $requestContract[
                        'payment_attempt_id'
                    ] ?? 0
                ) !== $paymentAttemptId
                || (int)(
                    $requestContract['farm_id']
                        ?? 0
                ) !== $farmId) {
                throw new RuntimeException(
                    'Refunded seat-top-up attempt does not match its durable seat request.'
                );
            }

            /*
             * An unapplied refunded top-up is handled by existing
             * terminal reconciliation. Only applied capacity needs
             * commercial review.
             */
            if ($requestState['status'] !== 'applied') {
                return [
                    'handled' => true,
                    'captured' => false,
                    'idempotent' => true,
                    'reason' =>
                        'refund_not_post_application',
                    'resolution' => null,
                ];
            }

            if (trim((string)(
                $requestState['applied_at'] ?? ''
            )) === '') {
                throw new RuntimeException(
                    'Applied refunded seat-top-up request is missing its application timestamp.'
                );
            }

            if (billing_audit_datetime(
                $attempt['paid_at'] ?? null
            ) === null) {
                throw new RuntimeException(
                    'Applied refunded seat-top-up attempt is missing its paid evidence.'
                );
            }

            $seatChangeRequestId =
                (int)$requestState['id'];

            if ($seatChangeRequestId < 1) {
                throw new RuntimeException(
                    'Applied refunded seat-top-up request has an invalid identity.'
                );
            }
        } else {
            throw new RuntimeException(
                'Unsupported payment purpose for refund-resolution capture.'
            );
        }

        if ($existing !== null) {
            $contract =
                billing_refund_resolution_row_contract(
                    $existing
                );

            if ((int)$contract['farm_id'] !== $farmId
                || (int)$contract[
                    'payment_attempt_id'
                ] !== $paymentAttemptId
                || (string)$contract['purpose']
                    !== $purpose
                || (
                    $purpose === 'subscription'
                    && (int)$contract[
                        'applied_subscription_record_id'
                    ] !== $subscriptionRecordId
                )
                || (
                    $purpose === 'seat_topup'
                    && (int)$contract[
                        'seat_change_request_id'
                    ] !== $seatChangeRequestId
                )) {
                throw new RuntimeException(
                    'Existing refund resolution does not match the refunded commercial lineage.'
                );
            }

            return [
                'handled' => true,
                'captured' => false,
                'idempotent' => true,
                'reason' => 'already_captured',
                'resolution' => $existing,
            ];
        }

        $insert =
            $pdo->prepare(
                "INSERT INTO billing_refund_resolutions (
                    farm_id,
                    payment_attempt_id,
                    purpose,
                    status,
                    resolution_action,
                    refund_verified_at,
                    applied_subscription_record_id,
                    seat_change_request_id,
                    resolved_at,
                    resolved_by_user_id,
                    resolution_reason
                 ) VALUES (
                    ?,
                    ?,
                    ?,
                    'pending_review',
                    NULL,
                    ?,
                    ?,
                    ?,
                    NULL,
                    NULL,
                    NULL
                 )"
            );

        $insert->execute([
            $farmId,
            $paymentAttemptId,
            $purpose,
            $refundVerifiedAt,
            $subscriptionRecordId,
            $seatChangeRequestId,
        ]);

        if ($insert->rowCount() !== 1) {
            throw new RuntimeException(
                'Refund resolution could not be captured exactly once.'
            );
        }

        $resolution =
            billing_refund_resolution_by_payment(
                $pdo,
                $paymentAttemptId,
                false
            );

        if ($resolution === null) {
            throw new RuntimeException(
                'Captured refund resolution could not be reloaded.'
            );
        }

        $contract =
            billing_refund_resolution_row_contract(
                $resolution
            );

        if ((string)$contract['status']
                !== 'pending_review'
            || $contract['resolution_action']
                !== null
            || (int)$contract['farm_id']
                !== $farmId
            || (string)$contract['purpose']
                !== $purpose) {
            throw new RuntimeException(
                'Captured refund resolution failed its post-write contract.'
            );
        }

        return [
            'handled' => true,
            'captured' => true,
            'idempotent' => false,
            'reason' => 'post_application_refund',
            'resolution' => $resolution,
        ];
    }
}

if (!function_exists(
    'billing_refund_resolution_assert_locked_lineage'
)) {
    /**
     * Revalidate an already-captured refund-resolution row against
     * the provider-refunded payment attempt and its immutable
     * post-application commercial lineage.
     *
     * Caller lock order must already be:
     * payment attempt -> refund resolution.
     *
     * Seat-top-up validation extends that order with:
     * -> seat request.
     *
     * This helper performs no commercial mutation and no provider call.
     */
    function billing_refund_resolution_assert_locked_lineage(
        PDO $pdo,
        array $attempt,
        array $resolution
    ): array {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Refund-resolution lineage validation requires an active database transaction.'
            );
        }

        $attemptId =
            (int)($attempt['id'] ?? 0);

        $farmId =
            (int)($attempt['farm_id'] ?? 0);

        if ($attemptId < 1 || $farmId < 1) {
            throw new RuntimeException(
                'Refund-resolution payment lineage has an invalid identity.'
            );
        }

        $paymentStatus = strtolower(trim(
            (string)($attempt['status'] ?? '')
        ));

        if ($paymentStatus !== 'refunded') {
            throw new RuntimeException(
                'Only a provider-refunded payment attempt can resolve refund review.'
            );
        }

        if (billing_audit_datetime(
            $attempt['verified_at'] ?? null
        ) === null) {
            throw new RuntimeException(
                'Refunded payment attempt is missing provider verification evidence.'
            );
        }

        if (billing_audit_datetime(
            $attempt['paid_at'] ?? null
        ) === null) {
            throw new RuntimeException(
                'Post-application refunded payment attempt is missing paid evidence.'
            );
        }

        $purpose =
            billing_payment_attempt_purpose(
                $attempt
            );

        $contract =
            billing_refund_resolution_row_contract(
                $resolution
            );

        if ((int)$contract['payment_attempt_id']
                !== $attemptId
            || (int)$contract['farm_id']
                !== $farmId
            || (string)$contract['purpose']
                !== $purpose) {
            throw new RuntimeException(
                'Refund resolution does not match its refunded payment lineage.'
            );
        }

        if ($purpose === 'subscription') {
            $subscriptionRecordId =
                (int)(
                    $attempt[
                        'applied_subscription_record_id'
                    ] ?? 0
                );

            if ($subscriptionRecordId < 1
                || (int)(
                    $contract[
                        'applied_subscription_record_id'
                    ] ?? 0
                ) !== $subscriptionRecordId
                || $contract[
                    'seat_change_request_id'
                ] !== null) {
                throw new RuntimeException(
                    'Refunded subscription resolution does not match its applied subscription history.'
                );
            }

            $stmt =
                $pdo->prepare(
                    "SELECT id, farm_id
                     FROM subscriptions
                     WHERE id = ?
                       AND farm_id = ?
                     LIMIT 1"
                );

            $stmt->execute([
                $subscriptionRecordId,
                $farmId,
            ]);

            $subscription =
                $stmt->fetch(PDO::FETCH_ASSOC)
                ?: null;

            if (!$subscription
                || (int)$subscription['id']
                    !== $subscriptionRecordId
                || (int)$subscription['farm_id']
                    !== $farmId) {
                throw new RuntimeException(
                    'Refunded subscription resolution points to missing or cross-tenant history.'
                );
            }

            return [
                'attempt_id' => $attemptId,
                'farm_id' => $farmId,
                'purpose' => $purpose,
                'resolution_contract' => $contract,
                'applied_subscription_record_id' =>
                    $subscriptionRecordId,
                'seat_change_request_id' => null,
            ];
        }

        if ($purpose !== 'seat_topup') {
            throw new RuntimeException(
                'Unsupported payment purpose for refund resolution.'
            );
        }

        $requestRow =
            billing_seat_change_request_by_payment(
                $pdo,
                $attemptId,
                true
            );

        if (!$requestRow) {
            throw new RuntimeException(
                'Refunded seat-top-up resolution has no durable seat request.'
            );
        }

        $requestState =
            billing_seat_change_row_contract(
                $requestRow
            );

        $requestContract =
            $requestState['contract'];

        $seatChangeRequestId =
            (int)$requestState['id'];

        if ($seatChangeRequestId < 1
            || (int)(
                $contract[
                    'seat_change_request_id'
                ] ?? 0
            ) !== $seatChangeRequestId
            || $contract[
                'applied_subscription_record_id'
            ] !== null
            || ($requestContract[
                'change_kind'
            ] ?? '') !== 'add'
            || (int)(
                $requestContract[
                    'payment_attempt_id'
                ] ?? 0
            ) !== $attemptId
            || (int)(
                $requestContract['farm_id']
                    ?? 0
            ) !== $farmId) {
            throw new RuntimeException(
                'Refunded seat-top-up resolution does not match its applied seat request.'
            );
        }

        if ($requestState['status'] !== 'applied'
            || trim((string)(
                $requestState['applied_at'] ?? ''
            )) === '') {
            throw new RuntimeException(
                'Refund resolution requires an already-applied seat-top-up request.'
            );
        }

        $seatApplicationHistoryId =
            (int)(
                $requestState[
                    'applied_subscription_record_id'
                ] ?? 0
            );

        if ($seatApplicationHistoryId < 1) {
            throw new RuntimeException(
                'Refunded seat-top-up request is missing its immutable application-history link.'
            );
        }

        $seatApplicationSource =
            subscription_record_history_source_contract(
                $pdo,
                $farmId,
                $seatApplicationHistoryId
            );

        if (
            (int)$seatApplicationSource['id']
                !== $seatApplicationHistoryId
            || (int)$seatApplicationSource['farm_id']
                !== $farmId
        ) {
            throw new RuntimeException(
                'Refunded seat-top-up application history is not valid for this tenant.'
            );
        }

        return [
            'attempt_id' => $attemptId,
            'farm_id' => $farmId,
            'purpose' => $purpose,
            'resolution_contract' => $contract,
            'applied_subscription_record_id' => null,
            'seat_change_request_id' =>
                $seatChangeRequestId,
            'seat_change_applied_subscription_record_id' =>
                $seatApplicationHistoryId,
            'seat_request_contract' =>
                $requestContract,
        ];
    }
}

if (!function_exists(
    'billing_refund_resolution_resolve_preserve'
)) {
    /**
     * Resolve a captured post-application refund by deliberately
     * preserving the tenant's already-applied commercial state.
     *
     * This action updates only billing_refund_resolutions.
     * It never changes payment fact, subscription state, modules,
     * purchased seats, effective seat limits or provider state.
     */
    function billing_refund_resolution_resolve_preserve(
        PDO $pdo,
        int $paymentAttemptId,
        int $resolvedByUserId,
        string $reason
    ): array {
        if ($paymentAttemptId < 1) {
            throw new InvalidArgumentException(
                'A valid billing payment attempt is required for refund resolution.'
            );
        }

        if ($resolvedByUserId < 1) {
            throw new InvalidArgumentException(
                'A valid resolving user is required for refund resolution.'
            );
        }

        $reason =
            billing_refund_resolution_reason(
                $reason
            );

        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Refund preservation requires an active database transaction.'
            );
        }

        if (!billing_refund_resolution_ready(
            $pdo
        )) {
            throw new RuntimeException(
                'Refund-resolution storage is not ready.'
            );
        }

        /*
         * Canonical transaction lock order:
         * payment attempt -> refund resolution -> purpose context.
         */
        $attempt =
            billing_audit_attempt_by_id(
                $pdo,
                $paymentAttemptId,
                true
            );

        if (!$attempt) {
            throw new RuntimeException(
                'Billing payment attempt could not be found for refund resolution.'
            );
        }

        $resolution =
            billing_refund_resolution_by_payment(
                $pdo,
                $paymentAttemptId,
                true
            );

        if ($resolution === null) {
            throw new RuntimeException(
                'No captured refund review exists for this payment attempt.'
            );
        }

        $lineage =
            billing_refund_resolution_assert_locked_lineage(
                $pdo,
                $attempt,
                $resolution
            );

        $contract =
            $lineage['resolution_contract'];

        if ((string)$contract['status']
            === 'resolved') {
            if ((string)(
                $contract[
                    'resolution_action'
                ] ?? ''
            ) !== 'preserve_entitlement') {
                throw new RuntimeException(
                    'Refund resolution was already completed with a different commercial action.'
                );
            }

            return [
                'resolved' => false,
                'idempotent' => true,
                'reason' =>
                    'already_preserved',
                'resolution' =>
                    $resolution,
                'lineage' => $lineage,
            ];
        }

        if ((string)$contract['status']
            !== 'pending_review') {
            throw new RuntimeException(
                'Refund resolution is not pending review.'
            );
        }

        $update =
            $pdo->prepare(
                "UPDATE billing_refund_resolutions
                 SET status = 'resolved',
                     resolution_action =
                         'preserve_entitlement',
                     resolved_at = CURRENT_TIMESTAMP,
                     resolved_by_user_id = ?,
                     resolution_reason = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                   AND payment_attempt_id = ?
                   AND status = 'pending_review'"
            );

        $update->execute([
            $resolvedByUserId,
            $reason,
            (int)$contract['id'],
            $paymentAttemptId,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Refund preservation could not be resolved exactly once.'
            );
        }

        $resolved =
            billing_refund_resolution_by_payment(
                $pdo,
                $paymentAttemptId,
                false
            );

        if ($resolved === null) {
            throw new RuntimeException(
                'Resolved refund review could not be reloaded.'
            );
        }

        $resolvedContract =
            billing_refund_resolution_row_contract(
                $resolved
            );

        if ((string)$resolvedContract['status']
                !== 'resolved'
            || (string)(
                $resolvedContract[
                    'resolution_action'
                ] ?? ''
            ) !== 'preserve_entitlement'
            || (int)(
                $resolvedContract[
                    'resolved_by_user_id'
                ] ?? 0
            ) !== $resolvedByUserId
            || (string)(
                $resolvedContract[
                    'resolution_reason'
                ] ?? ''
            ) !== $reason
            || empty(
                $resolvedContract[
                    'resolved_at'
                ]
            )) {
            throw new RuntimeException(
                'Preserved refund resolution failed its post-write audit contract.'
            );
        }

        return [
            'resolved' => true,
            'idempotent' => false,
            'reason' =>
                'preserve_entitlement',
            'resolution' => $resolved,
            'lineage' => $lineage,
        ];
    }
}

if (!function_exists(
    'billing_refund_resolution_finalize_reverse'
)) {
    /**
     * Finish one already-proven compensating refund reversal.
     *
     * Provider/payment audit fact and original application history
     * remain untouched. The caller owns the surrounding transaction.
     */
    function billing_refund_resolution_finalize_reverse(
        PDO $pdo,
        array $resolutionContract,
        int $paymentAttemptId,
        int $farmId,
        int $resolvedByUserId,
        string $reason,
        int $reversalSubscriptionRecordId
    ): array {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Refund reversal finalization requires an active database transaction.'
            );
        }

        if (
            $paymentAttemptId < 1
            || $farmId < 1
            || $resolvedByUserId < 1
            || $reversalSubscriptionRecordId < 1
        ) {
            throw new RuntimeException(
                'Refund reversal finalization identity is invalid.'
            );
        }

        if (
            (string)(
                $resolutionContract['status']
                ?? ''
            ) !== 'pending_review'
        ) {
            throw new RuntimeException(
                'Only a pending refund review can be finalized as reversed.'
            );
        }

        subscription_record_history_source_contract(
            $pdo,
            $farmId,
            $reversalSubscriptionRecordId
        );

        $update =
            $pdo->prepare(
                "UPDATE billing_refund_resolutions
                 SET status = 'resolved',
                     resolution_action =
                         'reverse_entitlement',
                     reversal_subscription_record_id = ?,
                     resolved_at = CURRENT_TIMESTAMP,
                     resolved_by_user_id = ?,
                     resolution_reason = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                   AND payment_attempt_id = ?
                   AND farm_id = ?
                   AND status = 'pending_review'
                   AND reversal_subscription_record_id
                       IS NULL"
            );

        $update->execute([
            $reversalSubscriptionRecordId,
            $resolvedByUserId,
            $reason,
            (int)$resolutionContract['id'],
            $paymentAttemptId,
            $farmId,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Refund reversal could not be resolved exactly once.'
            );
        }

        $resolved =
            billing_refund_resolution_by_payment(
                $pdo,
                $paymentAttemptId,
                false
            );

        if ($resolved === null) {
            throw new RuntimeException(
                'Resolved refund reversal could not be reloaded.'
            );
        }

        $resolvedContract =
            billing_refund_resolution_row_contract(
                $resolved
            );

        if (
            (string)$resolvedContract['status']
                !== 'resolved'
            || (string)(
                $resolvedContract[
                    'resolution_action'
                ] ?? ''
            ) !== 'reverse_entitlement'
            || (int)(
                $resolvedContract[
                    'reversal_subscription_record_id'
                ] ?? 0
            ) !== $reversalSubscriptionRecordId
            || (int)(
                $resolvedContract[
                    'resolved_by_user_id'
                ] ?? 0
            ) !== $resolvedByUserId
            || (string)(
                $resolvedContract[
                    'resolution_reason'
                ] ?? ''
            ) !== $reason
            || empty(
                $resolvedContract[
                    'resolved_at'
                ]
            )
        ) {
            throw new RuntimeException(
                'Refund reversal failed its post-write resolution audit contract.'
            );
        }

        return $resolved;
    }
}

if (!function_exists(
    'billing_refund_resolution_resolve_reverse_seat_topup_pending'
)) {
    /**
     * Compensate one still-current applied seat-top-up.
     *
     * The original payment fact, seat request and application history
     * remain immutable. Only purchased extra seats and their effective
     * role limits are restored to the immediate predecessor snapshot.
     */
    function billing_refund_resolution_resolve_reverse_seat_topup_pending(
        PDO $pdo,
        int $paymentAttemptId,
        int $resolvedByUserId,
        string $reason,
        array $lineage
    ): array {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Seat-top-up refund reversal requires an active database transaction.'
            );
        }

        $resolutionContract =
            $lineage['resolution_contract']
            ?? null;

        $requestContract =
            $lineage['seat_request_contract']
            ?? null;

        if (
            !is_array($resolutionContract)
            || !is_array($requestContract)
            || (string)(
                $resolutionContract['status']
                ?? ''
            ) !== 'pending_review'
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal requires a locked pending review contract.'
            );
        }

        $farmId =
            (int)(
                $lineage['farm_id']
                ?? 0
            );

        $seatChangeRequestId =
            (int)(
                $lineage[
                    'seat_change_request_id'
                ] ?? 0
            );

        $applicationHistoryId =
            (int)(
                $lineage[
                    'seat_change_applied_subscription_record_id'
                ] ?? 0
            );

        if (
            $farmId < 1
            || $seatChangeRequestId < 1
            || $applicationHistoryId < 1
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal lineage is incomplete.'
            );
        }

        if (
            (string)(
                $requestContract[
                    'change_kind'
                ] ?? ''
            ) !== 'add'
            || (int)(
                $requestContract[
                    'payment_attempt_id'
                ] ?? 0
            ) !== $paymentAttemptId
            || (int)(
                $requestContract[
                    'farm_id'
                ] ?? 0
            ) !== $farmId
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal request identity is invalid.'
            );
        }

        $roleCode =
            billing_seat_change_normalize_role(
                (string)(
                    $requestContract[
                        'role_code'
                    ] ?? ''
                )
            );

        $fromExtraSeats =
            billing_seat_change_normalize_count(
                $requestContract[
                    'from_extra_seats'
                ] ?? null
            );

        $toExtraSeats =
            billing_seat_change_normalize_count(
                $requestContract[
                    'to_extra_seats'
                ] ?? null
            );

        if ($toExtraSeats <= $fromExtraSeats) {
            throw new RuntimeException(
                'Seat-top-up refund reversal request is not an increase.'
            );
        }

        /*
         * Canonical lock order has already locked:
         * payment attempt -> refund resolution -> seat request.
         * Current tenant runtime is locked next.
         */
        $farmStmt =
            $pdo->prepare(
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
                'Tenant farm could not be locked for seat-top-up refund reversal.'
            );
        }

        /*
         * The exact history created by the paid top-up must still
         * be this tenant's latest immutable commercial row.
         */
        $latestStmt =
            $pdo->prepare(
                "SELECT
                     id,
                     change_reason
                 FROM subscriptions
                 WHERE farm_id = ?
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE"
            );

        $latestStmt->execute([
            $farmId,
        ]);

        $latest =
            $latestStmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (
            !is_array($latest)
            || (int)$latest['id']
                !== $applicationHistoryId
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal is stale because newer tenant commercial history exists.'
            );
        }

        if (
            (string)(
                $latest['change_reason']
                ?? ''
            ) !== 'seat_topup_payment_applied'
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal application history has an unexpected reason.'
            );
        }

        $applicationSource =
            subscription_record_history_source_contract(
                $pdo,
                $farmId,
                $applicationHistoryId
            );

        /*
         * Runtime must still exactly equal the applied top-up snapshot.
         */
        $runtimeBefore =
            subscription_record_build_snapshot(
                $pdo,
                $farmId
            );

        $runtimeBeforeHash =
            (string)(
                $runtimeBefore[
                    'snapshot_hash'
                ] ?? ''
            );

        if (
            $runtimeBeforeHash === ''
            || !hash_equals(
                (string)$applicationSource[
                    'snapshot_hash'
                ],
                $runtimeBeforeHash
            )
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal is stale because current commercial state no longer matches the applied top-up.'
            );
        }

        /*
         * Immediate same-tenant predecessor only.
         */
        $predecessorStmt =
            $pdo->prepare(
                "SELECT id
                 FROM subscriptions
                 WHERE farm_id = ?
                   AND id < ?
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE"
            );

        $predecessorStmt->execute([
            $farmId,
            $applicationHistoryId,
        ]);

        $predecessorHistoryId =
            (int)$predecessorStmt->fetchColumn();

        if ($predecessorHistoryId < 1) {
            throw new RuntimeException(
                'Seat-top-up refund reversal has no immediate predecessor commercial history.'
            );
        }

        $predecessor =
            subscription_record_history_source_contract(
                $pdo,
                $farmId,
                $predecessorHistoryId
            );

        $appliedSnapshot =
            $applicationSource['snapshot'];

        $targetSnapshot =
            $predecessor['snapshot'];

        /*
         * Paid seat-top-up application changes seats only.
         * Plan/status/term/modules must therefore be identical.
         */
        $commercialInvariantOk =
            (string)$appliedSnapshot['plan_code']
                === (string)$targetSnapshot['plan_code']
            && (string)$appliedSnapshot['status']
                === (string)$targetSnapshot['status']
            && (
                $appliedSnapshot[
                    'subscription_starts_at'
                ] ?? null
            ) === (
                $targetSnapshot[
                    'subscription_starts_at'
                ] ?? null
            )
            && (
                $appliedSnapshot[
                    'subscription_ends_at'
                ] ?? null
            ) === (
                $targetSnapshot[
                    'subscription_ends_at'
                ] ?? null
            )
            && (
                $appliedSnapshot['modules']
                ?? null
            ) === (
                $targetSnapshot['modules']
                ?? null
            );

        $requestPlan =
            billing_seat_change_normalize_plan(
                (string)(
                    $requestContract[
                        'plan_code'
                    ] ?? ''
                )
            );

        $requestModules =
            billing_seat_change_normalize_modules(
                is_array(
                    $requestContract[
                        'modules'
                    ] ?? null
                )
                    ? $requestContract[
                        'modules'
                    ]
                    : []
            );

        if (
            !$commercialInvariantOk
            || $requestPlan
                !== (string)$appliedSnapshot['plan_code']
            || $requestPlan
                !== (string)$targetSnapshot['plan_code']
            || $requestModules
                !== $appliedSnapshot['modules']
            || $requestModules
                !== $targetSnapshot['modules']
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal predecessor differs outside the purchased seat change.'
            );
        }

        $appliedSeats =
            subscription_seat_normalize_addons(
                $appliedSnapshot[
                    'seat_addons'
                ] ?? []
            );

        $targetSeats =
            subscription_seat_normalize_addons(
                $targetSnapshot[
                    'seat_addons'
                ] ?? []
            );

        ksort(
            $appliedSeats,
            SORT_STRING
        );

        ksort(
            $targetSeats,
            SORT_STRING
        );

        /*
         * Target role must be exactly from -> to.
         */
        if (
            (int)(
                $appliedSeats[
                    $roleCode
                ] ?? 0
            ) !== $toExtraSeats
            || (int)(
                $targetSeats[
                    $roleCode
                ] ?? 0
            ) !== $fromExtraSeats
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal history does not match the requested role seat transition.'
            );
        }

        /*
         * Every non-target role must be identical.
         */
        $appliedOtherSeats =
            $appliedSeats;

        $targetOtherSeats =
            $targetSeats;

        unset(
            $appliedOtherSeats[
                $roleCode
            ],
            $targetOtherSeats[
                $roleCode
            ]
        );

        if ($appliedOtherSeats !== $targetOtherSeats) {
            throw new RuntimeException(
                'Seat-top-up refund reversal detects unrelated seat changes in the application history.'
            );
        }

        /*
         * Never reduce purchased capacity below assigned users.
         */
        subscription_seat_assert_capacity(
            $pdo,
            $farmId,
            (string)$targetSnapshot[
                'plan_code'
            ],
            $targetSnapshot[
                'modules'
            ],
            $targetSeats
        );

        /*
         * Restore only the runtime state originally changed by top-up.
         */
        subscription_seat_save_addons(
            $pdo,
            $farmId,
            $targetSeats
        );

        $effectiveLimits =
            subscription_seat_save_effective_limits(
                $pdo,
                $farmId,
                (string)$targetSnapshot[
                    'plan_code'
                ],
                $targetSnapshot[
                    'modules'
                ],
                $targetSeats
            );

        $restored =
            subscription_record_build_snapshot(
                $pdo,
                $farmId
            );

        $restoredHash =
            (string)(
                $restored[
                    'snapshot_hash'
                ] ?? ''
            );

        if (
            $restoredHash === ''
            || !hash_equals(
                (string)$predecessor[
                    'snapshot_hash'
                ],
                $restoredHash
            )
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal failed to restore the exact predecessor commercial snapshot.'
            );
        }

        /*
         * Append compensation; never rewrite original history.
         */
        $compensation =
            subscription_record_append_from_history_source(
                $pdo,
                $farmId,
                $predecessorHistoryId,
                'billing_refund_reversed',
                $resolvedByUserId
            );

        $reversalHistoryId =
            (int)(
                $compensation['id']
                ?? 0
            );

        if ($reversalHistoryId < 1) {
            throw new RuntimeException(
                'Seat-top-up refund reversal compensating history has no valid identity.'
            );
        }

        $resolved =
            billing_refund_resolution_finalize_reverse(
                $pdo,
                $resolutionContract,
                $paymentAttemptId,
                $farmId,
                $resolvedByUserId,
                $reason,
                $reversalHistoryId
            );

        /*
         * Final audit: compensation must exactly equal predecessor
         * and must now be latest commercial history.
         */
        $reversalSource =
            subscription_record_history_source_contract(
                $pdo,
                $farmId,
                $reversalHistoryId
            );

        if (
            !hash_equals(
                (string)$predecessor[
                    'snapshot_hash'
                ],
                (string)$reversalSource[
                    'snapshot_hash'
                ]
            )
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal compensation does not match its predecessor source.'
            );
        }

        $latestAfterStmt =
            $pdo->prepare(
                "SELECT id
                 FROM subscriptions
                 WHERE farm_id = ?
                 ORDER BY id DESC
                 LIMIT 1"
            );

        $latestAfterStmt->execute([
            $farmId,
        ]);

        if (
            (int)$latestAfterStmt->fetchColumn()
                !== $reversalHistoryId
        ) {
            throw new RuntimeException(
                'Seat-top-up refund reversal compensation is not the tenant latest commercial record.'
            );
        }

        return [
            'resolved' => true,
            'idempotent' => false,
            'reason' => 'reverse_entitlement',
            'purpose' => 'seat_topup',
            'resolution' => $resolved,
            'lineage' => $lineage,
            'seat_change_request_id' =>
                $seatChangeRequestId,
            'role_code' =>
                $roleCode,
            'from_extra_seats' =>
                $fromExtraSeats,
            'to_extra_seats' =>
                $toExtraSeats,
            'predecessor_subscription_record_id' =>
                $predecessorHistoryId,
            'reversal_subscription_record_id' =>
                $reversalHistoryId,
            'restored_snapshot_hash' =>
                $restoredHash,
            'effective_role_limits' =>
                $effectiveLimits,
        ];
    }
}

if (!function_exists(
    'billing_refund_resolution_resolve_reverse'
)) {
    /**
     * Resolve a captured post-application SUBSCRIPTION refund by
     * compensating the still-current applied commercial state back to
     * its immediate immutable predecessor.
     *
     * Safety contract:
     * - payment/provider fact remains untouched;
     * - payment attempt -> refund resolution -> farm is the lock order;
     * - the refunded applied history must still be the tenant's latest row;
     * - current runtime snapshot must still equal that applied history;
     * - the immediate same-tenant predecessor must exist and pass capacity;
     * - current runtime is restored only through shared entitlement/seat helpers;
     * - one new immutable compensating subscriptions row is appended;
     * - the refund resolution durably links that exact compensating row;
     * - repeated calls after successful reversal are idempotent;
     * - seat_topup reversal is deliberately unsupported here and fails closed.
     */
    function billing_refund_resolution_resolve_reverse(
        PDO $pdo,
        int $paymentAttemptId,
        int $resolvedByUserId,
        string $reason
    ): array {
        if ($paymentAttemptId < 1) {
            throw new InvalidArgumentException(
                'A valid billing payment attempt is required for refund reversal.'
            );
        }

        if ($resolvedByUserId < 1) {
            throw new InvalidArgumentException(
                'A valid resolving user is required for refund reversal.'
            );
        }

        $reason =
            billing_refund_resolution_reason(
                $reason
            );

        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Refund reversal requires an active database transaction.'
            );
        }

        if (!billing_refund_resolution_ready($pdo)) {
            throw new RuntimeException(
                'Refund-resolution storage is not ready.'
            );
        }

        /*
         * Canonical lock order begins with provider/payment audit fact.
         */
        $attempt =
            billing_audit_attempt_by_id(
                $pdo,
                $paymentAttemptId,
                true
            );

        if (!$attempt) {
            throw new RuntimeException(
                'Billing payment attempt could not be found for refund reversal.'
            );
        }

        $purpose =
            billing_payment_attempt_purpose(
                $attempt
            );

        if (!in_array(
            $purpose,
            [
                'subscription',
                'seat_topup',
            ],
            true
        )) {
            throw new RuntimeException(
                'Unsupported payment purpose for refund reversal.'
            );
        }

        /*
         * Second lock: durable refund review.
         */
        $resolution =
            billing_refund_resolution_by_payment(
                $pdo,
                $paymentAttemptId,
                true
            );

        if ($resolution === null) {
            throw new RuntimeException(
                'No captured refund review exists for this payment attempt.'
            );
        }

        $lineage =
            billing_refund_resolution_assert_locked_lineage(
                $pdo,
                $attempt,
                $resolution
            );

        $contract =
            $lineage['resolution_contract'];

        $farmId =
            (int)$lineage['farm_id'];

        $appliedSubscriptionRecordId =
            (int)(
                $lineage[
                    'applied_subscription_record_id'
                ] ?? 0
            );

        $seatApplicationHistoryId =
            (int)(
                $lineage[
                    'seat_change_applied_subscription_record_id'
                ] ?? 0
            );

        $seatChangeRequestId =
            (int)(
                $lineage[
                    'seat_change_request_id'
                ] ?? 0
            );

        if ($farmId < 1) {
            throw new RuntimeException(
                'Refund reversal tenant lineage is invalid.'
            );
        }

        if (
            $purpose === 'subscription'
            && $appliedSubscriptionRecordId < 1
        ) {
            throw new RuntimeException(
                'Refund reversal subscription lineage is invalid.'
            );
        }

        if (
            $purpose === 'seat_topup'
            && (
                $seatApplicationHistoryId < 1
                || $seatChangeRequestId < 1
                || !is_array(
                    $lineage[
                        'seat_request_contract'
                    ] ?? null
                )
            )
        ) {
            throw new RuntimeException(
                'Refund reversal seat-top-up lineage is invalid.'
            );
        }

        /*
         * A completed reverse action is idempotent even if newer commercial
         * history has subsequently been appended. Never replay old reversal.
         */
        if ((string)$contract['status'] === 'resolved') {
            if (
                (string)(
                    $contract[
                        'resolution_action'
                    ] ?? ''
                ) !== 'reverse_entitlement'
            ) {
                throw new RuntimeException(
                    'Refund resolution was already completed with a different commercial action.'
                );
            }

            $reversalSubscriptionRecordId =
                (int)(
                    $contract[
                        'reversal_subscription_record_id'
                    ] ?? 0
                );

            if ($reversalSubscriptionRecordId < 1) {
                throw new RuntimeException(
                    'Resolved refund reversal is missing its compensating history identity.'
                );
            }

            $reversalSource =
                subscription_record_history_source_contract(
                    $pdo,
                    $farmId,
                    $reversalSubscriptionRecordId
                );

            return [
                'resolved' => false,
                'idempotent' => true,
                'reason' => 'already_reversed',
                'resolution' => $resolution,
                'lineage' => $lineage,
                'reversal_subscription_record_id' =>
                    $reversalSubscriptionRecordId,
                'reversal_snapshot_hash' =>
                    $reversalSource['snapshot_hash'],
            ];
        }

        if ((string)$contract['status'] !== 'pending_review') {
            throw new RuntimeException(
                'Refund resolution is not pending review.'
            );
        }

        if ($purpose === 'seat_topup') {
            return billing_refund_resolution_resolve_reverse_seat_topup_pending(
                $pdo,
                $paymentAttemptId,
                $resolvedByUserId,
                $reason,
                $lineage
            );
        }

        /*
         * Third lock: current tenant runtime anchor.
         * All current-state checks and compensation happen while farm is locked.
         */
        $farmStmt =
            $pdo->prepare(
                "SELECT
                     id,
                     slug,
                     subscription_plan,
                     subscription_status,
                     subscription_starts_at,
                     subscription_ends_at
                 FROM farms
                 WHERE id = ?
                   AND slug <> 'owner'
                 LIMIT 1
                 FOR UPDATE"
            );

        $farmStmt->execute([
            $farmId,
        ]);

        $farm =
            $farmStmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        if (!is_array($farm)) {
            throw new RuntimeException(
                'Tenant farm could not be locked for refund reversal.'
            );
        }

        /*
         * Refuse reversal through newer commercial history.
         */
        $latestStmt =
            $pdo->prepare(
                "SELECT id
                 FROM subscriptions
                 WHERE farm_id = ?
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE"
            );

        $latestStmt->execute([
            $farmId,
        ]);

        $latestSubscriptionRecordId =
            (int)$latestStmt->fetchColumn();

        if (
            $latestSubscriptionRecordId
            !== $appliedSubscriptionRecordId
        ) {
            throw new RuntimeException(
                'Refund reversal is stale because newer tenant commercial history exists.'
            );
        }

        $appliedSource =
            subscription_record_history_source_contract(
                $pdo,
                $farmId,
                $appliedSubscriptionRecordId
            );

        $runtimeBefore =
            subscription_record_build_snapshot(
                $pdo,
                $farmId
            );

        $runtimeBeforeHash =
            (string)(
                $runtimeBefore['snapshot_hash']
                ?? ''
            );

        if (
            $runtimeBeforeHash === ''
            || !hash_equals(
                (string)$appliedSource[
                    'snapshot_hash'
                ],
                $runtimeBeforeHash
            )
        ) {
            throw new RuntimeException(
                'Refund reversal is stale because current tenant commercial state no longer matches the refunded application.'
            );
        }

        /*
         * Immediate same-tenant predecessor only.
         */
        $predecessorStmt =
            $pdo->prepare(
                "SELECT id
                 FROM subscriptions
                 WHERE farm_id = ?
                   AND id < ?
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE"
            );

        $predecessorStmt->execute([
            $farmId,
            $appliedSubscriptionRecordId,
        ]);

        $predecessorSubscriptionRecordId =
            (int)$predecessorStmt->fetchColumn();

        if ($predecessorSubscriptionRecordId < 1) {
            throw new RuntimeException(
                'Refund reversal cannot continue because no immediate predecessor commercial history exists.'
            );
        }

        $predecessor =
            subscription_record_history_source_contract(
                $pdo,
                $farmId,
                $predecessorSubscriptionRecordId
            );

        $target =
            $predecessor['snapshot'];

        $targetPlanCode =
            (string)(
                $target['plan_code']
                ?? ''
            );

        $targetStatus =
            (string)(
                $target['status']
                ?? ''
            );

        $targetModules =
            $target['modules']
            ?? null;

        $targetSeatAddons =
            $target['seat_addons']
            ?? null;

        if (
            $targetPlanCode === ''
            || $targetStatus === ''
            || !is_array($targetModules)
            || !is_array($targetSeatAddons)
            || !subscription_plan_is_valid(
                $targetPlanCode
            )
        ) {
            throw new RuntimeException(
                'Refund reversal predecessor commercial snapshot is invalid.'
            );
        }

        /*
         * Capacity is checked before any current-state mutation.
         */
        subscription_seat_assert_capacity(
            $pdo,
            $farmId,
            $targetPlanCode,
            $targetModules,
            $targetSeatAddons
        );

        /*
         * Restore the predecessor's current commercial snapshot.
         */
        $updateFarm =
            $pdo->prepare(
                "UPDATE farms
                 SET subscription_plan = ?,
                     subscription_status = ?,
                     subscription_starts_at = ?,
                     subscription_ends_at = ?
                 WHERE id = ?
                   AND slug <> 'owner'"
            );

        $updateFarm->execute([
            $targetPlanCode,
            $targetStatus,
            $target[
                'subscription_starts_at'
            ],
            $target[
                'subscription_ends_at'
            ],
            $farmId,
        ]);

        if ($updateFarm->rowCount() > 1) {
            throw new RuntimeException(
                'Refund reversal updated an unexpected number of tenant rows.'
            );
        }

        sync_farm_entitlements(
            $pdo,
            $farmId,
            $targetModules
        );

        subscription_seat_save_addons(
            $pdo,
            $farmId,
            $targetSeatAddons
        );

        $effectiveLimits =
            subscription_seat_save_effective_limits(
                $pdo,
                $farmId,
                $targetPlanCode,
                $targetModules,
                $targetSeatAddons
            );

        /*
         * Prove exact restoration before appending compensation history.
         */
        $restored =
            subscription_record_build_snapshot(
                $pdo,
                $farmId
            );

        $restoredHash =
            (string)(
                $restored['snapshot_hash']
                ?? ''
            );

        if (
            $restoredHash === ''
            || !hash_equals(
                (string)$predecessor[
                    'snapshot_hash'
                ],
                $restoredHash
            )
        ) {
            throw new RuntimeException(
                'Refund reversal failed to restore the exact predecessor commercial snapshot.'
            );
        }

        /*
         * Foundation 3: append a NEW immutable row whose runtime snapshot and
         * historical billing/provider metadata come from the exact predecessor.
         */
        $compensation =
            subscription_record_append_from_history_source(
                $pdo,
                $farmId,
                $predecessorSubscriptionRecordId,
                'billing_refund_reversed',
                $resolvedByUserId
            );

        $reversalSubscriptionRecordId =
            (int)(
                $compensation['id']
                ?? 0
            );

        if ($reversalSubscriptionRecordId < 1) {
            throw new RuntimeException(
                'Refund reversal compensating history has no valid identity.'
            );
        }

        /*
         * Complete subscription reversal through the same
         * exactly-once finalizer used by seat-top-up reversal.
         */
        $resolved =
            billing_refund_resolution_finalize_reverse(
                $pdo,
                $contract,
                $paymentAttemptId,
                $farmId,
                $resolvedByUserId,
                $reason,
                $reversalSubscriptionRecordId
            );

        $reversalSource =
            subscription_record_history_source_contract(
                $pdo,
                $farmId,
                $reversalSubscriptionRecordId
            );

        if (
            !hash_equals(
                (string)$predecessor[
                    'snapshot_hash'
                ],
                (string)$reversalSource[
                    'snapshot_hash'
                ]
            )
        ) {
            throw new RuntimeException(
                'Refund reversal compensating history does not match its predecessor source.'
            );
        }

        $latestAfterStmt =
            $pdo->prepare(
                "SELECT id
                 FROM subscriptions
                 WHERE farm_id = ?
                 ORDER BY id DESC
                 LIMIT 1"
            );

        $latestAfterStmt->execute([
            $farmId,
        ]);

        if (
            (int)$latestAfterStmt->fetchColumn()
            !== $reversalSubscriptionRecordId
        ) {
            throw new RuntimeException(
                'Refund reversal compensating history is not the tenant latest commercial record.'
            );
        }

        return [
            'resolved' => true,
            'idempotent' => false,
            'reason' => 'reverse_entitlement',
            'resolution' => $resolved,
            'lineage' => $lineage,
            'predecessor_subscription_record_id' =>
                $predecessorSubscriptionRecordId,
            'reversal_subscription_record_id' =>
                $reversalSubscriptionRecordId,
            'restored_snapshot_hash' =>
                $restoredHash,
            'effective_role_limits' =>
                $effectiveLimits,
        ];
    }
}
