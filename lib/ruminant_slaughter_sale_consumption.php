<?php

require_once __DIR__ . '/stock_service.php';
require_once __DIR__ . '/inventory_category_role.php';
require_once __DIR__ . '/sales_units.php';

/**
 * Slaughter-output Sales lot consumption.
 *
 * Responsibilities:
 *   - explicit output-lot selection only; never infer a lot from product text;
 *   - one sale line consumes one Inventory item/unit from one production cycle;
 *   - physical decrement goes through stock_apply_movement();
 *   - source-lot remaining quantity changes in the same transaction;
 *   - lot-specific COGS uses the frozen slaughter-output cost snapshot;
 *   - edits reverse old lot movements append-only, then append new movements;
 *   - no live-population mutation is owned here.
 */

if (!class_exists('RuminantSlaughterSaleException')) {
    class RuminantSlaughterSaleException extends RuntimeException
    {
    }
}

function ruminant_slaughter_sale_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuminantSlaughterSaleException(
            'Slaughter-output Sales changes require a caller-owned database transaction.'
        );
    }
}

function ruminant_slaughter_sale_sales_unit(
    string $inventoryUnit
): string {
    $unit =
        strtolower(
            trim(
                $inventoryUnit
            )
        );

    $map = [
        'kg' => 'Kg',
        'kilogram' => 'Kg',
        'kilograms' => 'Kg',
        'g' => 'Gram',
        'gram' => 'Gram',
        'grams' => 'Gram',
        'pc' => 'Piece',
        'pcs' => 'Piece',
        'piece' => 'Piece',
        'pieces' => 'Piece',
        'head' => 'Head',
        'litre' => 'Litre',
        'liter' => 'Litre',
        'litres' => 'Litre',
        'liters' => 'Litre',
        'ml' => 'Ml',
        'unit' => 'Unit',
    ];

    if (isset($map[$unit])) {
        return $map[$unit];
    }

    foreach (array_keys(sales_unit_presets()) as $preset) {
        if (strtolower($preset) === $unit) {
            return $preset;
        }
    }

    $clean =
        trim(
            preg_replace(
                '/\s+/',
                ' ',
                $inventoryUnit
            )
        );

    if ($clean === '') {
        throw new RuminantSlaughterSaleException(
            'The selected slaughter output has no usable unit of measure.'
        );
    }

    if (
        (function_exists('mb_strlen') ? mb_strlen($clean) : strlen($clean))
        > 30
    ) {
        throw new RuminantSlaughterSaleException(
            'The slaughter output unit is too long for Sales.'
        );
    }

    return $clean;
}

function ruminant_slaughter_sale_normalize_rows(
    array $rows
): array {
    $normalized = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new RuminantSlaughterSaleException(
                'Slaughter sale lot selection is invalid.'
            );
        }

        $outputId =
            (int)(
                $row['output_id']
                ?? 0
            );

        $quantity =
            round(
                (float)(
                    $row['quantity']
                    ?? 0
                ),
                2
            );

        if (
            $outputId <= 0
            || !is_finite($quantity)
            || $quantity <= 0
        ) {
            throw new RuminantSlaughterSaleException(
                'Choose a valid slaughter output lot and quantity greater than zero.'
            );
        }

        if (isset($normalized[$outputId])) {
            throw new RuminantSlaughterSaleException(
                'The same slaughter output lot cannot be selected twice in one sale.'
            );
        }

        $normalized[$outputId] = [
            'output_id' => $outputId,
            'quantity' => $quantity,
        ];
    }

    ksort(
        $normalized,
        SORT_NUMERIC
    );

    return $normalized;
}

function ruminant_slaughter_sale_rows_from_post(
    array $input
): array {
    $mode =
        strtolower(
            trim(
                (string)(
                    $input['sale_stock_source']
                    ?? 'financial_only'
                )
            )
        );

    if ($mode === '' || $mode === 'financial_only') {
        return [];
    }

    if ($mode !== 'slaughter_output') {
        throw new RuminantSlaughterSaleException(
            'Choose a valid Sales stock source.'
        );
    }

    $ids =
        $input['slaughter_output_ids']
        ?? [];

    $quantities =
        $input['slaughter_output_quantities']
        ?? [];

    if (!is_array($ids)) {
        $ids = [$ids];
    }

    if (!is_array($quantities)) {
        $quantities = [$quantities];
    }

    if (count($ids) !== count($quantities)) {
        throw new RuminantSlaughterSaleException(
            'Every slaughter output lot requires a sale quantity.'
        );
    }

    $rows = [];

    foreach ($ids as $index => $outputId) {
        $rows[] = [
            'output_id' =>
                (int)$outputId,

            'quantity' =>
                (float)(
                    $quantities[$index]
                    ?? 0
                ),
        ];
    }

    $normalized =
        ruminant_slaughter_sale_normalize_rows(
            $rows
        );

    if (!$normalized) {
        throw new RuminantSlaughterSaleException(
            'Select at least one slaughter output lot.'
        );
    }

    return $normalized;
}

