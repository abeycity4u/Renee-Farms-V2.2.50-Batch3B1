<?php

declare(strict_types=1);

/**
 * Ruminant Daily Record mortality summary read model.
 *
 * Daily Record mortality owns unregistered/group losses.
 * Tagged animal deaths own ruminant_exit mortality movements.
 * The summary combines both read paths without writing or duplicating
 * canonical population movements.
 */

if (!function_exists('ruminant_daily_record_mortality_from_records')) {
    function ruminant_daily_record_mortality_from_records(
        array $records
    ): int {
        $total = 0;

        foreach ($records as $record) {
            $mortality = (int)($record['mortality'] ?? 0);

            if ($mortality > 0) {
                $total += $mortality;
            }
        }

        return $total;
    }
}

if (!function_exists('ruminant_daily_record_mortality_summary')) {
    function ruminant_daily_record_mortality_summary(
        PDO $pdo,
        int $farmId,
        string $yearMonth,
        array $records,
        ?int $selectedCycleId = null
    ): array {
        if ($farmId <= 0) {
            throw new InvalidArgumentException('Select a valid farm.');
        }

        if (
            !preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)
        ) {
            throw new InvalidArgumentException(
                'Select a valid reporting month.'
            );
        }

        if ($selectedCycleId !== null && $selectedCycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid production cycle.'
            );
        }

        $monthStart = $yearMonth . '-01';
        $monthEnd = (new DateTimeImmutable($monthStart))
            ->modify('last day of this month')
            ->format('Y-m-d');

        $dailyRecordMortality =
            ruminant_daily_record_mortality_from_records($records);

        $sql =
            "SELECT
                 COALESCE(SUM(ABS(m.quantity_delta)), 0)
             FROM production_population_movements m
             INNER JOIN production_cycles pc
               ON pc.id = m.cycle_id
              AND pc.farm_id = m.farm_id
             WHERE m.farm_id = ?
               AND pc.farm_type = 'ruminant'
               AND m.movement_date >= ?
               AND m.movement_date <= ?
               AND m.movement_type = 'mortality'
               AND m.source_type = 'ruminant_exit'
               AND m.quantity_delta < 0
               AND m.reversal_of_id IS NULL
               AND NOT EXISTS (
                   SELECT 1
                   FROM production_population_movements r
                   WHERE r.farm_id = m.farm_id
                     AND r.cycle_id = m.cycle_id
                     AND r.reversal_of_id = m.id
               )";

        $params = [
            $farmId,
            $monthStart,
            $monthEnd,
        ];

        if ($selectedCycleId !== null) {
            $sql .= ' AND m.cycle_id = ?';
            $params[] = $selectedCycleId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $taggedMortality = max(
            0,
            (int)$stmt->fetchColumn()
        );

        return [
            'daily_record_mortality' => $dailyRecordMortality,
            'tagged_mortality' => $taggedMortality,
            'total_mortality' =>
                $dailyRecordMortality + $taggedMortality,
        ];
    }
}
