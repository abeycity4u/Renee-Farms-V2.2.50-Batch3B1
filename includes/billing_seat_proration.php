<?php
/**
 * V2.3 server-authoritative extra-seat proration service.
 *
 * Commercial contract:
 * - only an active tenant with an authoritative paid subscription lineage
 *   can obtain an immediate seat-top-up quote;
 * - current billing boundaries come from recorded commercial history, never
 *   from reverse month/year arithmetic;
 * - the current interval is prorated by exact elapsed seconds;
 * - every already-paid future full interval is charged at one full current
 *   extra-seat unit price;
 * - calculations use integer minor currency units and half-up rounding;
 * - this service is read-only and performs no payment, entitlement or
 *   subscription mutation.
 */

require_once __DIR__ . '/billing_current_product.php';
require_once __DIR__ . '/billing_pricing_contract.php';
require_once __DIR__ . '/subscription_seat_policy.php';

if (!function_exists('billing_seat_proration_parse_datetime')) {
    function billing_seat_proration_parse_datetime($value): ?DateTimeImmutable
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable(
                (string)$value,
                new DateTimeZone(date_default_timezone_get())
            );
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('billing_seat_proration_decode_modules')) {
    function billing_seat_proration_decode_modules($raw): ?array
    {
        $decoded = json_decode((string)$raw, true);

        if (!is_array($decoded)) {
            return null;
        }

        try {
            return billing_pricing_normalize_modules($decoded);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('billing_seat_proration_modules_match')) {
    function billing_seat_proration_modules_match(
        $raw,
        array $expected
    ): bool {
        $actual = billing_seat_proration_decode_modules($raw);

        if ($actual === null) {
            return false;
        }

        $expected = billing_pricing_normalize_modules($expected);

        return $actual === $expected;
    }
}

if (!function_exists('billing_seat_proration_amount')) {
    function billing_seat_proration_amount(
        DateTimeImmutable $now,
        DateTimeImmutable $segmentStart,
        DateTimeImmutable $segmentEnd,
        int $unitMinor,
        int $futureFullPeriods,
        int $quantity = 1
    ): array {
        if ($unitMinor < 1) {
            throw new InvalidArgumentException(
                'Extra-seat unit price must be positive.'
            );
        }

        if ($futureFullPeriods < 0) {
            throw new InvalidArgumentException(
                'Future paid period count cannot be negative.'
            );
        }

        if ($quantity < 1 || $quantity > 500) {
            throw new InvalidArgumentException(
                'Extra-seat quantity must be between 1 and 500.'
            );
        }

        if ($segmentStart >= $segmentEnd) {
            throw new RuntimeException(
                'Commercial billing segment is invalid.'
            );
        }

        if ($now < $segmentStart || $now >= $segmentEnd) {
            throw new RuntimeException(
                'Quote time is outside the current commercial billing segment.'
            );
        }

        $duration = $segmentEnd->getTimestamp()
            - $segmentStart->getTimestamp();

        $remaining = $segmentEnd->getTimestamp()
            - $now->getTimestamp();

        if ($duration < 1 || $remaining < 1) {
            throw new RuntimeException(
                'Commercial proration duration is invalid.'
            );
        }

        $partialUnitMinor = intdiv(
            ($unitMinor * $remaining)
                + intdiv($duration, 2),
            $duration
        );

        $perSeatMinor = $partialUnitMinor
            + ($futureFullPeriods * $unitMinor);

        $totalMinor = $perSeatMinor * $quantity;

        if ($totalMinor < 1) {
            throw new RuntimeException(
                'The remaining seat top-up amount is below the minimum billable unit.'
            );
        }

        return [
            'unit_minor' => $unitMinor,
            'partial_unit_minor' => $partialUnitMinor,
            'future_full_periods' => $futureFullPeriods,
            'per_seat_minor' => $perSeatMinor,
            'quantity' => $quantity,
            'total_minor' => $totalMinor,
            'segment_duration_seconds' => $duration,
            'segment_remaining_seconds' => $remaining,
        ];
    }
}

if (!function_exists('billing_seat_proration_timeline')) {
    function billing_seat_proration_timeline(
        PDO $pdo,
        int $farmId,
        ?DateTimeImmutable $now = null
    ): array {
        if ($farmId < 1) {
            throw new InvalidArgumentException(
                'A valid tenant farm is required for seat proration.'
            );
        }

        $current = billing_current_product(
            $pdo,
            $farmId,
            ['active']
        );

        $planCode = (string)$current['plan_code'];
        $modules = billing_pricing_normalize_modules(
            $current['modules']
        );
        $interval = billing_pricing_normalize_interval(
            (string)$current['pricing']['billing_interval']
        );

        $now = $now ?: new DateTimeImmutable(
            'now',
            new DateTimeZone(date_default_timezone_get())
        );

        $paidStmt = $pdo->prepare(
            "SELECT
                s.id AS subscription_id,
                s.plan_code,
                s.billing_interval,
                s.modules_snapshot,
                s.subscription_starts_at,
                s.subscription_ends_at,
                s.current_period_ends_at,
                b.id AS payment_attempt_id,
                b.purpose,
                b.status,
                b.paid_at
             FROM subscriptions s
             INNER JOIN billing_payment_attempts b
               ON b.applied_subscription_record_id = s.id
             WHERE s.farm_id = ?
               AND b.status = 'paid'
               AND b.purpose = 'subscription'
               AND s.change_reason = 'billing_payment_applied'
             ORDER BY s.id DESC
             LIMIT 1"
        );

        $paidStmt->execute([$farmId]);
        $latestPaid = $paidStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$latestPaid) {
            throw new RuntimeException(
                'Self-service seat top-up requires an authoritative paid subscription history.'
            );
        }

        $paidPlan = strtolower(
            trim((string)$latestPaid['plan_code'])
        );

        $paidInterval = billing_pricing_normalize_interval(
            (string)$latestPaid['billing_interval']
        );

        if ($paidPlan !== $planCode
            || $paidInterval !== $interval
            || !billing_seat_proration_modules_match(
                $latestPaid['modules_snapshot'] ?? null,
                $modules
            )) {
            throw new RuntimeException(
                'Current commercial product does not match the latest paid subscription lineage.'
            );
        }

        $lineageStart = billing_seat_proration_parse_datetime(
            $latestPaid['subscription_starts_at'] ?? null
        );

        $paidEnd = billing_seat_proration_parse_datetime(
            $latestPaid['current_period_ends_at'] ?? null
        );

        if (!$lineageStart || !$paidEnd) {
            throw new RuntimeException(
                'Paid subscription lineage has incomplete commercial dates.'
            );
        }

        if ($lineageStart >= $paidEnd) {
            throw new RuntimeException(
                'Paid subscription lineage dates are invalid.'
            );
        }

        if ($now < $lineageStart || $now >= $paidEnd) {
            throw new RuntimeException(
                'The paid subscription is not currently inside an eligible seat-top-up period.'
            );
        }

        $latestHistory = $current['latest_subscription'] ?? null;

        if (!is_array($latestHistory)) {
            throw new RuntimeException(
                'Current subscription history is unavailable.'
            );
        }

        $latestPeriodEnd = billing_seat_proration_parse_datetime(
            $latestHistory['current_period_ends_at'] ?? null
        );

        if (!$latestPeriodEnd
            || $latestPeriodEnd->getTimestamp()
                !== $paidEnd->getTimestamp()) {
            throw new RuntimeException(
                'Current billing period does not match the authoritative paid subscription end.'
            );
        }

        $historyStmt = $pdo->prepare(
            "SELECT
                s.id AS subscription_id,
                s.plan_code,
                s.billing_interval,
                s.modules_snapshot,
                s.subscription_starts_at,
                s.subscription_ends_at,
                s.current_period_ends_at,
                b.id AS payment_attempt_id,
                b.purpose AS payment_purpose,
                b.status AS payment_status
             FROM subscriptions s
             LEFT JOIN billing_payment_attempts b
               ON b.applied_subscription_record_id = s.id
             WHERE s.farm_id = ?
               AND s.id <= ?
             ORDER BY s.id"
        );

        $historyStmt->execute([
            $farmId,
            (int)$latestPaid['subscription_id'],
        ]);

        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        $boundaries = [
            $lineageStart->getTimestamp() => $lineageStart,
            $paidEnd->getTimestamp() => $paidEnd,
        ];

        $paidEndpoints = [];

        foreach ($history as $row) {
            if (strtolower(trim((string)$row['plan_code']))
                !== $planCode) {
                continue;
            }

            try {
                $rowInterval = billing_pricing_normalize_interval(
                    (string)$row['billing_interval']
                );
            } catch (Throwable $e) {
                continue;
            }

            if ($rowInterval !== $interval) {
                continue;
            }

            if (!billing_seat_proration_modules_match(
                $row['modules_snapshot'] ?? null,
                $modules
            )) {
                continue;
            }

            $rowStart = billing_seat_proration_parse_datetime(
                $row['subscription_starts_at'] ?? null
            );

            if (!$rowStart
                || $rowStart->getTimestamp()
                    !== $lineageStart->getTimestamp()) {
                continue;
            }

            foreach ([
                'subscription_ends_at',
                'current_period_ends_at',
            ] as $field) {
                $boundary = billing_seat_proration_parse_datetime(
                    $row[$field] ?? null
                );

                if (!$boundary) {
                    continue;
                }

                $timestamp = $boundary->getTimestamp();

                if ($timestamp <= $lineageStart->getTimestamp()
                    || $timestamp > $paidEnd->getTimestamp()) {
                    continue;
                }

                $boundaries[$timestamp] = $boundary;
            }

            $isPaidEndpoint =
                (string)($row['payment_purpose'] ?? '') === 'subscription'
                && (string)($row['payment_status'] ?? '') === 'paid';

            if ($isPaidEndpoint) {
                $paymentEnd = billing_seat_proration_parse_datetime(
                    $row['current_period_ends_at'] ?? null
                );

                if ($paymentEnd
                    && $paymentEnd > $lineageStart
                    && $paymentEnd <= $paidEnd) {
                    $paidEndpoints[
                        $paymentEnd->getTimestamp()
                    ] = $paymentEnd;
                }
            }
        }

        ksort($boundaries, SORT_NUMERIC);
        ksort($paidEndpoints, SORT_NUMERIC);

        $ordered = array_values($boundaries);

        if (count($ordered) < 2) {
            throw new RuntimeException(
                'Commercial billing history does not contain enough boundaries for proration.'
            );
        }

        $segmentStart = null;
        $segmentEnd = null;

        for ($i = 1; $i < count($ordered); $i++) {
            $left = $ordered[$i - 1];
            $right = $ordered[$i];

            if ($left <= $now && $now < $right) {
                $segmentStart = $left;
                $segmentEnd = $right;
                break;
            }
        }

        if (!$segmentStart || !$segmentEnd) {
            throw new RuntimeException(
                'Unable to resolve the current commercial billing segment.'
            );
        }

        $futureFullPeriods = 0;

        foreach ($paidEndpoints as $paymentEnd) {
            if ($paymentEnd > $segmentEnd
                && $paymentEnd <= $paidEnd) {
                $futureFullPeriods++;
            }
        }

        return [
            'farm_id' => $farmId,
            'plan_code' => $planCode,
            'modules' => $modules,
            'billing_interval' => $interval,
            'lineage_start' => $lineageStart,
            'segment_start' => $segmentStart,
            'segment_end' => $segmentEnd,
            'paid_end' => $paidEnd,
            'future_full_periods' => $futureFullPeriods,
            'current_product' => $current,
            'latest_paid_subscription_id' =>
                (int)$latestPaid['subscription_id'],
            'latest_paid_attempt_id' =>
                (int)$latestPaid['payment_attempt_id'],
        ];
    }
}

if (!function_exists('billing_seat_proration_unit_price')) {
    function billing_seat_proration_unit_price(
        string $planCode,
        string $roleCode,
        string $billingInterval,
        array $modules
    ): array {
        $roles = subscription_seat_roles();

        if (!array_key_exists($roleCode, $roles)) {
            throw new InvalidArgumentException(
                'Unknown extra-seat role.'
            );
        }

        if (!subscription_seat_role_relevant(
            $roleCode,
            $modules
        )) {
            throw new InvalidArgumentException(
                'The selected extra-seat role is not available for the current livestock subscription.'
            );
        }

        $book = billing_pricing_validate_price_book(
            billing_pricing_price_book()
        );

        $raw = $book[
            'seat_unit_prices'
        ][$planCode][$roleCode][$billingInterval] ?? null;

        if ($raw === null) {
            throw new RuntimeException(
                'No server-authoritative extra-seat unit price is configured.'
            );
        }

        return [
            'pricing_version' => $book['version'],
            'pricing_hash' => $book['price_book_hash'],
            'currency' => $book['currency'],
            'unit_minor' => billing_pricing_decimal_to_minor(
                (string)$raw
            ),
        ];
    }
}

if (!function_exists('billing_seat_proration_quote')) {
    function billing_seat_proration_quote(
        PDO $pdo,
        int $farmId,
        string $roleCode,
        int $quantity,
        ?DateTimeImmutable $now = null
    ): array {
        if ($quantity < 1 || $quantity > 500) {
            throw new InvalidArgumentException(
                'Extra-seat quantity must be between 1 and 500.'
            );
        }

        $timeline = billing_seat_proration_timeline(
            $pdo,
            $farmId,
            $now
        );

        $current = $timeline['current_product'];
        $seatAddOns = subscription_seat_normalize_addons(
            $current['seat_addons']
        );

        if (!array_key_exists($roleCode, $seatAddOns)) {
            throw new InvalidArgumentException(
                'Unknown extra-seat role.'
            );
        }

        $fromExtraSeats = (int)$seatAddOns[$roleCode];
        $toExtraSeats = $fromExtraSeats + $quantity;

        if ($toExtraSeats > 500) {
            throw new InvalidArgumentException(
                'The resulting extra-seat quantity exceeds the supported limit.'
            );
        }

        $targetSeatAddOns = $seatAddOns;
        $targetSeatAddOns[$roleCode] = $toExtraSeats;

        subscription_seat_assert_capacity(
            $pdo,
            $farmId,
            $timeline['plan_code'],
            $timeline['modules'],
            $targetSeatAddOns
        );

        $price = billing_seat_proration_unit_price(
            $timeline['plan_code'],
            $roleCode,
            $timeline['billing_interval'],
            $timeline['modules']
        );

        $quoteNow = $now ?: new DateTimeImmutable(
            'now',
            new DateTimeZone(date_default_timezone_get())
        );

        $amount = billing_seat_proration_amount(
            $quoteNow,
            $timeline['segment_start'],
            $timeline['segment_end'],
            $price['unit_minor'],
            $timeline['future_full_periods'],
            $quantity
        );

        return [
            'farm_id' => $farmId,
            'role_code' => $roleCode,
            'quantity' => $quantity,
            'from_extra_seats' => $fromExtraSeats,
            'to_extra_seats' => $toExtraSeats,
            'target_seat_addons' => $targetSeatAddOns,
            'plan_code' => $timeline['plan_code'],
            'modules' => $timeline['modules'],
            'billing_interval' => $timeline['billing_interval'],
            'pricing_version' => $price['pricing_version'],
            'pricing_hash' => $price['pricing_hash'],
            'currency' => $price['currency'],
            'unit_amount' => billing_pricing_minor_to_decimal(
                $price['unit_minor']
            ),
            'partial_unit_amount' =>
                billing_pricing_minor_to_decimal(
                    $amount['partial_unit_minor']
                ),
            'future_full_periods' =>
                $amount['future_full_periods'],
            'per_seat_amount' =>
                billing_pricing_minor_to_decimal(
                    $amount['per_seat_minor']
                ),
            'amount' => billing_pricing_minor_to_decimal(
                $amount['total_minor']
            ),
            'quoted_at' => $quoteNow->format('Y-m-d H:i:s'),
            'segment_start' =>
                $timeline['segment_start']->format(
                    'Y-m-d H:i:s'
                ),
            'segment_end' =>
                $timeline['segment_end']->format(
                    'Y-m-d H:i:s'
                ),
            'current_period_ends_at' =>
                $timeline['paid_end']->format(
                    'Y-m-d H:i:s'
                ),
            'latest_paid_subscription_id' =>
                $timeline['latest_paid_subscription_id'],
            'latest_paid_attempt_id' =>
                $timeline['latest_paid_attempt_id'],
        ];
    }
}
