<?php
/**
 * Renee Farms V3.0 — explicit Sales physical-population effects.
 *
 * sales_records remains financial truth.
 * sale_population_effects is the durable physical source owned here.
 *
 * No population effect is inferred from product name, quantity, or sales UOM.
 * Tagged-ruminant exits remain owned by the ruminant lifecycle service.
 */
require_once __DIR__ . '/production_population_projection.php';

if (!class_exists('SalePopulationEffectException')) {
    class SalePopulationEffectException extends RuntimeException {}
}

if (!function_exists('sale_population_effect_positive_int')) {
    function sale_population_effect_positive_int($value, string $label): int
    {
        if (is_int($value)) {
            $parsed = $value;
        } elseif (
            is_string($value)
            && preg_match('/^[0-9]+$/', trim($value)) === 1
        ) {
            $parsed = (int)trim($value);
        } else {
            throw new InvalidArgumentException(
                "{$label} must be a whole number greater than 0."
            );
        }

        if ($parsed <= 0) {
            throw new InvalidArgumentException(
                "{$label} must be a whole number greater than 0."
            );
        }

        return $parsed;
    }
}

if (!function_exists('sale_population_effect_normalize_rows')) {
    function sale_population_effect_normalize_rows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException(
                    'Each sale population allocation must identify a cycle and headcount.'
                );
            }

            $cycleId = sale_population_effect_positive_int(
                $row['cycle_id'] ?? null,
                'Production cycle'
            );

            $quantity = sale_population_effect_positive_int(
                $row['population_quantity'] ?? null,
                'Population quantity'
            );

            if (isset($normalized[$cycleId])) {
                throw new InvalidArgumentException(
                    'Each production cycle can appear only once in a sale population allocation.'
                );
            }

            $normalized[$cycleId] = $quantity;
        }

        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }
}

if (!function_exists('sale_population_effect_rows_from_post')) {
    /**
     * Convert the explicit Sales form contract into canonical physical rows.
     *
     * Missing mode is deliberately financial-only for backwards compatibility.
     * Financial quantity, product text, and UOM are never inspected here.
     */
    function sale_population_effect_rows_from_post(array $post): array
    {
        $mode = strtolower(trim(
            (string)($post['population_effect_mode'] ?? 'financial_only')
        ));

        if ($mode === '' || $mode === 'financial_only') {
            return [];
        }

        if ($mode !== 'remove_live_population') {
            throw new InvalidArgumentException(
                'Select whether this sale is financial-only or removes live population.'
            );
        }

        $cycleIds = $post['population_cycle_ids'] ?? [];
        $quantities = $post['population_quantities'] ?? [];

        if (!is_array($cycleIds) || !is_array($quantities)) {
            throw new InvalidArgumentException(
                'Population source cycles and headcounts must be submitted as matching rows.'
            );
        }

        $cycleIds = array_values($cycleIds);
        $quantities = array_values($quantities);

        if (
            !$cycleIds
            || count($cycleIds) !== count($quantities)
        ) {
            throw new InvalidArgumentException(
                'Add at least one source cycle and whole live headcount.'
            );
        }

        $rows = [];
        foreach ($cycleIds as $index => $cycleId) {
            $rows[] = [
                'cycle_id' => $cycleId,
                'population_quantity' => $quantities[$index] ?? null,
            ];
        }

        $normalized = sale_population_effect_normalize_rows($rows);
        $result = [];

        foreach ($normalized as $cycleId => $quantity) {
            $result[] = [
                'cycle_id' => $cycleId,
                'population_quantity' => $quantity,
            ];
        }

        return $result;
    }
}

if (!function_exists('sale_population_effect_rows_for_sales')) {
    /**
     * Read current explicit physical effects for Sales presentation/editing.
     * Inactive rows remain durable audit history but are not current stock effect.
     */
    function sale_population_effect_rows_for_sales(
        PDO $pdo,
        int $farmId,
        array $saleIds
    ): array {
        if ($farmId <= 0) {
            throw new InvalidArgumentException('A valid farm is required.');
        }

        $saleIds = array_values(array_unique(array_filter(
            array_map('intval', $saleIds),
            static fn(int $id): bool => $id > 0
        )));

        if (!$saleIds) {
            return [];
        }

        sort($saleIds, SORT_NUMERIC);
        $placeholders = implode(',', array_fill(0, count($saleIds), '?'));

        $stmt = $pdo->prepare(
            "SELECT
                 spe.id,
                 spe.sale_id,
                 spe.cycle_id,
                 spe.population_quantity,
                 pc.cycle_code,
                 pc.farm_type,
                 pc.production_type
             FROM sale_population_effects spe
             INNER JOIN production_cycles pc
                 ON pc.id=spe.cycle_id
                AND pc.farm_id=spe.farm_id
             WHERE spe.farm_id=?
               AND spe.sale_id IN ({$placeholders})
               AND spe.is_active=1
             ORDER BY spe.sale_id,spe.cycle_id,spe.id"
        );

        $stmt->execute(array_merge([$farmId], $saleIds));

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $saleId = (int)$row['sale_id'];
            $map[$saleId][] = [
                'id' => (int)$row['id'],
                'cycle_id' => (int)$row['cycle_id'],
                'population_quantity' => (int)$row['population_quantity'],
                'cycle_code' => (string)$row['cycle_code'],
                'farm_type' => (string)$row['farm_type'],
                'production_type' => (string)$row['production_type'],
            ];
        }

        return $map;
    }
}

