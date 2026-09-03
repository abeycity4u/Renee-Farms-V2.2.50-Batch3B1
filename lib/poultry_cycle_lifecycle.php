<?php
/**
 * V2.2.50 Batch 1 — Poultry lifecycle history.
 *
 * production_cycles remains the parent and its status remains operational.
 * This service records explicit, dated biological phases only. It never infers
 * a phase from bird age, egg output, feed, mortality or cycle status.
 */

if (!class_exists('PoultryLifecycleException')) {
    class PoultryLifecycleException extends RuntimeException {}
}

if (!function_exists('poultry_lifecycle_allowed_phases')) {
    function poultry_lifecycle_allowed_phases(string $productionType): array
    {
        $type = strtolower(trim($productionType));
        if ($type === 'layer') {
            return [
                'rearing' => 'Rearing',
                'production' => 'Production',
            ];
        }
        if ($type === 'broiler') {
            return [
                'growing' => 'Growing / Rearing',
                'harvest' => 'Harvest / Sale',
            ];
        }
        return [];
    }
}

if (!function_exists('poultry_lifecycle_phase_label')) {
    function poultry_lifecycle_phase_label(string $productionType, ?string $phase): string
    {
        if ($phase === null || $phase === '') {
            return 'Lifecycle history not yet defined';
        }
        $allowed = poultry_lifecycle_allowed_phases($productionType);
        return $allowed[$phase] ?? ucfirst(str_replace('_', ' ', $phase));
    }
}

if (!function_exists('poultry_lifecycle_next_phases')) {
    function poultry_lifecycle_next_phases(string $productionType, string $currentPhase): array
    {
        $type = strtolower(trim($productionType));
        $current = strtolower(trim($currentPhase));
        if ($type === 'layer' && $current === 'rearing') {
            return ['production' => 'Production'];
        }
        if ($type === 'broiler' && $current === 'growing') {
            return ['harvest' => 'Harvest / Sale'];
        }
        return [];
    }
}

if (!function_exists('poultry_lifecycle_valid_date')) {
    function poultry_lifecycle_valid_date(string $date): bool
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }
}

if (!function_exists('poultry_lifecycle_assert_date_in_cycle')) {
    function poultry_lifecycle_assert_date_in_cycle(array $cycle, string $date): void
    {
        if (!poultry_lifecycle_valid_date($date)) {
            throw new InvalidArgumentException('Enter a valid lifecycle date.');
        }
        $start = (string)($cycle['start_date'] ?? '');
        $close = (string)($cycle['close_date'] ?? '');
        if ($start !== '' && $date < $start) {
            throw new InvalidArgumentException('Lifecycle date cannot be earlier than the production cycle start date.');
        }
        if ($close !== '' && $date > $close) {
            throw new InvalidArgumentException('Lifecycle date cannot be later than the production cycle close date.');
        }
    }
}

if (!function_exists('poultry_lifecycle_lock_cycle')) {
    function poultry_lifecycle_lock_cycle(PDO $pdo, int $farmId, int $cycleId): array
    {
        $stmt = $pdo->prepare(
            "SELECT id, farm_id, cycle_code, farm_type, production_type, status, start_date, close_date
             FROM production_cycles
             WHERE id = ? AND farm_id = ? AND farm_type = 'poultry'
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$cycleId, $farmId]);
        $cycle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cycle) {
            throw new PoultryLifecycleException('The selected poultry cycle was not found in this farm.');
        }
        if (!in_array(strtolower((string)$cycle['production_type']), ['layer', 'broiler'], true)) {
            throw new PoultryLifecycleException('Lifecycle phases are currently supported only for Layer and Broiler cycles.');
        }
        return $cycle;
    }
}

