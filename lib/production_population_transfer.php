<?php
/**
 * Renee Farms V3.0 — paired production population transfer service.
 *
 * One transfer is one durable parent fact with two durable source legs.
 *
 * OUT leg:
 *   source cycle -> transfer_out -> negative population delta
 *
 * IN leg:
 *   destination cycle -> transfer_in -> positive population delta
 *
 * The two legs have distinct durable IDs because canonical population source
 * identity allows only one active movement per durable source fact.
 *
 * Scope of this service:
 * - same farm only;
 * - distinct production cycles;
 * - same production identity;
 * - both cycles already under V3 population tracking;
 * - one atomic OUT + IN operation;
 * - corrections reverse both legs rather than rewriting population history.
 */

require_once __DIR__ . '/production_cycle_service.php';

if (!class_exists('ProductionPopulationTransferException')) {
    class ProductionPopulationTransferException extends RuntimeException {}
}

if (!function_exists('production_population_transfer_request_token')) {
    function production_population_transfer_request_token(
        string $requestToken
    ): string {
        $token = production_population_normalize_request_token(
            $requestToken
        );

        if ($token === null) {
            throw new InvalidArgumentException(
                'Production transfer requires a submission token.'
            );
        }

        return $token;
    }
}

if (!function_exists('production_population_transfer_duplicate_exception')) {
    function production_population_transfer_duplicate_exception(
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

if (!function_exists('production_population_transfer_load_by_token')) {
    function production_population_transfer_load_by_token(
        PDO $pdo,
        int $farmId,
        string $requestToken,
        bool $forUpdate = false
    ): ?array {
        $sql =
            'SELECT
                 id,
                 farm_id,
                 from_cycle_id,
                 to_cycle_id,
                 transfer_date,
                 quantity,
                 notes,
                 request_token,
                 created_by,
                 created_at,
                 reversed_at,
                 reversed_by,
                 reversal_reason
             FROM production_population_transfers
             WHERE farm_id = ?
               AND request_token = ?
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$farmId, $requestToken]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('production_population_transfer_load')) {
    function production_population_transfer_load(
        PDO $pdo,
        int $farmId,
        int $transferId,
        bool $forUpdate = false
    ): array {
        if ($farmId <= 0 || $transferId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid production transfer.'
            );
        }

        $sql =
            'SELECT
                 id,
                 farm_id,
                 from_cycle_id,
                 to_cycle_id,
                 transfer_date,
                 quantity,
                 notes,
                 request_token,
                 created_by,
                 created_at,
                 reversed_at,
                 reversed_by,
                 reversal_reason
             FROM production_population_transfers
             WHERE id = ?
               AND farm_id = ?
             LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$transferId, $farmId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new ProductionPopulationTransferException(
                'The selected production transfer was not found in this farm.'
            );
        }

        return $row;
    }
}

if (!function_exists('production_population_transfer_legs')) {
    function production_population_transfer_legs(
        PDO $pdo,
        int $farmId,
        int $transferId,
        bool $forUpdate = false
    ): array {
        $sql =
            'SELECT
                 id,
                 farm_id,
                 transfer_id,
                 cycle_id,
                 direction,
                 population_movement_id,
                 created_at
             FROM production_population_transfer_legs
             WHERE farm_id = ?
               AND transfer_id = ?
             ORDER BY id ASC';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$farmId, $transferId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) !== 2) {
            throw new ProductionPopulationTransferException(
                'Production transfer integrity failed: exactly two transfer legs are required.'
            );
        }

        $byDirection = [];

        foreach ($rows as $row) {
            $direction = strtolower(
                trim((string)($row['direction'] ?? ''))
            );

            if (!in_array($direction, ['out', 'in'], true)) {
                throw new ProductionPopulationTransferException(
                    'Production transfer integrity failed: invalid transfer-leg direction.'
                );
            }

            if (isset($byDirection[$direction])) {
                throw new ProductionPopulationTransferException(
                    'Production transfer integrity failed: duplicate transfer-leg direction.'
                );
            }

            $byDirection[$direction] = $row;
        }

        if (!isset($byDirection['out'], $byDirection['in'])) {
            throw new ProductionPopulationTransferException(
                'Production transfer integrity failed: OUT and IN legs are both required.'
            );
        }

        return $byDirection;
    }
}

