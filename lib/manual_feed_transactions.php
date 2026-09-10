<?php
require_once __DIR__ . '/stock_service.php';

/**
 * Canonical feed-ledger action classification.
 *
 * Corrections are deliberately origin-aware:
 * - manual feed usage: correct from Feed Record
 * - Inventory movement/receipt: correct from Inventory
 * - Daily Record movement: correct from Daily Record
 * - reversals/restorations: immutable audit evidence
 */
function manual_feed_transaction_action_state(array $transaction): string
{
    if (!empty($transaction['is_reversed'])) {
        return 'reversed';
    }

    if (!empty($transaction['reversal_of_id'])) {
        return 'restoration';
    }

    $sourceType = trim((string)($transaction['source_type'] ?? ''));
    $transactionType = strtolower(
        trim((string)($transaction['transaction_type'] ?? ''))
    );

    if (
        $sourceType !== '' &&
        str_starts_with($sourceType, 'daily_')
    ) {
        return 'daily_record';
    }

    if (
        in_array(
            $sourceType,
            ['inventory_api', 'inventory_manual'],
            true
        ) ||
        $transactionType === 'received'
    ) {
        return 'inventory';
    }

    if (
        $sourceType === 'manual_feed' &&
        $transactionType === 'used'
    ) {
        return 'manual_editable';
    }

    return 'readonly';
}

function manual_feed_transaction_origin_label(array $transaction): string
{
    $sourceType = trim((string)($transaction['source_type'] ?? ''));

    if (!empty($transaction['reversal_of_id'])) {
        return 'Ledger Correction';
    }

    if (
        $sourceType !== '' &&
        str_starts_with($sourceType, 'daily_')
    ) {
        return 'Daily Record';
    }

    if (
        in_array(
            $sourceType,
            ['inventory_api', 'inventory_manual'],
            true
        )
    ) {
        return 'Inventory';
    }

    if ($sourceType === 'manual_feed') {
        return 'Manual Feed';
    }

    if (strpos($sourceType, 'reversal') !== false) {
        return 'Ledger Correction';
    }

    return 'Stock Ledger';
}

/**
 * Create a manual feed transaction (feed-record pages). Usage is currently
 * the normal manual action; received movements are still supported for
 * future/admin use.
 */
function create_manual_feed_transaction(
    PDO $pdo,
    int $farmId,
    int $itemId,
    string $type,
    float $quantity,
    string $date,
    string $remarks,
    ?int $cycleId,
    string $farmType,
    string $feedCategory,
    int $userId
): int {
    if ($cycleId !== null) {
        $cycleSql = "SELECT id FROM production_cycles
            WHERE id = ? AND farm_id = ? AND status = 'active' AND farm_type = ?";
        $cycleParams = [$cycleId, $farmId, $farmType];
        if ($farmType === 'poultry') {
            $cycleSql .= " AND production_type = ?";
            $cycleParams[] = $feedCategory;
        }
        $cycleStmt = $pdo->prepare($cycleSql);
        $cycleStmt->execute($cycleParams);
        if (!$cycleStmt->fetchColumn()) {
            throw new RuntimeException('The selected production cycle is not valid for this feed.');
        }
    }

    return stock_apply_movement(
        $pdo,
        $farmId,
        $itemId,
        $type,
        $quantity,
        $date,
        $remarks,
        $userId,
        $farmType,
        $feedCategory,
        $cycleId,
        'manual_feed',
        null
    );
}

function delete_manual_feed_transaction(PDO $pdo, int $farmId, int $transactionId, int $userId, string $farmType): int
{
    $stmt = $pdo->prepare("SELECT * FROM stock_transactions WHERE id = ? AND farm_id = ? AND farm_type = ? FOR UPDATE");
    $stmt->execute([$transactionId, $farmId, $farmType]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tx) throw new RuntimeException('Transaction not found.');
    if (!empty($tx['source_type']) && str_starts_with((string)$tx['source_type'], 'daily_')) {
        throw new RuntimeException('This feed transaction is linked to a Daily Record. Edit or delete the Daily Record instead so inventory stays synchronized.');
    }
    if (($tx['source_type'] ?? '') !== 'manual_feed') {
        throw new RuntimeException('Only manual feed transactions can be deleted here.');
    }
    return stock_reverse_transaction(
        $pdo,
        $farmId,
        $transactionId,
        'Restored from manual feed transaction deletion',
        $userId,
        'manual_feed_reversal',
        $transactionId
    );
}

function edit_manual_feed_transaction(
    PDO $pdo,
    int $farmId,
    int $transactionId,
    int $newItemId,
    string $newType,
    float $newQuantity,
    string $newDate,
    string $newRemarks,
    ?int $newCycleId,
    string $farmType,
    string $feedCategory,
    int $userId
): array {
    $stmt = $pdo->prepare("SELECT * FROM stock_transactions WHERE id = ? AND farm_id = ? AND farm_type = ? FOR UPDATE");
    $stmt->execute([$transactionId, $farmId, $farmType]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) throw new RuntimeException('Transaction not found.');
    if (!empty($existing['source_type']) && str_starts_with((string)$existing['source_type'], 'daily_')) {
        throw new RuntimeException('This feed transaction is linked to a Daily Record. Edit or delete the Daily Record instead so inventory stays synchronized.');
    }
    if (($existing['source_type'] ?? '') !== 'manual_feed') {
        throw new RuntimeException('Only manual feed transactions can be edited here.');
    }
    if (!empty($existing['is_reversed'])) {
        throw new RuntimeException('This transaction has already been reversed and can no longer be edited.');
    }
    if ($newType !== 'used') {
        throw new RuntimeException('Received feed stock must be recorded from Inventory so the actual receipt price is captured for historical costing.');
    }

    if ($newCycleId !== null) {
        $cycleSql = "SELECT id FROM production_cycles WHERE id = ? AND farm_id = ? AND status = 'active' AND farm_type = ?";
        $params = [$newCycleId, $farmId, $farmType];
        if ($farmType === 'poultry') {
            $cycleSql .= " AND production_type = ?";
            $params[] = $feedCategory;
        }
        $cycleStmt = $pdo->prepare($cycleSql);
        $cycleStmt->execute($params);
        if (!$cycleStmt->fetchColumn()) throw new RuntimeException('The selected production cycle is not valid for this feed.');
    }

    stock_reverse_transaction(
        $pdo,
        $farmId,
        $transactionId,
        'Restored from manual feed transaction edit',
        $userId,
        'manual_feed_reversal',
        $transactionId
    );

    $newId = stock_apply_movement(
        $pdo,
        $farmId,
        $newItemId,
        $newType,
        $newQuantity,
        $newDate,
        $newRemarks,
        $userId,
        $farmType,
        $feedCategory,
        $newCycleId,
        'manual_feed',
        null
    );

    return ['old_id' => $transactionId, 'new_id' => $newId];
}
