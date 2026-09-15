<?php
/**
 * Renee Farms V3.0 — canonical production cycle service.
 *
 * This service owns production-cycle application policy and writes. It does not
 * own Daily Record, Sales, transfer, lifecycle, acquisition, or financial SQL.
 *
 * V3 creation establishes the immutable cycle-opening population baseline.
 * Live Create Cycle delegates canonical cycle + opening-baseline ownership here.
 * Daily Record seeding and poultry onboarding remain caller-owned orchestration
 * inside the same transaction.
 */

require_once __DIR__ . '/livestock_types.php';
require_once __DIR__ . '/production_population.php';

if (!class_exists('ProductionCycleException')) {
    class ProductionCycleException extends RuntimeException {}
}

if (!function_exists('production_cycle_valid_date')) {
    function production_cycle_valid_date(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable
            && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('production_cycle_text_length')) {
    function production_cycle_text_length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value)
            : strlen($value);
    }
}

if (!function_exists('production_cycle_assert_farm_id')) {
    function production_cycle_assert_farm_id(int $farmId): void
    {
        if ($farmId <= 0) {
            throw new InvalidArgumentException('Select a valid farm.');
        }
    }
}

if (!function_exists('production_cycle_assert_user_id')) {
    function production_cycle_assert_user_id(?int $userId): void
    {
        if ($userId !== null && $userId <= 0) {
            throw new InvalidArgumentException(
                'Invalid production cycle user.'
            );
        }
    }
}

if (!function_exists('production_cycle_is_duplicate_exception')) {
    function production_cycle_is_duplicate_exception(
        Throwable $error
    ): bool {
        if (!$error instanceof PDOException) {
            return false;
        }

        $sqlState = (string)($error->errorInfo[0] ?? $error->getCode());
        $driverCode = (int)($error->errorInfo[1] ?? 0);

        return $sqlState === '23000' && $driverCode === 1062;
    }
}

if (!function_exists('production_cycle_normalize_code')) {
    function production_cycle_normalize_code(string $cycleCode): string
    {
        $cycleCode = trim($cycleCode);

        if ($cycleCode === '') {
            throw new InvalidArgumentException(
                'Enter a production cycle code.'
            );
        }

        if (production_cycle_text_length($cycleCode) > 100) {
            throw new InvalidArgumentException(
                'Cycle code must be 100 characters or fewer.'
            );
        }

        return $cycleCode;
    }
}

if (!function_exists('production_cycle_normalize_notes')) {
    function production_cycle_normalize_notes(?string $notes): ?string
    {
        $notes = trim((string)$notes);

        return $notes === '' ? null : $notes;
    }
}

if (!function_exists('production_cycle_nonnegative_int')) {
    function production_cycle_nonnegative_int(
        $value,
        string $label
    ): int {
        if (is_int($value)) {
            $parsed = $value;
        } elseif (
            is_string($value)
            && preg_match('/^[0-9]+$/', trim($value)) === 1
        ) {
            $parsed = (int)trim($value);
        } else {
            throw new InvalidArgumentException(
                "{$label} must be a whole number of 0 or greater."
            );
        }

        if ($parsed < 0) {
            throw new InvalidArgumentException(
                "{$label} must be a whole number of 0 or greater."
            );
        }

        return $parsed;
    }
}

if (!function_exists('production_cycle_optional_money')) {
    function production_cycle_optional_money(
        $value,
        string $label
    ): ?float {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "Enter a valid {$label}."
            );
        }

        $parsed = (float)$value;

        if (!is_finite($parsed) || $parsed < 0) {
            throw new InvalidArgumentException(
                "{$label} cannot be negative."
            );
        }

        return $parsed;
    }
}

