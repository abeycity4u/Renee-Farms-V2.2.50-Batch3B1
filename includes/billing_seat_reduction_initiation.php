<?php
/**
 * V2.3 scheduled seat-reduction initiation foundation.
 *
 * Contract:
 * - Farm/actor identity is supplied by the authenticated caller;
 * - this service owns its transaction;
 * - tenant commercial activity is serialized through the shared coordination
 *   foundation before the reduction is derived;
 * - only an active paid commercial product may schedule a reduction;
 * - role/current seats/period/currency/product facts are server authoritative;
 * - removal is no-refund and carries no payment attempt;
 * - effective_at is exactly the current paid-period end;
 * - durable persistence delegates to billing_seat_change_request_insert();
 * - current tenant entitlement is NOT changed here;
 * - no provider or network work occurs here.
 */

require_once __DIR__ . '/billing_current_product.php';
require_once __DIR__ . '/billing_seat_change_request.php';
require_once __DIR__ . '/billing_commercial_attempt_coordination.php';

if (!function_exists('billing_seat_reduction_request_input')) {
    function billing_seat_reduction_request_input(
        array $current,
        int $farmId,
        string $roleCode,
        int $quantity,
        int $initiatedByUserId
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for seat reduction.'
            );
        }

        if ($initiatedByUserId < 1) {
            throw new InvalidArgumentException(
                'A valid initiating user is required for seat reduction.'
            );
        }

        $roleCode =
            billing_seat_change_normalize_role(
                $roleCode
            );

        $quantityValidated = filter_var(
            $quantity,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 500,
                ],
            ]
        );

        if ($quantityValidated === false) {
            throw new InvalidArgumentException(
                'Seat-reduction quantity must be an integer between 1 and 500.'
            );
        }

        $quantity = (int)$quantityValidated;

        if ((int)($current['farm']['id'] ?? 0)
            !== $farmId) {
            throw new RuntimeException(
                'Current commercial product does not belong to the requested tenant.'
            );
        }

        if (strtolower(trim(
            (string)($current['status'] ?? '')
        )) !== 'active') {
            throw new RuntimeException(
                'Seat reduction requires an active paid subscription.'
            );
        }

        $planCode = strtolower(trim(
            (string)($current['plan_code'] ?? '')
        ));

        $modules =
            billing_payment_normalize_modules(
                is_array($current['modules'] ?? null)
                    ? $current['modules']
                    : []
            );

        if (!subscription_seat_role_relevant(
            $roleCode,
            $modules
        )) {
            throw new InvalidArgumentException(
                'The selected seat role is unavailable for this livestock subscription.'
            );
        }

        $currentSeats =
            subscription_seat_normalize_addons(
                is_array(
                    $current['seat_addons'] ?? null
                )
                    ? $current['seat_addons']
                    : []
            );

        $from = (int)($currentSeats[$roleCode] ?? 0);

        if ($from < 1) {
            throw new InvalidArgumentException(
                'There are no purchased extra seats to remove for this role.'
            );
        }

        if ($quantity > $from) {
            throw new InvalidArgumentException(
                'Seat reduction cannot remove more purchased extra seats than currently exist.'
            );
        }

        $to = $from - $quantity;

        $latest =
            $current['latest_subscription'] ?? null;

        if (!is_array($latest)) {
            throw new RuntimeException(
                'Current paid subscription history is unavailable for seat reduction.'
            );
        }

        $periodEnd =
            billing_seat_change_normalize_datetime(
                $latest['current_period_ends_at']
                    ?? null
            );

        $timezone = new DateTimeZone(
            date_default_timezone_get()
        );

        $periodEndDate = new DateTimeImmutable(
            $periodEnd,
            $timezone
        );

        $now = new DateTimeImmutable(
            'now',
            $timezone
        );

        if ($periodEndDate <= $now) {
            throw new RuntimeException(
                'Seat reduction requires a future active paid-period boundary.'
            );
        }

        $pricing =
            $current['pricing'] ?? null;

        if (!is_array($pricing)) {
            throw new RuntimeException(
                'Current server pricing is unavailable for seat reduction.'
            );
        }

        $interval =
            billing_payment_normalize_interval(
                (string)(
                    $pricing['billing_interval']
                        ?? ''
                )
            );

        $currency =
            billing_payment_normalize_currency(
                (string)(
                    $pricing['currency']
                        ?? ''
                )
            );

        return [
            'farm_id' => $farmId,
            'change_kind' => 'remove',
            'role_code' => $roleCode,
            'from_extra_seats' => $from,
            'to_extra_seats' => $to,
            'plan_code' => $planCode,
            'billing_interval' => $interval,
            'modules' => $modules,
            'amount' => '0.00',
            'currency' => $currency,
            'current_period_ends_at' => $periodEnd,

            // No-refund removal deliberately carries no paid-proration
            // or paid-subscription lineage snapshot.
            'quoted_at' => null,
            'lineage_start_at' => null,
            'segment_start_at' => null,
            'segment_end_at' => null,
            'pricing_version' => null,
            'pricing_hash' => null,
            'unit_amount' => null,
            'partial_unit_amount' => null,
            'future_full_periods' => null,
            'per_seat_amount' => null,
            'latest_paid_subscription_id' => null,
            'latest_paid_attempt_id' => null,

            'effective_at' => $periodEnd,
            'payment_attempt_id' => null,
            'initiated_by_user_id' =>
                $initiatedByUserId,
        ];
    }
}

