<?php
/**
 * Shared Daily Record population-continuity correction service.
 *
 * Business contract:
 * - Daily Record mortality remains the durable population-loss fact.
 * - Canonical mortality projection remains owned by daily_population_sync.php.
 * - This service owns only downstream Daily Record opening-stock continuity.
 * - It never changes later mortality or unrelated operational facts.
 * - Layer laying_rate is recalculated because it is derived from opening stock.
 * - Ruminant continuity is isolated by animal_type.
 */

if (!class_exists('DailyPopulationContinuityException')) {
    class DailyPopulationContinuityException extends RuntimeException {}
}

if (!function_exists('daily_population_continuity_types')) {
    function daily_population_continuity_types(): array
    {
        return [
            'layer' => [
                'table' => 'layer_daily_records',
                'source_type' => 'daily_layer_record',
                'population_label' => 'flock',
                'animal_type_required' => false,
            ],
            'broiler' => [
                'table' => 'broiler_daily_records',
                'source_type' => 'daily_broiler_record',
                'population_label' => 'flock',
                'animal_type_required' => false,
            ],
            'ruminant' => [
                'table' => 'ruminant_daily_records',
                'source_type' => 'daily_ruminant_record',
                'population_label' => 'herd',
                'animal_type_required' => true,
            ],
        ];
    }
}

if (!function_exists('daily_population_continuity_type')) {
    function daily_population_continuity_type(string $recordType): array
    {
        $recordType = strtolower(trim($recordType));
        $types = daily_population_continuity_types();

        if (!isset($types[$recordType])) {
            throw new InvalidArgumentException(
                'Select a valid Daily Record population type.'
            );
        }

        return [
            'key' => $recordType,
            'table' => $types[$recordType]['table'],
            'source_type' => $types[$recordType]['source_type'],
            'population_label' => $types[$recordType]['population_label'],
            'animal_type_required' =>
                (bool)$types[$recordType]['animal_type_required'],
        ];
    }
}

if (!function_exists('daily_population_continuity_valid_date')) {
    function daily_population_continuity_valid_date(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable
            && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('daily_population_continuity_animal_type')) {
    function daily_population_continuity_animal_type(
        array $config,
        ?string $animalType
    ): ?string {
        if (!$config['animal_type_required']) {
            return null;
        }

        $animalType = strtolower(trim((string)$animalType));
        $allowed = ['cattle', 'goat', 'sheep', 'other'];

        if (!in_array($animalType, $allowed, true)) {
            throw new InvalidArgumentException(
                'Select a valid ruminant animal type.'
            );
        }

        return $animalType;
    }
}

if (!function_exists('daily_population_continuity_lock_suffix')) {
    function daily_population_continuity_lock_suffix(
        PDO $pdo,
        bool $forUpdate
    ): string {
        if (!$forUpdate) {
            return '';
        }

        /*
         * Production is MySQL/MariaDB. Keeping the driver check makes the
         * read-only planner testable with another PDO driver without weakening
         * production locking.
         */
        $driver = strtolower(
            (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)
        );

        return in_array($driver, ['mysql'], true)
            ? ' FOR UPDATE'
            : '';
    }
}

if (!function_exists('daily_population_continuity_validate_target')) {
    function daily_population_continuity_validate_target(
        int $farmId,
        int $cycleId,
        string $recordDate,
        int $openingStock,
        int $mortality
    ): void {
        if ($farmId <= 0 || $cycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid farm and production cycle.'
            );
        }

        if (!daily_population_continuity_valid_date($recordDate)) {
            throw new InvalidArgumentException(
                'Enter a valid Daily Record date.'
            );
        }

        if ($openingStock < 1) {
            throw new InvalidArgumentException(
                'Opening stock must be greater than zero.'
            );
        }

        if ($mortality < 0 || $mortality > $openingStock) {
            throw new InvalidArgumentException(
                'Mortality must be between zero and opening stock.'
            );
        }
    }
}

