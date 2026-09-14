<?php
/**
 * Renee Farms V3.0 — canonical production population service.
 *
 * One population authority for Poultry and Ruminant/Livestock cycles.
 *
 * Important contract:
 * - callers provide a positive physical quantity;
 * - this service owns movement direction/sign policy;
 * - all population-changing writers use the same baseline + movement ledger;
 * - no caller rewrites an historical movement;
 * - corrections use compensating reversals and later source versions;
 * - all writes are tenant/cycle scoped and transaction-safe.
 */

if (!class_exists('ProductionPopulationException')) {
    class ProductionPopulationException extends RuntimeException {}
}

if (!function_exists('production_population_valid_date')) {
    function production_population_valid_date(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable
            && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('production_population_text_length')) {
    function production_population_text_length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value)
            : strlen($value);
    }
}

if (!function_exists('production_population_movement_types')) {
    /**
     * Direction is canonical:
     * +1 adds physical population.
     * -1 removes physical population.
     *
     * "reversal" is deliberately absent. It is internal-only and may have
     * either sign depending on the movement being reversed.
     */
    function production_population_movement_types(): array
    {
        return [
            'acquisition'    => 1,
            'birth'          => 1,
            'transfer_in'    => 1,
            'mortality'      => -1,
            'sale'           => -1,
            'cull'           => -1,
            'slaughter'      => -1,
            'transfer_out'   => -1,
            'adjustment_in'  => 1,
            'adjustment_out' => -1,
        ];
    }
}

if (!function_exists('production_population_source_types')) {
    /**
     * Integration ownership is intentionally central.
     *
     * A durable source row is expected for every source except adjustment.
     * Manual adjustments are request-token owned until/unless a dedicated
     * adjustment entity is introduced later.
     */
    function production_population_source_types(): array
    {
        return [
            'daily_record',
            'sale',
            'ruminant_exit',
            'transfer',
            'poultry_acquisition',
            'adjustment',
        ];
    }
}

if (!function_exists('production_population_baseline_sources')) {
    function production_population_baseline_sources(): array
    {
        return [
            'cycle_opening',
            'legacy_cutover',
        ];
    }
}

if (!function_exists('production_population_normalize_note')) {
    function production_population_normalize_note(?string $notes): ?string
    {
        $notes = trim((string)$notes);

        if ($notes === '') {
            return null;
        }

        if (production_population_text_length($notes) > 255) {
            throw new InvalidArgumentException(
                'Population note must be 255 characters or fewer.'
            );
        }

        return $notes;
    }
}

if (!function_exists('production_population_normalize_request_token')) {
    function production_population_normalize_request_token(
        ?string $requestToken
    ): ?string {
        $requestToken = strtolower(trim((string)$requestToken));

        if ($requestToken === '') {
            return null;
        }

        if (!preg_match('/^[a-f0-9]{32,64}$/', $requestToken)) {
            throw new InvalidArgumentException(
                'Invalid population submission token. Refresh the page and try again.'
            );
        }

        return $requestToken;
    }
}

if (!function_exists('production_population_assert_user_id')) {
    function production_population_assert_user_id(?int $userId): void
    {
        if ($userId !== null && $userId <= 0) {
            throw new InvalidArgumentException('Invalid population record user.');
        }
    }
}

if (!function_exists('production_population_lock_cycle')) {
    /**
     * Canonical write lock order begins with production_cycles.
     */
    function production_population_lock_cycle(
        PDO $pdo,
        int $farmId,
        int $cycleId
    ): array {
        if ($farmId <= 0 || $cycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid production cycle.'
            );
        }

        $stmt = $pdo->prepare(
            "SELECT
                 id,
                 farm_id,
                 cycle_code,
                 farm_type,
                 production_type,
                 status,
                 start_date,
                 close_date,
                 opening_headcount
             FROM production_cycles
             WHERE id = ?
               AND farm_id = ?
               AND farm_type IN ('poultry', 'ruminant')
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->execute([$cycleId, $farmId]);
        $cycle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cycle) {
            throw new ProductionPopulationException(
                'The selected production cycle was not found in this farm.'
            );
        }

        return $cycle;
    }
}

