<?php

require_once __DIR__ . '/stock_service.php';
require_once __DIR__ . '/inventory_category_role.php';
require_once __DIR__ . '/slaughter_output_inventory.php';
require_once __DIR__ . '/ruminant_slaughter_costing.php';
require_once __DIR__ . '/ruminant_slaughter_expense_service.php';

/**
 * Ruminant slaughter processing.
 *
 * Lifecycle and inventory remain separate authorities:
 * - ruminant_animal_exit_events proves the animal left live population;
 * - this service creates the slaughter processing batch;
 * - stock_service.php remains the only writer of physical Inventory stock.
 *
 * Slaughter output rows retain source-specific balances so a later Sales
 * allocation can consume the exact carcass/output lot over several days
 * without touching live population again.
 */

function ruminant_slaughter_processing_batch_code(
    string $slaughterDate,
    int $exitEventId
): string {
    return 'SL-'
        . str_replace('-', '', $slaughterDate)
        . '-E'
        . $exitEventId;
}

function ruminant_slaughter_processing_eligible_exits(
    PDO $pdo,
    int $farmId
): array {
    $stmt = $pdo->prepare(
        "SELECT
             e.id AS exit_event_id,
             e.animal_id,
             e.exit_date,
             e.exit_outcome,
             a.tag_no,
             a.species,
             a.status,
             m.id AS membership_id,
             m.cycle_id,
             pc.cycle_code,
             pc.production_type
         FROM ruminant_animal_exit_events e
         INNER JOIN ruminant_animals a
             ON a.id=e.animal_id
            AND a.farm_id=e.farm_id
         INNER JOIN ruminant_animal_cycle_memberships m
             ON m.farm_id=e.farm_id
            AND m.animal_id=e.animal_id
            AND m.closed_by_exit_event_id=e.id
         INNER JOIN production_cycles pc
             ON pc.id=m.cycle_id
            AND pc.farm_id=m.farm_id
         LEFT JOIN ruminant_slaughter_batches b
             ON b.farm_id=e.farm_id
            AND b.exit_event_id=e.id
         WHERE e.farm_id=?
           AND e.exit_outcome='manual_slaughtered'
           AND e.resulting_status='slaughtered'
           AND a.status='slaughtered'
           AND pc.farm_type='ruminant'
           AND b.id IS NULL
         ORDER BY e.exit_date DESC,e.id DESC"
    );
    $stmt->execute([$farmId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ruminant_slaughter_processing_create_batch(
    PDO $pdo,
    int $farmId,
    int $exitEventId,
    ?string $notes,
    ?int $userId
): int {
    if ($farmId <= 0 || $exitEventId <= 0) {
        throw new InvalidArgumentException(
            'A valid farm and slaughter exit are required.'
        );
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $existingStmt = $pdo->prepare(
            'SELECT id
             FROM ruminant_slaughter_batches
             WHERE farm_id=?
               AND exit_event_id=?
             LIMIT 1
             FOR UPDATE'
        );
        $existingStmt->execute([$farmId, $exitEventId]);
        $existingId = $existingStmt->fetchColumn();

        if ($existingId !== false) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return (int)$existingId;
        }

        $exitStmt = $pdo->prepare(
            "SELECT
                 e.id,
                 e.animal_id,
                 e.exit_date,
                 e.exit_outcome,
                 e.resulting_status,
                 a.tag_no,
                 a.species,
                 a.status
             FROM ruminant_animal_exit_events e
             INNER JOIN ruminant_animals a
                 ON a.id=e.animal_id
                AND a.farm_id=e.farm_id
             WHERE e.id=?
               AND e.farm_id=?
             LIMIT 1
             FOR UPDATE"
        );
        $exitStmt->execute([$exitEventId, $farmId]);
        $exit = $exitStmt->fetch(PDO::FETCH_ASSOC);

        if (!$exit) {
            throw new RuntimeException(
                'The selected slaughter exit could not be found in this farm.'
            );
        }

        if (
            (string)$exit['exit_outcome'] !== 'manual_slaughtered'
            || (string)$exit['resulting_status'] !== 'slaughtered'
            || (string)$exit['status'] !== 'slaughtered'
        ) {
            throw new RuntimeException(
                'Only an animal already recorded as Slaughtered can start a slaughter processing batch.'
            );
        }

        $membershipStmt = $pdo->prepare(
            "SELECT
                 m.id,
                 m.cycle_id,
                 pc.cycle_code,
                 pc.farm_type,
                 pc.production_type
             FROM ruminant_animal_cycle_memberships m
             INNER JOIN production_cycles pc
                 ON pc.id=m.cycle_id
                AND pc.farm_id=m.farm_id
             WHERE m.farm_id=?
               AND m.animal_id=?
               AND m.closed_by_exit_event_id=?
             ORDER BY m.id
             FOR UPDATE"
        );
        $membershipStmt->execute([
            $farmId,
            (int)$exit['animal_id'],
            $exitEventId,
        ]);
        $memberships = $membershipStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($memberships) !== 1) {
            throw new RuntimeException(
                'Slaughter processing requires exactly one production-cycle membership closed by this slaughter exit.'
            );
        }

        $membership = $memberships[0];

        if ((string)$membership['farm_type'] !== 'ruminant') {
            throw new RuntimeException(
                'The slaughtered animal is not linked to a Ruminant production cycle.'
            );
        }

        $slaughterDate = (string)$exit['exit_date'];
        $batchCode = ruminant_slaughter_processing_batch_code(
            $slaughterDate,
            $exitEventId
        );

        $insert = $pdo->prepare(
            "INSERT INTO ruminant_slaughter_batches
             (
                 farm_id,
                 animal_id,
                 exit_event_id,
                 cycle_id,
                 batch_code,
                 slaughter_date,
                 status,
                 notes,
                 created_by
             )
             VALUES (?,?,?,?,?,?,'open',?,?)"
        );
        $insert->execute([
            $farmId,
            (int)$exit['animal_id'],
            $exitEventId,
            (int)$membership['cycle_id'],
            $batchCode,
            $slaughterDate,
            trim((string)$notes) !== '' ? trim((string)$notes) : null,
            $userId && $userId > 0 ? $userId : null,
        ]);

        $batchId = (int)$pdo->lastInsertId();

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $batchId;
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function ruminant_slaughter_processing_add_output(
    PDO $pdo,
    int $farmId,
    int $batchId,
    int $stockItemId,
    float $quantity,
    float $costSharePercent,
    ?int $userId
): int {
    $quantity = round($quantity, 2);
    $costSharePercent =
        round(
            $costSharePercent,
            4
        );

    if (
        $farmId <= 0
        || $batchId <= 0
        || $stockItemId <= 0
        || $quantity <= 0
        || !is_finite($quantity)
        || $costSharePercent <= 0
        || $costSharePercent > 100
        || !is_finite($costSharePercent)
    ) {
        throw new InvalidArgumentException(
            'Choose a valid slaughter batch, inventory item, quantity and cost share greater than zero.'
        );
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $batchStmt = $pdo->prepare(
            "SELECT
                 b.*,
                 a.tag_no,
                 a.species,
                 a.status AS animal_status,
                 e.exit_outcome,
                 e.resulting_status,
                 pc.production_type
             FROM ruminant_slaughter_batches b
             INNER JOIN ruminant_animals a
                 ON a.id=b.animal_id
                AND a.farm_id=b.farm_id
             INNER JOIN ruminant_animal_exit_events e
                 ON e.id=b.exit_event_id
                AND e.farm_id=b.farm_id
             INNER JOIN production_cycles pc
                 ON pc.id=b.cycle_id
                AND pc.farm_id=b.farm_id
             WHERE b.id=?
               AND b.farm_id=?
             LIMIT 1
             FOR UPDATE"
        );
        $batchStmt->execute([$batchId, $farmId]);
        $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            throw new RuntimeException(
                'The selected slaughter batch could not be found.'
            );
        }

        if ((string)$batch['status'] !== 'open') {
            throw new RuntimeException(
                'Completed slaughter batches cannot receive additional outputs.'
            );
        }

        if (
            (string)$batch['animal_status'] !== 'slaughtered'
            || (string)$batch['exit_outcome'] !== 'manual_slaughtered'
            || (string)$batch['resulting_status'] !== 'slaughtered'
        ) {
            throw new RuntimeException(
                'This slaughter batch is no longer linked to a valid Slaughtered lifecycle event.'
            );
        }

        /*
         * Freeze the animal's cost basis on the first output receipt.
         * Future changes to selling price never rewrite this snapshot.
         */
        if ($batch['cost_basis_amount'] === null) {
            $legacyOutputStmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM ruminant_slaughter_outputs
                 WHERE farm_id=?
                   AND batch_id=?'
            );
            $legacyOutputStmt->execute([
                $farmId,
                $batchId,
            ]);

            if ((int)$legacyOutputStmt->fetchColumn() > 0) {
                throw new RuntimeException(
                    'This batch already has an uncosted legacy output and requires costing review before another output can be received.'
                );
            }

            $basis =
                ruminant_slaughter_costing_as_of(
                    $pdo,
                    $farmId,
                    (int)$batch['animal_id'],
                    (string)$batch['slaughter_date']
                );

            $pdo->prepare(
                'UPDATE ruminant_slaughter_batches
                 SET
                     cost_basis_amount=?,
                     cost_basis_purchase=?,
                     cost_basis_direct_expense=?,
                     cost_basis_shared=?,
                     cost_basis_method=?,
                     cost_basis_snapshot_at=NOW()
                 WHERE id=?
                   AND farm_id=?'
            )->execute([
                (float)$basis['total_cost_basis'],
                (float)$basis['purchase_cost'],
                (float)$basis['direct_expense_cost'],
                (float)$basis['shared_cost'],
                (string)$basis['method'],
                $batchId,
                $farmId,
            ]);

            $batch['cost_basis_amount'] =
                (float)$basis['total_cost_basis'];
            $batch['cost_basis_purchase'] =
                (float)$basis['purchase_cost'];
            $batch['cost_basis_direct_expense'] =
                (float)$basis['direct_expense_cost'];
            $batch['cost_basis_shared'] =
                (float)$basis['shared_cost'];
        }

        $allocationStmt = $pdo->prepare(
            "SELECT
                 COALESCE(
                     SUM(cost_share_percent),
                     0
                 ) AS allocated_percent,
                 COALESCE(
                     SUM(allocated_cost),
                     0
                 ) AS allocated_cost
             FROM ruminant_slaughter_outputs
             WHERE farm_id=?
               AND batch_id=?"
        );
        $allocationStmt->execute([
            $farmId,
            $batchId,
        ]);
        $allocation =
            $allocationStmt->fetch(
                PDO::FETCH_ASSOC
            ) ?: [];

        $batchCost =
            round(
                (float)(
                    $batch['cost_basis_amount']
                    ?? 0
                ),
                2
            );

        $allocationQuote =
            slaughter_output_inventory_allocation(
                $batchCost,
                (float)(
                    $allocation['allocated_percent']
                    ?? 0
                ),
                (float)(
                    $allocation['allocated_cost']
                    ?? 0
                ),
                $quantity,
                $costSharePercent
            );

        $allocatedCost =
            (float)$allocationQuote[
                'allocated_cost'
            ];

        $unitCostSnapshot =
            (float)$allocationQuote[
                'unit_cost_snapshot'
            ];

        $item =
            slaughter_output_inventory_lock_item(
                $pdo,
                $farmId,
                $stockItemId,
                'ruminant'
            );

        $duplicateStmt = $pdo->prepare(
            'SELECT id
             FROM ruminant_slaughter_outputs
             WHERE farm_id=?
               AND batch_id=?
               AND stock_item_id=?
             LIMIT 1
             FOR UPDATE'
        );
        $duplicateStmt->execute([
            $farmId,
            $batchId,
            $stockItemId,
        ]);

        if ($duplicateStmt->fetchColumn() !== false) {
            throw new RuntimeException(
                'This Inventory item is already recorded for the slaughter batch. Use a different output item.'
            );
        }

        $insert = $pdo->prepare(
            "INSERT INTO ruminant_slaughter_outputs
             (
                 farm_id,
                 batch_id,
                 stock_item_id,
                 stock_transaction_id,
                 initial_quantity,
                 remaining_quantity,
                 unit,
                 cost_share_percent,
                 allocated_cost,
                 unit_cost_snapshot,
                 created_by
             )
             VALUES (?,?,?,NULL,?,?,?,?,?,?,?)"
        );
        $insert->execute([
            $farmId,
            $batchId,
            $stockItemId,
            $quantity,
            $quantity,
            (string)$item['unit'],
            $costSharePercent,
            $allocatedCost,
            $unitCostSnapshot,
            $userId && $userId > 0 ? $userId : null,
        ]);

        $outputId = (int)$pdo->lastInsertId();

        $transactionId =
            slaughter_output_inventory_receive(
                $pdo,
                $farmId,
                $stockItemId,
                $quantity,
                (string)$batch['slaughter_date'],
                'Slaughter output '
                    . (string)$batch['batch_code']
                    . ' · '
                    . (string)$batch['tag_no'],
                $userId,
                'ruminant',
                (int)$batch['cycle_id'],
                $outputId,
                $unitCostSnapshot,
                (string)$batch['production_type'],
                $allocatedCost
            );

        $pdo->prepare(
            'UPDATE ruminant_slaughter_outputs
             SET stock_transaction_id=?
             WHERE id=?
               AND farm_id=?'
        )->execute([
            $transactionId,
            $outputId,
            $farmId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $outputId;
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function ruminant_slaughter_processing_batches(
    PDO $pdo,
    int $farmId
): array {
    $stmt = $pdo->prepare(
        "SELECT
             b.*,
             a.tag_no,
             a.species,
             pc.cycle_code,
             pc.production_type
         FROM ruminant_slaughter_batches b
         INNER JOIN ruminant_animals a
             ON a.id=b.animal_id
            AND a.farm_id=b.farm_id
         INNER JOIN production_cycles pc
             ON pc.id=b.cycle_id
            AND pc.farm_id=b.farm_id
         WHERE b.farm_id=?
         ORDER BY b.slaughter_date DESC,b.id DESC"
    );
    $stmt->execute([$farmId]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$batches) {
        return [];
    }

    $batchIds = array_map(
        static fn(array $row): int => (int)$row['id'],
        $batches
    );

    $placeholders = implode(
        ',',
        array_fill(0, count($batchIds), '?')
    );

    $outputStmt = $pdo->prepare(
        "SELECT
             o.*,
             si.item_name,
             st.public_reference AS stock_reference
         FROM ruminant_slaughter_outputs o
         INNER JOIN stock_items si
             ON si.id=o.stock_item_id
            AND si.farm_id=o.farm_id
         LEFT JOIN stock_transactions st
             ON st.id=o.stock_transaction_id
            AND st.farm_id=o.farm_id
         WHERE o.farm_id=?
           AND o.batch_id IN ({$placeholders})
         ORDER BY o.batch_id,o.id"
    );
    $outputStmt->execute(
        array_merge([$farmId], $batchIds)
    );

    $outputMap = [];
    foreach ($outputStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $outputMap[(int)$row['batch_id']][] = $row;
    }

    $processingExpenseMap =
        ruminant_slaughter_processing_expenses_for_batches(
            $pdo,
            $farmId,
            $batchIds
        );

    foreach ($batches as &$batch) {
        $batch['outputs'] =
            $outputMap[(int)$batch['id']] ?? [];

        $processingExpenseEntry =
            $processingExpenseMap[
                (int)$batch['id']
            ]
            ?? [
                'rows' => [],
                'snapshot_total' => 0.0,
            ];

        $batch['processing_expenses'] =
            $processingExpenseEntry[
                'rows'
            ];

        $batch['processing_expense_count'] =
            count(
                $batch[
                    'processing_expenses'
                ]
            );

        $batch['processing_expense_snapshot_total'] =
            round(
                (float)$processingExpenseEntry[
                    'snapshot_total'
                ],
                2
            );

        $batch['allocated_cost_percent'] = 0.0;
        $batch['allocated_cost_amount'] = 0.0;

        foreach ($batch['outputs'] as $output) {
            $batch['allocated_cost_percent'] +=
                (float)(
                    $output['cost_share_percent']
                    ?? 0
                );

            $batch['allocated_cost_amount'] +=
                (float)(
                    $output['allocated_cost']
                    ?? 0
                );
        }

        $batch['allocated_cost_percent'] =
            round(
                (float)$batch['allocated_cost_percent'],
                4
            );

        $batch['allocated_cost_amount'] =
            round(
                (float)$batch['allocated_cost_amount'],
                2
            );

        $batch['unallocated_cost_percent'] =
            round(
                max(
                    0,
                    100
                    - (float)$batch['allocated_cost_percent']
                ),
                4
            );

        $batch['unallocated_cost_amount'] =
            $batch['cost_basis_amount'] === null
                ? null
                : round(
                    max(
                        0,
                        (float)$batch['cost_basis_amount']
                        - (float)$batch['allocated_cost_amount']
                    ),
                    2
                );
    }
    unset($batch);

    return $batches;
}
