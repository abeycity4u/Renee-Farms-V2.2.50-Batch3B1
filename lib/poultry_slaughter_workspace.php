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


/*
 * Read-only Shared Processing Cost candidates for one Poultry slaughter batch.
 *
 * Mutation authority remains in poultry_slaughter_service.php.
 *
 * This reader exposes only canonical Poultry Shared parent expenses that:
 *
 * - are allocated through the existing financial_allocations authority;
 * - allocate value to this batch's exact production cycle;
 * - share the physical slaughter date;
 * - use a processing-eligible category from the central category catalog; and
 * - still have unconsumed allocation available for Slaughter.
 *
 * One Shared parent may serve more than one slaughter batch, but the summed
 * non-reversed slaughter snapshots for a cycle are deducted here so the UI
 * displays only the remaining available allocation.
 */
if (!function_exists(
    'poultry_slaughter_workspace_shared_processing_expenses'
)) {
function poultry_slaughter_workspace_shared_processing_expenses(
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

    require_once __DIR__
        .
        '/expense_revision_service.php';

    require_once dirname(__DIR__)
        .
        '/includes/expense_category_catalog.php';

    $categoryOptions =
        expense_category_options(
            'slaughter_processing'
        );

    $categoryKeys =
        array_keys(
            $categoryOptions
        );

    if ($categoryKeys === []) {
        return [];
    }

    $categoryPlaceholders =
        implode(
            ',',
            array_fill(
                0,
                count($categoryKeys),
                '?'
            )
        );

    /*
     * The used-value subquery is cycle-specific.
     *
     * A Shared expense can legitimately allocate one amount to Layer and a
     * different amount to Broiler. Slaughter consumption therefore must be
     * deducted only against the allocation belonging to this batch's cycle.
     */
    $sql =
        "SELECT
             e.id AS expense_id,
             e.public_reference,
             e.expense_date,
             e.created_at AS expense_created_at,
             e.category,
             e.amount,
             e.unit,
             ROUND(
                 e.amount * e.unit,
                 2
             ) AS parent_gross_amount,

             e.expense_revision_no,
             e.expense_causal_fingerprint,

             b.cost_basis_snapshot_at,

             fa.id AS allocation_id,
             fa.cycle_id AS allocated_cycle_id,
             fa.allocated_amount,
             fa.allocation_percent,

             COALESCE(
                 used.linked_amount,
                 0
             ) AS linked_amount,

             ROUND(
                 fa.allocated_amount
                 -
                 COALESCE(
                     used.linked_amount,
                     0
                 ),
                 2
             ) AS remaining_amount

         FROM poultry_slaughter_batches b

         INNER JOIN financial_allocations fa
           ON fa.farm_id = b.farm_id
          AND fa.cycle_id = b.cycle_id

         INNER JOIN farm_expenses e
           ON e.id = fa.expense_id
          AND e.farm_id = fa.farm_id

         INNER JOIN farm_expense_revisions er
           ON er.farm_id = e.farm_id
          AND er.expense_id = e.id
          AND er.revision_no = e.expense_revision_no
          AND er.causal_fingerprint = e.expense_causal_fingerprint

         LEFT JOIN (
             SELECT
                 l.farm_id,
                 l.expense_id,
                 linked_batch.cycle_id,
                 SUM(
                     l.amount_snapshot
                 ) AS linked_amount

             FROM poultry_slaughter_batch_expenses l

             INNER JOIN poultry_slaughter_batches linked_batch
               ON linked_batch.id = l.batch_id
              AND linked_batch.farm_id = l.farm_id

             WHERE linked_batch.status <> 'reversed'

             GROUP BY
                 l.farm_id,
                 l.expense_id,
                 linked_batch.cycle_id
         ) used
           ON used.farm_id = e.farm_id
          AND used.expense_id = e.id
          AND used.cycle_id = b.cycle_id

         WHERE b.farm_id = ?
           AND b.id = ?
           AND b.status <> 'reversed'

           AND e.farm_type = 'poultry'
           AND e.production_type = 'shared'
           AND e.attribution_scope = 'farm'
           AND e.cycle_id IS NULL
           AND e.poultry_category IS NULL

           AND e.expense_date = b.slaughter_date

           AND e.category IN ({$categoryPlaceholders})

           AND fa.allocated_amount > 0

           AND (
               fa.allocated_amount
               -
               COALESCE(
                   used.linked_amount,
                   0
               )
           ) > 0

         ORDER BY
             e.expense_date ASC,
             e.id ASC";

    $params = [
        $farmId,
        $batchId,
    ];

    foreach ($categoryKeys as $categoryKey) {
        $params[] =
            $categoryKey;
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];

    /*
     * Historical anti-double-counting authority:
     *
     * current financial_allocations proves what is available now;
     * the immutable revision ledger proves whether that same cycle allocation
     * had already entered the batch's frozen profitability basis.
     *
     * Only candidates proven safe after the cost-basis snapshot are exposed.
     */
    $eligibleRows = [];

    foreach ($rows as $row) {
        $timing =
            expense_revision_service_cycle_allocation_timing(
                $pdo,
                $farmId,
                (int)$row[
                    'expense_id'
                ],
                (int)$row[
                    'allocated_cycle_id'
                ],
                (string)$row[
                    'expense_created_at'
                ],
                (string)$row[
                    'cost_basis_snapshot_at'
                ]
            );

        if (
            empty(
                $timing[
                    'eligible_as_post_snapshot_processing'
                ]
            )
        ) {
            continue;
        }

        /*
         * Presentation labels remain central too. SQL carries only stable
         * expense-category keys.
         */
        $row['category_label'] =
            expense_category_label(
                (string)$row[
                    'category'
                ]
            );

        $row['allocated_amount'] =
            round(
                (float)$row[
                    'allocated_amount'
                ],
                2
            );

        $row['linked_amount'] =
            round(
                (float)$row[
                    'linked_amount'
                ],
                2
            );

        $row['remaining_amount'] =
            round(
                (float)$row[
                    'remaining_amount'
                ],
                2
            );

        $row['parent_gross_amount'] =
            round(
                (float)$row[
                    'parent_gross_amount'
                ],
                2
            );

        $row[
            'historical_allocation_at_cost_basis_snapshot'
        ] =
            round(
                (float)(
                    $timing[
                        'allocated_amount'
                    ]
                    ?? 0
                ),
                2
            );

        $row[
            'processing_timing_reason'
        ] =
            (string)(
                $timing[
                    'timing_reason'
                ]
                ?? ''
            );

        $eligibleRows[] =
            $row;
    }

    return $eligibleRows;
}
}
