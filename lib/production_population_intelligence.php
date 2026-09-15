<?php
/**
 * V3 population intelligence read contract.
 *
 * Physical population truth is canonical when a cycle has entered the V3
 * population ledger. Legacy Daily Records remain a clearly identified fallback
 * for cycles/dates that have not entered that contract.
 *
 * This helper owns read policy only. It never writes population, Daily Records,
 * production cycles, or baselines.
 */

require_once __DIR__ . '/production_population.php';

if (!function_exists('production_population_intelligence_legacy_snapshot')) {
    function production_population_intelligence_legacy_snapshot(
        PDO $pdo,
        int $farmId,
        array $cycle,
        ?string $asOfDate = null
    ): array {
        if ($farmId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid farm.'
            );
        }

        $cycleId = (int)($cycle['id'] ?? 0);

        if ($cycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid production cycle.'
            );
        }

        if ($asOfDate !== null) {
            $asOfDate = trim($asOfDate);

            if (!production_population_valid_date($asOfDate)) {
                throw new InvalidArgumentException(
                    'Enter a valid population snapshot date.'
                );
            }
        }

        $farmType = strtolower(
            trim((string)($cycle['farm_type'] ?? ''))
        );

        $productionType = strtolower(
            trim((string)($cycle['production_type'] ?? ''))
        );

        if ($farmType === 'poultry') {
            $tables = [
                'layer' => 'layer_daily_records',
                'broiler' => 'broiler_daily_records',
            ];

            if (!isset($tables[$productionType])) {
                return [
                    'quantity' => 0,
                    'has_snapshot' => false,
                    'snapshot_date' => null,
                ];
            }

            $table = $tables[$productionType];

            $sql =
                "SELECT opening_stock, mortality, record_date
                 FROM {$table}
                 WHERE farm_id = ?
                   AND cycle_id = ?";

            $params = [
                $farmId,
                $cycleId,
            ];

            if ($asOfDate !== null) {
                $sql .= ' AND record_date <= ?';
                $params[] = $asOfDate;
            }

            $sql .=
                ' ORDER BY record_date DESC, id DESC
                  LIMIT 1';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return [
                    'quantity' => 0,
                    'has_snapshot' => false,
                    'snapshot_date' => null,
                ];
            }

            return [
                'quantity' => max(
                    0,
                    (int)$row['opening_stock']
                    - (int)$row['mortality']
                ),
                'has_snapshot' => true,
                'snapshot_date' =>
                    (string)$row['record_date'],
            ];
        }

        if ($farmType === 'ruminant') {
            $dateSql =
                'SELECT MAX(record_date)
                 FROM ruminant_daily_records
                 WHERE farm_id = ?
                   AND cycle_id = ?';

            $dateParams = [
                $farmId,
                $cycleId,
            ];

            if ($asOfDate !== null) {
                $dateSql .= ' AND record_date <= ?';
                $dateParams[] = $asOfDate;
            }

            $dateStmt = $pdo->prepare($dateSql);
            $dateStmt->execute($dateParams);

            $snapshotDate =
                $dateStmt->fetchColumn();

            if (!$snapshotDate) {
                return [
                    'quantity' => 0,
                    'has_snapshot' => false,
                    'snapshot_date' => null,
                ];
            }

            $sumStmt = $pdo->prepare(
                'SELECT COALESCE(
                     SUM(opening_stock - mortality),
                     0
                 )
                 FROM ruminant_daily_records
                 WHERE farm_id = ?
                   AND cycle_id = ?
                   AND record_date = ?'
            );

            $sumStmt->execute([
                $farmId,
                $cycleId,
                $snapshotDate,
            ]);

            return [
                'quantity' => max(
                    0,
                    (int)$sumStmt->fetchColumn()
                ),
                'has_snapshot' => true,
                'snapshot_date' =>
                    (string)$snapshotDate,
            ];
        }

        return [
            'quantity' => 0,
            'has_snapshot' => false,
            'snapshot_date' => null,
        ];
    }
}

if (!function_exists('production_population_intelligence_cycle_snapshot')) {
    function production_population_intelligence_cycle_snapshot(
        PDO $pdo,
        int $farmId,
        array $cycle,
        ?string $asOfDate = null,
        bool $canonicalAvailable = true
    ): array {
        if ($farmId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid farm.'
            );
        }

        $cycleId = (int)($cycle['id'] ?? 0);

        if ($cycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid production cycle.'
            );
        }

        if ($asOfDate !== null) {
            $asOfDate = trim($asOfDate);

            if (!production_population_valid_date($asOfDate)) {
                throw new InvalidArgumentException(
                    'Enter a valid population snapshot date.'
                );
            }
        }

        $trackingStatus = 'legacy_untracked';
        $currentCanonicalState = null;

        if ($canonicalAvailable) {
            $currentCanonicalState =
                production_population_state(
                    $pdo,
                    $farmId,
                    $cycleId
                );

            if ($currentCanonicalState !== null) {
                $baselineDate =
                    (string)$currentCanonicalState[
                        'baseline_date'
                    ];

                if (
                    $asOfDate === null
                    || $asOfDate >= $baselineDate
                ) {
                    $state =
                        $asOfDate === null
                            ? $currentCanonicalState
                            : production_population_state(
                                $pdo,
                                $farmId,
                                $cycleId,
                                $asOfDate
                            );

                    return [
                        'cycle_id' => $cycleId,
                        'quantity' =>
                            (int)$state['quantity'],
                        'tracking_status' =>
                            'canonical',
                        'source' =>
                            'v3_population_ledger',
                        'is_canonical' => true,
                        'has_snapshot' => true,
                        'as_of_date' => $asOfDate,
                        'baseline_date' =>
                            (string)$state[
                                'baseline_date'
                            ],
                        'legacy_snapshot_date' =>
                            null,
                        'canonical_state' => $state,
                    ];
                }

                $trackingStatus =
                    'before_baseline_untracked';
            }
        }

        $legacy =
            production_population_intelligence_legacy_snapshot(
                $pdo,
                $farmId,
                $cycle,
                $asOfDate
            );

        return [
            'cycle_id' => $cycleId,
            'quantity' =>
                (int)$legacy['quantity'],
            'tracking_status' =>
                $trackingStatus,
            'source' =>
                'legacy_daily_records',
            'is_canonical' => false,
            'has_snapshot' =>
                (bool)$legacy['has_snapshot'],
            'as_of_date' => $asOfDate,
            'baseline_date' =>
                $currentCanonicalState !== null
                    ? (string)$currentCanonicalState[
                        'baseline_date'
                    ]
                    : null,
            'legacy_snapshot_date' =>
                $legacy['snapshot_date'],
            'canonical_state' => null,
        ];
    }
}