if (!function_exists('billing_seat_reduction_schedule')) {
    function billing_seat_reduction_schedule(
        PDO $pdo,
        int $farmId,
        string $roleCode,
        int $quantity,
        int $initiatedByUserId
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for seat reduction.'
            );
        }

        if ($initiatedByUserId < 1) {
            throw new InvalidArgumentException(
                'A valid initiating user is required for seat reduction.'
            );
        }

        if ($pdo->inTransaction()) {
            throw new RuntimeException(
                'Seat-reduction initiation must own its database transaction.'
            );
        }

        if (!billing_seat_change_ready($pdo)) {
            throw new RuntimeException(
                'Seat-change request storage is not transactionally ready.'
            );
        }

        $pdo->beginTransaction();

        try {
            /*
             * This is the shared serialization point. It locks the farm row
             * and fails closed while an earlier subscription checkout remains
             * commercially unresolved.
             */
            billing_commercial_attempt_assert_clear(
                $pdo,
                $farmId
            );

            $current = billing_current_product(
                $pdo,
                $farmId,
                ['active']
            );

            $requestInput =
                billing_seat_reduction_request_input(
                    $current,
                    $farmId,
                    $roleCode,
                    $quantity,
                    $initiatedByUserId
                );

            $request =
                billing_seat_change_request_insert(
                    $pdo,
                    $requestInput
                );

            $contract =
                $request['contract'] ?? null;

            if (($request['status'] ?? '')
                    !== 'scheduled'
                || !is_array($contract)
                || ($contract['change_kind'] ?? '')
                    !== 'remove'
                || (int)($contract['farm_id'] ?? 0)
                    !== $farmId
                || ($contract['role_code'] ?? '')
                    !== $requestInput['role_code']
                || (int)(
                    $contract['from_extra_seats']
                        ?? -1
                ) !== $requestInput[
                    'from_extra_seats'
                ]
                || (int)(
                    $contract['to_extra_seats']
                        ?? -1
                ) !== $requestInput[
                    'to_extra_seats'
                ]
                || ($contract['effective_at'] ?? null)
                    !== $requestInput['effective_at']
                || ($contract[
                    'payment_attempt_id'
                ] ?? null) !== null) {
                throw new RuntimeException(
                    'Scheduled seat reduction was not persisted with the authoritative contract.'
                );
            }

            $requestId =
                (int)($request['id'] ?? 0);

            if ($requestId < 1) {
                throw new RuntimeException(
                    'Scheduled seat reduction did not receive a durable request id.'
                );
            }

            $pdo->commit();

            return [
                'farm_id' => $farmId,
                'request_id' => $requestId,
                'role_code' =>
                    $requestInput['role_code'],
                'quantity' => $quantity,
                'from_extra_seats' =>
                    $requestInput[
                        'from_extra_seats'
                    ],
                'to_extra_seats' =>
                    $requestInput[
                        'to_extra_seats'
                    ],
                'effective_at' =>
                    $requestInput['effective_at'],
                'request' => $request,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
?>
