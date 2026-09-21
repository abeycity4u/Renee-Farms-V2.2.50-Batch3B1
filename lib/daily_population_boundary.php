<?php
/**
 * Renee Farms V3.0.1 — canonical Daily Record population boundary read model.
 *
 * Daily Records keep ownership of operational facts such as mortality, feed,
 * water and production. Physical population truth stays in the canonical
 * production population baseline + movement ledger.
 *
 * This reader answers two questions for any tracked date:
 * - opening population: quantity before that date's physical movements;
 * - closing population: quantity after that date's physical movements.
 *
 * A pending Daily Record mortality correction can be overlaid read-only so
 * continuity can be planned before the canonical projection writer runs.
 * This file never writes population movements.
 */

require_once __DIR__ . '/production_population.php';
require_once __DIR__ . '/production_population_projection.php';

if (!class_exists('DailyPopulationBoundaryException')) {
    class DailyPopulationBoundaryException extends RuntimeException {}
}

if (!function_exists('daily_population_boundary_valid_date')) {
    function daily_population_boundary_valid_date(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable
            && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('daily_population_boundary_normalize_dates')) {
    function daily_population_boundary_normalize_dates(array $dates): array
    {
        $normalized = [];

        foreach ($dates as $date) {
            $date = trim((string)$date);

            if (!daily_population_boundary_valid_date($date)) {
                throw new InvalidArgumentException(
                    'Enter a valid Daily Record population date.'
                );
            }

            $normalized[$date] = true;
        }

        $dates = array_keys($normalized);
        sort($dates, SORT_STRING);

        return $dates;
    }
}

if (!function_exists('daily_population_boundary_movement_labels')) {
    function daily_population_boundary_movement_labels(): array
    {
        return [
            'acquisition' => 'Acquired',
            'birth' => 'Birth',
            'transfer_in' => 'Transferred in',
            'mortality' => 'Mortality',
            'sale' => 'Sold',
            'cull' => 'Culled',
            'slaughter' => 'Slaughtered',
            'transfer_out' => 'Transferred out',
            'adjustment_in' => 'Adjustment in',
            'adjustment_out' => 'Adjustment out',
        ];
    }
}

if (!function_exists('daily_population_boundary_movement_label')) {
    function daily_population_boundary_movement_label(string $movementType): string
    {
        $movementType = strtolower(trim($movementType));
        $labels = daily_population_boundary_movement_labels();

        return $labels[$movementType]
            ?? ucwords(str_replace('_', ' ', $movementType));
    }
}

if (!function_exists('daily_population_boundary_build_snapshots')) {
    /**
     * Pure population-boundary calculator.
     *
     * $movementByDate shape:
     * [
     *   '2026-09-21' => [
     *       'mortality' => -2,
     *       'sale' => -500,
     *   ],
     * ]
     */
    function daily_population_boundary_build_snapshots(
        string $baselineDate,
        int $baselineQuantity,
        array $dates,
        array $movementByDate
    ): array {
        if (!daily_population_boundary_valid_date($baselineDate)) {
            throw new InvalidArgumentException(
                'Canonical population baseline date is invalid.'
            );
        }

        if ($baselineQuantity < 0) {
            throw new DailyPopulationBoundaryException(
                'Canonical population baseline cannot be negative.'
            );
        }

        $dates = daily_population_boundary_normalize_dates($dates);

        if (!$dates) {
            return [];
        }

        $requested = array_fill_keys($dates, true);
        $timeline = $requested;

        foreach ($movementByDate as $movementDate => $totals) {
            $movementDate = trim((string)$movementDate);

            if (!daily_population_boundary_valid_date($movementDate)) {
                throw new DailyPopulationBoundaryException(
                    'Canonical population movement date is invalid.'
                );
            }

            if (!is_array($totals)) {
                throw new DailyPopulationBoundaryException(
                    'Canonical population movement totals are invalid.'
                );
            }

            $timeline[$movementDate] = true;
        }

        $timeline = array_keys($timeline);
        sort($timeline, SORT_STRING);

        $running = $baselineQuantity;
        $snapshots = [];

        foreach ($timeline as $date) {
            if ($date < $baselineDate) {
                continue;
            }

            $opening = $running;
            $movementTotals = $movementByDate[$date] ?? [];
            ksort($movementTotals, SORT_STRING);

            $additions = 0;
            $removals = 0;

            foreach ($movementTotals as $movementType => $deltaRaw) {
                $delta = (int)$deltaRaw;
                $running += $delta;

                if ($running < 0) {
                    throw new DailyPopulationBoundaryException(
                        'Canonical population history becomes negative on '
                        . $date
                        . '.'
                    );
                }

                if ($delta > 0) {
                    $additions += $delta;
                } elseif ($delta < 0) {
                    $removals += abs($delta);
                }
            }

            if (!isset($requested[$date])) {
                continue;
            }

            $snapshots[$date] = [
                'record_date' => $date,
                'opening_quantity' => $opening,
                'closing_quantity' => $running,
                'net_change' => $running - $opening,
                'additions' => $additions,
                'removals' => $removals,
                'movement_totals' => $movementTotals,
            ];
        }

        return $snapshots;
    }
}

if (!function_exists('daily_population_boundary_apply_pending_mortality')) {
    /**
     * Replace the currently active Daily Record mortality projection with the
     * requested one in an in-memory movement map. No ledger write occurs here.
     */
    function daily_population_boundary_apply_pending_mortality(
        array $movementByDate,
        int $cycleId,
        ?array $currentProjection,
        string $sourceType,
        int $sourceId,
        string $recordDate,
        int $mortality
    ): array {
        if ($cycleId <= 0 || $sourceId <= 0) {
            throw new InvalidArgumentException(
                'A valid cycle and Daily Record source are required.'
            );
        }

        if (!daily_population_boundary_valid_date($recordDate)) {
            throw new InvalidArgumentException(
                'Enter a valid Daily Record population date.'
            );
        }

        if ($mortality < 0) {
            throw new InvalidArgumentException(
                'Daily Record mortality cannot be negative.'
            );
        }

        $allowedSources = [
            'daily_layer_record',
            'daily_broiler_record',
            'daily_ruminant_record',
        ];

        $sourceType = strtolower(trim($sourceType));

        if (!in_array($sourceType, $allowedSources, true)) {
            throw new InvalidArgumentException(
                'Select a valid Daily Record population source.'
            );
        }

        if ($currentProjection !== null) {
            $currentCycleId = (int)($currentProjection['cycle_id'] ?? 0);

            if ($currentCycleId !== $cycleId) {
                throw new DailyPopulationBoundaryException(
                    'Daily Record population projection belongs to another cycle.'
                );
            }

            $currentDate = (string)($currentProjection['movement_date'] ?? '');
            $currentType = strtolower(
                trim((string)($currentProjection['movement_type'] ?? ''))
            );
            $currentDelta = (int)($currentProjection['quantity_delta'] ?? 0);

            if (
                !daily_population_boundary_valid_date($currentDate)
                || $currentType === ''
            ) {
                throw new DailyPopulationBoundaryException(
                    'Current Daily Record population projection is invalid.'
                );
            }

            if (!isset($movementByDate[$currentDate])) {
                $movementByDate[$currentDate] = [];
            }

            $movementByDate[$currentDate][$currentType] =
                (int)($movementByDate[$currentDate][$currentType] ?? 0)
                - $currentDelta;

            if ($movementByDate[$currentDate][$currentType] === 0) {
                unset($movementByDate[$currentDate][$currentType]);
            }

            if (!$movementByDate[$currentDate]) {
                unset($movementByDate[$currentDate]);
            }
        }

        if ($mortality > 0) {
            if (!isset($movementByDate[$recordDate])) {
                $movementByDate[$recordDate] = [];
            }

            $movementByDate[$recordDate]['mortality'] =
                (int)($movementByDate[$recordDate]['mortality'] ?? 0)
                - $mortality;
        }

        return $movementByDate;
    }
}

if (!function_exists('daily_population_boundary_active_movements')) {
    /**
     * Return effective active population movements, grouped by date and type.
     * Reversed originals and their compensating reversal rows are both omitted.
     */
    function daily_population_boundary_active_movements(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $fromDate,
        string $toDate
    ): array {
        if (
            $farmId <= 0
            || $cycleId <= 0
            || !daily_population_boundary_valid_date($fromDate)
            || !daily_population_boundary_valid_date($toDate)
            || $toDate < $fromDate
        ) {
            throw new InvalidArgumentException(
                'Select a valid canonical population movement range.'
            );
        }

        $stmt = $pdo->prepare(
            'SELECT
                 m.movement_date,
                 m.movement_type,
                 COALESCE(SUM(m.quantity_delta), 0) AS quantity_delta
             FROM production_population_movements m
             WHERE m.farm_id = ?
               AND m.cycle_id = ?
               AND m.movement_date >= ?
               AND m.movement_date <= ?
               AND m.reversal_of_id IS NULL
               AND NOT EXISTS (
                   SELECT 1
                   FROM production_population_movements r
                   WHERE r.farm_id = m.farm_id
                     AND r.cycle_id = m.cycle_id
                     AND r.reversal_of_id = m.id
               )
             GROUP BY m.movement_date, m.movement_type
             ORDER BY m.movement_date ASC, m.movement_type ASC'
        );

        $stmt->execute([
            $farmId,
            $cycleId,
            $fromDate,
            $toDate,
        ]);

        $movements = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = (string)$row['movement_date'];
            $type = (string)$row['movement_type'];
            $delta = (int)$row['quantity_delta'];

            if ($delta === 0) {
                continue;
            }

            if (!isset($movements[$date])) {
                $movements[$date] = [];
            }

            $movements[$date][$type] = $delta;
        }

        return $movements;
    }
}