if (!function_exists('production_population_assert_date_in_cycle')) {
    function production_population_assert_date_in_cycle(
        array $cycle,
        string $date,
        string $label = 'Population movement date'
    ): void {
        if (!production_population_valid_date($date)) {
            throw new InvalidArgumentException(
                "Enter a valid {$label}."
            );
        }

        $startDate = (string)($cycle['start_date'] ?? '');
        $closeDate = trim((string)($cycle['close_date'] ?? ''));

        if ($startDate !== '' && $date < $startDate) {
            throw new InvalidArgumentException(
                "{$label} cannot be earlier than the production cycle start date."
            );
        }

        if ($closeDate !== '' && $date > $closeDate) {
            throw new InvalidArgumentException(
                "{$label} cannot be later than the production cycle close date."
            );
        }
    }
}

if (!function_exists('production_population_lock_baseline')) {
    function production_population_lock_baseline(
        PDO $pdo,
        int $farmId,
        int $cycleId
    ): ?array {
        $stmt = $pdo->prepare(
            'SELECT
                 id,
                 farm_id,
                 cycle_id,
                 baseline_date,
                 baseline_quantity,
                 baseline_source,
                 notes,
                 created_by,
                 created_at
             FROM production_population_baselines
             WHERE farm_id = ?
               AND cycle_id = ?
             LIMIT 1
             FOR UPDATE'
        );

        $stmt->execute([$farmId, $cycleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('production_population_balance_locked')) {
    /**
     * Caller must already hold the baseline row lock.
     */
    function production_population_balance_locked(
        PDO $pdo,
        array $baseline,
        ?string $asOfDate = null
    ): int {
        $farmId = (int)$baseline['farm_id'];
        $cycleId = (int)$baseline['cycle_id'];
        $baselineDate = (string)$baseline['baseline_date'];

        if ($asOfDate !== null) {
            if (!production_population_valid_date($asOfDate)) {
                throw new InvalidArgumentException(
                    'Enter a valid population balance date.'
                );
            }

            if ($asOfDate < $baselineDate) {
                throw new ProductionPopulationException(
                    'Population balance cannot be calculated before the V3 baseline date.'
                );
            }

            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(quantity_delta), 0)
                 FROM production_population_movements
                 WHERE farm_id = ?
                   AND cycle_id = ?
                   AND movement_date <= ?'
            );

            $stmt->execute([$farmId, $cycleId, $asOfDate]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(quantity_delta), 0)
                 FROM production_population_movements
                 WHERE farm_id = ?
                   AND cycle_id = ?'
            );

            $stmt->execute([$farmId, $cycleId]);
        }

        $delta = (int)$stmt->fetchColumn();
        $quantity = (int)$baseline['baseline_quantity'] + $delta;

        if ($quantity < 0) {
            throw new ProductionPopulationException(
                'Population ledger integrity check failed: calculated population is negative.'
            );
        }

        return $quantity;
    }
}

if (!function_exists('production_population_project_delta_locked')) {
    /**
     * Prove a proposed delta is safe on its effective date and every later
     * already-recorded population date.
     *
     * Caller must already hold the cycle and baseline locks.
     */
    function production_population_project_delta_locked(
        PDO $pdo,
        array $baseline,
        string $effectiveDate,
        int $quantityDelta
    ): int {
        if (!production_population_valid_date($effectiveDate)) {
            throw new InvalidArgumentException(
                'Enter a valid population movement date.'
            );
        }

        if ($effectiveDate < (string)$baseline['baseline_date']) {
            throw new ProductionPopulationException(
                'Population change cannot be earlier than the V3 population baseline.'
            );
        }

        if ($quantityDelta === 0) {
            throw new InvalidArgumentException(
                'Population change cannot have a zero quantity.'
            );
        }

        $projectedQuantity =
            production_population_balance_locked(
                $pdo,
                $baseline,
                $effectiveDate
            ) + $quantityDelta;

        if ($projectedQuantity < 0) {
            throw new ProductionPopulationException(
                'This population change would reduce the cycle population below zero.'
            );
        }

        if ($quantityDelta > 0) {
            return $projectedQuantity;
        }

        $stmt = $pdo->prepare(
            'SELECT
                 movement_date,
                 COALESCE(SUM(quantity_delta), 0) AS daily_delta
             FROM production_population_movements
             WHERE farm_id = ?
               AND cycle_id = ?
               AND movement_date > ?
             GROUP BY movement_date
             ORDER BY movement_date ASC'
        );

        $stmt->execute([
            (int)$baseline['farm_id'],
            (int)$baseline['cycle_id'],
            $effectiveDate,
        ]);

        $runningQuantity = $projectedQuantity;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $runningQuantity += (int)$row['daily_delta'];

            if ($runningQuantity < 0) {
                throw new ProductionPopulationException(
                    'This population change would make a later recorded population balance negative.'
                );
            }
        }

        return $projectedQuantity;
    }
}