if (!function_exists('production_cycle_type_choices')) {
    /**
     * Canonical one-field cycle identity choices for future thin UIs.
     */
    function production_cycle_type_choices(
        PDO $pdo,
        int $farmId,
        bool $includeGenericOther = true,
        bool $includeInactiveCustom = false
    ): array {
        production_cycle_assert_farm_id($farmId);

        $choices = [
            [
                'value' => 'poultry:layer',
                'farm_type' => 'poultry',
                'production_type' => 'layer',
                'livestock_type_id' => null,
                'label' => 'Layers',
                'is_custom' => false,
                'is_active' => true,
            ],
            [
                'value' => 'poultry:broiler',
                'farm_type' => 'poultry',
                'production_type' => 'broiler',
                'livestock_type_id' => null,
                'label' => 'Broilers',
                'is_custom' => false,
                'is_active' => true,
            ],
        ];

        foreach (
            livestock_type_choices(
                $pdo,
                $farmId,
                $includeGenericOther,
                $includeInactiveCustom
            ) as $choice
        ) {
            $choices[] = [
                'value' => 'ruminant:' . (string)$choice['value'],
                'farm_type' => 'ruminant',
                'production_type' => (string)$choice['legacy_type'],
                'livestock_type_id' => $choice['livestock_type_id'],
                'label' => (string)$choice['label'],
                'is_custom' => (bool)$choice['is_custom'],
                'is_active' => (bool)$choice['is_active'],
            ];
        }

        return $choices;
    }
}

if (!function_exists('production_cycle_parse_type_choice')) {
    /**
     * Parse a stable future UI choice without touching the database.
     */
    function production_cycle_parse_type_choice(string $value): array
    {
        $value = strtolower(trim($value));

        if ($value === 'poultry:layer') {
            return [
                'farm_type' => 'poultry',
                'production_type' => 'layer',
                'livestock_type_id' => null,
            ];
        }

        if ($value === 'poultry:broiler') {
            return [
                'farm_type' => 'poultry',
                'production_type' => 'broiler',
                'livestock_type_id' => null,
            ];
        }

        if (str_starts_with($value, 'ruminant:')) {
            $livestockChoice = substr($value, strlen('ruminant:'));
            $identity = livestock_type_parse_choice($livestockChoice);

            return [
                'farm_type' => 'ruminant',
                'production_type' => (string)$identity['legacy_type'],
                'livestock_type_id' => $identity['livestock_type_id'],
            ];
        }

        throw new InvalidArgumentException(
            'Select a valid production type.'
        );
    }
}

if (!function_exists('production_cycle_resolve_identity')) {
    /**
     * Resolve the canonical storage identity for an incremental integration.
     *
     * Poultry choice: layer|broiler
     * Ruminant choice: cattle|goat|sheep|other|custom:<id>
     */
    function production_cycle_resolve_identity(
        PDO $pdo,
        int $farmId,
        string $farmType,
        string $productionChoice
    ): array {
        production_cycle_assert_farm_id($farmId);

        $farmType = strtolower(trim($farmType));
        $productionChoice = strtolower(trim($productionChoice));

        if ($farmType === 'poultry') {
            if (!in_array(
                $productionChoice,
                ['layer', 'broiler'],
                true
            )) {
                throw new InvalidArgumentException(
                    'Select Layer or Broiler for a poultry cycle.'
                );
            }

            return [
                'farm_type' => 'poultry',
                'production_type' => $productionChoice,
                'livestock_type_id' => null,
            ];
        }

        if ($farmType !== 'ruminant') {
            throw new InvalidArgumentException(
                'Farm type must be poultry or ruminant.'
            );
        }

        $identity = livestock_type_parse_choice($productionChoice);

        livestock_type_validate_link(
            $pdo,
            $farmId,
            (string)$identity['legacy_type'],
            $identity['livestock_type_id'],
            true
        );

        return [
            'farm_type' => 'ruminant',
            'production_type' => (string)$identity['legacy_type'],
            'livestock_type_id' => $identity['livestock_type_id'],
        ];
    }
}