if (!function_exists('sale_population_effect_sync')) {
    /**
     * Synchronize all explicit physical headcount removals owned by one sale.
     *
     * $rows = [
     *   ['cycle_id' => 12, 'population_quantity' => 50],
     *   ...
     * ];
     *
     * Empty rows means financial-only: any existing sale-owned physical effects
     * are reversed through the canonical projection synchronizer and removed.
     */
    function sale_population_effect_sync(
        PDO $pdo,
        int $farmId,
        int $saleId,
        array $rows,
        ?int $userId
    ): array {
        if ($farmId <= 0 || $saleId <= 0) {
            throw new InvalidArgumentException(
                'A valid farm and sale are required.'
            );
        }

        production_population_assert_user_id($userId);
        $desired = sale_population_effect_normalize_rows($rows);

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $saleStmt = $pdo->prepare(
                "SELECT
                     id,
                     farm_id,
                     sale_date,
                     farm_type,
                     production_type,
                     cycle_id
                 FROM sales_records
                 WHERE id=?
                   AND farm_id=?
                 LIMIT 1
                 FOR UPDATE"
            );
            $saleStmt->execute([$saleId, $farmId]);
            $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                throw new SalePopulationEffectException(
                    'The selected sale was not found in this farm.'
                );
            }

            $saleDate = (string)$sale['sale_date'];
            $saleFarmType = strtolower(trim((string)$sale['farm_type']));
            $saleProductionType =
                strtolower(trim((string)$sale['production_type']));
            $saleCycleId =
                isset($sale['cycle_id']) && (int)$sale['cycle_id'] > 0
                    ? (int)$sale['cycle_id']
                    : null;

            if (
                $desired
                && !in_array($saleFarmType, ['poultry', 'ruminant'], true)
            ) {
                throw new SalePopulationEffectException(
                    'Only poultry or livestock sales can remove physical population.'
                );
            }

            /*
             * Tagged ruminants and aggregate/unregistered animals have separate
             * population writers and may legitimately coexist in one sale.
             *
             * This service owns only the explicitly supplied aggregate/group
             * headcount. It never reads, counts, derives, or re-projects tagged
             * Animal Registry exits; those remain lifecycle-owned.
             */
            $existingStmt = $pdo->prepare(
                "SELECT
                     id,
                     cycle_id,
                     population_quantity,
                     is_active
                 FROM sale_population_effects
                 WHERE farm_id=?
                   AND sale_id=?
                 ORDER BY cycle_id,id
                 FOR UPDATE"
            );
            $existingStmt->execute([$farmId, $saleId]);

            $existing = [];
            foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[(int)$row['cycle_id']] = $row;
            }

            /*
             * Lock every old/new cycle in deterministic numeric order before
             * any projection change.
             */
            $cycleIds = array_values(array_unique(array_merge(
                array_keys($existing),
                array_keys($desired)
            )));
            sort($cycleIds, SORT_NUMERIC);

            $cycles = [];
            foreach ($cycleIds as $cycleId) {
                $cycles[$cycleId] = production_population_lock_cycle(
                    $pdo,
                    $farmId,
                    (int)$cycleId
                );
            }

            /*
             * A financial sale attributed to one exact cycle may remove
             * population only from that same cycle. If physical population came
             * from multiple cycles, the sale itself must use shared attribution.
             * Financial-only sales remain valid with no population effect.
             */
            if ($desired && $saleCycleId !== null) {
                if (
                    count($desired) !== 1
                    || !array_key_exists($saleCycleId, $desired)
                ) {
                    throw new SalePopulationEffectException(
                        'A cycle-attributed sale can remove population only from its selected production cycle. Use shared cycle attribution when live population comes from multiple cycles.'
                    );
                }
            }

            /*
             * Validate only desired effects against the sale's current scope.
             * Obsolete effects must remain removable even when an edit changes
             * the financial sale's farm/production classification.
             */
            foreach ($desired as $cycleId => $quantity) {
                $cycle = $cycles[$cycleId];

                if (
                    strtolower((string)$cycle['farm_type'])
                    !== $saleFarmType
                ) {
                    throw new SalePopulationEffectException(
                        'A population source cycle must match the sale farm type.'
                    );
                }

                if (
                    $saleProductionType !== 'shared'
                    && strtolower((string)$cycle['production_type'])
                        !== $saleProductionType
                ) {
                    throw new SalePopulationEffectException(
                        'A population source cycle must match the sale production type.'
                    );
                }

                production_population_assert_date_in_cycle(
                    $cycle,
                    $saleDate,
                    'Sale date'
                );
            }

            $projectionResults = [];

            /*
             * Remove obsolete projections first. The durable effect row remains
             * as the source identity referenced by immutable population history.
             */
            foreach ($existing as $cycleId => $row) {
                if (isset($desired[$cycleId])) {
                    continue;
                }

                $effectId = (int)$row['id'];

                $projectionResults[$effectId] =
                    production_population_projection_sync(
                        $pdo,
                        $farmId,
                        (int)$cycleId,
                        'sale',
                        $effectId,
                        null,
                        $userId,
                        'Sale population effect removed after sale correction.'
                    );

                $deactivate = $pdo->prepare(
                    "UPDATE sale_population_effects
                     SET is_active=0,
                         updated_by=?
                     WHERE id=?
                       AND farm_id=?
                       AND sale_id=?"
                );
                $deactivate->execute([
                    $userId,
                    $effectId,
                    $farmId,
                    $saleId
                ]);
            }

            /*
             * Existing cycle facts keep their durable id. Quantity/date edits
             * therefore become projection reversals + later source versions.
             */
            foreach ($desired as $cycleId => $quantity) {
                $old = $existing[$cycleId] ?? null;

                if ($old) {
                    $effectId = (int)$old['id'];

                    if (
                        (int)$old['population_quantity'] !== $quantity
                        || (int)$old['is_active'] !== 1
                    ) {
                        $update = $pdo->prepare(
                            "UPDATE sale_population_effects
                             SET population_quantity=?,
                                 is_active=1,
                                 updated_by=?
                             WHERE id=?
                               AND farm_id=?
                               AND sale_id=?"
                        );
                        $update->execute([
                            $quantity,
                            $userId,
                            $effectId,
                            $farmId,
                            $saleId
                        ]);
                    }
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO sale_population_effects
                         (
                             farm_id,
                             sale_id,
                             cycle_id,
                             population_quantity,
                             is_active,
                             created_by,
                             updated_by
                         )
                         VALUES (?,?,?,?,?,?,?)"
                    );
                    $insert->execute([
                        $farmId,
                        $saleId,
                        $cycleId,
                        $quantity,
                        1,
                        $userId,
                        $userId
                    ]);

                    $effectId = (int)$pdo->lastInsertId();
                }

                $projectionResults[$effectId] =
                    production_population_projection_sync(
                        $pdo,
                        $farmId,
                        (int)$cycleId,
                        'sale',
                        $effectId,
                        [
                            'movement_type' => 'sale',
                            'movement_date' => $saleDate,
                            'quantity' => $quantity,
                            'notes' => 'Explicit live-population removal from Sale #'
                                . $saleId . '.',
                        ],
                        $userId,
                        'Sale population effect synchronized after sale change.'
                    );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'status' => $desired ? 'synchronized' : 'financial_only',
                'sale_id' => $saleId,
                'effect_count' => count($desired),
                'projections' => $projectionResults,
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}