if (!function_exists('production_population_state')) {
    /**
     * Read-only population view.
     *
     * NULL means this legacy/new cycle has not yet entered the V3 population
     * contract. Existing V2.x workflows can therefore remain untouched until
     * an explicit baseline is established.
     */
    function production_population_state(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        ?string $asOfDate = null
    ): ?array {
        if ($farmId <= 0 || $cycleId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid production cycle.'
            );
        }

        if ($asOfDate !== null && !production_population_valid_date($asOfDate)) {
            throw new InvalidArgumentException(
                'Enter a valid population balance date.'
            );
        }

        $stmt = $pdo->prepare(
            "SELECT
                 b.id,
                 b.farm_id,
                 b.cycle_id,
                 b.baseline_date,
                 b.baseline_quantity,
                 b.baseline_source,
                 pc.cycle_code,
                 pc.farm_type,
                 pc.production_type,
                 pc.status,
                 pc.start_date,
                 pc.close_date
             FROM production_population_baselines b
             INNER JOIN production_cycles pc
               ON pc.id = b.cycle_id
              AND pc.farm_id = b.farm_id
             WHERE b.farm_id = ?
               AND b.cycle_id = ?
             LIMIT 1"
        );

        $stmt->execute([$farmId, $cycleId]);
        $baseline = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$baseline) {
            return null;
        }

        $baselineDate = (string)$baseline['baseline_date'];

        if ($asOfDate !== null && $asOfDate < $baselineDate) {
            throw new ProductionPopulationException(
                'Population balance cannot be calculated before the V3 baseline date.'
            );
        }

        if ($asOfDate !== null) {
            $sumStmt = $pdo->prepare(
                'SELECT COALESCE(SUM(quantity_delta), 0)
                 FROM production_population_movements
                 WHERE farm_id = ?
                   AND cycle_id = ?
                   AND movement_date <= ?'
            );
            $sumStmt->execute([$farmId, $cycleId, $asOfDate]);
        } else {
            $sumStmt = $pdo->prepare(
                'SELECT COALESCE(SUM(quantity_delta), 0)
                 FROM production_population_movements
                 WHERE farm_id = ?
                   AND cycle_id = ?'
            );
            $sumStmt->execute([$farmId, $cycleId]);
        }

        $movementDelta = (int)$sumStmt->fetchColumn();
        $quantity =
            (int)$baseline['baseline_quantity']
            + $movementDelta;

        if ($quantity < 0) {
            throw new ProductionPopulationException(
                'Population ledger integrity check failed: calculated population is negative.'
            );
        }

        return [
            'enabled' => true,
            'farm_id' => $farmId,
            'cycle_id' => $cycleId,
            'cycle_code' => (string)$baseline['cycle_code'],
            'farm_type' => (string)$baseline['farm_type'],
            'production_type' => (string)$baseline['production_type'],
            'cycle_status' => (string)$baseline['status'],
            'baseline_date' => $baselineDate,
            'baseline_quantity' => (int)$baseline['baseline_quantity'],
            'baseline_source' => (string)$baseline['baseline_source'],
            'movement_delta' => $movementDelta,
            'quantity' => $quantity,
            'as_of_date' => $asOfDate,
        ];
    }
}