if (!function_exists('production_population_transfer_snapshot')) {
    function production_population_transfer_snapshot(
        PDO $pdo,
        array $transfer,
        bool $forUpdateLegs = false
    ): array {
        $farmId = (int)$transfer['farm_id'];
        $transferId = (int)$transfer['id'];

        $legs = production_population_transfer_legs(
            $pdo,
            $farmId,
            $transferId,
            $forUpdateLegs
        );

        return [
            'transfer_id' => $transferId,
            'farm_id' => $farmId,
            'from_cycle_id' => (int)$transfer['from_cycle_id'],
            'to_cycle_id' => (int)$transfer['to_cycle_id'],
            'transfer_date' => (string)$transfer['transfer_date'],
            'quantity' => (int)$transfer['quantity'],
            'status' => empty($transfer['reversed_at'])
                ? 'active'
                : 'reversed',
            'out_leg_id' => (int)$legs['out']['id'],
            'in_leg_id' => (int)$legs['in']['id'],
            'out_movement_id' =>
                $legs['out']['population_movement_id'] !== null
                    ? (int)$legs['out']['population_movement_id']
                    : null,
            'in_movement_id' =>
                $legs['in']['population_movement_id'] !== null
                    ? (int)$legs['in']['population_movement_id']
                    : null,
            'reversed_at' => $transfer['reversed_at'],
            'reversal_reason' => $transfer['reversal_reason'],
        ];
    }
}

if (!function_exists('production_population_transfer_same_submission')) {
    function production_population_transfer_same_submission(
        array $existing,
        int $fromCycleId,
        int $toCycleId,
        string $transferDate,
        int $quantity,
        ?string $notes
    ): bool {
        $existingNotes = production_population_normalize_note(
            isset($existing['notes'])
                ? (string)$existing['notes']
                : null
        );

        return
            (int)$existing['from_cycle_id'] === $fromCycleId
            && (int)$existing['to_cycle_id'] === $toCycleId
            && (string)$existing['transfer_date'] === $transferDate
            && (int)$existing['quantity'] === $quantity
            && $existingNotes === $notes;
    }
}

