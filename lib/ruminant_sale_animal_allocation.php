<?php
/**
 * V2.2.48 — individual-animal revenue attribution for ruminant sales.
 *
 * sales_records remains the single financial source of truth. Allocation rows
 * only attribute that sale revenue to registered ruminant animals; they do not
 * duplicate revenue and they do not change animal lifecycle/status.
 */

function ruminant_sale_build_animal_allocations(PDO $pdo, int $farmId, string $productionType, float $saleTotal, array $input): array
{
    $mode = strtolower(trim((string)($input['sale_animal_allocation_mode'] ?? 'shared')));
    if ($mode === '' || $mode === 'shared') {
        return ['mode' => 'shared', 'rows' => []];
    }
    if (!in_array($mode, ['equal', 'custom'], true)) {
        throw new RuntimeException('Choose a valid animal revenue allocation method.');
    }
    if ($productionType === 'shared') {
        throw new RuntimeException('Choose a specific ruminant production type before allocating sale revenue to individual animals.');
    }

    $rawIds = $input['sale_animal_ids'] ?? [];
    if (!is_array($rawIds)) $rawIds = [$rawIds];
    $animalIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn($id) => $id > 0)));
    if (!$animalIds) {
        throw new RuntimeException('Select at least one animal for individual animal revenue allocation.');
    }

    $placeholders = implode(',', array_fill(0, count($animalIds), '?'));
    $stmt = $pdo->prepare("SELECT id, tag_no, species, status FROM ruminant_animals WHERE farm_id=? AND id IN ($placeholders)");
    $stmt->execute(array_merge([$farmId], $animalIds));
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($animals) !== count($animalIds)) {
        throw new RuntimeException('One or more selected animals do not belong to this farm.');
    }
    foreach ($animals as $animal) {
        if ((string)$animal['species'] !== $productionType) {
            throw new RuntimeException('Selected animals must match the sale production type.');
        }
    }

    $totalCents = (int)round(round($saleTotal, 2) * 100);
    if ($totalCents <= 0) {
        throw new RuntimeException('Sale total must be greater than zero before animal revenue allocation.');
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
        $custom = $input['sale_animal_amounts'] ?? [];
        if (!is_array($custom)) $custom = [];
        $sumCents = 0;
        foreach ($animalIds as $animalId) {
            $raw = $custom[$animalId] ?? null;
            if ($raw === null || $raw === '' || !is_numeric($raw)) {
                throw new RuntimeException('Enter an allocated revenue amount for every selected animal.');
            }
            $cents = (int)round(((float)$raw) * 100);
            if ($cents < 0) {
                throw new RuntimeException('Animal revenue allocation amounts cannot be negative.');
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
            throw new RuntimeException('Custom animal revenue allocations must add up exactly to the sale total of ₦' . number_format($totalCents / 100, 2) . '.');
        }
    }

    return ['mode' => $mode, 'rows' => $rows];
}


/**
 * Build individual-animal revenue attribution from canonical slaughter-output
 * lot provenance.
 *
 * This does not mutate animal lifecycle or population. It only attributes the
 * already-created financial sale revenue to the animal(s) whose processed
 * output physically supplied the sale.
 *
 * When more than one source animal contributes matching lots, revenue follows
 * contributed quantity. Integer centi-units and largest-remainder monetary
 * rounding guarantee that allocated revenue equals the sale total exactly.
 */
