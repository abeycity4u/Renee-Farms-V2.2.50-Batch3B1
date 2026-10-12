<?php
declare(strict_types=1);

/**
 * B12 — shared Daily Record revision authority (SOURCE-ONLY, NOT WIRED).
 *
 * This deliberately DOES NOT load config.php, start/commit transactions,
 * mutate external population/stock/sales ledgers, or grant permissions.
 *
 * Requires the FUTURE, separately approved three-table record_revision
 * migration and browser expected-id/revision wiring before any caller uses it.
 *
 * One edit sequence, owned by the authorized caller's PDO transaction:
 *   existing farm/cycle/date/species source FOR UPDATE
 *     -> validate CLIENT-LOADED revision, not a freshly invented revision
 *     -> one field-allowlisted CAS UPDATE plus revision increment
 *     -> original continuity/feed/mortality/(Layer egg-sale) writers
 *     -> caller COMMIT; failure => caller ROLLBACK.
 *
 * The helper cannot implement safe concurrent CREATE for nullable cycle_id
 * legacy rows. Do not make such writes until the separate legacy-key contract
 * is resolved and a database uniqueness gate has been audited.
 */

if (!class_exists('DailyRecordRevisionConflict', false)) {
    final class DailyRecordRevisionConflict extends RuntimeException {}
}

if (!function_exists('daily_record_revision_definitions')) {
    function daily_record_revision_definitions(): array
    {
        return [
            'layer' => [
                'table' => 'layer_daily_records',
                'animal_type' => false,
                'fields' => [
                    'opening_stock', 'mortality', 'feed_consumption_bags',
                    'feed_item_id', 'water_consumption_liters', 'medications',
                    'egg_production', 'crates_count', 'laying_rate', 'birds_age',
                    'remarks',
                ],
            ],
            'broiler' => [
                'table' => 'broiler_daily_records',
                'animal_type' => false,
                'fields' => [
                    'opening_stock', 'mortality', 'feed_consumption_bags',
                    'feed_item_id', 'water_consumption_liters', 'medications',
                    'birds_age', 'remarks',
                ],
            ],
            'ruminant' => [
                'table' => 'ruminant_daily_records',
                'animal_type' => true,
                'fields' => [
                    'opening_stock', 'mortality', 'feed_consumption_kg',
                    'feed_item_id', 'feed_consumption_unit',
                    'water_consumption_liters', 'other_details', 'tag_no',
                    'medications', 'reproduction_details', 'remarks',
                ],
            ],
        ];
    }
}

if (!function_exists('daily_record_revision_definition')) {
    function daily_record_revision_definition(string $type): array
    {
        $type = strtolower(trim($type));
        $definitions = daily_record_revision_definitions();
        if (!array_key_exists($type, $definitions)) {
            throw new InvalidArgumentException('Unsupported Daily Record type.');
        }
        return $definitions[$type];
    }
}

if (!function_exists('daily_record_revision_require_transaction')) {
    function daily_record_revision_require_transaction(PDO $pdo): void
    {
        if (!$pdo->inTransaction()) {
            throw new LogicException(
                'Daily Record revision authority requires a caller-owned transaction.'
            );
        }
    }
}

if (!function_exists('daily_record_revision_validate_scope')) {
    function daily_record_revision_validate_scope(
        int $farmId,
        int $recordId,
        ?int $cycleId,
        string $recordDate,
        bool $requiresAnimalType,
        ?string $animalType
    ): ?string {
        if ($farmId <= 0 || $recordId <= 0 ||
            ($cycleId !== null && $cycleId <= 0)) {
            throw new InvalidArgumentException('Invalid Daily Record identity.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $recordDate);
        if ($date === false || $date->format('Y-m-d') !== $recordDate) {
            throw new InvalidArgumentException('Invalid Daily Record date.');
        }
        if (!$requiresAnimalType) {
            if ($animalType !== null && trim($animalType) !== '') {
                throw new InvalidArgumentException('Unexpected Daily Record species.');
            }
            return null;
        }
        $normalized = strtolower(trim((string)$animalType));
        if ($normalized === '' || strlen($normalized) > 100) {
            throw new InvalidArgumentException('Invalid Daily Record species.');
        }
        return $normalized;
    }
}

if (!function_exists('daily_record_revision_expected')) {
    function daily_record_revision_expected(int $expectedRevision): int
    {
        // Client intent must be strictly parsed before it reaches this method.
        if ($expectedRevision < 1 || $expectedRevision >= PHP_INT_MAX) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record has changed. Reload it before saving.'
            );
        }
        return $expectedRevision;
    }
}

