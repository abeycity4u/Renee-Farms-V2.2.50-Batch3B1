<?php
declare(strict_types=1);

/**
 * B12 coordinated-cutover helper: bump the authoritative revision whenever
 * continuity changes a LATER Daily Record. SOURCE ONLY; NOT WIRED TO ROUTES.
 *
 * The caller owns the transaction and first obtains the normal chronological
 * later-row locks through daily_population_continuity_preview(..., true).
 * This one-row conditional UPDATE retains the original opening-stock guard,
 * keeps Layer laying-rate recalculation, and increments revision exactly once.
 * The helper requires the future three-table record_revision migration; do
 * not call from existing live writers until the entire CAS cutover is ready.
 * No permissions, CSRF, cycle-lifecycle rules or side-effect order are granted
 * or changed here. Any exception must roll back the caller-owned transaction.
 */
require_once __DIR__ . '/daily_record_revision_authority.php';

if (!function_exists('daily_record_revision_apply_continuity_change')) {
    function daily_record_revision_apply_continuity_change(
        PDO $pdo,
        string $type,
        int $farmId,
        int $cycleId,
        ?string $animalType,
        array $change
    ): void {
        daily_record_revision_require_transaction($pdo);
        $definition = daily_record_revision_definition($type);
        foreach (['id', 'record_date', 'old_opening_stock', 'new_opening_stock'] as $key) {
            if (!array_key_exists($key, $change)) {
                throw new InvalidArgumentException('Incomplete continuity row change.');
            }
        }
        if (!is_int($change['id']) || !is_string($change['record_date']) ||
            !is_int($change['old_opening_stock']) || !is_int($change['new_opening_stock']) ||
            $change['old_opening_stock'] < 0 || $change['new_opening_stock'] < 1 ||
            $change['old_opening_stock'] === $change['new_opening_stock']) {
            throw new InvalidArgumentException('Invalid or unchanged continuity row.');
        }
        if ($cycleId < 1) {
            throw new InvalidArgumentException('Continuity needs an explicit cycle.');
        }
        $species = daily_record_revision_validate_scope(
            $farmId,
            $change['id'],
            $cycleId,
            $change['record_date'],
            (bool)$definition['animal_type'],
            $animalType
        );
        if ($definition['animal_type'] && !in_array(
            $species, ['cattle', 'goat', 'sheep', 'other'], true
        )) {
            throw new InvalidArgumentException('Unsupported continuity species.');
        }

        $set = 'opening_stock = ?, record_revision = record_revision + 1';
        $params = [$change['new_opening_stock']];
        if ($type === 'layer') {
            $rate = $change['new_laying_rate'] ?? null;
            if ((!is_int($rate) && !is_float($rate)) ||
                !is_finite((float)$rate) || $rate < 0 || $rate > 100) {
                throw new InvalidArgumentException('Invalid recalculated laying rate.');
            }
            $set = 'opening_stock = ?, laying_rate = ?, record_revision = record_revision + 1';
            $params[] = $rate;
        }
        $sql = 'UPDATE ' . $definition['table'] . ' SET ' . $set
            . ' WHERE id = ? AND farm_id = ? AND cycle_id = ?'
            . ' AND record_date = ? AND opening_stock = ?';
        array_push(
            $params,
            $change['id'],
            $farmId,
            $cycleId,
            $change['record_date'],
            $change['old_opening_stock']
        );
        if ($definition['animal_type']) {
            $sql .= ' AND LOWER(animal_type) = ?';
            $params[] = $species;
        }
        // Never let an unsigned MariaDB revision exceed the range that can
        // subsequently be represented by the strict 64-bit PHP edit protocol.
        $sql .= ' AND record_revision >= 1 AND record_revision < ?';
        $params[] = PHP_INT_MAX - 1;
        $stmt = $pdo->prepare($sql);
        if ($stmt === false || !$stmt->execute($params) || $stmt->rowCount() !== 1) {
            throw new DailyRecordRevisionConflict(
                'A later Daily Record changed while applying population continuity.'
            );
        }
    }
}
