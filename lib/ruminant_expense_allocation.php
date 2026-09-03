<?php
/**
 * V2.2.48 — direct/shared animal allocation for ruminant non-stock expenses.
 *
 * The farm_expenses row remains the single financial source of truth. This
 * table only attributes that expense total to one or more registered animals.
 */

function ruminant_expense_build_animal_allocations(PDO $pdo, int $farmId, string $productionType, float $expenseTotal, array $input): array
{
    $mode = strtolower(trim((string)($input['animal_allocation_mode'] ?? 'herd')));
    if ($mode === '' || $mode === 'herd') {
        return ['mode' => 'herd', 'rows' => []];
    }
    if (!in_array($mode, ['equal', 'custom'], true)) {
        throw new RuntimeException('Choose a valid animal allocation method.');
    }
    if ($productionType === 'shared') {
        throw new RuntimeException('Choose a specific ruminant production type before allocating an expense to individual animals.');
    }

    $rawIds = $input['animal_ids'] ?? [];
    if (!is_array($rawIds)) $rawIds = [$rawIds];
    $animalIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), fn($id) => $id > 0)));
    if (!$animalIds) {
        throw new RuntimeException('Select at least one animal for individual animal allocation.');
    }

    $placeholders = implode(',', array_fill(0, count($animalIds), '?'));
    $stmt = $pdo->prepare("SELECT id, tag_no, species, status FROM ruminant_animals WHERE farm_id=? AND id IN ($placeholders)");
    $stmt->execute(array_merge([$farmId], $animalIds));
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($animals) !== count($animalIds)) {
        throw new RuntimeException('One or more selected animals do not belong to this farm.');
    }

    $animalById = [];
    foreach ($animals as $animal) {
        if ((string)$animal['species'] !== $productionType) {
            throw new RuntimeException('Selected animals must match the expense production type.');
        }
        $animalById[(int)$animal['id']] = $animal;
    }

    $totalCents = (int)round(round($expenseTotal, 2) * 100);
    if ($totalCents <= 0) {
        throw new RuntimeException('Expense total must be greater than zero before animal allocation.');
    }

    $rows = [];
    if ($mode === 'equal') {
        $count = count($animalIds);
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents - ($base * $count);
        foreach ($animalIds as $index => $animalId) {
            $cents = $base + ($index < $remainder ? 1 : 0);
            $rows[] = [
                'animal_id' => $animalId,
                'allocated_amount' => $cents / 100,
                'allocation_percent' => round(($cents / $totalCents) * 100, 4),
                'allocation_method' => 'equal',
            ];
        }
    } else {
        $custom = $input['animal_amounts'] ?? [];
        if (!is_array($custom)) $custom = [];
        $sumCents = 0;
        foreach ($animalIds as $animalId) {
            $raw = $custom[$animalId] ?? null;
            if ($raw === null || $raw === '' || !is_numeric($raw)) {
                throw new RuntimeException('Enter an allocated amount for every selected animal.');
            }
            $cents = (int)round(((float)$raw) * 100);
            if ($cents < 0) {
                throw new RuntimeException('Animal allocation amounts cannot be negative.');
            }
            $sumCents += $cents;
            $rows[] = [
                'animal_id' => $animalId,
                'allocated_amount' => $cents / 100,
                'allocation_percent' => round(($cents / $totalCents) * 100, 4),
                'allocation_method' => 'custom',
            ];
        }
        if ($sumCents !== $totalCents) {
            throw new RuntimeException('Custom animal allocations must add up exactly to the expense total of ₦' . number_format($totalCents / 100, 2) . '.');
        }
    }

    return ['mode' => $mode, 'rows' => $rows];
}

function ruminant_expense_save_animal_allocations(PDO $pdo, int $farmId, int $expenseId, array $allocation, ?int $createdBy): void
{
    $delete = $pdo->prepare('DELETE FROM ruminant_expense_animal_allocations WHERE farm_id=? AND expense_id=?');
    $delete->execute([$farmId, $expenseId]);

    if (empty($allocation['rows'])) return;

    $insert = $pdo->prepare('INSERT INTO ruminant_expense_animal_allocations
        (farm_id, expense_id, animal_id, allocation_method, allocation_percent, allocated_amount, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($allocation['rows'] as $row) {
        $insert->execute([
            $farmId,
            $expenseId,
            (int)$row['animal_id'],
            (string)$row['allocation_method'],
            (float)$row['allocation_percent'],
            (float)$row['allocated_amount'],
            $createdBy ?: null,
        ]);
    }
}

function ruminant_expense_allocations_for_expenses(PDO $pdo, int $farmId, array $expenseIds): array
{
    $expenseIds = array_values(array_unique(array_filter(array_map('intval', $expenseIds), fn($id) => $id > 0)));
    if (!$expenseIds) return [];
    $placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
    $sql = "SELECT a.expense_id, a.animal_id, a.allocation_method, a.allocation_percent, a.allocated_amount,
                   r.tag_no, r.species, r.status
            FROM ruminant_expense_animal_allocations a
            JOIN ruminant_animals r ON r.id=a.animal_id AND r.farm_id=a.farm_id
            WHERE a.farm_id=? AND a.expense_id IN ($placeholders)
            ORDER BY a.expense_id, r.species, r.tag_no";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$farmId], $expenseIds));
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['expense_id']][] = $row;
    }
    return $map;
}