if (!function_exists('production_population_establish_baseline')) {
    function production_population_establish_baseline(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $baselineDate,
        int $baselineQuantity,
        string $baselineSource,
        ?string $notes,
        ?int $userId
    ): int {
        $baselineSource = strtolower(trim($baselineSource));
        $notes = production_population_normalize_note($notes);
        production_population_assert_user_id($userId);

        if ($baselineQuantity < 0) {
            throw new InvalidArgumentException(
                'Population baseline cannot be negative.'
            );
        }

        if (!in_array(
            $baselineSource,
            production_population_baseline_sources(),
            true
        )) {
            throw new InvalidArgumentException(
                'Select a valid population baseline source.'
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

            if (!in_array(
                (string)$cycle['status'],
                ['planned', 'active'],
                true
            )) {
                throw new ProductionPopulationException(
                    'A V3 population baseline can only be established for a planned or active cycle.'
                );
            }

            production_population_assert_date_in_cycle(
                $cycle,
                $baselineDate,
                'Population baseline date'
            );

            if ($baselineSource === 'cycle_opening') {
                if ($baselineDate !== (string)$cycle['start_date']) {
                    throw new InvalidArgumentException(
                        'Cycle-opening population baseline must use the production cycle start date.'
                    );
                }

                if ($baselineQuantity !== (int)$cycle['opening_headcount']) {
                    throw new InvalidArgumentException(
                        'Cycle-opening population baseline must equal the production cycle opening headcount.'
                    );
                }
            }

            if (
                $baselineSource === 'legacy_cutover'
                && (string)$cycle['status'] !== 'active'
            ) {
                throw new ProductionPopulationException(
                    'Legacy population cutover can only be established for an active cycle.'
                );
            }

            $existing = production_population_lock_baseline(
                $pdo,
                $farmId,
                $cycleId
            );

            if ($existing) {
                $same =
                    (string)$existing['baseline_date'] === $baselineDate
                    && (int)$existing['baseline_quantity'] === $baselineQuantity
                    && (string)$existing['baseline_source'] === $baselineSource;

                if (!$same) {
                    throw new ProductionPopulationException(
                        'This cycle already has a different V3 population baseline. Use a population adjustment instead of rewriting the baseline.'
                    );
                }

                if ($startedTransaction) {
                    $pdo->commit();
                }

                return (int)$existing['id'];
            }

            $stmt = $pdo->prepare(
                'INSERT INTO production_population_baselines
                 (
                     farm_id,
                     cycle_id,
                     baseline_date,
                     baseline_quantity,
                     baseline_source,
                     notes,
                     created_by
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $farmId,
                $cycleId,
                $baselineDate,
                $baselineQuantity,
                $baselineSource,
                $notes,
                $userId,
            ]);

            $id = (int)$pdo->lastInsertId();

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'production_population_baseline_established',
                    'production_population_baseline',
                    $id,
                    [
                        'cycle_id' => $cycleId,
                        'cycle_code' => (string)$cycle['cycle_code'],
                        'farm_type' => (string)$cycle['farm_type'],
                        'production_type' => (string)$cycle['production_type'],
                        'baseline_date' => $baselineDate,
                        'baseline_quantity' => $baselineQuantity,
                        'baseline_source' => $baselineSource,
                    ]
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $id;
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}

if (!function_exists('production_population_record_movement')) {
    /**
     * Record one canonical physical population movement.
     *
     * IMPORTANT: $quantity is always positive. Direction comes only from
     * production_population_movement_types(), never from the caller.
     */
    function production_population_record_movement(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $movementType,
        string $movementDate,
        int $quantity,
        string $sourceType,
        ?int $sourceId,
        int $sourceVersion,
        ?string $requestToken,
        ?string $notes,
        ?int $userId
    ): int {
        $movementType = strtolower(trim($movementType));
        $sourceType = strtolower(trim($sourceType));
        $requestToken =
            production_population_normalize_request_token($requestToken);
        $notes = production_population_normalize_note($notes);
        production_population_assert_user_id($userId);

        $movementTypes = production_population_movement_types();

        if (!isset($movementTypes[$movementType])) {
            throw new InvalidArgumentException(
                'Select a valid population movement type.'
            );
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Population movement quantity must be greater than 0.'
            );
        }

        if (!in_array(
            $sourceType,
            production_population_source_types(),
            true
        )) {
            throw new InvalidArgumentException(
                'Select a valid population movement source.'
            );
        }

        if ($sourceVersion < 1) {
            throw new InvalidArgumentException(
                'Population source version must be at least 1.'
            );
        }

        if ($sourceId !== null && $sourceId <= 0) {
            throw new InvalidArgumentException(
                'Population source ID must be greater than 0.'
            );
        }

        if ($sourceType === 'adjustment') {
            if ($sourceId !== null) {
                throw new InvalidArgumentException(
                    'Manual population adjustments must be request-token owned.'
                );
            }

            if ($requestToken === null) {
                throw new InvalidArgumentException(
                    'Population adjustment requires a submission token.'
                );
            }

            if ($sourceVersion !== 1) {
                throw new InvalidArgumentException(
                    'Request-owned population adjustments use source version 1.'
                );
            }
        } elseif ($sourceId === null) {
            throw new InvalidArgumentException(
                'This population movement source requires a durable source record.'
            );
        }

        if (!production_population_valid_date($movementDate)) {
            throw new InvalidArgumentException(
                'Enter a valid population movement date.'
            );
        }

        $quantityDelta =
            $quantity * (int)$movementTypes[$movementType];

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

            if (!in_array(
                (string)$cycle['status'],
                ['active', 'closed'],
                true
            )) {
                throw new ProductionPopulationException(
                    'Population movements can only be recorded for an active cycle or as a dated correction to a closed cycle.'
                );
            }

            production_population_assert_date_in_cycle(
                $cycle,
                $movementDate
            );

            $baseline = production_population_lock_baseline(
                $pdo,
                $farmId,
                $cycleId
            );

            if (!$baseline) {
                throw new ProductionPopulationException(
                    'Confirm the current population for this cycle before recording population-changing events.'
                );
            }

            if ($movementDate < (string)$baseline['baseline_date']) {
                throw new ProductionPopulationException(
                    'Population movement cannot be earlier than the V3 population baseline.'
                );
            }

            if ($sourceId !== null) {
                $sourceStmt = $pdo->prepare(
                    'SELECT
                         id,
                         movement_date,
                         movement_type,
                         quantity_delta
                     FROM production_population_movements
                     WHERE farm_id = ?
                       AND cycle_id = ?
                       AND source_type = ?
                       AND source_id = ?
                       AND source_version = ?
                     LIMIT 1'
                );

                $sourceStmt->execute([
                    $farmId,
                    $cycleId,
                    $sourceType,
                    $sourceId,
                    $sourceVersion,
                ]);

                $existing = $sourceStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $same =
                        (string)$existing['movement_date'] === $movementDate
                        && (string)$existing['movement_type'] === $movementType
                        && (int)$existing['quantity_delta'] === $quantityDelta;

                    if (!$same) {
                        throw new ProductionPopulationException(
                            'This source/version already owns a different population movement. Reverse it and record a later source version instead.'
                        );
                    }

                    if ($startedTransaction) {
                        $pdo->commit();
                    }

                    return (int)$existing['id'];
                }
            }

            if ($requestToken !== null) {
                $requestStmt = $pdo->prepare(
                    'SELECT
                         id,
                         cycle_id,
                         movement_date,
                         movement_type,
                         quantity_delta,
                         source_type,
                         source_id,
                         source_version,
                         reversal_of_id
                     FROM production_population_movements
                     WHERE farm_id = ?
                       AND request_token = ?
                     LIMIT 1'
                );

                $requestStmt->execute([$farmId, $requestToken]);
                $existing = $requestStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $existingSourceId =
                        $existing['source_id'] !== null
                        ? (int)$existing['source_id']
                        : null;

                    $same =
                        (int)$existing['cycle_id'] === $cycleId
                        && (string)$existing['movement_date'] === $movementDate
                        && (string)$existing['movement_type'] === $movementType
                        && (int)$existing['quantity_delta'] === $quantityDelta
                        && (string)$existing['source_type'] === $sourceType
                        && $existingSourceId === $sourceId
                        && (int)$existing['source_version'] === $sourceVersion
                        && $existing['reversal_of_id'] === null;

                    if (!$same) {
                        throw new ProductionPopulationException(
                            'This population submission token has already been used for another movement.'
                        );
                    }

                    if ($startedTransaction) {
                        $pdo->commit();
                    }

                    return (int)$existing['id'];
                }
            }

            $projectedQuantity =
                production_population_project_delta_locked(
                    $pdo,
                    $baseline,
                    $movementDate,
                    $quantityDelta
                );

            $stmt = $pdo->prepare(
                'INSERT INTO production_population_movements
                 (
                     farm_id,
                     cycle_id,
                     movement_date,
                     movement_type,
                     quantity_delta,
                     source_type,
                     source_id,
                     source_version,
                     request_token,
                     reversal_of_id,
                     notes,
                     created_by
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)'
            );

            $stmt->execute([
                $farmId,
                $cycleId,
                $movementDate,
                $movementType,
                $quantityDelta,
                $sourceType,
                $sourceId,
                $sourceVersion,
                $requestToken,
                $notes,
                $userId,
            ]);

            $id = (int)$pdo->lastInsertId();

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'production_population_movement_recorded',
                    'production_population_movement',
                    $id,
                    [
                        'cycle_id' => $cycleId,
                        'cycle_code' => (string)$cycle['cycle_code'],
                        'movement_date' => $movementDate,
                        'movement_type' => $movementType,
                        'quantity' => $quantity,
                        'quantity_delta' => $quantityDelta,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                        'source_version' => $sourceVersion,
                        'projected_quantity' => $projectedQuantity,
                    ]
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $id;
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}

if (!function_exists('production_population_reverse_movement')) {
    /**
     * Create the one compensating reversal for an existing movement.
     *
     * Reversal rows are internal-only. They cannot themselves be reversed.
     */
    function production_population_reverse_movement(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        int $movementId,
        string $reason,
        ?int $userId
    ): int {
        $reason = trim($reason);
        production_population_assert_user_id($userId);

        if ($movementId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid population movement to reverse.'
            );
        }

        if (
            $reason === ''
            || production_population_text_length($reason) < 4
        ) {
            throw new InvalidArgumentException(
                'Enter a short reason for the population correction.'
            );
        }

        if (production_population_text_length($reason) > 255) {
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
                throw new ProductionPopulationException(
                    'This cycle does not have a V3 population baseline.'
                );
            }

            $originalStmt = $pdo->prepare(
                'SELECT
                     id,
                     movement_date,
                     movement_type,
                     quantity_delta,
                     source_type,
                     source_id,
                     source_version,
                     reversal_of_id
                 FROM production_population_movements
                 WHERE id = ?
                   AND farm_id = ?
                   AND cycle_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );

            $originalStmt->execute([
                $movementId,
                $farmId,
                $cycleId,
            ]);

            $original = $originalStmt->fetch(PDO::FETCH_ASSOC);

            if (!$original) {
                throw new ProductionPopulationException(
                    'The selected population movement was not found in this cycle.'
                );
            }

            if (
                (string)$original['movement_type'] === 'reversal'
                || $original['reversal_of_id'] !== null
            ) {
                throw new ProductionPopulationException(
                    'A compensating reversal cannot itself be reversed.'
                );
            }

            $reversalDate = (string)$original['movement_date'];

            production_population_assert_date_in_cycle(
                $cycle,
                $reversalDate,
                'Population reversal date'
            );

            if ($reversalDate < (string)$baseline['baseline_date']) {
                throw new ProductionPopulationException(
                    'Population reversal cannot be earlier than the V3 baseline.'
                );
            }

            $existingStmt = $pdo->prepare(
                'SELECT id
                 FROM production_population_movements
                 WHERE farm_id = ?
                   AND cycle_id = ?
                   AND reversal_of_id = ?
                 LIMIT 1'
            );

            $existingStmt->execute([
                $farmId,
                $cycleId,
                $movementId,
            ]);

            $existingId =
                (int)($existingStmt->fetchColumn() ?: 0);

            if ($existingId > 0) {
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return $existingId;
            }

            $quantityDelta =
                -1 * (int)$original['quantity_delta'];

            if ($quantityDelta === 0) {
                throw new ProductionPopulationException(
                    'Zero-value population movement cannot be reversed.'
                );
            }

            $projectedQuantity =
                production_population_project_delta_locked(
                    $pdo,
                    $baseline,
                    $reversalDate,
                    $quantityDelta
                );

            $stmt = $pdo->prepare(
                "INSERT INTO production_population_movements
                 (
                     farm_id,
                     cycle_id,
                     movement_date,
                     movement_type,
                     quantity_delta,
                     source_type,
                     source_id,
                     source_version,
                     request_token,
                     reversal_of_id,
                     notes,
                     created_by
                 )
                 VALUES (?, ?, ?, 'reversal', ?, 'reversal', ?, 1, NULL, ?, ?, ?)"
            );

            $stmt->execute([
                $farmId,
                $cycleId,
                $reversalDate,
                $quantityDelta,
                $movementId,
                $movementId,
                $reason,
                $userId,
            ]);

            $reversalId =
                (int)$pdo->lastInsertId();

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'production_population_movement_reversed',
                    'production_population_movement',
                    $reversalId,
                    [
                        'cycle_id' => $cycleId,
                        'cycle_code' => (string)$cycle['cycle_code'],
                        'original_movement_id' => $movementId,
                        'original_movement_type' =>
                            (string)$original['movement_type'],
                        'original_quantity_delta' =>
                            (int)$original['quantity_delta'],
                        'reversal_date' => $reversalDate,
                        'reversal_quantity_delta' =>
                            $quantityDelta,
                        'reason' => $reason,
                        'projected_quantity' =>
                            $projectedQuantity,
                    ]
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $reversalId;
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