if (!function_exists('daily_population_boundary_snapshots')) {
    /**
     * Read canonical opening/closing population for several dates in one cycle.
     *
     * Returns an empty array when the cycle has not entered the V3 population
     * contract or all requested dates are earlier than its baseline. Callers
     * can then preserve the legacy Daily Record fallback.
     *
     * Optional $pendingMortality shape:
     * [
     *   'source_type' => 'daily_layer_record',
     *   'source_id' => 123,
     *   'record_date' => '2026-09-21',
     *   'mortality' => 2,
     *   'for_update' => true,
     * ]
     */
    function daily_population_boundary_snapshots(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        array $dates,
        ?array $pendingMortality = null
    ): array {
        if ($farmId <= 0 || $cycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid production cycle.'
            );
        }

        $dates = daily_population_boundary_normalize_dates($dates);

        if (!$dates) {
            return [];
        }

        $canonical = production_population_state(
            $pdo,
            $farmId,
            $cycleId
        );

        if ($canonical === null) {
            return [];
        }

        $baselineDate = (string)$canonical['baseline_date'];
        $trackedDates = array_values(
            array_filter(
                $dates,
                static fn(string $date): bool => $date >= $baselineDate
            )
        );

        if (!$trackedDates) {
            return [];
        }

        $maxDate = max($trackedDates);

        $movementByDate =
            daily_population_boundary_active_movements(
                $pdo,
                $farmId,
                $cycleId,
                $baselineDate,
                $maxDate
            );

        if ($pendingMortality !== null) {
            $sourceType = (string)($pendingMortality['source_type'] ?? '');
            $sourceId = (int)($pendingMortality['source_id'] ?? 0);
            $recordDate = (string)($pendingMortality['record_date'] ?? '');
            $mortality = (int)($pendingMortality['mortality'] ?? 0);
            $forUpdate = !empty($pendingMortality['for_update']);

            $currentProjection = $forUpdate
                ? production_population_projection_active_locked(
                    $pdo,
                    $farmId,
                    $sourceType,
                    $sourceId
                )
                : production_population_projection_active_snapshot(
                    $pdo,
                    $farmId,
                    $sourceType,
                    $sourceId
                );

            $movementByDate =
                daily_population_boundary_apply_pending_mortality(
                    $movementByDate,
                    $cycleId,
                    $currentProjection,
                    $sourceType,
                    $sourceId,
                    $recordDate,
                    $mortality
                );
        }

        $snapshots = daily_population_boundary_build_snapshots(
            $baselineDate,
            (int)$canonical['baseline_quantity'],
            $trackedDates,
            $movementByDate
        );

        foreach ($snapshots as &$snapshot) {
            $snapshot['tracking_status'] = 'canonical';
            $snapshot['source'] = 'v3_population_ledger';
            $snapshot['farm_id'] = $farmId;
            $snapshot['cycle_id'] = $cycleId;
            $snapshot['baseline_date'] = $baselineDate;
        }
        unset($snapshot);

        return $snapshots;
    }
}

if (!function_exists('daily_population_boundary_snapshot')) {
    function daily_population_boundary_snapshot(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $recordDate,
        ?array $pendingMortality = null
    ): ?array {
        $snapshots = daily_population_boundary_snapshots(
            $pdo,
            $farmId,
            $cycleId,
            [$recordDate],
            $pendingMortality
        );

        return $snapshots[$recordDate] ?? null;
    }
}