function ruminant_sale_build_slaughter_output_allocations(
    array $slaughterSelection,
    float $saleTotal
): array {
    if (
        ($slaughterSelection['mode'] ?? 'financial_only')
        !== 'slaughter_output'
    ) {
        return [
            'mode' => 'shared',
            'rows' => [],
        ];
    }

    $totalCents =
        (int)round(
            round(
                $saleTotal,
                2
            ) * 100
        );

    if ($totalCents <= 0) {
        throw new RuntimeException(
            'Sale total must be greater than zero before slaughter-output animal revenue attribution.'
        );
    }

    $quantityUnitsByAnimal = [];

    foreach (
        ($slaughterSelection['rows'] ?? [])
        as $row
    ) {
        $animalId =
            (int)(
                $row['animal_id']
                ?? 0
            );

        $quantityUnits =
            (int)round(
                round(
                    (float)(
                        $row['quantity']
                        ?? 0
                    ),
                    2
                ) * 100
            );

        if (
            $animalId <= 0
            || $quantityUnits <= 0
        ) {
            throw new RuntimeException(
                'Slaughter-output revenue attribution requires valid source animal and quantity provenance.'
            );
        }

        $quantityUnitsByAnimal[$animalId] =
            (
                $quantityUnitsByAnimal[$animalId]
                ?? 0
            )
            + $quantityUnits;
    }

    if (!$quantityUnitsByAnimal) {
        throw new RuntimeException(
            'Slaughter-output revenue attribution could not identify a source animal.'
        );
    }

    ksort(
        $quantityUnitsByAnimal,
        SORT_NUMERIC
    );

    $totalQuantityUnits =
        array_sum(
            $quantityUnitsByAnimal
        );

    if ($totalQuantityUnits <= 0) {
        throw new RuntimeException(
            'Slaughter-output revenue attribution has no usable source quantity.'
        );
    }

    $centsByAnimal = [];
    $remainders = [];
    $assignedCents = 0;

    foreach (
        $quantityUnitsByAnimal
        as $animalId => $quantityUnits
    ) {
        $numerator =
            $totalCents
            * $quantityUnits;

        $baseCents =
            intdiv(
                $numerator,
                $totalQuantityUnits
            );

        $remainder =
            $numerator
            % $totalQuantityUnits;

        $centsByAnimal[$animalId] =
            $baseCents;

        $remainders[] = [
            'animal_id' =>
                (int)$animalId,

            'remainder' =>
                $remainder,
        ];

        $assignedCents +=
            $baseCents;
    }

    $remainingCents =
        $totalCents
        - $assignedCents;

    usort(
        $remainders,
        static function (
            array $a,
            array $b
        ): int {
            $remainderCompare =
                $b['remainder']
                <=> $a['remainder'];

            if ($remainderCompare !== 0) {
                return $remainderCompare;
            }

            return
                $a['animal_id']
                <=> $b['animal_id'];
        }
    );

    for (
        $i = 0;
        $i < $remainingCents;
        $i++
    ) {
        $animalId =
            (int)$remainders[
                $i % count($remainders)
            ]['animal_id'];

        $centsByAnimal[$animalId]++;
    }

    ksort(
        $centsByAnimal,
        SORT_NUMERIC
    );

    $rows = [];

    foreach (
        $centsByAnimal
        as $animalId => $cents
    ) {
        $rows[] = [
            'animal_id' =>
                (int)$animalId,

            'allocated_amount' =>
                $cents / 100,

            'allocation_percent' =>
                round(
                    (
                        $cents
                        / $totalCents
                    ) * 100,
                    4
                ),

            /*
             * Reuse the established persisted allocation method vocabulary.
             * Provenance is determined by the slaughter selection, not by
             * trusting browser-entered custom amounts.
             */
            'allocation_method' =>
                'custom',
        ];
    }

    return [
        'mode' => 'custom',
        'rows' => $rows,
        'source' => 'slaughter_output',
    ];
}

function ruminant_sale_save_animal_allocations(PDO $pdo, int $farmId, int $saleId, array $allocation, ?int $createdBy): void
{
    $delete = $pdo->prepare('DELETE FROM ruminant_sale_animal_allocations WHERE farm_id=? AND sale_id=?');
    $delete->execute([$farmId, $saleId]);
    if (empty($allocation['rows'])) return;

    $insert = $pdo->prepare('INSERT INTO ruminant_sale_animal_allocations
        (farm_id, sale_id, animal_id, allocation_method, allocation_percent, allocated_amount, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($allocation['rows'] as $row) {
        $insert->execute([
            $farmId,
            $saleId,
            (int)$row['animal_id'],
            (string)$row['allocation_method'],
            (float)$row['allocation_percent'],
            (float)$row['allocated_amount'],
            $createdBy ?: null,
        ]);
    }
}

function ruminant_sale_allocations_for_sales(PDO $pdo, int $farmId, array $saleIds): array
{
    $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds), static fn($id) => $id > 0)));
    if (!$saleIds) return [];
    $placeholders = implode(',', array_fill(0, count($saleIds), '?'));
    $sql = "SELECT a.sale_id, a.animal_id, a.allocation_method, a.allocation_percent, a.allocated_amount,
                   r.tag_no, r.species, r.status
            FROM ruminant_sale_animal_allocations a
            JOIN ruminant_animals r ON r.id=a.animal_id AND r.farm_id=a.farm_id
            WHERE a.farm_id=? AND a.sale_id IN ($placeholders)
            ORDER BY a.sale_id, r.species, r.tag_no";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$farmId], $saleIds));
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['sale_id']][] = $row;
    }
    return $map;
}