if (!function_exists('production_population_transfer_lock_context')) {
    /**
     * Lock both production cycles and both baselines in deterministic ID order.
     *
     * Deterministic ordering prevents opposite-direction transfers from taking
     * source/destination locks in different orders.
     */
    function production_population_transfer_lock_context(
        PDO $pdo,
        int $farmId,
        int $fromCycleId,
        int $toCycleId,
        string $transferDate,
        bool $requireActive
    ): array {
        if ($farmId <= 0) {
            throw new InvalidArgumentException(
                'Select a valid farm.'
            );
        }

        if ($fromCycleId <= 0 || $toCycleId <= 0) {
            throw new InvalidArgumentException(
                'Select valid source and destination production cycles.'
            );
        }

        if ($fromCycleId === $toCycleId) {
            throw new InvalidArgumentException(
                'Source and destination production cycles must be different.'
            );
        }

        if (!production_population_valid_date($transferDate)) {
            throw new InvalidArgumentException(
                'Enter a valid production transfer date.'
            );
        }

        $cycleIds = [$fromCycleId, $toCycleId];
        sort($cycleIds, SORT_NUMERIC);

        $lockedCycles = [];

        foreach ($cycleIds as $cycleId) {
            $lockedCycles[$cycleId] =
                production_population_lock_cycle(
                    $pdo,
                    $farmId,
                    $cycleId
                );
        }

        $baselines = [];

        foreach ($cycleIds as $cycleId) {
            $baselines[$cycleId] =
                production_population_lock_baseline(
                    $pdo,
                    $farmId,
                    $cycleId
                );

            if ($baselines[$cycleId] === null) {
                throw new ProductionPopulationTransferException(
                    'Confirm the current population for both production cycles before recording a transfer.'
                );
            }
        }

        $fromCycle = production_cycle_get(
            $pdo,
            $farmId,
            $fromCycleId,
            false
        );

        $toCycle = production_cycle_get(
            $pdo,
            $farmId,
            $toCycleId,
            false
        );

        if ($requireActive) {
            if (
                (string)$fromCycle['status'] !== 'active'
                || (string)$toCycle['status'] !== 'active'
            ) {
                throw new ProductionPopulationTransferException(
                    'Production transfers can only be recorded between active production cycles.'
                );
            }
        }

        production_population_assert_date_in_cycle(
            $lockedCycles[$fromCycleId],
            $transferDate,
            'Transfer date'
        );

        production_population_assert_date_in_cycle(
            $lockedCycles[$toCycleId],
            $transferDate,
            'Transfer date'
        );

        if (
            $transferDate
            < (string)$baselines[$fromCycleId]['baseline_date']
            || $transferDate
            < (string)$baselines[$toCycleId]['baseline_date']
        ) {
            throw new ProductionPopulationTransferException(
                'Transfer date cannot be earlier than either cycle population baseline.'
            );
        }

        $sameFarmType =
            strtolower((string)$fromCycle['farm_type'])
            === strtolower((string)$toCycle['farm_type']);

        $sameProductionType =
            strtolower((string)$fromCycle['production_type'])
            === strtolower((string)$toCycle['production_type']);

        $fromLivestockTypeId =
            $fromCycle['livestock_type_id'] !== null
                ? (int)$fromCycle['livestock_type_id']
                : null;

        $toLivestockTypeId =
            $toCycle['livestock_type_id'] !== null
                ? (int)$toCycle['livestock_type_id']
                : null;

        if (
            !$sameFarmType
            || !$sameProductionType
            || $fromLivestockTypeId !== $toLivestockTypeId
        ) {
            throw new ProductionPopulationTransferException(
                'Source and destination cycles must represent the same production type.'
            );
        }

        return [
            'from_cycle' => $fromCycle,
            'to_cycle' => $toCycle,
            'from_baseline' => $baselines[$fromCycleId],
            'to_baseline' => $baselines[$toCycleId],
        ];
    }
}