if (!function_exists('sale_population_effect_assert_deletable')) {
    /**
     * A sale that has ever owned a physical population effect is historical
     * source evidence and must not be hard-deleted.
     *
     * Active and inactive rows both count: inactive means the physical effect
     * was reversed, not that its source history ceased to exist.
     */
    function sale_population_effect_assert_deletable(
        PDO $pdo,
        int $farmId,
        int $saleId
    ): void {
        if ($farmId <= 0 || $saleId <= 0) {
            throw new InvalidArgumentException(
                'A valid farm and sale are required.'
            );
        }

        $stmt = $pdo->prepare(
            "SELECT id
             FROM sale_population_effects
             WHERE farm_id=?
               AND sale_id=?
             LIMIT 1"
        );
        $stmt->execute([$farmId, $saleId]);

        if ($stmt->fetchColumn() !== false) {
            throw new SalePopulationEffectException(
                'This sale has physical population history and cannot be deleted. Edit the sale instead so its population correction remains auditable.'
            );
        }
    }
}

if (!function_exists('sale_population_effect_clear')) {
    function sale_population_effect_clear(
        PDO $pdo,
        int $farmId,
        int $saleId,
        ?int $userId
    ): array {
        return sale_population_effect_sync(
            $pdo,
            $farmId,
            $saleId,
            [],
            $userId
        );
    }
}
