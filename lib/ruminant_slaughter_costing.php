<?php

require_once __DIR__ . '/ruminant_shared_cost_economics.php';

/**
 * Cost basis for one slaughtered animal as of a business date.
 *
 * This is cost-only. Revenue and selling prices are deliberately excluded.
 * Shared rows are taken from the existing canonical Ruminant shared-cost
 * economics reader, then bounded to the slaughter date before snapshotting.
 */
function ruminant_slaughter_costing_as_of(
    PDO $pdo,
    int $farmId,
    int $animalId,
    string $asOfDate
): array {
    if (
        $farmId <= 0
        || $animalId <= 0
        || preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $asOfDate
        ) !== 1
    ) {
        throw new InvalidArgumentException(
            'A valid farm, animal and costing date are required.'
        );
    }

    $animalStmt = $pdo->prepare(
        'SELECT
             id,
             tag_no,
             species,
             purchase_date,
             purchase_cost
         FROM ruminant_animals
         WHERE id=?
           AND farm_id=?
         LIMIT 1'
    );
    $animalStmt->execute([
        $animalId,
        $farmId,
    ]);

    $animal = $animalStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$animal) {
        throw new RuntimeException(
            'The animal cost basis could not be resolved.'
        );
    }

    $purchaseCost =
        round(
            (float)(
                $animal['purchase_cost']
                ?? 0
            ),
            2
        );

    $purchaseDate =
        trim(
            (string)(
                $animal['purchase_date']
                ?? ''
            )
        );

    if (
        $purchaseCost > 0
        && $purchaseDate !== ''
        && $purchaseDate > $asOfDate
    ) {
        throw new RuntimeException(
            'Animal purchase cost is dated after the slaughter date.'
        );
    }

    $directStmt = $pdo->prepare(
        "SELECT
             COALESCE(
                 SUM(a.allocated_amount),
                 0
             )
         FROM ruminant_expense_animal_allocations a
         INNER JOIN farm_expenses e
             ON e.id=a.expense_id
            AND e.farm_id=a.farm_id
         WHERE a.farm_id=?
           AND a.animal_id=?
           AND e.expense_date<=?"
    );
    $directStmt->execute([
        $farmId,
        $animalId,
        $asOfDate,
    ]);

    $directExpense =
        round(
            (float)$directStmt->fetchColumn(),
            2
        );

    $shared =
        ruminant_shared_cost_economics(
            $pdo,
            $farmId,
            $animalId,
            (string)$animal['species']
        );

    $sharedAmount = 0.0;
    $sharedRows = [];

    foreach (
        ($shared['shared_cost_rows'] ?? [])
        as $row
    ) {
        $sourceDate =
            substr(
                (string)(
                    $row['source_date']
                    ?? ''
                ),
                0,
                10
            );

        if (
            $sourceDate === ''
            || $sourceDate > $asOfDate
        ) {
            continue;
        }

        $allocated =
            round(
                (float)(
                    $row['allocated_amount']
                    ?? 0
                ),
                2
            );

        if ($allocated <= 0) {
            continue;
        }

        $sharedAmount += $allocated;
        $sharedRows[] = $row;
    }

    $sharedAmount =
        round(
            $sharedAmount,
            2
        );

    $total =
        round(
            $purchaseCost
            + $directExpense
            + $sharedAmount,
            2
        );

    return [
        'as_of_date' => $asOfDate,
        'purchase_cost' => $purchaseCost,
        'direct_expense_cost' => $directExpense,
        'shared_cost' => $sharedAmount,
        'total_cost_basis' => $total,
        'shared_rows' => $sharedRows,
        'method' =>
            'Purchase + direct animal expenses + allocated shared costs through slaughter date',
    ];
}