if (!function_exists('production_population_transfer_record')) {
    /**
     * Record one paired same-farm production-cycle transfer.
     *
     * Both population movements and both durable leg rows commit or roll back
     * together. This function preserves a caller-owned transaction.
     */
    function production_population_transfer_record(
        PDO $pdo,
        int $farmId,
        int $fromCycleId,
        int $toCycleId,
        string $transferDate,
        int $quantity,
        ?string $notes,
        ?int $userId,
        string $requestToken
    ): array {
        production_population_assert_user_id($userId);

        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Transfer quantity must be greater than 0.'
            );
        }

        $notes = production_population_normalize_note($notes);
        $requestToken =
            production_population_transfer_request_token(
                $requestToken
            );

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $existing =
                production_population_transfer_load_by_token(
                    $pdo,
                    $farmId,
                    $requestToken,
                    true
                );

            if ($existing !== null) {
                if (
                    !production_population_transfer_same_submission(
                        $existing,
                        $fromCycleId,
                        $toCycleId,
                        $transferDate,
                        $quantity,
                        $notes
                    )
                ) {
                    throw new ProductionPopulationTransferException(
                        'This transfer submission token already belongs to a different transfer.'
                    );
                }

                $snapshot =
                    production_population_transfer_snapshot(
                        $pdo,
                        $existing,
                        true
                    );

                if ($startedTransaction) {
                    $pdo->commit();
                }

                return $snapshot;
            }

            $context =
                production_population_transfer_lock_context(
                    $pdo,
                    $farmId,
                    $fromCycleId,
                    $toCycleId,
                    $transferDate,
                    true
                );

            $insert = $pdo->prepare(
                'INSERT INTO production_population_transfers
                 (
                     farm_id,
                     from_cycle_id,
                     to_cycle_id,
                     transfer_date,
                     quantity,
                     notes,
                     request_token,
                     created_by
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $insert->execute([
                $farmId,
                $fromCycleId,
                $toCycleId,
                $transferDate,
                $quantity,
                $notes,
                $requestToken,
                $userId,
            ]);

            $transferId = (int)$pdo->lastInsertId();

            $legInsert = $pdo->prepare(
                'INSERT INTO production_population_transfer_legs
                 (
                     farm_id,
                     transfer_id,
                     cycle_id,
                     direction,
                     population_movement_id
                 )
                 VALUES (?, ?, ?, ?, NULL)'
            );

            $legInsert->execute([
                $farmId,
                $transferId,
                $fromCycleId,
                'out',
            ]);
            $outLegId = (int)$pdo->lastInsertId();

            $legInsert->execute([
                $farmId,
                $transferId,
                $toCycleId,
                'in',
            ]);
            $inLegId = (int)$pdo->lastInsertId();

            $outMovementId =
                production_population_record_movement(
                    $pdo,
                    $farmId,
                    $fromCycleId,
                    'transfer_out',
                    $transferDate,
                    $quantity,
                    'transfer',
                    $outLegId,
                    1,
                    null,
                    'Paired production transfer #'
                        . $transferId
                        . ' out to cycle '
                        . (string)$context['to_cycle']['cycle_code']
                        . '.',
                    $userId
                );

            $inMovementId =
                production_population_record_movement(
                    $pdo,
                    $farmId,
                    $toCycleId,
                    'transfer_in',
                    $transferDate,
                    $quantity,
                    'transfer',
                    $inLegId,
                    1,
                    null,
                    'Paired production transfer #'
                        . $transferId
                        . ' in from cycle '
                        . (string)$context['from_cycle']['cycle_code']
                        . '.',
                    $userId
                );

            $linkMovement = $pdo->prepare(
                'UPDATE production_population_transfer_legs
                 SET population_movement_id = ?
                 WHERE id = ?
                   AND farm_id = ?
                   AND transfer_id = ?
                   AND population_movement_id IS NULL'
            );

            $linkMovement->execute([
                $outMovementId,
                $outLegId,
                $farmId,
                $transferId,
            ]);

            if ($linkMovement->rowCount() !== 1) {
                throw new ProductionPopulationTransferException(
                    'Could not link the source transfer leg to its canonical population movement.'
                );
            }

            $linkMovement->execute([
                $inMovementId,
                $inLegId,
                $farmId,
                $transferId,
            ]);

            if ($linkMovement->rowCount() !== 1) {
                throw new ProductionPopulationTransferException(
                    'Could not link the destination transfer leg to its canonical population movement.'
                );
            }

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'production_population_transfer_recorded',
                    'production_population_transfer',
                    $transferId,
                    [
                        'from_cycle_id' => $fromCycleId,
                        'to_cycle_id' => $toCycleId,
                        'transfer_date' => $transferDate,
                        'quantity' => $quantity,
                        'out_leg_id' => $outLegId,
                        'in_leg_id' => $inLegId,
                        'out_movement_id' => $outMovementId,
                        'in_movement_id' => $inMovementId,
                    ]
                );
            }

            $transfer =
                production_population_transfer_load(
                    $pdo,
                    $farmId,
                    $transferId,
                    true
                );

            $snapshot =
                production_population_transfer_snapshot(
                    $pdo,
                    $transfer,
                    true
                );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $snapshot;
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (
                production_population_transfer_duplicate_exception(
                    $error
                )
            ) {
                throw new ProductionPopulationTransferException(
                    'This production transfer submission was already recorded. Refresh and review the transfer history before retrying.'
                );
            }

            throw $error;
        }
    }
}

