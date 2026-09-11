<?php
/**
 * V2.3 paid-renewal scheduled seat-reduction application foundation.
 *
 * Contract:
 * - caller must already own the commercial database transaction;
 * - the authoritative tenant and scheduled-removal rows are locked before use;
 * - an early renewal cannot consume a scheduled reduction before its effective
 *   paid-period boundary;
 * - a due reduction must exactly match the frozen paid subscription seat snapshot;
 * - scheduled removal requests are marked applied inside the same caller transaction;
 * - this service does not mutate farm/subscription/seat entitlement state itself;
 * - this service performs no payment-provider or network work.
 */

require_once __DIR__ . '/billing_renewal_seat_target.php';

if (!function_exists('billing_renewal_seat_application_datetime')) {
    function billing_renewal_seat_application_datetime(
        $value,
        string $label
    ): DateTimeImmutable {
        $value = trim((string)$value);

        if ($value === '') {
            throw new RuntimeException(
                $label . ' is required for renewal seat application.'
            );
        }

        try {
            return new DateTimeImmutable(
                $value,
                new DateTimeZone(
                    date_default_timezone_get()
                )
            );
        } catch (Throwable $e) {
            throw new RuntimeException(
                $label . ' is invalid for renewal seat application.',
                0,
                $e
            );
        }
    }
}

if (!function_exists('billing_renewal_seat_apply_paid_snapshot')) {
    function billing_renewal_seat_apply_paid_snapshot(
        PDO $pdo,
        array $attemptContract
    ): array {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Renewal seat application requires an active caller transaction.'
            );
        }

        $farmId = (int)($attemptContract['farm_id'] ?? 0);

        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for renewal seat application.'
            );
        }

        // Serialize the tenant commercial snapshot before resolving and
        // locking its scheduled renewal reductions. If the caller already
        // holds this farm row, the repeated lock is harmless.
        $farmLock = $pdo->prepare(
            "SELECT id
             FROM farms
             WHERE id = ?
               AND slug <> 'owner'
             LIMIT 1
             FOR UPDATE"
        );

        $farmLock->execute([
            $farmId,
        ]);

        if (!$farmLock->fetchColumn()) {
            throw new RuntimeException(
                'Tenant farm could not be locked for renewal seat application.'
            );
        }

        $paidAt =
            billing_renewal_seat_application_datetime(
                $attemptContract['paid_at'] ?? null,
                'Paid subscription timestamp'
            );

        $renewal =
            billing_renewal_seat_target(
                $pdo,
                $farmId,
                true,
                [
                    'trial',
                    'active',
                    'past_due',
                    'suspended',
                    'cancelled',
                ]
            );

        $attemptPlan = strtolower(trim(
            (string)($attemptContract['plan_code'] ?? '')
        ));

        $attemptInterval =
            billing_payment_normalize_interval(
                (string)(
                    $attemptContract[
                        'billing_interval'
                    ] ?? ''
                )
            );

        $attemptModules =
            billing_payment_normalize_modules(
                is_array(
                    $attemptContract['modules'] ?? null
                )
                    ? $attemptContract['modules']
                    : []
            );

        $attemptSeats =
            subscription_seat_normalize_addons(
                is_array(
                    $attemptContract[
                        'seat_addons'
                    ] ?? null
                )
                    ? $attemptContract['seat_addons']
                    : []
            );

        if ($attemptPlan
                !== (string)$renewal['plan_code']
            || $attemptInterval
                !== (string)$renewal[
                    'billing_interval'
                ]
            || $attemptModules
                !== $renewal['modules']) {
            throw new RuntimeException(
                'Paid subscription quote no longer matches the current commercial lineage.'
            );
        }

        $currentSeats =
            subscription_seat_normalize_addons(
                is_array(
                    $renewal[
                        'current_seat_addons'
                    ] ?? null
                )
                    ? $renewal[
                        'current_seat_addons'
                    ]
                    : []
            );

        $renewalSeats =
            subscription_seat_normalize_addons(
                is_array(
                    $renewal[
                        'renewal_seat_addons'
                    ] ?? null
                )
                    ? $renewal[
                        'renewal_seat_addons'
                    ]
                    : []
            );

        $requestIds = array_values(array_map(
            'intval',
            is_array(
                $renewal[
                    'scheduled_request_ids'
                ] ?? null
            )
                ? $renewal[
                    'scheduled_request_ids'
                ]
                : []
        ));

        $scheduledCount =
            (int)($renewal['scheduled_count'] ?? 0);

        if ($scheduledCount !== count($requestIds)) {
            throw new RuntimeException(
                'Scheduled renewal seat-reduction count is inconsistent.'
            );
        }

        if ($scheduledCount < 1) {
            if ($attemptSeats !== $currentSeats) {
                throw new RuntimeException(
                    'Paid subscription seat snapshot is stale against current tenant seats.'
                );
            }

            return [
                'scheduled_count' => 0,
                'applied_count' => 0,
                'applied_request_ids' => [],
                'current_seat_addons' =>
                    $currentSeats,
                'renewal_seat_addons' =>
                    $renewalSeats,
                'had_scheduled_reductions' =>
                    false,
            ];
        }

        $periodEnd =
            billing_renewal_seat_application_datetime(
                $renewal[
                    'current_period_ends_at'
                ] ?? null,
                'Current paid-period end'
            );

        if ($paidAt < $periodEnd) {
            throw new RuntimeException(
                'Scheduled seat reductions cannot be applied by an early renewal payment.'
            );
        }

        if ($attemptSeats !== $renewalSeats) {
            throw new RuntimeException(
                'Paid subscription seat snapshot does not match the due renewal seat target.'
            );
        }

        $update = $pdo->prepare(
            "UPDATE billing_seat_change_requests
             SET status = 'applied',
                 applied_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND farm_id = ?
               AND change_kind = 'remove'
               AND status = 'scheduled'"
        );

        $appliedIds = [];

        foreach ($requestIds as $requestId) {
            if ($requestId < 1) {
                throw new RuntimeException(
                    'Scheduled renewal seat reduction has an invalid request id.'
                );
            }

            $update->execute([
                $requestId,
                $farmId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'Scheduled renewal seat reduction could not be marked applied exactly once.'
                );
            }

            $appliedRow =
                billing_seat_change_request_by_id(
                    $pdo,
                    $requestId,
                    false
                );

            if (!$appliedRow) {
                throw new RuntimeException(
                    'Applied renewal seat reduction could not be reloaded.'
                );
            }

            $appliedState =
                billing_seat_change_row_contract(
                    $appliedRow
                );

            $appliedContract =
                $appliedState['contract'] ?? [];

            if (($appliedState['status'] ?? '')
                    !== 'applied'
                || empty(
                    $appliedState['applied_at']
                )
                || ($appliedContract[
                    'change_kind'
                ] ?? '') !== 'remove'
                || (int)(
                    $appliedContract[
                        'farm_id'
                    ] ?? 0
                ) !== $farmId) {
                throw new RuntimeException(
                    'Applied renewal seat reduction failed post-update verification.'
                );
            }

            $appliedIds[] = $requestId;
        }

        if (count($appliedIds)
            !== $scheduledCount) {
            throw new RuntimeException(
                'Not all scheduled renewal seat reductions were applied.'
            );
        }

        return [
            'scheduled_count' =>
                $scheduledCount,
            'applied_count' =>
                count($appliedIds),
            'applied_request_ids' =>
                $appliedIds,
            'current_seat_addons' =>
                $currentSeats,
            'renewal_seat_addons' =>
                $renewalSeats,
            'had_scheduled_reductions' =>
                true,
        ];
    }
}
?>