function ruminant_slaughter_sale_current_active_rows(
    PDO $pdo,
    int $farmId,
    int $saleId,
    bool $forUpdate = false
): array {
    $sql =
        "SELECT
             a.*
         FROM ruminant_slaughter_sale_allocations a
         WHERE a.farm_id=?
           AND a.sale_id=?
           AND a.is_active=1
         ORDER BY a.output_id,a.id";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $saleId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}

function ruminant_slaughter_sale_history_for_sales(
    PDO $pdo,
    int $farmId,
    array $saleIds
): array {
    $saleIds =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $saleIds
                    ),
                    static fn(int $id): bool =>
                        $id > 0
                )
            )
        );

    if (!$saleIds) {
        return [];
    }

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($saleIds),
                '?'
            )
        );

    $stmt =
        $pdo->prepare(
            "SELECT
                 a.*,
                 o.batch_id,
                 o.remaining_quantity,
                 o.initial_quantity,
                 b.batch_code,
                 b.slaughter_date,
                 b.animal_id,
                 b.cycle_id,
                 r.tag_no,
                 r.species,
                 si.item_name,
                 si.unit AS inventory_unit
             FROM ruminant_slaughter_sale_allocations a
             INNER JOIN ruminant_slaughter_outputs o
                 ON o.id=a.output_id
                AND o.farm_id=a.farm_id
             INNER JOIN ruminant_slaughter_batches b
                 ON b.id=o.batch_id
                AND b.farm_id=o.farm_id
             INNER JOIN ruminant_animals r
                 ON r.id=b.animal_id
                AND r.farm_id=b.farm_id
             INNER JOIN stock_items si
                 ON si.id=o.stock_item_id
                AND si.farm_id=o.farm_id
             WHERE a.farm_id=?
               AND a.sale_id IN ({$placeholders})
             ORDER BY a.sale_id,a.is_active DESC,a.id"
        );

    $stmt->execute(
        array_merge(
            [$farmId],
            $saleIds
        )
    );

    $map = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $row
    ) {
        $map[
            (int)$row['sale_id']
        ][] = $row;
    }

    return $map;
}

