<?php

class PoultryAcquisitionException extends RuntimeException {}

if (!function_exists('poultry_acquisition_allowed_types')) {
    function poultry_acquisition_allowed_types(string $productionType): array
    {
        $type = strtolower(trim($productionType));
        if ($type === 'layer') {
            return [
                'purchased' => 'Purchased birds',
                'purchased_point_of_lay' => 'Purchased Point-of-Lay',
                'internal_transfer' => 'Farm-raised / transferred in',
            ];
        }
        if ($type === 'broiler') {
            return [
                'purchased' => 'Purchased birds',
                'internal_transfer' => 'Farm-raised / transferred in',
            ];
        }
        return [];
    }
}

if (!function_exists('poultry_acquisition_type_label')) {
    function poultry_acquisition_type_label(string $productionType, string $acquisitionType): string
    {
        $allowed = poultry_acquisition_allowed_types($productionType);
        return $allowed[$acquisitionType] ?? ucwords(str_replace('_', ' ', $acquisitionType));
    }
}

if (!function_exists('poultry_acquisition_valid_date')) {
    function poultry_acquisition_valid_date(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('poultry_acquisition_lock_cycle')) {
    function poultry_acquisition_lock_cycle(PDO $pdo, int $farmId, int $cycleId): array
    {
        if ($cycleId <= 0) {
            throw new InvalidArgumentException('Select a poultry cycle.');
        }
        $stmt = $pdo->prepare(
            "SELECT id, cycle_code, farm_type, production_type, status, start_date, close_date, opening_headcount
             FROM production_cycles
             WHERE id = ? AND farm_id = ?
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$cycleId, $farmId]);
        $cycle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cycle || strtolower((string)$cycle['farm_type']) !== 'poultry') {
            throw new PoultryAcquisitionException('The selected poultry cycle was not found in this farm.');
        }
        if (!in_array(strtolower((string)$cycle['production_type']), ['layer', 'broiler'], true)) {
            throw new PoultryAcquisitionException('Acquisition entry is available only for Layer and Broiler cycles.');
        }
        return $cycle;
    }
}

if (!function_exists('poultry_acquisition_first_phase_start')) {
    function poultry_acquisition_first_phase_start(PDO $pdo, int $farmId, int $cycleId): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT start_date FROM production_cycle_phases
             WHERE farm_id = ? AND cycle_id = ?
             ORDER BY start_date ASC, id ASC LIMIT 1'
        );
        $stmt->execute([$farmId, $cycleId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string)$value : null;
    }
}

