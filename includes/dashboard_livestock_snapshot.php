<?php
/**
 * Constant-query dashboard livestock snapshot.
 *
 * Preserves the legacy dashboard ticker semantics while avoiding per-cycle
 * database round trips. Query count stays constant as active cycle count grows.
 */

if (!function_exists('dashboard_livestock_snapshot')) {
function dashboard_livestock_snapshot(PDO $pdo, int $farmId, string $farmAccess): array
{
    $snapshot = [
        'poultry' => [
            'Layer' => null,
            'Broiler' => null,
        ],
        'ruminant' => [
            'Cattle' => null,
            'Goat' => null,
            'Sheep' => null,
            'Other' => null,
        ],
    ];

    if ($farmId <= 0 || !in_array($farmAccess, ['poultry', 'ruminant', 'both'], true)) {
        return $snapshot;
    }

    try {
        $cycleTableAvailable = $pdo->query("SHOW TABLES LIKE 'production_cycles'")->rowCount() > 0;
    } catch (Throwable $e) {
        return $snapshot;
    }
    if (!$cycleTableAvailable) return $snapshot;

    if ($farmAccess === 'poultry' || $farmAccess === 'both') {
        $poultrySources = [
            'layer' => ['table' => 'layer_daily_records', 'label' => 'Layer'],
            'broiler' => ['table' => 'broiler_daily_records', 'label' => 'Broiler'],
        ];

        foreach ($poultrySources as $productionType => $source) {
            $table = $source['table'];
            $stmt = $pdo->prepare(
                "SELECT d.opening_stock, d.mortality
                 FROM production_cycles pc
                 INNER JOIN {$table} d
                    ON d.farm_id = pc.farm_id
                   AND d.cycle_id = pc.id
                   AND d.id = (
                        SELECT d2.id
                        FROM {$table} d2
                        WHERE d2.farm_id = pc.farm_id
                          AND d2.cycle_id = pc.id
                        ORDER BY d2.record_date DESC, d2.id DESC
                        LIMIT 1
                   )
                 WHERE pc.farm_id = ?
                   AND pc.farm_type = 'poultry'
                   AND LOWER(pc.production_type) = ?
                   AND pc.status = 'active'"
            );
            $stmt->execute([$farmId, $productionType]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) continue;
            $total = 0;
            foreach ($rows as $row) {
                $total += max(0, (int)$row['opening_stock'] - (int)$row['mortality']);
            }
            $snapshot['poultry'][$source['label']] = $total;
        }
    }

    if ($farmAccess === 'ruminant' || $farmAccess === 'both') {
        $stmt = $pdo->prepare(
            "SELECT pc.id AS cycle_id,
                    pc.production_type,
                    COALESCE(SUM(d.opening_stock - d.mortality), 0) AS cycle_stock,
                    COUNT(d.id) AS record_count
             FROM production_cycles pc
             LEFT JOIN ruminant_daily_records d
               ON d.farm_id = pc.farm_id
              AND d.cycle_id = pc.id
              AND d.record_date = (
                    SELECT MAX(d2.record_date)
                    FROM ruminant_daily_records d2
                    WHERE d2.farm_id = pc.farm_id
                      AND d2.cycle_id = pc.id
              )
             WHERE pc.farm_id = ?
               AND pc.farm_type = 'ruminant'
               AND pc.status = 'active'
             GROUP BY pc.id, pc.production_type"
        );
        $stmt->execute([$farmId]);

        $totals = ['cattle' => 0, 'goat' => 0, 'sheep' => 0, 'other' => 0];
        $found = ['cattle' => false, 'goat' => false, 'sheep' => false, 'other' => false];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int)$row['record_count'] <= 0) continue;
            $cycleType = strtolower((string)$row['production_type']);
            if (!array_key_exists($cycleType, $totals)) $cycleType = 'other';
            $totals[$cycleType] += max(0, (int)$row['cycle_stock']);
            $found[$cycleType] = true;
        }

        foreach (['Cattle' => 'cattle', 'Goat' => 'goat', 'Sheep' => 'sheep', 'Other' => 'other'] as $label => $key) {
            $snapshot['ruminant'][$label] = $found[$key] ? $totals[$key] : null;
        }
    }

    return $snapshot;
}
}