function ruminant_slaughter_sale_available_lots(
    PDO $pdo,
    int $farmId
): array {
    $stmt =
        $pdo->prepare(
            "SELECT
                 o.id AS output_id,
                 o.batch_id,
                 o.stock_item_id,
                 o.initial_quantity,
                 o.remaining_quantity,
                 o.unit,
                 o.unit_cost_snapshot,
                 o.allocated_cost,
                 b.batch_code,
                 b.slaughter_date,
                 b.status AS batch_status,
                 b.animal_id,
                 b.cycle_id,
                 r.tag_no,
                 r.species,
                 pc.production_type,
                 pc.cycle_code,
                 si.item_name
             FROM ruminant_slaughter_outputs o
             INNER JOIN ruminant_slaughter_batches b
                 ON b.id=o.batch_id
                AND b.farm_id=o.farm_id
             INNER JOIN ruminant_animals r
                 ON r.id=b.animal_id
                AND r.farm_id=b.farm_id
             INNER JOIN production_cycles pc
                 ON pc.id=b.cycle_id
                AND pc.farm_id=b.farm_id
             INNER JOIN stock_items si
                 ON si.id=o.stock_item_id
                AND si.farm_id=o.farm_id
             INNER JOIN inventory_categories ic
                 ON ic.id=si.category_id
                AND ic.farm_id=si.farm_id
                AND ic.inventory_role=?
             WHERE o.farm_id=?
               AND o.remaining_quantity>0
             ORDER BY
                 si.item_name,
                 b.slaughter_date,
                 b.id,
                 o.id"
        );

    $stmt->execute([
        inventory_category_slaughter_output_role(),
        $farmId,
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    foreach ($rows as &$row) {
        $row['sales_unit'] =
            ruminant_slaughter_sale_sales_unit(
                (string)$row['unit']
            );
    }

    unset($row);

    return $rows;
}

function ruminant_slaughter_sale_selection(
    PDO $pdo,
    int $farmId,
    string $saleDate,
    array $desiredRows,
    ?int $saleId = null
): array {
    $desired =
        ruminant_slaughter_sale_normalize_rows(
            $desiredRows
        );

    if (!$desired) {
        return [
            'mode' => 'financial_only',
            'rows' => [],
        ];
    }

    $saleDate =
        trim(
            $saleDate
        );

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $saleDate
        )
    ) {
        throw new RuminantSlaughterSaleException(
            'Choose a valid sale date before selecting slaughter output lots.'
        );
    }

    $outputIds =
        array_keys(
            $desired
        );

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($outputIds),
                '?'
            )
        );

    $sql =
        "SELECT
             o.id AS output_id,
             o.batch_id,
             o.stock_item_id,
             o.initial_quantity,
             o.remaining_quantity,
             o.unit,
             o.unit_cost_snapshot,
             o.allocated_cost,
             b.batch_code,
             b.slaughter_date,
             b.status AS batch_status,
             b.animal_id,
             b.cycle_id,
             r.tag_no,
             r.species,
             pc.production_type,
             pc.cycle_code,
             si.item_name,
             si.is_active,
             COALESCE(
                 NULLIF(ic.inventory_role,''),
                 'operational'
             ) AS inventory_role
         FROM ruminant_slaughter_outputs o
         INNER JOIN ruminant_slaughter_batches b
             ON b.id=o.batch_id
            AND b.farm_id=o.farm_id
         INNER JOIN ruminant_animals r
             ON r.id=b.animal_id
            AND r.farm_id=b.farm_id
         INNER JOIN production_cycles pc
             ON pc.id=b.cycle_id
            AND pc.farm_id=b.farm_id
         INNER JOIN stock_items si
             ON si.id=o.stock_item_id
            AND si.farm_id=o.farm_id
         INNER JOIN inventory_categories ic
             ON ic.id=si.category_id
            AND ic.farm_id=si.farm_id
         WHERE o.farm_id=?
           AND o.id IN ({$placeholders})
         ORDER BY o.id";

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute(
        array_merge(
            [$farmId],
            $outputIds
        )
    );

    $outputs =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    if (count($outputs) !== count($outputIds)) {
        throw new RuminantSlaughterSaleException(
            'One or more selected slaughter output lots do not belong to this farm.'
        );
    }

    $currentByOutput = [];

    if ($saleId !== null && $saleId > 0) {
        foreach (
            ruminant_slaughter_sale_current_active_rows(
                $pdo,
                $farmId,
                $saleId,
                false
            ) as $row
        ) {
            $currentByOutput[
                (int)$row['output_id']
            ] =
                round(
                    (float)$row['quantity'],
                    2
                );
        }
    }

    $stockItemId = null;
    $cycleId = null;
    $productionType = null;
    $inventoryUnit = null;
    $itemName = null;
    $quantity = 0.0;
    $rows = [];

    foreach ($outputs as $output) {
        $outputId =
            (int)$output['output_id'];

        $wanted =
            (float)$desired[
                $outputId
            ]['quantity'];

        if (
            (string)$output['inventory_role']
                !== inventory_category_slaughter_output_role()
            || (int)$output['is_active'] !== 1
        ) {
            throw new RuminantSlaughterSaleException(
                'The selected lot is not an active Slaughter Output Inventory item.'
            );
        }

        if (
            (string)$output['batch_status'] === 'completed'
            && !isset($currentByOutput[$outputId])
        ) {
            throw new RuminantSlaughterSaleException(
                'A completed slaughter batch has no new quantity available for sale.'
            );
        }

        if ($saleDate < (string)$output['slaughter_date']) {
            throw new RuminantSlaughterSaleException(
                'Sale date cannot be earlier than the slaughter date of a selected output lot.'
            );
        }

        $available =
            round(
                (float)$output['remaining_quantity']
                + (float)(
                    $currentByOutput[$outputId]
                    ?? 0
                ),
                2
            );

        if ($wanted > $available + 0.00001) {
            throw new RuminantSlaughterSaleException(
                'Selected slaughter output quantity exceeds the available lot balance.'
            );
        }

        if ($stockItemId === null) {
            $stockItemId =
                (int)$output['stock_item_id'];

            $cycleId =
                (int)$output['cycle_id'];

            $productionType =
                strtolower(
                    trim(
                        (string)$output['production_type']
                    )
                );

            $inventoryUnit =
                (string)$output['unit'];

            $itemName =
                (string)$output['item_name'];

        } elseif (
            $stockItemId !== (int)$output['stock_item_id']
            || $cycleId !== (int)$output['cycle_id']
            || $productionType
                !== strtolower(
                    trim(
                        (string)$output['production_type']
                    )
                )
            || strtolower(trim((string)$inventoryUnit))
                !== strtolower(trim((string)$output['unit']))
        ) {
            throw new RuminantSlaughterSaleException(
                'One sale line may consume multiple lots only when they are the same product, unit, production type and production cycle.'
            );
        }

        $quantity =
            round(
                $quantity
                + $wanted,
                2
            );

        $rows[$outputId] =
            array_merge(
                $output,
                [
                    'quantity' =>
                        $wanted,

                    'available_for_sale' =>
                        $available,
                ]
            );
    }

    return [
        'mode' => 'slaughter_output',
        'farm_type' => 'ruminant',
        'production_type' => $productionType,
        'cycle_id' => $cycleId,
        'stock_item_id' => $stockItemId,
        'product_type' => $itemName,
        'inventory_unit' => $inventoryUnit,
        'unit_of_measure' =>
            ruminant_slaughter_sale_sales_unit(
                (string)$inventoryUnit
            ),
        'quantity' => $quantity,
        'rows' => $rows,
    ];
}