if (!function_exists('poultry_acquisition_record')) {
    function poultry_acquisition_record(
        PDO $pdo,
        int $farmId,
        int $cycleId,
        string $acquisitionType,
        string $acquisitionDate,
        int $quantity,
        int $ageDays,
        ?float $totalCost,
        ?string $sourceName,
        ?string $referenceNo,
        ?string $notes,
        ?int $userId,
        ?string $requestToken = null
    ): int {
        $acquisitionType = strtolower(trim($acquisitionType));
        $sourceName = trim((string)$sourceName);
        $referenceNo = trim((string)$referenceNo);
        $notes = trim((string)$notes);
        $requestToken = trim((string)$requestToken);
        if ($requestToken !== '' && !preg_match('/^[a-f0-9]{32,64}$/', $requestToken)) {
            throw new InvalidArgumentException('Invalid acquisition submission token. Refresh the page and try again.');
        }

        if (!poultry_acquisition_valid_date($acquisitionDate)) {
            throw new InvalidArgumentException('Enter a valid acquisition date.');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Acquisition quantity must be greater than 0.');
        }
        if ($ageDays < 1) {
            throw new InvalidArgumentException('Age at acquisition must be at least 1 day.');
        }
        if ($totalCost !== null && $totalCost < 0) {
            throw new InvalidArgumentException('Total acquisition cost cannot be negative.');
        }
        if ($acquisitionType !== 'internal_transfer' && $totalCost === null) {
            throw new InvalidArgumentException('Enter the actual total amount paid for purchased birds.');
        }

        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $cycle = poultry_acquisition_lock_cycle($pdo, $farmId, $cycleId);
            $allowed = poultry_acquisition_allowed_types((string)$cycle['production_type']);
            if (!isset($allowed[$acquisitionType])) {
                throw new InvalidArgumentException('Select a valid acquisition type for this poultry production type.');
            }

            if ($acquisitionDate < (string)$cycle['start_date']) {
                throw new InvalidArgumentException('Acquisition date cannot be earlier than the production cycle start date.');
            }
            if (!empty($cycle['close_date']) && $acquisitionDate > (string)$cycle['close_date']) {
                throw new InvalidArgumentException('Acquisition date cannot be later than the cycle close date.');
            }

            $firstPhaseStart = poultry_acquisition_first_phase_start($pdo, $farmId, $cycleId);
            if ($firstPhaseStart !== null && $acquisitionDate > $firstPhaseStart) {
                throw new InvalidArgumentException('Acquisition must be recorded on or before the first known biological phase start date.');
            }

            if ($requestToken !== '') {
                $existingStmt = $pdo->prepare('SELECT id FROM poultry_cycle_acquisitions WHERE farm_id = ? AND request_token = ? LIMIT 1');
                $existingStmt->execute([$farmId, $requestToken]);
                $existingId = (int)($existingStmt->fetchColumn() ?: 0);
                if ($existingId > 0) {
                    if ($startedTransaction && $pdo->inTransaction()) {
                        $pdo->commit();
                    }
                    return $existingId;
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO poultry_cycle_acquisitions
                 (farm_id, cycle_id, acquisition_type, acquisition_date, quantity, age_days, total_cost, source_name, reference_no, notes, request_token, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $farmId,
                $cycleId,
                $acquisitionType,
                $acquisitionDate,
                $quantity,
                $ageDays,
                $totalCost,
                $sourceName !== '' ? $sourceName : null,
                $referenceNo !== '' ? $referenceNo : null,
                $notes !== '' ? $notes : null,
                $requestToken !== '' ? $requestToken : null,
                $userId,
            ]);
            $id = (int)$pdo->lastInsertId();

            if (function_exists('audit_log_event')) {
                audit_log_event('poultry_cycle_acquisition_recorded', 'poultry_cycle_acquisition', $id, [
                    'cycle_id' => $cycleId,
                    'cycle_code' => $cycle['cycle_code'],
                    'production_type' => $cycle['production_type'],
                    'acquisition_type' => $acquisitionType,
                    'acquisition_date' => $acquisitionDate,
                    'quantity' => $quantity,
                    'age_days' => $ageDays,
                    'total_cost' => $totalCost,
                    'source_name' => $sourceName !== '' ? $sourceName : null,
                    'reference_no' => $referenceNo !== '' ? $referenceNo : null,
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

if (!function_exists('poultry_acquisition_void')) {
    function poultry_acquisition_void(PDO $pdo, int $farmId, int $acquisitionId, string $reason, ?int $userId): void
    {
        $reason = trim($reason);
        if ($acquisitionId <= 0) {
            throw new InvalidArgumentException('Select a valid acquisition entry to void.');
        }
        if ($reason === '' || mb_strlen($reason) < 4) {
            throw new InvalidArgumentException('Enter a short correction reason explaining why this acquisition entry is being voided.');
        }
        if (mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('Correction reason must be 255 characters or fewer.');
        }

        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT a.id, a.cycle_id, a.voided_at, pc.cycle_code
                 FROM poultry_cycle_acquisitions a
                 INNER JOIN production_cycles pc ON pc.id = a.cycle_id AND pc.farm_id = a.farm_id
                 WHERE a.id = ? AND a.farm_id = ? AND pc.farm_type = 'poultry'
                 FOR UPDATE"
            );
            $stmt->execute([$acquisitionId, $farmId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new PoultryAcquisitionException('The selected acquisition entry was not found in this farm.');
            }
            if (!empty($row['voided_at'])) {
                throw new PoultryAcquisitionException('This acquisition entry has already been voided.');
            }

            $update = $pdo->prepare(
                'UPDATE poultry_cycle_acquisitions
                 SET voided_at = NOW(), voided_by = ?, void_reason = ?
                 WHERE id = ? AND farm_id = ? AND voided_at IS NULL'
            );
            $update->execute([$userId, $reason, $acquisitionId, $farmId]);
            if ($update->rowCount() !== 1) {
                throw new PoultryAcquisitionException('The acquisition entry could not be voided because its state changed. Refresh and try again.');
            }

            if (function_exists('audit_log_event')) {
                audit_log_event('poultry_cycle_acquisition_voided', 'poultry_cycle_acquisition', $acquisitionId, [
                    'cycle_id' => (int)$row['cycle_id'],
                    'cycle_code' => (string)$row['cycle_code'],
                    'reason' => $reason,
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

if (!function_exists('poultry_acquisition_history')) {
    function poultry_acquisition_history(PDO $pdo, int $farmId, int $cycleId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, cycle_id, acquisition_type, acquisition_date, quantity, age_days, total_cost,
                    source_name, reference_no, notes, request_token, created_by, created_at,
                    voided_at, voided_by, void_reason
             FROM poultry_cycle_acquisitions
             WHERE farm_id = ? AND cycle_id = ?
             ORDER BY acquisition_date ASC, id ASC'
        );
        $stmt->execute([$farmId, $cycleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('poultry_acquisition_summary')) {
    function poultry_acquisition_summary(array $rows): array
    {
        $quantity = 0;
        $totalCost = 0.0;
        $allCosted = !empty($rows);
        $activeRows = array_values(array_filter($rows, static function (array $row): bool {
            return empty($row['voided_at']);
        }));
        $allCosted = !empty($activeRows);
        foreach ($activeRows as $row) {
            $quantity += (int)($row['quantity'] ?? 0);
            if ($row['total_cost'] === null || $row['total_cost'] === '') {
                $allCosted = false;
            } else {
                $totalCost += (float)$row['total_cost'];
            }
        }

        return [
            'entry_count' => count($activeRows),
            'quantity' => $quantity,
            'total_cost' => $allCosted ? $totalCost : null,
            'effective_cost_per_bird' => ($allCosted && $quantity > 0) ? ($totalCost / $quantity) : null,
            'has_uncosted_entry' => !$allCosted && !empty($activeRows),
        ];
    }
}