if (!function_exists('production_population_transfer_reverse')) {
    /**
     * Reverse both legs of one paired transfer atomically.
     *
     * Destination IN is reversed first because that is the subtractive
     * correction. Canonical population validation therefore proves the
     * destination can surrender the transferred quantity before the source
     * OUT leg is restored.
     */
    function production_population_transfer_reverse(
        PDO $pdo,
        int $farmId,
        int $transferId,
        string $reason,
        ?int $userId
    ): array {
        production_population_assert_user_id($userId);

        $reason = trim($reason);

        if (
            $reason === ''
            || production_population_text_length($reason) < 4
        ) {
            throw new InvalidArgumentException(
                'Enter a short reason for reversing this production transfer.'
            );
        }

        if (production_population_text_length($reason) > 255) {
            throw new InvalidArgumentException(
                'Production transfer reversal reason must be 255 characters or fewer.'
            );
        }

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $transfer =
                production_population_transfer_load(
                    $pdo,
                    $farmId,
                    $transferId,
                    true
                );

            if (!empty($transfer['reversed_at'])) {
                $snapshot =
                    production_population_transfer_snapshot(
                        $pdo,
                        $transfer,
                        true
                    );

                $snapshot['already_reversed'] = true;

                if ($startedTransaction) {
                    $pdo->commit();
                }

                return $snapshot;
            }

            production_population_transfer_lock_context(
                $pdo,
                $farmId,
                (int)$transfer['from_cycle_id'],
                (int)$transfer['to_cycle_id'],
                (string)$transfer['transfer_date'],
                false
            );

            $legs =
                production_population_transfer_legs(
                    $pdo,
                    $farmId,
                    $transferId,
                    true
                );

            $outMovementId =
                (int)($legs['out']['population_movement_id'] ?? 0);
            $inMovementId =
                (int)($legs['in']['population_movement_id'] ?? 0);

            if ($outMovementId <= 0 || $inMovementId <= 0) {
                throw new ProductionPopulationTransferException(
                    'Production transfer integrity failed: canonical movement links are incomplete.'
                );
            }

            $inReversalId =
                production_population_reverse_movement(
                    $pdo,
                    $farmId,
                    (int)$transfer['to_cycle_id'],
                    $inMovementId,
                    $reason,
                    $userId
                );

            $outReversalId =
                production_population_reverse_movement(
                    $pdo,
                    $farmId,
                    (int)$transfer['from_cycle_id'],
                    $outMovementId,
                    $reason,
                    $userId
                );

            $update = $pdo->prepare(
                'UPDATE production_population_transfers
                 SET
                     reversed_at = NOW(),
                     reversed_by = ?,
                     reversal_reason = ?
                 WHERE id = ?
                   AND farm_id = ?
                   AND reversed_at IS NULL'
            );

            $update->execute([
                $userId,
                $reason,
                $transferId,
                $farmId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new ProductionPopulationTransferException(
                    'Production transfer reversal could not be finalized because its state changed. Refresh and try again.'
                );
            }

            if (function_exists('audit_log_event')) {
                audit_log_event(
                    'production_population_transfer_reversed',
                    'production_population_transfer',
                    $transferId,
                    [
                        'reason' => $reason,
                        'in_reversal_movement_id' => $inReversalId,
                        'out_reversal_movement_id' => $outReversalId,
                    ]
                );
            }

            $finalTransfer =
                production_population_transfer_load(
                    $pdo,
                    $farmId,
                    $transferId,
                    true
                );

            $snapshot =
                production_population_transfer_snapshot(
                    $pdo,
                    $finalTransfer,
                    true
                );

            $snapshot['in_reversal_movement_id'] =
                $inReversalId;
            $snapshot['out_reversal_movement_id'] =
                $outReversalId;
            $snapshot['already_reversed'] = false;

            if ($startedTransaction) {
                $pdo->commit();
            }

            return $snapshot;
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}
