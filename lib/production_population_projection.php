<?php
/**
 * Renee Farms V3.0 — durable-source population projection synchronizer.
 *
 * Business writers own their durable source rows. This service owns how one
 * mutable source fact is projected into the append-only population ledger.
 *
 * A source correction never updates/deletes a population movement:
 * - unchanged source projection => no-op;
 * - changed projection => reverse the current movement, append next version;
 * - source no longer changes population => reverse current movement only;
 * - cycle without a V3 baseline => remain on the legacy path with no V3 write.
 */
require_once __DIR__ . '/production_population.php';

if (!class_exists('ProductionPopulationProjectionException')) {
    class ProductionPopulationProjectionException extends RuntimeException {}
}

if (!function_exists('production_population_projection_source_types')) {
    function production_population_projection_source_types(): array
    {
        return array_values(array_filter(
            production_population_source_types(),
            static fn(string $type): bool => $type !== 'adjustment'
        ));
    }
}

if (!function_exists('production_population_projection_normalize_source_type')) {
    function production_population_projection_normalize_source_type(string $sourceType): string
    {
        $sourceType = strtolower(trim($sourceType));

        if (!in_array(
            $sourceType,
            production_population_projection_source_types(),
            true
        )) {
            throw new InvalidArgumentException(
                'Select a valid durable population source type.'
            );
        }

        return $sourceType;
    }
}

if (!function_exists('production_population_projection_desired')) {
    /**
     * NULL means the durable source currently has no physical population effect.
     */
    function production_population_projection_desired(?array $desired): ?array
    {
        if ($desired === null) {
            return null;
        }

        $movementType = strtolower(trim((string)($desired['movement_type'] ?? '')));
        $movementDate = trim((string)($desired['movement_date'] ?? ''));
        $quantity = (int)($desired['quantity'] ?? 0);
        $notes = array_key_exists('notes', $desired)
            ? production_population_normalize_note(
                $desired['notes'] !== null ? (string)$desired['notes'] : null
            )
            : null;

        if (!array_key_exists(
            $movementType,
            production_population_movement_types()
        )) {
            throw new InvalidArgumentException(
                'Select a valid population movement type.'
            );
        }

        if (!production_population_valid_date($movementDate)) {
            throw new InvalidArgumentException(
                'Enter a valid population movement date.'
            );
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Population projection quantity must be greater than 0.'
            );
        }

        return [
            'movement_type' => $movementType,
            'movement_date' => $movementDate,
            'quantity' => $quantity,
            'notes' => $notes,
        ];
    }
}

if (!function_exists('production_population_projection_active_locked')) {
    /**
     * Caller already owns the cycle/baseline write boundary.
     */
    function production_population_projection_active_locked(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $sourceType,
        int $sourceId
    ): ?array {
        $stmt = $pdo->prepare(
            'SELECT
                 m.id,
                 m.movement_date,
                 m.movement_type,
                 m.quantity_delta,
                 m.source_version,
                 m.notes
             FROM production_population_movements m
             WHERE m.farm_id = ?
               AND m.cycle_id = ?
               AND m.source_type = ?
               AND m.source_id = ?
               AND m.reversal_of_id IS NULL
               AND NOT EXISTS (
                   SELECT 1
                   FROM production_population_movements r
                   WHERE r.farm_id = m.farm_id
                     AND r.cycle_id = m.cycle_id
                     AND r.reversal_of_id = m.id
               )
             ORDER BY m.source_version DESC, m.id DESC
             LIMIT 2
             FOR UPDATE'
        );

        $stmt->execute([
            $farmId,
            $cycleId,
            $sourceType,
            $sourceId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 1) {
            throw new ProductionPopulationProjectionException(
                'Population projection integrity check failed: this source has multiple active movements in one cycle.'
            );
        }

        return $rows[0] ?? null;
    }
}

if (!function_exists('production_population_projection_latest_version_locked')) {
    function production_population_projection_latest_version_locked(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $sourceType,
        int $sourceId
    ): int {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(source_version), 0)
             FROM production_population_movements
             WHERE farm_id = ?
               AND cycle_id = ?
               AND source_type = ?
               AND source_id = ?
               AND reversal_of_id IS NULL'
        );

        $stmt->execute([
            $farmId,
            $cycleId,
            $sourceType,
            $sourceId,
        ]);

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('production_population_projection_matches')) {
    function production_population_projection_matches(
        array $current,
        array $desired
    ): bool {
        $direction = production_population_movement_types()[
            $desired['movement_type']
        ];

        $desiredDelta = (int)$desired['quantity'] * (int)$direction;

        return (string)$current['movement_date']
                === (string)$desired['movement_date']
            && (string)$current['movement_type']
                === (string)$desired['movement_type']
            && (int)$current['quantity_delta'] === $desiredDelta;
    }
}

