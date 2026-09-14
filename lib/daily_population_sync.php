<?php
/**
 * Renee Farms V3.0 — shared Daily Record mortality population projection.
 *
 * Daily Record rows remain the durable source facts. This helper owns how
 * Layer, Broiler, and aggregate/unregistered Ruminant mortality is projected
 * into the append-only production population ledger.
 *
 * Tagged ruminant exits are NOT owned here; they are owned by ruminant_exit
 * through the lifecycle service.
 */
require_once __DIR__ . '/production_population_projection.php';

if (!function_exists('daily_population_mortality_source_type')) {
    function daily_population_mortality_source_type(string $sourceType): string
    {
        $sourceType = strtolower(trim($sourceType));
        $allowed = [
            'daily_layer_record',
            'daily_broiler_record',
            'daily_ruminant_record',
        ];

        if (!in_array($sourceType, $allowed, true)) {
            throw new InvalidArgumentException(
                'Select a valid Daily Record population source.'
            );
        }

        return $sourceType;
    }
}

if (!function_exists('daily_population_sync_mortality')) {
    function daily_population_sync_mortality(
        PDO $pdo,
        int $farmId,
        string $sourceType,
        int $sourceId,
        ?int $cycleId,
        string $recordDate,
        int $mortality,
        ?int $userId
    ): array {
        if ($farmId <= 0 || $sourceId <= 0) {
            throw new InvalidArgumentException(
                'A valid farm and Daily Record are required.'
            );
        }

        if (!production_population_valid_date($recordDate)) {
            throw new InvalidArgumentException(
                'Enter a valid Daily Record date.'
            );
        }

        if ($mortality < 0) {
            throw new InvalidArgumentException(
                'Daily Record mortality cannot be negative.'
            );
        }

        $sourceType = daily_population_mortality_source_type($sourceType);
        $cycleId = ($cycleId !== null && $cycleId > 0) ? $cycleId : null;

        if ($cycleId === null) {
            $current = production_population_projection_active_snapshot(
                $pdo,
                $farmId,
                $sourceType,
                $sourceId
            );

            if ($current === null) {
                return [
                    'status' => 'legacy_untracked',
                    'movement_id' => null,
                    'reversal_id' => null,
                    'source_version' => null,
                    'cycle_id' => null,
                ];
            }

            return production_population_projection_sync(
                $pdo,
                $farmId,
                (int)$current['cycle_id'],
                $sourceType,
                $sourceId,
                null,
                $userId,
                'Daily mortality projection removed because the source record no longer belongs to a production cycle.'
            );
        }

        $desired = $mortality > 0
            ? [
                'movement_type' => 'mortality',
                'movement_date' => $recordDate,
                'quantity' => $mortality,
                'notes' => 'Daily Record mortality.',
            ]
            : null;

        return production_population_projection_sync(
            $pdo,
            $farmId,
            $cycleId,
            $sourceType,
            $sourceId,
            $desired,
            $userId,
            'Daily mortality synchronized after Daily Record change.'
        );
    }
}

if (!function_exists('daily_population_remove_mortality')) {
    function daily_population_remove_mortality(
        PDO $pdo,
        int $farmId,
        string $sourceType,
        int $sourceId,
        ?int $userId
    ): array {
        if ($farmId <= 0 || $sourceId <= 0) {
            throw new InvalidArgumentException(
                'A valid farm and Daily Record are required.'
            );
        }

        $sourceType = daily_population_mortality_source_type($sourceType);

        $current = production_population_projection_active_snapshot(
            $pdo,
            $farmId,
            $sourceType,
            $sourceId
        );

        if ($current === null) {
            return [
                'status' => 'unchanged_empty',
                'movement_id' => null,
                'reversal_id' => null,
                'source_version' => null,
                'cycle_id' => null,
            ];
        }

        return production_population_projection_sync(
            $pdo,
            $farmId,
            (int)$current['cycle_id'],
            $sourceType,
            $sourceId,
            null,
            $userId,
            'Daily mortality projection removed because the Daily Record was deleted.'
        );
    }
}
