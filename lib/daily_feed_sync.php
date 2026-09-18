<?php
/**
 * Synchronise one daily record's selected feed consumption with inventory.
 * Caller must have an open PDO transaction. source_id is the daily record id.
 * All inventory mutations go through the canonical stock ledger service.
 */
require_once __DIR__ . '/stock_service.php';
require_once __DIR__ . '/stock_consumption_source_resolver.php';

/**
 * The saved Daily Record is authoritative for cycle attribution.
 *
 * All Daily Record feed writers pass through this guard:
 * - Layer
 * - Broiler
 * - Ruminant
 *
 * Lock order is source Daily Record -> stock movement, matching the
 * consumed-stock allocation authority boundary.
 */
function daily_feed_sync_authoritative_cycle_guard(
    PDO $pdo,
    int $farmId,
    int $recordId,
    ?int $requestedCycleId,
    string $sourceType
): ?int {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Daily feed synchronization requires an active transaction.'
        );
    }

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
            'Daily feed synchronization requires a supported Daily Record source.'
        );
    }

    /*
     * FOR UPDATE makes the saved Daily Record the first lock in the
     * synchronization boundary. We do not trust a page parameter alone.
     */
    $resolution =
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

    $authoritativeCycleId =
        (int)(
            $resolution[
                'authoritative_source'
            ]['cycle_id']
            ?? 0
        );

    $requestedCycleId =
        (int)(
            $requestedCycleId
            ?? 0
        );

    if (
        $requestedCycleId
        !== $authoritativeCycleId
    ) {
        throw new RuntimeException(
            'The saved Daily Record cycle does not match the feed synchronization request. '
            . 'No stock movement was changed.'
        );
    }

    return $authoritativeCycleId > 0
        ? $authoritativeCycleId
        : null;
}

function sync_daily_feed_usage(PDO $pdo, int $farmId, int $recordId, ?int $feedItemId, float $quantity, ?int $cycleId, string $transactionDate, string $farmType, string $feedCategory, string $sourceType): void
{
    $quantity = round($quantity, 2);

    /*
     * Central safety boundary:
     * resolve and lock the authoritative Daily Record before reading or
     * reversing any stock movement. Layer, Broiler and Ruminant all use it.
     */
    $cycleId =
        daily_feed_sync_authoritative_cycle_guard(
            $pdo,
            $farmId,
            $recordId,
            $cycleId,
            $sourceType
        );
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