if (!function_exists('daily_population_continuity_later_rows')) {
    function daily_population_continuity_later_rows(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $recordType,
        string $recordDate,
        ?string $animalType = null,
        bool $forUpdate = false
    ): array {
        $config = daily_population_continuity_type($recordType);
        $animalType = daily_population_continuity_animal_type(
            $config,
            $animalType
        );

        $select =
            'id, record_date, opening_stock, mortality';

        if ($config['key'] === 'layer') {
            $select .= ', egg_production, laying_rate';
        }

        if ($config['key'] === 'ruminant') {
            $select .= ', animal_type';
        }

        /*
         * Table name is never caller supplied. It comes only from the fixed
         * configuration map above.
         */
        $sql =
            'SELECT ' . $select
            . ' FROM ' . $config['table']
            . ' WHERE farm_id = ?'
            . ' AND cycle_id = ?'
            . ' AND record_date > ?';

        $params = [
            $farmId,
            $cycleId,
            $recordDate,
        ];

        if ($config['key'] === 'ruminant') {
            $sql .= ' AND LOWER(animal_type) = ?';
            $params[] = $animalType;
        }

        $sql .= ' ORDER BY record_date ASC, id ASC';
        $sql .= daily_population_continuity_lock_suffix(
            $pdo,
            $forUpdate
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('daily_population_continuity_preview')) {
    /**
     * Build the deterministic downstream continuity plan.
     *
     * This function is read-only.
     */
    function daily_population_continuity_preview(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $recordType,
        string $recordDate,
        int $openingStock,
        int $mortality,
        ?string $animalType = null,
        bool $forUpdate = false
    ): array {
        $config = daily_population_continuity_type($recordType);

        daily_population_continuity_validate_target(
            $farmId,
            $cycleId,
            $recordDate,
            $openingStock,
            $mortality
        );

        $animalType = daily_population_continuity_animal_type(
            $config,
            $animalType
        );

        $rows = daily_population_continuity_later_rows(
            $pdo,
            $farmId,
            $cycleId,
            $config['key'],
            $recordDate,
            $animalType,
            $forUpdate
        );

        $expectedOpening = $openingStock - $mortality;
        $changes = [];

        foreach ($rows as $row) {
            $rowDate = (string)$row['record_date'];
            $oldOpening = (int)$row['opening_stock'];
            $rowMortality = (int)$row['mortality'];

            if ($expectedOpening < 1) {
                throw new DailyPopulationContinuityException(
                    'The corrected population would leave no opening '
                    . $config['population_label']
                    . ' for the later Daily Record on '
                    . $rowDate
                    . '.'
                );
            }

            if ($rowMortality > $expectedOpening) {
                throw new DailyPopulationContinuityException(
                    'The existing mortality on '
                    . $rowDate
                    . ' exceeds the corrected opening '
                    . $config['population_label']
                    . '. Correct that later Daily Record before applying '
                    . 'this historical population correction.'
                );
            }

            $newLayingRate = null;
            $oldLayingRate = null;
            $eggProduction = null;

            if ($config['key'] === 'layer') {
                $eggProduction = (int)$row['egg_production'];

                if ($eggProduction > $expectedOpening) {
                    throw new DailyPopulationContinuityException(
                        'The existing egg production on '
                        . $rowDate
                        . ' exceeds the corrected opening flock. '
                        . 'Correct that later Daily Record before applying '
                        . 'this historical population correction.'
                    );
                }

                $oldLayingRate = (float)$row['laying_rate'];
                $newLayingRate = round(
                    ($eggProduction / $expectedOpening) * 100,
                    2
                );
            }

            $oldClosing = max(
                0,
                $oldOpening - $rowMortality
            );

            $newClosing = max(
                0,
                $expectedOpening - $rowMortality
            );

            if ($oldOpening !== $expectedOpening) {
                $changes[] = [
                    'id' => (int)$row['id'],
                    'record_date' => $rowDate,
                    'old_opening_stock' => $oldOpening,
                    'new_opening_stock' => $expectedOpening,
                    'mortality' => $rowMortality,
                    'old_closing_stock' => $oldClosing,
                    'new_closing_stock' => $newClosing,
                    'egg_production' => $eggProduction,
                    'old_laying_rate' => $oldLayingRate,
                    'new_laying_rate' => $newLayingRate,
                ];
            }

            /*
             * Every later row keeps its own mortality fact. Only its opening
             * follows the corrected closing from the preceding Daily Record.
             */
            $expectedOpening = $newClosing;
        }

        return [
            'record_type' => $config['key'],
            'source_type' => $config['source_type'],
            'farm_id' => $farmId,
            'cycle_id' => $cycleId,
            'record_date' => $recordDate,
            'animal_type' => $animalType,
            'target_opening_stock' => $openingStock,
            'target_mortality' => $mortality,
            'target_closing_stock' =>
                $openingStock - $mortality,
            'affected_count' => count($changes),
            'changes' => $changes,
            'projected_final_closing_stock' =>
                $expectedOpening,
        ];
    }
}

if (!function_exists('daily_population_continuity_apply')) {
    /**
     * Apply only the downstream opening-stock part of a correction.
     *
     * Caller owns the target Daily Record update and the canonical mortality
     * projection. Caller MUST already have an active transaction so all parts
     * commit or roll back together.
     */
    function daily_population_continuity_apply(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $recordType,
        string $recordDate,
        int $openingStock,
        int $mortality,
        ?string $animalType = null
    ): array {
        if (!$pdo->inTransaction()) {
            throw new DailyPopulationContinuityException(
                'Daily population continuity must be applied inside '
                . 'an active transaction.'
            );
        }

        /*
         * Rebuild the plan under row locks. A browser preview is advisory;
         * this locked plan is authoritative at apply time.
         */
        $plan = daily_population_continuity_preview(
            $pdo,
            $farmId,
            $cycleId,
            $recordType,
            $recordDate,
            $openingStock,
            $mortality,
            $animalType,
            true
        );

        $config = daily_population_continuity_type($recordType);
        $animalType = daily_population_continuity_animal_type(
            $config,
            $animalType
        );

        $applied = 0;

        foreach ($plan['changes'] as $change) {
            if ($config['key'] === 'layer') {
                $stmt = $pdo->prepare(
                    'UPDATE layer_daily_records'
                    . ' SET opening_stock = ?, laying_rate = ?'
                    . ' WHERE id = ?'
                    . ' AND farm_id = ?'
                    . ' AND cycle_id = ?'
                    . ' AND opening_stock = ?'
                );

                $stmt->execute([
                    (int)$change['new_opening_stock'],
                    (float)$change['new_laying_rate'],
                    (int)$change['id'],
                    $farmId,
                    $cycleId,
                    (int)$change['old_opening_stock'],
                ]);
            } elseif ($config['key'] === 'broiler') {
                $stmt = $pdo->prepare(
                    'UPDATE broiler_daily_records'
                    . ' SET opening_stock = ?'
                    . ' WHERE id = ?'
                    . ' AND farm_id = ?'
                    . ' AND cycle_id = ?'
                    . ' AND opening_stock = ?'
                );

                $stmt->execute([
                    (int)$change['new_opening_stock'],
                    (int)$change['id'],
                    $farmId,
                    $cycleId,
                    (int)$change['old_opening_stock'],
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE ruminant_daily_records'
                    . ' SET opening_stock = ?'
                    . ' WHERE id = ?'
                    . ' AND farm_id = ?'
                    . ' AND cycle_id = ?'
                    . ' AND LOWER(animal_type) = ?'
                    . ' AND opening_stock = ?'
                );

                $stmt->execute([
                    (int)$change['new_opening_stock'],
                    (int)$change['id'],
                    $farmId,
                    $cycleId,
                    $animalType,
                    (int)$change['old_opening_stock'],
                ]);
            }

            if ($stmt->rowCount() !== 1) {
                throw new DailyPopulationContinuityException(
                    'A later Daily Record changed while population '
                    . 'continuity was being applied. Retry the correction.'
                );
            }

            $applied++;
        }

        $plan['applied_count'] = $applied;

        return $plan;
    }
}
