<?php
/**
 * Synchronise one daily record's selected feed consumption with inventory.
 * Caller must have an open PDO transaction. source_id is the daily record id.
 * All inventory mutations go through the canonical stock ledger service.
 */
require_once __DIR__ . '/stock_service.php';
require_once __DIR__ . '/stock_consumption_source_resolver.php';

function sync_daily_feed_usage(PDO $pdo, int $farmId, int $recordId, ?int $feedItemId, float $quantity, ?int $cycleId, string $transactionDate, string $farmType, string $feedCategory, string $sourceType): void
{
    $quantity = round($quantity, 2);
    $oldStmt = $pdo->prepare("SELECT * FROM stock_transactions
        WHERE farm_id = ? AND source_type = ? AND source_id = ?
          AND transaction_type = 'used' AND is_reversed = 0
        ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $oldStmt->execute([$farmId, $sourceType, $recordId]);
    $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

    if ($old
        && (int)$old['stock_item_id'] === (int)$feedItemId
        && abs((float)$old['quantity'] - $quantity) < 0.00001
        && (int)($old['cycle_id'] ?? 0) === (int)($cycleId ?? 0)
        && (string)$old['transaction_date'] === (string)$transactionDate) {
        return;
    }

    if ($old) {
        $feedChanged = (int)$old['stock_item_id'] !== (int)$feedItemId;
        stock_reverse_transaction(
            $pdo,
            $farmId,
            (int)$old['id'],
            'Restored from Daily Record edit' . ($feedChanged ? ' (feed item changed)' : ' (quantity/cycle/date changed)'),
            $_SESSION['user_id'] ?? null,
            $sourceType . '_reversal',
            $recordId
        );
    }

    if ($quantity <= 0 || !$feedItemId) {
        return;
    }

    stock_apply_movement(
        $pdo,
        $farmId,
        $feedItemId,
        'used',
        $quantity,
        $transactionDate,
        'Daily record feed consumption',
        $_SESSION['user_id'] ?? null,
        $farmType,
        $feedCategory,
        $cycleId,
        $sourceType,
        $recordId
    );
}

function delete_daily_feed_usage(PDO $pdo, int $farmId, int $recordId, string $sourceType): void
{
    /*
     * The linked Daily Record is the authoritative operational source.
     *
     * Lock source -> stock, matching the canonical allocation-persistence
     * lock order. This prevents deletion from taking stock first while a
     * concurrent allocation transaction owns the Daily Record source row.
     */
    $definition =
        stock_consumption_source_resolver_definition(
            $sourceType
        );

    if (
        empty($definition['supported'])
        ||
        empty($definition['allocatable'])
        ||
        ($definition['mode'] ?? '')
            !== 'linked_daily_record'
    ) {
        throw new RuntimeException(
            'Daily feed deletion requires a supported Daily Record source.'
        );
    }

    stock_consumption_source_resolver_resolve(
        $pdo,
        [
            'farm_id' =>
                $farmId,

            'source_type' =>
                $sourceType,

            'source_id' =>
                $recordId,
        ],
        true
    );

    $stmt = $pdo->prepare("SELECT * FROM stock_transactions
        WHERE farm_id = ? AND source_type = ? AND source_id = ?
          AND transaction_type = 'used' AND is_reversed = 0
        ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$farmId, $sourceType, $recordId]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tx) return;

    stock_reverse_transaction(
        $pdo,
        $farmId,
        (int)$tx['id'],
        'Restored from Daily Record deletion',
        $_SESSION['user_id'] ?? null,
        $sourceType . '_reversal',
        $recordId
    );
}