if (!function_exists('production_cycle_get')) {
    function production_cycle_get(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        bool $forUpdate = false
    ): array {
        production_cycle_assert_farm_id($farmId);

        if ($cycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid production cycle.'
            );
        }

        $sql =
            'SELECT
                 id,
                 farm_id,
                 cycle_code,
                 farm_type,
                 production_type,
                 livestock_type_id,
                 status,
                 start_date,
                 expected_end_date,
                 close_date,
                 opening_headcount,
                 closing_headcount,
                 bird_unit_cost,
                 notes,
                 created_by,
                 created_at
             FROM production_cycles
             WHERE id = ?
               AND farm_id = ?
               AND farm_type IN (\'poultry\', \'ruminant\')
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cycleId, $farmId]);
        $cycle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cycle) {
            throw new ProductionCycleException(
                'The selected production cycle was not found in this farm.'
            );
        }

        $cycle['id'] = (int)$cycle['id'];
        $cycle['farm_id'] = (int)$cycle['farm_id'];
        $cycle['livestock_type_id'] =
            $cycle['livestock_type_id'] === null
                ? null
                : (int)$cycle['livestock_type_id'];
        $cycle['opening_headcount'] =
            (int)$cycle['opening_headcount'];
        $cycle['closing_headcount'] =
            $cycle['closing_headcount'] === null
                ? null
                : (int)$cycle['closing_headcount'];

        return $cycle;
    }
}

if (!function_exists('production_cycle_display_type')) {
    function production_cycle_display_type(
        PDO $pdo,
        int $farmId,
        array $cycle
    ): string {
        $farmType = strtolower((string)($cycle['farm_type'] ?? ''));
        $productionType =
            strtolower((string)($cycle['production_type'] ?? ''));
        $livestockTypeId =
            isset($cycle['livestock_type_id'])
            && $cycle['livestock_type_id'] !== null
                ? (int)$cycle['livestock_type_id']
                : null;

        if ($farmType === 'poultry') {
            if ($productionType === 'layer') {
                return 'Layers';
            }

            if ($productionType === 'broiler') {
                return 'Broilers';
            }

            throw new ProductionCycleException(
                'This poultry cycle has an unsupported production type.'
            );
        }

        if ($farmType !== 'ruminant') {
            throw new ProductionCycleException(
                'This production cycle has an unsupported farm type.'
            );
        }

        return livestock_type_display_name(
            $pdo,
            $farmId,
            $productionType,
            $livestockTypeId
        );
    }
}

