<?php
declare(strict_types=1);

/**
 * B12 future production-cycle seed writer. SOURCE ONLY / NOT ACTIVATED.
 *
 * The existing cycle service creates the canonical cycle and population
 * baseline. The Create Cycle route continues to own the surrounding
 * transaction, poultry onboarding, authorization, CSRF and error handling.
 *
 * This helper seeds exactly one newly created, still-uncommitted cycle and
 * explicitly initializes record_revision=1 in its first durable Daily Record.
 * It does not invent browser CREATE tokens, mutate an existing Daily Record,
 * begin/commit/roll back, or replace any population ledger movement.
 *
 * Only call behind daily_record_revision_cutover_enabled() after all seven
 * writers, three-column migration, API and browser cutover are coordinated.
 */
require_once __DIR__ . '/daily_record_revision_authority.php';

if (!function_exists('daily_record_revision_seed_cycle_entry')) {
    /**
     * @return array{record_id:int,record_revision:int,type:string}
     */
    function daily_record_revision_seed_cycle_entry(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $startDate,
        int $openingHeadcount,
        int $startAgeDays,
        ?int $actorId
    ): array {
        daily_record_revision_require_transaction($pdo);
        if ($farmId < 1 || $cycleId < 1 || $openingHeadcount < 0 ||
            $startAgeDays < 1 || ($actorId !== null && $actorId < 1)) {
            throw new InvalidArgumentException('Invalid cycle-opening Daily Record seed.');
        }
        // Preserve cycle identity ownership in its canonical shared service.
        // The cycle was just created in the SAME caller-owned transaction;
        // other transactions cannot legitimately address it before commit.
        if (!function_exists('production_cycle_get')) {
            throw new LogicException('Canonical cycle reader is unavailable.');
        }
        $cycle = production_cycle_get($pdo, $farmId, $cycleId, true);
        if (!is_array($cycle) ||
            (int)($cycle['id'] ?? 0) !== $cycleId ||
            (int)($cycle['farm_id'] ?? 0) !== $farmId ||
            (string)($cycle['status'] ?? '') !== 'active' ||
            (string)($cycle['start_date'] ?? '') !== $startDate ||
            (int)($cycle['opening_headcount'] ?? -1) !== $openingHeadcount) {
            throw new DailyRecordRevisionConflict('Cycle opening seed identity changed.');
        }
        $farmType = strtolower(trim((string)($cycle['farm_type'] ?? '')));
        $productionType = strtolower(trim((string)($cycle['production_type'] ?? '')));
        if ($farmType === 'poultry' &&
            in_array($productionType, ['layer', 'broiler'], true)) {
            $type = $productionType;
        } elseif ($farmType === 'ruminant') {
            // Built-in and custom livestock cycle choices resolve to the
            // same four persisted Daily Record species as the shared reader.
            // Never seed an uneditable or unsupported historical species.
            if (!in_array($productionType, ['cattle', 'goat', 'sheep', 'other'], true)) {
                throw new DailyRecordRevisionConflict(
                    'Unsupported Ruminant production cycle species for Daily Record seed.'
                );
            }
            $type = 'ruminant';
        } else {
            throw new DailyRecordRevisionConflict('Unsupported production cycle seed.');
        }
        $config = daily_record_revision_definition($type);
        $species = daily_record_revision_validate_scope(
            $farmId, $cycleId, $cycleId, $startDate,
            (bool)$config['animal_type'],
            $type === 'ruminant' ? $productionType : null
        );

        // The new cycle row is locked and invisible until commit. Reject a
        // repeated seed explicitly; neither nullable legacy UNIQUE keys nor
        // the browser absence-probe result is a duplicate-prevention policy.
        $probeSql = 'SELECT id FROM ' . $config['table']
            . ' WHERE farm_id = ? AND cycle_id = ? AND record_date = ?';
        $probeArgs = [$farmId, $cycleId, $startDate];
        if ($type === 'ruminant') {
            $probeSql .= ' AND LOWER(TRIM(animal_type)) = ?';
            $probeArgs[] = $species;
        }
        $probeSql .= ' LIMIT 1 FOR UPDATE';
        $probe = $pdo->prepare($probeSql);
        if ($probe === false || !$probe->execute($probeArgs)) {
            throw new RuntimeException('Daily Record seed preflight is unavailable.');
        }
        if ($probe->fetchColumn() !== false) {
            throw new DailyRecordRevisionConflict('The cycle-opening Daily Record already exists.');
        }

        // Preserve the active route's opening-row defaults exactly.
        $fields = [
            'opening_stock' => $openingHeadcount,
            'mortality' => 0,
            'water_consumption_liters' => 0,
            'remarks' => 'Auto-created from Production Cycle opening stock.',
        ];
        if ($type === 'layer' || $type === 'broiler') {
            $fields['feed_consumption_bags'] = 0;
            $fields['medications'] = null;
            $fields['birds_age'] = $startAgeDays;
            if ($type === 'layer') {
                $fields['egg_production'] = 0;
                $fields['crates_count'] = 0;
                $fields['laying_rate'] = 0;
            }
        } else {
            $fields['feed_consumption_kg'] = 0;
            $fields['other_details'] = null;
            $fields['tag_no'] = null;
            $fields['medications'] = null;
            $fields['reproduction_details'] = null;
        }
        $allowedFields = array_fill_keys($config['fields'], true);
        foreach (array_keys($fields) as $name) {
            if (!isset($allowedFields[$name])) {
                throw new LogicException('Seed is not aligned with the Daily Record field contract.');
            }
        }

        $columns = ['farm_id', 'cycle_id', 'record_date'];
        $values = [$farmId, $cycleId, $startDate];
        if ($type === 'ruminant') {
            $columns[] = 'animal_type';
            $values[] = $species;
        }
        foreach ($fields as $name => $value) {
            $columns[] = $name;
            $values[] = $value;
        }
        $columns[] = 'user_id';
        $values[] = $actorId;
        $columns[] = 'record_revision';
        $values[] = 1;
        $sql = 'INSERT INTO ' . $config['table']
            . ' (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $stmt = $pdo->prepare($sql);
        if ($stmt === false || !$stmt->execute($values) ||
            $stmt->rowCount() !== 1) {
            throw new DailyRecordRevisionConflict('Cycle-opening Daily Record seed failed.');
        }
        return [
            'record_id' => daily_record_revision_parse_record_id($pdo->lastInsertId()),
            'record_revision' => 1,
            'type' => $type,
        ];
    }
}
