<?php
/**
 * V2.3 server-authoritative renewal seat target.
 *
 * Scheduled seat removals do not alter current-period entitlement. This service
 * derives only the next renewal seat snapshot and price from durable scheduled
 * removal requests.
 *
 * Contract:
 * - active tenant only;
 * - read-only;
 * - current entitlement remains unchanged;
 * - scheduled removals are integrity-checked before influencing renewal;
 * - future capacity must still fit assigned users;
 * - renewal price is rebuilt server-side from the resolved target seats.
 */

require_once __DIR__ . '/billing_current_product.php';
require_once __DIR__ . '/billing_seat_change_request.php';

if (!function_exists('billing_renewal_seat_target')) {
    function billing_renewal_seat_target(
        PDO $pdo,
        int $farmId,
        bool $forUpdate = false,
        array $allowedStatuses = ['active']
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for renewal seat targeting.'
            );
        }

        $allowedStatuses = array_values(array_unique(array_map(
            static fn($value): string =>
                strtolower(trim((string)$value)),
            $allowedStatuses
        )));

        $allowedStatuses = array_values(array_filter(
            $allowedStatuses,
            static fn(string $value): bool =>
                $value !== ''
        ));

        if (!$allowedStatuses) {
            throw new InvalidArgumentException(
                'At least one subscription status is required for renewal seat targeting.'
            );
        }

        $supportedStatuses = [
            'trial',
            'active',
            'past_due',
            'suspended',
            'cancelled',
        ];

        foreach ($allowedStatuses as $status) {
            if (!in_array(
                $status,
                $supportedStatuses,
                true
            )) {
                throw new InvalidArgumentException(
                    'Unsupported subscription status for renewal seat targeting.'
                );
            }
        }

        if ($forUpdate && !$pdo->inTransaction()) {
            throw new RuntimeException(
                'Locked renewal seat targeting requires an active database transaction.'
            );
        }

        if (!billing_seat_change_ready($pdo)) {
            throw new RuntimeException(
                'Seat-change request storage is not transactionally ready.'
            );
        }

        $current = billing_current_product(
            $pdo,
            $farmId,
            $allowedStatuses
        );

        $farm = $current['farm'] ?? null;

        if (!is_array($farm)
            || (int)($farm['id'] ?? 0) !== $farmId) {
            throw new RuntimeException(
                'Current tenant product does not belong to the requested farm.'
            );
        }

        $planCode = strtolower(trim(
            (string)($current['plan_code'] ?? '')
        ));

        if (!subscription_plan_is_valid($planCode)) {
            throw new RuntimeException(
                'Current tenant plan is invalid for renewal seat targeting.'
            );
        }

        $modules = billing_payment_normalize_modules(
            is_array($current['modules'] ?? null)
                ? $current['modules']
                : []
        );

        $pricing = is_array($current['pricing'] ?? null)
            ? $current['pricing']
            : [];

        $billingInterval =
            billing_payment_normalize_interval(
                (string)(
                    $pricing['billing_interval']
                        ?? ''
                )
            );

        $currentSeats =
            subscription_seat_normalize_addons(
                is_array($current['seat_addons'] ?? null)
                    ? $current['seat_addons']
                    : []
            );

        $latest =
            $current['latest_subscription']
                ?? null;

        if (!is_array($latest)) {
            throw new RuntimeException(
                'Current subscription history is unavailable for renewal seat targeting.'
            );
        }

        $periodEnd =
            billing_seat_change_normalize_datetime(
                $latest['current_period_ends_at']
                    ?? null
            );

        $sql =
            "SELECT *
             FROM billing_seat_change_requests
             WHERE farm_id = ?
               AND change_kind = 'remove'
               AND status = 'scheduled'
             ORDER BY id ASC";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$farmId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC)
            ?: [];

        $targetSeats = $currentSeats;
        $requestIds = [];
        $seenRoles = [];

        foreach ($rows as $row) {
            $state =
                billing_seat_change_row_contract(
                    $row
                );

            $contract =
                $state['contract']
                    ?? [];

            if (($state['status'] ?? '')
                    !== 'scheduled'
                || ($contract['change_kind'] ?? '')
                    !== 'remove') {
                throw new RuntimeException(
                    'Renewal seat target encountered an invalid scheduled removal state.'
                );
            }

            if ((int)($contract['farm_id'] ?? 0)
                    !== $farmId
                || ($contract['plan_code'] ?? '')
                    !== $planCode
                || ($contract['modules'] ?? [])
                    !== $modules
                || ($contract['billing_interval'] ?? '')
                    !== $billingInterval
                || ($contract['current_period_ends_at'] ?? '')
                    !== $periodEnd
                || ($contract['effective_at'] ?? '')
                    !== $periodEnd) {
                throw new RuntimeException(
                    'Scheduled seat removal no longer matches the current renewal lineage.'
                );
            }

            $role = (string)(
                $contract['role_code']
                    ?? ''
            );

            if ($role === ''
                || !array_key_exists(
                    $role,
                    $currentSeats
                )) {
                throw new RuntimeException(
                    'Scheduled seat removal contains an invalid role.'
                );
            }

            if (isset($seenRoles[$role])) {
                throw new RuntimeException(
                    'More than one scheduled renewal change exists for the same role.'
                );
            }

            $seenRoles[$role] = true;

            if ((int)$currentSeats[$role]
                    !== (int)(
                        $contract['from_extra_seats']
                            ?? -1
                    )) {
                throw new RuntimeException(
                    'Scheduled seat removal is stale because current extra seats changed.'
                );
            }

            $targetSeats[$role] =
                (int)(
                    $contract['to_extra_seats']
                        ?? -1
                );

            $requestId =
                (int)($state['id'] ?? 0);

            if ($requestId < 1) {
                throw new RuntimeException(
                    'Scheduled seat removal has an invalid durable request id.'
                );
            }

            $requestIds[] = $requestId;
        }

        $targetSeats =
            subscription_seat_normalize_addons(
                $targetSeats
            );

        subscription_seat_assert_capacity(
            $pdo,
            $farmId,
            $planCode,
            $modules,
            $targetSeats
        );

        $paymentQuote =
            billing_pricing_build_payment_quote(
                $planCode,
                $billingInterval,
                $modules,
                $targetSeats
            );

        $renewalPricing =
            $paymentQuote['pricing']
                ?? null;

        if (!is_array($renewalPricing)
            || ($renewalPricing['seat_addons'] ?? null)
                !== $targetSeats) {
            throw new RuntimeException(
                'Renewal pricing did not preserve the resolved seat target.'
            );
        }

        return [
            'farm_id' => $farmId,
            'plan_code' => $planCode,
            'billing_interval' =>
                $billingInterval,
            'modules' => $modules,
            'current_period_ends_at' =>
                $periodEnd,
            'current_seat_addons' =>
                $currentSeats,
            'renewal_seat_addons' =>
                $targetSeats,
            'scheduled_request_ids' =>
                $requestIds,
            'scheduled_count' =>
                count($requestIds),
            'has_scheduled_reductions' =>
                count($requestIds) > 0,
            'pricing' =>
                $renewalPricing,
            'payment_quote' =>
                $paymentQuote,
            'current_product' =>
                $current,
        ];
    }
}
?>