if (!function_exists('production_population_projection_sync')) {
    /**
     * Synchronize one durable source row to its canonical population projection.
     *
     * $desired = NULL removes any current projection.
     *
     * Return status is intentionally explicit so legacy callers can preserve
     * existing V2.x behavior until their cycle has entered the V3 baseline.
     */
    function production_population_projection_sync(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $sourceType,
        int $sourceId,
        ?array $desired,
        ?int $userId,
        ?string $correctionReason = null
    ): array {
        if ($farmId <= 0 || $cycleId <= 0 || $sourceId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid farm, production cycle, and durable source record.'
            );
        }

        $sourceType =
            production_population_projection_normalize_source_type($sourceType);

        $desired =
            production_population_projection_desired($desired);

        production_population_assert_user_id($userId);

        $correctionReason = trim((string)$correctionReason);
        if ($correctionReason === '') {
            $correctionReason =
                'Source projection synchronized after source record change.';
        }

        if (production_population_text_length($correctionReason) > 255) {
            throw new InvalidArgumentException(
                'Population correction reason must be 255 characters or fewer.'
            );
        }

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $cycle = production_population_lock_cycle(
                $pdo,
                $farmId,
                $cycleId
            );

            $baseline = production_population_lock_baseline(
                $pdo,
                $farmId,
                $cycleId
            );

            if (!$baseline) {
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return [
                    'status' => 'legacy_untracked',
                    'movement_id' => null,
                    'reversal_id' => null,
                    'source_version' => null,
                    'cycle_id' => $cycleId,
                ];
            }

            $current =
                production_population_projection_active_locked(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $sourceType,
                    $sourceId
                );

            $latestVersion =
                production_population_projection_latest_version_locked(
                    $pdo,
                    $farmId,
                    $cycleId,
                    $sourceType,
                    $sourceId
                );

            $beforeBaseline =
                $desired !== null
                && (string)$desired['movement_date']
                    < (string)$baseline['baseline_date'];

            if (
                $current !== null
                && !$beforeBaseline
                && $desired !== null
                && production_population_projection_matches(
                    $current,
                    $desired
                )
            ) {
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return [
                    'status' => 'unchanged',
                    'movement_id' => (int)$current['id'],
                    'reversal_id' => null,
                    'source_version' => (int)$current['source_version'],
                    'cycle_id' => $cycleId,
                ];
            }

            $reversalId = null;

            if ($current !== null) {
                $reversalId =
                    production_population_reverse_movement(
                        $pdo,
                        $farmId,
                        $cycleId,
                        (int)$current['id'],
                        $correctionReason,
                        $userId
                    );
            }

            if ($desired === null || $beforeBaseline) {
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return [
                    'status' => $beforeBaseline
                        ? 'before_baseline_untracked'
                        : ($current !== null ? 'removed' : 'unchanged_empty'),
                    'movement_id' => null,
                    'reversal_id' => $reversalId,
                    'source_version' => null,
                    'cycle_id' => $cycleId,
                ];
            }

            $nextVersion = $latestVersion + 1;

            $movementId =
                production_population_record_movement(
                    $pdo,
                    $farmId,
                    $cycleId,
                    (string)$desired['movement_type'],
                    (string)$desired['movement_date'],
                    (int)$desired['quantity'],
                    $sourceType,
                    $sourceId,
                    $nextVersion,
                    null,
                    $desired['notes'],
                    $userId
                );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'status' => $current !== null ? 'corrected' : 'created',
                'movement_id' => $movementId,
                'reversal_id' => $reversalId,
                'source_version' => $nextVersion,
                'cycle_id' => $cycleId,
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