if (!function_exists('daily_record_revision_lock_and_compare')) {
    /**
     * Read and lock the CURRENT source row, then verify immutable identity and
     * the CLIENT-LOADED expected revision. Never supply a server-fetched
     * revision as the expected value at submit time; that defeats B12.
     *
     * Caller is responsible for the existing module permission/CSRF policy.
     * Returns the locked row (not a permission or tenant-grant token).
     */
    function daily_record_revision_lock_and_compare(
        PDO $pdo,
        string $type,
        int $farmId,
        int $recordId,
        ?int $cycleId,
        string $recordDate,
        ?string $animalType,
        int $expectedRevision
    ): array {
        daily_record_revision_require_transaction($pdo);
        $config = daily_record_revision_definition($type);
        $animalType = daily_record_revision_validate_scope(
            $farmId, $recordId, $cycleId, $recordDate,
            (bool)$config['animal_type'], $animalType
        );
        $expectedRevision = daily_record_revision_expected($expectedRevision);

        // Identifiers originate only from the closed definition map above.
        $columns = 'id, farm_id, cycle_id, record_date, record_revision';
        if ($config['animal_type']) {
            $columns .= ', animal_type';
        }
        $stmt = $pdo->prepare(
            'SELECT ' . $columns .
            ' FROM ' . $config['table'] .
            ' WHERE id = ? AND farm_id = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$recordId, $farmId]);
        $locked = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($locked)) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record is no longer available. Reload it before saving.'
            );
        }
        $savedCycle = $locked['cycle_id'] === null
            ? null : (int)$locked['cycle_id'];
        $savedAnimal = $config['animal_type']
            ? strtolower(trim((string)$locked['animal_type']))
            : null;
        if ($savedCycle !== $cycleId ||
            (string)$locked['record_date'] !== $recordDate ||
            $savedAnimal !== $animalType) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record identity changed. Reload it before saving.'
            );
        }
        if ((string)$locked['record_revision'] !== (string)$expectedRevision) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record has changed. Reload it before saving.'
            );
        }
        return $locked;
    }
}

if (!function_exists('daily_record_revision_write')) {
    /**
     * One authoritative, tenant-scoped source edit. Never call from CREATE.
     * Every submitted data column must be in the fixed per-type allowlist;
     * numeric/date/business validation remains in the originating route.
     *
     * All downstream writers MUST execute after this function, within the
     * same still-open caller transaction.
     */
    function daily_record_revision_write(
        PDO $pdo,
        string $type,
        int $farmId,
        int $recordId,
        ?int $cycleId,
        string $recordDate,
        ?string $animalType,
        int $expectedRevision,
        array $fields
    ): int {
        $config = daily_record_revision_definition($type);
        if (!$fields) {
            throw new InvalidArgumentException('A Daily Record edit needs fields.');
        }
        $allowed = array_fill_keys($config['fields'], true);
        foreach ($fields as $column => $_) {
            if (!is_string($column) || !isset($allowed[$column])) {
                throw new InvalidArgumentException(
                    'Unsupported Daily Record edit field.'
                );
            }
        }

        daily_record_revision_lock_and_compare(
            $pdo, $type, $farmId, $recordId, $cycleId,
            $recordDate, $animalType, $expectedRevision
        );

        $setParts = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $setParts[] = $column . ' = ?';
            $params[] = $value;
        }
        $setParts[] = 'record_revision = record_revision + 1';
        $params[] = $recordId;
        $params[] = $farmId;
        $params[] = $expectedRevision;

        $sql =
            'UPDATE ' . $config['table'] .
            ' SET ' . implode(', ', $setParts) .
            ' WHERE id = ? AND farm_id = ? AND record_revision = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() !== 1) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record changed while saving. Reload and review it.'
            );
        }
        return $expectedRevision + 1;
    }
}

if (!function_exists('daily_record_revision_delete_locked')) {
    /**
     * Caller first invokes lock_and_compare() BEFORE any downstream feed or
     * mortality reversal, then calls this in the SAME transaction after those
     * original shared reversals. This second lock/compare is intentionally
     * harmless and prevents callers from omitting the final identity guard.
     *
     * No foreign-cascade or retained-audit policy is modified here.
     */
    function daily_record_revision_delete_locked(
        PDO $pdo,
        string $type,
        int $farmId,
        int $recordId,
        ?int $cycleId,
        string $recordDate,
        ?string $animalType,
        int $expectedRevision
    ): void {
        $config = daily_record_revision_definition($type);
        daily_record_revision_lock_and_compare(
            $pdo, $type, $farmId, $recordId, $cycleId,
            $recordDate, $animalType, $expectedRevision
        );
        $stmt = $pdo->prepare(
            'DELETE FROM ' . $config['table'] .
            ' WHERE id = ? AND farm_id = ? AND record_revision = ?'
        );
        $stmt->execute([$recordId, $farmId, $expectedRevision]);
        if ($stmt->rowCount() !== 1) {
            throw new DailyRecordRevisionConflict(
                'The Daily Record changed while deleting. Reload and review it.'
            );
        }
    }
}
