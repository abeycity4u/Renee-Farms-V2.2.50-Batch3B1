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
