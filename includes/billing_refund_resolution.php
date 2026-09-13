<?php
/**
 * V2.3 post-application refund-resolution foundation.
 *
 * Provider payment fact and commercial refund response remain separate:
 *
 * - billing_payment_attempts.status='refunded' is provider/audit fact;
 * - billing_refund_resolutions records what commercial review must do next.
 *
 * This foundation deliberately performs no provider call and no entitlement,
 * subscription, seat-limit or payment-state mutation.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_payment_audit_state.php';
require_once __DIR__ . '/billing_seat_change_request.php';

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
             WHERE filename = ?"
        );

        $stmt->execute([
            '050_billing_refund_resolution.sql',
        ]);

        return (int)$stmt->fetchColumn() === 1;
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
                || $reasonRaw !== '') {
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

        return [
            'attempt_id' => $attemptId,
            'farm_id' => $farmId,
            'purpose' => $purpose,
            'resolution_contract' => $contract,
            'applied_subscription_record_id' => null,
            'seat_change_request_id' =>
                $seatChangeRequestId,
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