function ruminant_slaughter_sale_semantic_rows(
    array $rows
): array {
    $semantic = [];

    foreach ($rows as $row) {
        $outputId =
            (int)(
                $row['output_id']
                ?? 0
            );

        $quantity =
            round(
                (float)(
                    $row['quantity']
                    ?? 0
                ),
                2
            );

        if ($outputId > 0 && $quantity > 0) {
            $semantic[$outputId] =
                number_format(
                    $quantity,
                    2,
                    '.',
                    ''
                );
        }
    }

    ksort(
        $semantic,
        SORT_NUMERIC
    );

    return $semantic;
}

function ruminant_slaughter_sale_refresh_batch_status(
    PDO $pdo,
    int $farmId,
    int $batchId
): string {
    $stmt =
        $pdo->prepare(
            "SELECT
                 COUNT(*) AS output_count,
                 COALESCE(
                     SUM(remaining_quantity),
                     0
                 ) AS remaining_quantity
             FROM ruminant_slaughter_outputs
             WHERE farm_id=?
               AND batch_id=?
             FOR UPDATE"
        );

    $stmt->execute([
        $farmId,
        $batchId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: [];

    $completed =
        (int)(
            $row['output_count']
            ?? 0
        ) > 0
        && (float)(
            $row['remaining_quantity']
            ?? 0
        ) <= 0.00001;

    $status =
        $completed
            ? 'completed'
            : 'open';

    $update =
        $pdo->prepare(
            "UPDATE ruminant_slaughter_batches
             SET status=?
             WHERE id=?
               AND farm_id=?"
        );

    $update->execute([
        $status,
        $batchId,
        $farmId,
    ]);

    return $status;
}

function ruminant_slaughter_sale_reverse_allocation(
    PDO $pdo,
    int $farmId,
    array $allocation,
    ?int $userId
): int {
    ruminant_slaughter_sale_require_transaction(
        $pdo
    );

    $allocationId =
        (int)$allocation['id'];

    $outputId =
        (int)$allocation['output_id'];

    $stockTransactionId =
        (int)$allocation['stock_transaction_id'];

    if (
        $allocationId <= 0
        || $outputId <= 0
        || $stockTransactionId <= 0
        || (int)$allocation['is_active'] !== 1
    ) {
        throw new RuminantSlaughterSaleException(
            'The slaughter sale allocation cannot be reversed from its current state.'
        );
    }

    $outputStmt =
        $pdo->prepare(
            "SELECT
                 o.*,
                 b.cycle_id,
                 b.batch_code,
                 pc.production_type,
                 si.item_name
             FROM ruminant_slaughter_outputs o
             INNER JOIN ruminant_slaughter_batches b
                 ON b.id=o.batch_id
                AND b.farm_id=o.farm_id
             INNER JOIN production_cycles pc
                 ON pc.id=b.cycle_id
                AND pc.farm_id=b.farm_id
             INNER JOIN stock_items si
                 ON si.id=o.stock_item_id
                AND si.farm_id=o.farm_id
             WHERE o.id=?
               AND o.farm_id=?
             LIMIT 1
             FOR UPDATE"
        );

    $outputStmt->execute([
        $outputId,
        $farmId,
    ]);

    $output =
        $outputStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$output) {
        throw new RuminantSlaughterSaleException(
            'The source slaughter output lot no longer exists.'
        );
    }

    $txStmt =
        $pdo->prepare(
            "SELECT *
             FROM stock_transactions
             WHERE id=?
               AND farm_id=?
             LIMIT 1
             FOR UPDATE"
        );

    $txStmt->execute([
        $stockTransactionId,
        $farmId,
    ]);

    $tx =
        $txStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (
        !$tx
        || (string)$tx['transaction_type'] !== 'used'
        || (string)$tx['source_type'] !== 'ruminant_slaughter_sale'
        || (int)$tx['source_id'] !== $allocationId
        || (int)$tx['is_reversed'] !== 0
        || !empty($tx['reversal_of_id'])
    ) {
        throw new RuminantSlaughterSaleException(
            'The slaughter sale stock movement is not in a reversible canonical state.'
        );
    }

    $quantity =
        round(
            (float)$allocation['quantity'],
            2
        );

    $restoredRemaining =
        round(
            (float)$output['remaining_quantity']
            + $quantity,
            2
        );

    if (
        $restoredRemaining
        > (float)$output['initial_quantity']
            + 0.00001
    ) {
        throw new RuminantSlaughterSaleException(
            'Reversing this sale would exceed the slaughter lot initial quantity.'
        );
    }

    $reversalId =
        stock_apply_movement(
            $pdo,
            $farmId,
            (int)$output['stock_item_id'],
            'received',
            $quantity,
            (string)$tx['transaction_date'],
            'Restore slaughter output after Sale lot correction',
            $userId,
            'ruminant',
            'general',
            (int)$output['cycle_id'],
            'ruminant_slaughter_sale_reversal',
            $allocationId,
            (float)$tx['unit_cost'],
            (string)$output['production_type'],
            (float)$tx['total_cost']
        );

    $pdo->prepare(
        "UPDATE stock_transactions
         SET is_reversed=1,
             reversal_of_id=?,
             reversed_at=NOW()
         WHERE id=?
           AND farm_id=?
           AND is_reversed=0
           AND reversal_of_id IS NULL"
    )->execute([
        $reversalId,
        $stockTransactionId,
        $farmId,
    ]);

    $pdo->prepare(
        "UPDATE stock_transactions
         SET reversal_of_id=?
         WHERE id=?
           AND farm_id=?"
    )->execute([
        $stockTransactionId,
        $reversalId,
        $farmId,
    ]);

    $pdo->prepare(
        "UPDATE ruminant_slaughter_outputs
         SET remaining_quantity=?
         WHERE id=?
           AND farm_id=?"
    )->execute([
        $restoredRemaining,
        $outputId,
        $farmId,
    ]);

    $pdo->prepare(
        "UPDATE ruminant_slaughter_sale_allocations
         SET is_active=0,
             reversal_stock_transaction_id=?,
             reversed_by=?,
             reversed_at=NOW()
         WHERE id=?
           AND farm_id=?
           AND is_active=1"
    )->execute([
        $reversalId,
        $userId && $userId > 0
            ? $userId
            : null,
        $allocationId,
        $farmId,
    ]);

    stock_recalculate_current_unit_cost(
        $pdo,
        $farmId,
        (int)$output['stock_item_id']
    );

    ruminant_slaughter_sale_refresh_batch_status(
        $pdo,
        $farmId,
        (int)$output['batch_id']
    );

    return $reversalId;
}

function ruminant_slaughter_sale_output_cost_so_far(
    PDO $pdo,
    int $farmId,
    int $outputId
): float {
    $stmt =
        $pdo->prepare(
            "SELECT
                 COALESCE(
                     SUM(total_cost_snapshot),
                     0
                 )
             FROM ruminant_slaughter_sale_allocations
             WHERE farm_id=?
               AND output_id=?
               AND is_active=1"
        );

    $stmt->execute([
        $farmId,
        $outputId,
    ]);

    return
        round(
            (float)$stmt->fetchColumn(),
            2
        );
}

function ruminant_slaughter_sale_apply_output(
    PDO $pdo,
    int $farmId,
    int $saleId,
    string $saleDate,
    array $output,
    float $quantity,
    ?int $userId
): int {
    ruminant_slaughter_sale_require_transaction(
        $pdo
    );

    $outputId =
        (int)$output['output_id'];

    $lockedStmt =
        $pdo->prepare(
            "SELECT
                 o.*,
                 b.batch_code,
                 b.slaughter_date,
                 b.cycle_id,
                 b.status AS batch_status,
                 pc.production_type,
                 si.item_name
             FROM ruminant_slaughter_outputs o
             INNER JOIN ruminant_slaughter_batches b
                 ON b.id=o.batch_id
                AND b.farm_id=o.farm_id
             INNER JOIN production_cycles pc
                 ON pc.id=b.cycle_id
                AND pc.farm_id=b.farm_id
             INNER JOIN stock_items si
                 ON si.id=o.stock_item_id
                AND si.farm_id=o.farm_id
             WHERE o.id=?
               AND o.farm_id=?
             LIMIT 1
             FOR UPDATE"
        );

    $lockedStmt->execute([
        $outputId,
        $farmId,
    ]);

    $locked =
        $lockedStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$locked) {
        throw new RuminantSlaughterSaleException(
            'The selected slaughter output lot could not be locked.'
        );
    }

    $quantity =
        round(
            $quantity,
            2
        );

    $remaining =
        round(
            (float)$locked['remaining_quantity'],
            2
        );

    if (
        $quantity <= 0
        || $quantity > $remaining + 0.00001
    ) {
        throw new RuminantSlaughterSaleException(
            'Slaughter output sale quantity exceeds the remaining lot balance.'
        );
    }

    if ($saleDate < (string)$locked['slaughter_date']) {
        throw new RuminantSlaughterSaleException(
            'Sale date cannot be earlier than the slaughter date.'
        );
    }

    $newRemaining =
        round(
            $remaining
            - $quantity,
            2
        );

    $unitCost =
        round(
            (float)$locked['unit_cost_snapshot'],
            4
        );

    $alreadyCost =
        ruminant_slaughter_sale_output_cost_so_far(
            $pdo,
            $farmId,
            $outputId
        );

    $allocatedCost =
        round(
            (float)$locked['allocated_cost'],
            2
        );

    $totalCost =
        $newRemaining <= 0.00001
            ? round(
                max(
                    0,
                    $allocatedCost
                    - $alreadyCost
                ),
                2
            )
            : round(
                $quantity
                * $unitCost,
                2
            );

    $insert =
        $pdo->prepare(
            "INSERT INTO ruminant_slaughter_sale_allocations
             (
                 farm_id,
                 sale_id,
                 output_id,
                 stock_transaction_id,
                 reversal_stock_transaction_id,
                 quantity,
                 unit,
                 unit_cost_snapshot,
                 total_cost_snapshot,
                 is_active,
                 created_by
             )
             VALUES (?,?,?,NULL,NULL,?,?,?,?,1,?)"
        );

    $insert->execute([
        $farmId,
        $saleId,
        $outputId,
        $quantity,
        (string)$locked['unit'],
        $unitCost,
        $totalCost,
        $userId && $userId > 0
            ? $userId
            : null,
    ]);

    $allocationId =
        (int)$pdo->lastInsertId();

    $transactionId =
        stock_apply_movement(
            $pdo,
            $farmId,
            (int)$locked['stock_item_id'],
            'used',
            $quantity,
            $saleDate,
            'Slaughter output consumed by Sale #'
                . $saleId
                . ' · '
                . (string)$locked['batch_code'],
            $userId,
            'ruminant',
            'general',
            (int)$locked['cycle_id'],
            'ruminant_slaughter_sale',
            $allocationId,
            null,
            (string)$locked['production_type'],
            null,
            $unitCost,
            $totalCost
        );

    $pdo->prepare(
        "UPDATE ruminant_slaughter_sale_allocations
         SET stock_transaction_id=?
         WHERE id=?
           AND farm_id=?"
    )->execute([
        $transactionId,
        $allocationId,
        $farmId,
    ]);

    $pdo->prepare(
        "UPDATE ruminant_slaughter_outputs
         SET remaining_quantity=?
         WHERE id=?
           AND farm_id=?"
    )->execute([
        $newRemaining,
        $outputId,
        $farmId,
    ]);

    ruminant_slaughter_sale_refresh_batch_status(
        $pdo,
        $farmId,
        (int)$locked['batch_id']
    );

    return $allocationId;
}