if (!function_exists('poultry_lifecycle_history')) {
    function poultry_lifecycle_history(PDO $pdo, int $farmId, int $cycleId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, phase, start_date, end_date, notes, created_by, created_at
             FROM production_cycle_phases
             WHERE farm_id = ? AND cycle_id = ?
             ORDER BY start_date ASC, id ASC'
        );
        $stmt->execute([$farmId, $cycleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('poultry_lifecycle_current_phase')) {
    function poultry_lifecycle_current_phase(PDO $pdo, int $farmId, int $cycleId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, phase, start_date, end_date, notes, created_by, created_at
             FROM production_cycle_phases
             WHERE farm_id = ? AND cycle_id = ? AND end_date IS NULL
             ORDER BY start_date DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$farmId, $cycleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('poultry_lifecycle_record_initial_phase')) {
    function poultry_lifecycle_record_initial_phase(PDO $pdo, int $farmId, int $cycleId, string $phase, string $startDate, ?string $notes, ?int $userId): int
    {
        $phase = strtolower(trim($phase));
        $notes = trim((string)$notes);
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $cycle = poultry_lifecycle_lock_cycle($pdo, $farmId, $cycleId);
            $allowed = poultry_lifecycle_allowed_phases((string)$cycle['production_type']);
            if (!isset($allowed[$phase])) {
                throw new InvalidArgumentException('Select a valid lifecycle phase for this poultry production type.');
            }
            poultry_lifecycle_assert_date_in_cycle($cycle, $startDate);

            $historyStmt = $pdo->prepare('SELECT COUNT(*) FROM production_cycle_phases WHERE farm_id = ? AND cycle_id = ? FOR UPDATE');
            $historyStmt->execute([$farmId, $cycleId]);
            if ((int)$historyStmt->fetchColumn() > 0) {
                throw new PoultryLifecycleException('Lifecycle history already exists for this cycle. Use a lifecycle transition instead.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO production_cycle_phases (farm_id, cycle_id, phase, start_date, end_date, notes, created_by)
                 VALUES (?, ?, ?, ?, NULL, ?, ?)'
            );
            $stmt->execute([$farmId, $cycleId, $phase, $startDate, $notes !== '' ? $notes : null, $userId]);
            $id = (int)$pdo->lastInsertId();

            if (function_exists('audit_log_event')) {
                audit_log_event('poultry_lifecycle_phase_started', 'production_cycle_phase', $id, [
                    'cycle_id' => $cycleId,
                    'cycle_code' => $cycle['cycle_code'],
                    'production_type' => $cycle['production_type'],
                    'phase' => $phase,
                    'start_date' => $startDate,
                    'mode' => 'initial',
                ]);
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

if (!function_exists('poultry_lifecycle_transition_phase')) {
    function poultry_lifecycle_transition_phase(PDO $pdo, int $farmId, int $cycleId, string $nextPhase, string $transitionDate, ?string $notes, ?int $userId): int
    {
        $nextPhase = strtolower(trim($nextPhase));
        $notes = trim((string)$notes);
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $cycle = poultry_lifecycle_lock_cycle($pdo, $farmId, $cycleId);
            poultry_lifecycle_assert_date_in_cycle($cycle, $transitionDate);

            $currentStmt = $pdo->prepare(
                'SELECT id, phase, start_date
                 FROM production_cycle_phases
                 WHERE farm_id = ? AND cycle_id = ? AND end_date IS NULL
                 ORDER BY start_date DESC, id DESC
                 LIMIT 1 FOR UPDATE'
            );
            $currentStmt->execute([$farmId, $cycleId]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                throw new PoultryLifecycleException('No open lifecycle phase exists for this cycle. Set the initial phase first.');
            }

            $nextAllowed = poultry_lifecycle_next_phases((string)$cycle['production_type'], (string)$current['phase']);
            if (!isset($nextAllowed[$nextPhase])) {
                throw new InvalidArgumentException('That lifecycle transition is not valid from the current phase.');
            }
            if ($transitionDate <= (string)$current['start_date']) {
                throw new InvalidArgumentException('Transition date must be later than the current phase start date.');
            }

            $previousEndDate = (new DateTimeImmutable($transitionDate))->modify('-1 day')->format('Y-m-d');
            $closeStmt = $pdo->prepare('UPDATE production_cycle_phases SET end_date = ? WHERE id = ? AND farm_id = ? AND cycle_id = ? AND end_date IS NULL');
            $closeStmt->execute([$previousEndDate, (int)$current['id'], $farmId, $cycleId]);
            if ($closeStmt->rowCount() !== 1) {
                throw new PoultryLifecycleException('The lifecycle phase changed while you were working. Refresh and try again.');
            }

            $insertStmt = $pdo->prepare(
                'INSERT INTO production_cycle_phases (farm_id, cycle_id, phase, start_date, end_date, notes, created_by)
                 VALUES (?, ?, ?, ?, NULL, ?, ?)'
            );
            $insertStmt->execute([$farmId, $cycleId, $nextPhase, $transitionDate, $notes !== '' ? $notes : null, $userId]);
            $id = (int)$pdo->lastInsertId();

            if (function_exists('audit_log_event')) {
                audit_log_event('poultry_lifecycle_phase_transitioned', 'production_cycle_phase', $id, [
                    'cycle_id' => $cycleId,
                    'cycle_code' => $cycle['cycle_code'],
                    'production_type' => $cycle['production_type'],
                    'from_phase' => $current['phase'],
                    'from_phase_id' => (int)$current['id'],
                    'from_end_date' => $previousEndDate,
                    'to_phase' => $nextPhase,
                    'transition_date' => $transitionDate,
                ]);
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

if (!function_exists('poultry_lifecycle_end_current_phase')) {
    function poultry_lifecycle_end_current_phase(PDO $pdo, int $farmId, int $cycleId, string $endDate, ?int $userId): void
    {
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $cycle = poultry_lifecycle_lock_cycle($pdo, $farmId, $cycleId);
            poultry_lifecycle_assert_date_in_cycle($cycle, $endDate);
            $stmt = $pdo->prepare(
                'SELECT id, phase, start_date FROM production_cycle_phases
                 WHERE farm_id = ? AND cycle_id = ? AND end_date IS NULL
                 ORDER BY start_date DESC, id DESC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([$farmId, $cycleId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                throw new PoultryLifecycleException('This cycle has no open lifecycle phase to end.');
            }
            if (!empty(poultry_lifecycle_next_phases((string)$cycle['production_type'], (string)$current['phase']))) {
                throw new PoultryLifecycleException('This phase has a defined next biological phase. Record a lifecycle transition instead of ending it directly.');
            }
            if ($endDate < (string)$current['start_date']) {
                throw new InvalidArgumentException('Phase end date cannot be earlier than its start date.');
            }
            $update = $pdo->prepare('UPDATE production_cycle_phases SET end_date = ? WHERE id = ? AND farm_id = ? AND cycle_id = ? AND end_date IS NULL');
            $update->execute([$endDate, (int)$current['id'], $farmId, $cycleId]);
            if ($update->rowCount() !== 1) {
                throw new PoultryLifecycleException('The lifecycle phase changed while you were working. Refresh and try again.');
            }
            if (function_exists('audit_log_event')) {
                audit_log_event('poultry_lifecycle_phase_ended', 'production_cycle_phase', (int)$current['id'], [
                    'cycle_id' => $cycleId,
                    'cycle_code' => $cycle['cycle_code'],
                    'production_type' => $cycle['production_type'],
                    'phase' => $current['phase'],
                    'end_date' => $endDate,
                ]);
            }
            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
