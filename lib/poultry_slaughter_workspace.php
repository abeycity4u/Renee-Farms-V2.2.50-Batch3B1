<?php

/**
 * Read model for the Poultry Slaughter Processing workspace.
 *
 * Mutation authority remains exclusively in poultry_slaughter_service.php.
 * This file is deliberately SELECT-only.
 */

if (!function_exists('poultry_slaughter_workspace_cycles')) {
function poultry_slaughter_workspace_cycles(
    PDO $pdo,
    int $farmId
): array {
    $stmt = $pdo->prepare(
        "SELECT
             id,
             cycle_code,
             production_type,
             start_date,
             expected_end_date,
             status
         FROM production_cycles
         WHERE farm_id = ?
           AND farm_type = 'poultry'
           AND production_type IN ('layer','broiler')
           AND status = 'active'
         ORDER BY
             production_type,
             start_date DESC,
             id DESC"
    );

    $stmt->execute([
        $farmId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}
}


if (!function_exists('poultry_slaughter_workspace_output_items')) {
function poultry_slaughter_workspace_output_items(
    PDO $pdo,
    int $farmId
): array {
    $stmt = $pdo->prepare(
        "SELECT
             si.id,
             si.item_name,
             si.unit,
             si.current_stock,
             si.farm_type,
             ic.category_name,
             ic.inventory_role
         FROM stock_items si
         INNER JOIN inventory_categories ic
             ON ic.id = si.category_id
            AND ic.farm_id = si.farm_id
         WHERE si.farm_id = ?
           AND si.is_active = 1
           AND si.farm_type IN ('poultry','both')
           AND COALESCE(
                   NULLIF(ic.inventory_role,''),
                   'operational'
               ) = 'slaughter_output'
         ORDER BY
             si.item_name,
             si.id"
    );

    $stmt->execute([
        $farmId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}
}


if (!function_exists('poultry_slaughter_workspace_batches')) {
function poultry_slaughter_workspace_batches(
    PDO $pdo,
    int $farmId
): array {
    $stmt = $pdo->prepare(
        "SELECT
             b.id,
             b.cycle_id,
             b.batch_code,
             b.slaughter_date,
             b.bird_count,
             b.live_weight_total_kg,
             b.population_before,
             b.capital_basis_transferred,
             b.embedded_operating_basis_transferred,
             b.processing_operating_cost,
             b.full_cost_basis_amount,
             b.cost_basis_finalized_at,
             b.status,
             b.notes,
             b.created_at,

             pc.cycle_code,
             pc.production_type,

             (
                 SELECT COUNT(*)
                 FROM poultry_slaughter_batch_expenses be
                 WHERE be.farm_id = b.farm_id
                   AND be.batch_id = b.id
             ) AS processing_expense_count,

             (
                 SELECT COALESCE(
                     SUM(be.amount_snapshot),
                     0
                 )
                 FROM poultry_slaughter_batch_expenses be
                 WHERE be.farm_id = b.farm_id
                   AND be.batch_id = b.id
             ) AS processing_expense_snapshot_total,

             (
                 SELECT COUNT(*)
                 FROM poultry_slaughter_outputs po
                 WHERE po.farm_id = b.farm_id
                   AND po.batch_id = b.id
             ) AS output_count,

             (
                 SELECT COALESCE(
                     SUM(po.allocated_cost),
                     0
                 )
                 FROM poultry_slaughter_outputs po
                 WHERE po.farm_id = b.farm_id
                   AND po.batch_id = b.id
             ) AS output_allocated_cost_total,

             (
                 SELECT COALESCE(
                     SUM(po.cost_share_percent),
                     0
                 )
                 FROM poultry_slaughter_outputs po
                 WHERE po.farm_id = b.farm_id
                   AND po.batch_id = b.id
             ) AS output_cost_share_percent_total,

             (
                 SELECT COUNT(*)
                 FROM poultry_slaughter_sale_allocations psa
                 INNER JOIN poultry_slaughter_outputs pso
                     ON pso.id = psa.output_id
                    AND pso.farm_id = psa.farm_id
                 WHERE psa.farm_id = b.farm_id
                   AND pso.batch_id = b.id
                   AND psa.is_active = 1
             ) AS active_sale_allocation_count

         FROM poultry_slaughter_batches b
         INNER JOIN production_cycles pc
             ON pc.id = b.cycle_id
            AND pc.farm_id = b.farm_id

         WHERE b.farm_id = ?

         ORDER BY
             b.slaughter_date DESC,
             b.id DESC

         LIMIT 100"
    );

    $stmt->execute([
        $farmId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}
}


if (!function_exists('poultry_slaughter_workspace_outputs')) {
function poultry_slaughter_workspace_outputs(
    PDO $pdo,
    int $farmId,
    int $batchId
): array {
    if (
        $farmId < 1
        ||
        $batchId < 1
    ) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT
             po.id,
             po.batch_id,
             po.stock_item_id,
             po.stock_transaction_id,
             po.initial_quantity,
             po.remaining_quantity,
             po.unit,
             po.cost_share_percent,
             po.allocated_cost,
             po.unit_cost_snapshot,
             po.created_at,

             si.item_name

         FROM poultry_slaughter_outputs po
         INNER JOIN stock_items si
             ON si.id = po.stock_item_id
            AND si.farm_id = po.farm_id

         WHERE po.farm_id = ?
           AND po.batch_id = ?

         ORDER BY
             po.id"
    );

    $stmt->execute([
        $farmId,
        $batchId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}
}


if (!function_exists('poultry_slaughter_workspace_batch_from_rows')) {
function poultry_slaughter_workspace_batch_from_rows(
    array $rows,
    int $batchId
): ?array {
    foreach (
        $rows
        as $row
    ) {
        if (
            (int)(
                $row[
                    'id'
                ]
                ?? 0
            )
            ===
            $batchId
        ) {
            return $row;
        }
    }

    return null;
}
}