function ruminant_slaughter_sale_sync(
    PDO $pdo,
    int $farmId,
    int $saleId,
    array $selection,
    ?int $userId
): array {
    ruminant_slaughter_sale_require_transaction(
        $pdo
    );

    $saleStmt =
        $pdo->prepare(
            "SELECT
                 id,
                 sale_date,
                 farm_type,
                 production_type,
                 cycle_id,
                 product_type,
                 quantity,
                 unit_of_measure
             FROM sales_records
             WHERE id=?
               AND farm_id=?
             LIMIT 1
             FOR UPDATE"
        );

    $saleStmt->execute([
        $saleId,
        $farmId,
    ]);

    $sale =
        $saleStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$sale) {
        throw new RuminantSlaughterSaleException(
            'The sale could not be found for slaughter-output synchronization.'
        );
    }

    $current =
        ruminant_slaughter_sale_current_active_rows(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    $desiredRows =
        $selection['rows']
        ?? [];

    if (!$desiredRows) {
        if (!$current) {
            return [
                'status' => 'financial_only',
                'changed' => false,
                'allocation_count' => 0,
            ];
        }

    } else {
        $expectedQuantity =
            round(
                (float)(
                    $selection['quantity']
                    ?? 0
                ),
                2
            );

        if (
            (string)$sale['farm_type'] !== 'ruminant'
            || strtolower((string)$sale['production_type'])
                !== strtolower(
                    (string)$selection['production_type']
                )
            || (int)$sale['cycle_id']
                !== (int)$selection['cycle_id']
            || (string)$sale['product_type']
                !== (string)$selection['product_type']
            || strtolower(trim((string)$sale['unit_of_measure']))
                !== strtolower(trim((string)$selection['unit_of_measure']))
            || abs(
                (float)$sale['quantity']
                - $expectedQuantity
            ) > 0.00001
        ) {
            throw new RuminantSlaughterSaleException(
                'The financial sale does not match its explicit slaughter-output lot selection.'
            );
        }
    }

    $currentSemantic =
        ruminant_slaughter_sale_semantic_rows(
            $current
        );

    $desiredSemantic =
        ruminant_slaughter_sale_semantic_rows(
            $desiredRows
        );

    if ($currentSemantic === $desiredSemantic) {
        return [
            'status' =>
                $desiredSemantic
                    ? 'lot_consumption'
                    : 'financial_only',

            'changed' => false,
            'allocation_count' =>
                count($desiredSemantic),
        ];
    }

    foreach ($current as $allocation) {
        ruminant_slaughter_sale_reverse_allocation(
            $pdo,
            $farmId,
            $allocation,
            $userId
        );
    }

    $created = [];

    foreach ($desiredRows as $outputId => $output) {
        $created[] =
            ruminant_slaughter_sale_apply_output(
                $pdo,
                $farmId,
                $saleId,
                (string)$sale['sale_date'],
                $output,
                (float)$output['quantity'],
                $userId
            );
    }

    return [
        'status' =>
            $created
                ? 'lot_consumption'
                : 'financial_only',

        'changed' => true,
        'allocation_count' => count($created),
        'allocation_ids' => $created,
    ];
}

function ruminant_slaughter_sale_assert_deletable(
    PDO $pdo,
    int $farmId,
    int $saleId
): void {
    $stmt =
        $pdo->prepare(
            "SELECT id
             FROM ruminant_slaughter_sale_allocations
             WHERE farm_id=?
               AND sale_id=?
             LIMIT 1"
        );

    $stmt->execute([
        $farmId,
        $saleId,
    ]);

    if ($stmt->fetchColumn() !== false) {
        throw new RuminantSlaughterSaleException(
            'This sale has slaughter-output Inventory history and cannot be hard-deleted. Edit/correct the sale so its lot and stock history remain auditable.'
        );
    }
}