if (!function_exists('production_cycle_create_v3')) {
    /**
     * Create a canonical V3 cycle and its cycle-opening population baseline.
     *
     * This deliberately does not seed any Daily Record table. The live Create
     * Cycle route delegates canonical cycle + opening-baseline ownership here,
     * then keeps Daily Record seeding and poultry onboarding inside its
     * caller-owned transaction.
     */
    function production_cycle_create_v3(
        PDO $pdo,
        int $farmId,
        array $input,
        ?int $userId
    ): int {
        production_cycle_assert_farm_id($farmId);
        production_cycle_assert_user_id($userId);

        $cycleCode = production_cycle_normalize_code(
            (string)($input['cycle_code'] ?? '')
        );

        $farmType = strtolower(
            trim((string)($input['farm_type'] ?? ''))
        );

        $productionChoice = strtolower(
            trim(
                (string)(
                    $input['production_choice']
                    ?? $input['production_type']
                    ?? ''
                )
            )
        );

        $identity = production_cycle_resolve_identity(
            $pdo,
            $farmId,
            $farmType,
            $productionChoice
        );

        $startDate = trim((string)($input['start_date'] ?? ''));

        if (!production_cycle_valid_date($startDate)) {
            throw new InvalidArgumentException(
                'Enter a valid cycle start date.'
            );
        }

        $expectedEndDate =
            trim((string)($input['expected_end_date'] ?? ''));

        if (
            $expectedEndDate !== ''
            && !production_cycle_valid_date($expectedEndDate)
        ) {
            throw new InvalidArgumentException(
                'Enter a valid expected end date.'
            );
        }

        if (
            $expectedEndDate !== ''
            && $expectedEndDate < $startDate
        ) {
            throw new InvalidArgumentException(
                'Expected end date cannot be earlier than the cycle start date.'
            );
        }

        $openingHeadcount = production_cycle_nonnegative_int(
            $input['opening_headcount'] ?? 0,
            'Opening headcount'
        );

        $birdUnitCost = production_cycle_optional_money(
            $input['bird_unit_cost'] ?? null,
            'bird cost basis'
        );

        if (
            $identity['farm_type'] !== 'poultry'
            && $birdUnitCost !== null
        ) {
            throw new InvalidArgumentException(
                'Bird cost basis applies only to poultry cycles.'
            );
        }

        $notes = production_cycle_normalize_notes(
            isset($input['notes'])
                ? (string)$input['notes']
                : null
        );

        $duplicateStmt = $pdo->prepare(
            'SELECT id
             FROM production_cycles
             WHERE farm_id = ?
               AND cycle_code = ?
             LIMIT 1'
        );

        $duplicateStmt->execute([$farmId, $cycleCode]);

        if ($duplicateStmt->fetchColumn()) {
            throw new ProductionCycleException(
                'This cycle code is already being used in this farm.'
            );
        }

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO production_cycles
                 (
                     farm_id,
                     cycle_code,
                     farm_type,
                     production_type,
                     livestock_type_id,
                     status,
                     start_date,
                     expected_end_date,
                     opening_headcount,
                     bird_unit_cost,
                     notes,
                     created_by
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $farmId,
                $cycleCode,
                $identity['farm_type'],
                $identity['production_type'],
                $identity['livestock_type_id'],
                'active',
                $startDate,
                $expectedEndDate !== ''
                    ? $expectedEndDate
                    : null,
                $openingHeadcount,
                $identity['farm_type'] === 'poultry'
                    ? $birdUnitCost
                    : null,
                $notes,
                $userId,
            ]);

            $cycleId = (int)$pdo->lastInsertId();

            production_population_establish_baseline(
                $pdo,
                $farmId,
                $cycleId,
                $startDate,
                $openingHeadcount,
                'cycle_opening',
                'Canonical opening population created with the production cycle.',
                $userId
            );

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'production_cycle_created_v3',
                    'production_cycle',
                    $cycleId,
                    [
                        'cycle_code' => $cycleCode,
                        'farm_type' => $identity['farm_type'],
                        'production_type' =>
                            $identity['production_type'],
                        'livestock_type_id' =>
                            $identity['livestock_type_id'],
                        'start_date' => $startDate,
                        'opening_headcount' => $openingHeadcount,
                        'population_contract' => 'cycle_opening',
                    ]
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $cycleId;
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (production_cycle_is_duplicate_exception($error)) {
                throw new ProductionCycleException(
                    'This cycle code is already being used in this farm.'
                );
            }

            throw $error;
        }
    }
}

if (!function_exists('production_cycle_cutover_population_v3')) {
    /**
     * Explicitly place an existing active legacy cycle under canonical V3
     * population tracking.
     *
     * The quantity is user-confirmed physical truth. Never infer it from Daily
     * Records, Animal Registry, Sales, opening-stock history, or other legacy
     * records.
     *
     * The canonical population service owns cycle eligibility, baseline
     * uniqueness/idempotency, locking, baseline persistence, and audit policy.
     */
    function production_cycle_cutover_population_v3(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $baselineDate,
        $baselineQuantity,
        ?string $notes,
        ?int $userId
    ): int {
        production_cycle_assert_farm_id($farmId);
        production_cycle_assert_user_id($userId);

        $baselineDate = trim($baselineDate);

        if (!production_cycle_valid_date($baselineDate)) {
            throw new InvalidArgumentException(
                'Enter a valid population cutover date.'
            );
        }

        $baselineQuantity = production_cycle_nonnegative_int(
            $baselineQuantity,
            'Current live population'
        );

        return production_population_establish_baseline(
            $pdo,
            $farmId,
            $cycleId,
            $baselineDate,
            $baselineQuantity,
            'legacy_cutover',
            $notes,
            $userId
        );
    }
}

