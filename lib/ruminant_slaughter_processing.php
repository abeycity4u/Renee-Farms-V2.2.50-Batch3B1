<?php

require_once __DIR__ . '/stock_service.php';
require_once __DIR__ . '/inventory_category_role.php';

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
    ?int $userId
): int {
    $quantity = round($quantity, 2);

    if (
        $farmId <= 0
        || $batchId <= 0
        || $stockItemId <= 0
        || $quantity <= 0
        || !is_finite($quantity)
    ) {
        throw new InvalidArgumentException(
            'Choose a valid slaughter batch, inventory item and quantity greater than zero.'
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

        $itemStmt = $pdo->prepare(
            "SELECT
                 si.id,
                 si.item_name,
                 si.unit,
                 si.farm_type,
                 si.feed_category,
                 si.is_active
             FROM stock_items si
             INNER JOIN inventory_categories ic
                 ON ic.id=si.category_id
                AND ic.farm_id=si.farm_id
                AND ic.inventory_role=?
             WHERE si.id=?
               AND si.farm_id=?
             LIMIT 1
             FOR UPDATE"
        );
        $itemStmt->execute([
            inventory_category_slaughter_output_role(),
            $stockItemId,
            $farmId,
        ]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new RuntimeException(
                'The selected Inventory item could not be found.'
            );
        }

        if (
            (int)$item['is_active'] !== 1
            || !in_array(
                (string)$item['farm_type'],
                ['ruminant', 'both'],
                true
            )
            || (string)$item['feed_category'] !== 'general'
        ) {
            throw new RuntimeException(
                'Slaughter outputs require an active Ruminant/Shared non-feed Inventory item.'
            );
        }

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
                 created_by
             )
             VALUES (?,?,?,NULL,?,?,?,?)"
        );
        $insert->execute([
            $farmId,
            $batchId,
            $stockItemId,
            $quantity,
            $quantity,
            (string)$item['unit'],
            $userId && $userId > 0 ? $userId : null,
        ]);

        $outputId = (int)$pdo->lastInsertId();

        $transactionId = stock_apply_movement(
            $pdo,
            $farmId,
            $stockItemId,
            'received',
            $quantity,
            (string)$batch['slaughter_date'],
            'Slaughter output '
                . (string)$batch['batch_code']
                . ' · '
                . (string)$batch['tag_no'],
            $userId,
            'ruminant',
            'general',
            (int)$batch['cycle_id'],
            'ruminant_slaughter_output',
            $outputId,
            null,
            (string)$batch['production_type']
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

    foreach ($batches as &$batch) {
        $batch['outputs'] =
            $outputMap[(int)$batch['id']] ?? [];
    }
    unset($batch);

    return $batches;
}