if (!function_exists('production_cycle_update_bird_cost_basis')) {
    function production_cycle_update_bird_cost_basis(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        $birdUnitCost,
        ?int $userId
    ): void {
        production_cycle_assert_user_id($userId);
        $value = production_cycle_optional_money(
            $birdUnitCost,
            'bird cost basis'
        );

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $cycle = production_cycle_get(
                $pdo,
                $farmId,
                $cycleId,
                true
            );

            if ((string)$cycle['farm_type'] !== 'poultry') {
                throw new ProductionCycleException(
                    'Bird cost basis can be changed only for a poultry cycle.'
                );
            }

            $stmt = $pdo->prepare(
                'UPDATE production_cycles
                 SET bird_unit_cost = ?
                 WHERE id = ?
                   AND farm_id = ?
                   AND farm_type = \'poultry\''
            );

            $stmt->execute([
                $value,
                $cycleId,
                $farmId,
            ]);

            if ($stmt->rowCount() > 1) {
                throw new ProductionCycleException(
                    'Production cycle cost basis update was not safely scoped.'
                );
            }

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'production_cycle_bird_cost_basis_updated',
                    'production_cycle',
                    $cycleId,
                    [
                        'cycle_code' => (string)$cycle['cycle_code'],
                        'previous_bird_unit_cost' =>
                            $cycle['bird_unit_cost'],
                        'bird_unit_cost' => $value,
                    ]
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}

if (!function_exists('production_cycle_close_v3')) {
    /**
     * Close a baseline-backed V3 cycle using canonical population as of close.
     *
     * Legacy cycles with no baseline are deliberately rejected here. The
     * current route may retain its legacy fallback until explicit cutover.
     */
    function production_cycle_close_v3(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $closeDate,
        ?int $userId
    ): int {
        production_cycle_assert_farm_id($farmId);
        production_cycle_assert_user_id($userId);

        $closeDate = trim($closeDate);

        if (!production_cycle_valid_date($closeDate)) {
            throw new InvalidArgumentException(
                'Enter a valid cycle close date.'
            );
        }

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $cycle = production_cycle_get(
                $pdo,
                $farmId,
                $cycleId,
                true
            );

            if ((string)$cycle['status'] !== 'active') {
                throw new ProductionCycleException(
                    'Only an active production cycle can be closed.'
                );
            }

            if ($closeDate < (string)$cycle['start_date']) {
                throw new InvalidArgumentException(
                    'Cycle close date cannot be earlier than its start date.'
                );
            }

            $futureMovementStmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM production_population_movements
                 WHERE farm_id = ?
                   AND cycle_id = ?
                   AND movement_date > ?'
            );

            $futureMovementStmt->execute([
                $farmId,
                $cycleId,
                $closeDate,
            ]);

            if ((int)$futureMovementStmt->fetchColumn() > 0) {
                throw new ProductionCycleException(
                    'This cycle has population movements after the selected close date. Choose a later close date or correct those movements first.'
                );
            }

            $population = production_population_state(
                $pdo,
                $farmId,
                $cycleId,
                $closeDate
            );

            if ($population === null) {
                throw new ProductionCycleException(
                    'This cycle has not entered the V3 population contract. Complete its population cutover before using canonical cycle closure.'
                );
            }

            $closingHeadcount = (int)$population['quantity'];

            $stmt = $pdo->prepare(
                'UPDATE production_cycles
                 SET status = ?,
                     close_date = ?,
                     closing_headcount = ?
                 WHERE id = ?
                   AND farm_id = ?
                   AND status = ?'
            );

            $stmt->execute([
                'closed',
                $closeDate,
                $closingHeadcount,
                $cycleId,
                $farmId,
                'active',
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new ProductionCycleException(
                    'The production cycle changed while you were working. Refresh and try again.'
                );
            }

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'production_cycle_closed_v3',
                    'production_cycle',
                    $cycleId,
                    [
                        'cycle_code' => (string)$cycle['cycle_code'],
                        'close_date' => $closeDate,
                        'closing_headcount' => $closingHeadcount,
                        'population_source' => 'v3_population_ledger',
                    ]
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $closingHeadcount;
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}
