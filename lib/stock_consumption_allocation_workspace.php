<?php

require_once __DIR__
    . '/stock_consumption_allocation_service.php';

require_once __DIR__
    . '/stock_consumption_source_resolver.php';

require_once __DIR__
    . '/stock_consumption_allocation_persistence.php';

/*
 * V3.0.1 Consumed Stock Allocation Workspace
 *
 * Reader / access adapter only.
 *
 * Authority remains:
 * - stock_consumption_source_resolver.php for source ownership;
 * - stock_consumption_allocation_service.php for allocation policy;
 * - stock_consumption_allocation_persistence.php for mutations and
 *   immutable allocation revision history.
 *
 * This adapter owns tenant-facing read composition only.
 * It must never mutate stock_transactions or allocation state.
 */

if (!function_exists(
    'stock_consumption_allocation_workspace_can_manage'
)) {
function stock_consumption_allocation_workspace_can_manage(): bool
{
    if (
        isPlatformOwner()
        ||
        hasRole('farm_admin')
    ) {
        return true;
    }

    return
        hasPermission(
            getUserType(),
            'update_stock'
        );
}
}

if (!function_exists(
    'stock_consumption_allocation_workspace_url'
)) {
function stock_consumption_allocation_workspace_url(
    int $stockTransactionId
): string {
    if ($stockTransactionId < 1) {
        throw new InvalidArgumentException(
            'Stock allocation source identity is invalid.'
        );
    }

    return
        rtrim(
            BASE_URL,
            '/'
        )
        . '/management/stock_consumption_allocation.php?'
        . http_build_query([
            'stock_transaction_id' =>
                $stockTransactionId,
        ]);
}
}

if (!function_exists(
    'stock_consumption_allocation_workspace_return_url'
)) {
function stock_consumption_allocation_workspace_return_url(
    int $stockItemId
): string {
    if ($stockItemId < 1) {
        return
            rtrim(
                BASE_URL,
                '/'
            )
            . '/inventory.php';
    }

    return
        rtrim(
            BASE_URL,
            '/'
        )
        . '/api/stock_history.php?'
        . http_build_query([
            'item_id' =>
                $stockItemId,
        ]);
}
}

if (!function_exists(
    'stock_consumption_allocation_workspace_eligibility'
)) {
function stock_consumption_allocation_workspace_eligibility(
    PDO $pdo,
    int $farmId,
    array $movement
): array {
    if ($farmId < 1) {
        throw new InvalidArgumentException(
            'Farm identity is invalid.'
        );
    }

    $movementFarmId =
        (int)(
            $movement['farm_id']
            ?? 0
        );

    if (
        $movementFarmId < 1
        ||
        $movementFarmId !== $farmId
    ) {
        return [
            'eligible' =>
                false,

            'reason' =>
                'The stock movement does not belong to this farm.',

            'parent' =>
                null,

            'resolution' =>
                null,
        ];
    }

    try {
        $resolution =
            stock_consumption_source_resolver_resolve(
                $pdo,
                $movement,
                false
            );

        stock_consumption_source_resolver_assert_cycle_consistency(
            $movement,
            $resolution
        );

        $parent =
            stock_consumption_allocation_service_parent_contract(
                $movement,
                $resolution[
                    'authoritative_source'
                ]
                ?? null
            );

        return [
            'eligible' =>
                true,

            'reason' =>
                null,

            'parent' =>
                $parent,

            'resolution' =>
                $resolution,
        ];

    } catch (
        InvalidArgumentException
        |
        RuntimeException
        $e
    ) {
        return [
            'eligible' =>
                false,

            'reason' =>
                trim(
                    $e->getMessage()
                ) !== ''
                    ? $e->getMessage()
                    : 'This stock movement is not eligible for consumed-stock allocation.',

            'parent' =>
                null,

            'resolution' =>
                null,
        ];
    }
}
}

if (!function_exists(
    'stock_consumption_allocation_workspace_action_state'
)) {
function stock_consumption_allocation_workspace_action_state(
    PDO $pdo,
    int $farmId,
    array $movement,
    bool $canManage
): array {
    if (!$canManage) {
        return [
            'visible' =>
                false,

            'eligible' =>
                false,

            'url' =>
                null,

            'reason' =>
                null,
        ];
    }

    $eligibility =
        stock_consumption_allocation_workspace_eligibility(
            $pdo,
            $farmId,
            $movement
        );

    if (!$eligibility['eligible']) {
        return [
            'visible' =>
                false,

            'eligible' =>
                false,

            'url' =>
                null,

            'reason' =>
                $eligibility['reason'],
        ];
    }

    $stockTransactionId =
        (int)(
            $movement['id']
            ?? 0
        );

    return [
        'visible' =>
            true,

        'eligible' =>
            true,

        'url' =>
            stock_consumption_allocation_workspace_url(
                $stockTransactionId
            ),

        'reason' =>
            null,
    ];
}
}

if (!function_exists(
    'stock_consumption_allocation_workspace_current_rows'
)) {
function stock_consumption_allocation_workspace_current_rows(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId
): array {
    $stmt =
        $pdo->prepare(
            "SELECT
                 id,
                 farm_id,
                 stock_transaction_id,
                 cycle_id,
                 allocated_amount,
                 allocation_percent,
                 notes,
                 allocation_revision_no,
                 created_by,
                 created_at,
                 updated_by,
                 updated_at
             FROM stock_consumption_allocations
             WHERE farm_id=?
               AND stock_transaction_id=?
             ORDER BY cycle_id,id"
        );

    $stmt->execute([
        $farmId,
        $stockTransactionId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}
}

if (!function_exists(
    'stock_consumption_allocation_workspace_latest_revision'
)) {
function stock_consumption_allocation_workspace_latest_revision(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId
): ?array {
    $stmt =
        $pdo->prepare(
            "SELECT *
             FROM stock_consumption_allocation_revisions
             WHERE farm_id=?
               AND stock_transaction_id=?
             ORDER BY revision_no DESC
             LIMIT 1"
        );

    $stmt->execute([
        $farmId,
        $stockTransactionId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $row
            ?: null;
}
}

if (!function_exists(
    'stock_consumption_allocation_workspace_snapshot'
)) {
function stock_consumption_allocation_workspace_snapshot(
    PDO $pdo,
    int $farmId,
    int $stockTransactionId
): array {
    if (
        $farmId < 1
        ||
        $stockTransactionId < 1
    ) {
        throw new InvalidArgumentException(
            'Stock allocation workspace identity is invalid.'
        );
    }

    /*
     * Canonical tenant-scoped stock movement reader.
     * No lock is taken for the display snapshot.
     * The mutation endpoint re-validates under canonical locks.
     */
    $movement =
        stock_consumption_allocation_persistence_movement(
            $pdo,
            $farmId,
            $stockTransactionId,
            false
        );

    $eligibility =
        stock_consumption_allocation_workspace_eligibility(
            $pdo,
            $farmId,
            $movement
        );

    if (!$eligibility['eligible']) {
        throw new RuntimeException(
            (string)$eligibility['reason']
        );
    }

    $parent =
        $eligibility['parent'];

    $resolution =
        $eligibility['resolution'];

    $stockItemId =
        (int)(
            $movement['stock_item_id']
            ?? 0
        );

    if ($stockItemId < 1) {
        throw new RuntimeException(
            'The stock movement has no inventory item.'
        );
    }

    $itemStmt =
        $pdo->prepare(
            "SELECT
                 id,
                 farm_id,
                 item_name,
                 unit,
                 farm_type,
                 feed_category,
                 financial_classification
             FROM stock_items
             WHERE farm_id=?
               AND id=?
             LIMIT 1"
        );

    $itemStmt->execute([
        $farmId,
        $stockItemId,
    ]);

    $item =
        $itemStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$item) {
        throw new RuntimeException(
            'The inventory item linked to this stock movement was not found.'
        );
    }

    $currentRows =
        stock_consumption_allocation_workspace_current_rows(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    $latestRevision =
        stock_consumption_allocation_workspace_latest_revision(
            $pdo,
            $farmId,
            $stockTransactionId
        );

    /*
     * Candidate discovery is intentionally broad.
     * Canonical target compatibility remains in the shared-cost service.
     *
     * Closed cycles remain visible for legitimate historical correction.
     */
    $cycleStmt =
        $pdo->prepare(
            "SELECT
                 id,
                 farm_id,
                 cycle_code,
                 farm_type,
                 production_type,
                 status,
                 start_date
             FROM production_cycles
             WHERE farm_id=?
             ORDER BY start_date DESC,id DESC"
        );

    $cycleStmt->execute([
        $farmId,
    ]);

    $candidateCycles =
        $cycleStmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    $eligibleCycles = [];

    foreach ($candidateCycles as $cycle) {
        try {
            stock_consumption_allocation_service_target_contract(
                $parent,
                $cycle
            );

            $eligibleCycles[] =
                $cycle;

        } catch (Throwable $ignored) {
            continue;
        }
    }

    $eligibleCycleMap = [];

    foreach ($eligibleCycles as $cycle) {
        $eligibleCycleMap[
            (int)$cycle['id']
        ] =
            $cycle;
    }

    $currentCycles = [];

    foreach ($currentRows as $row) {
        $cycleId =
            (int)(
                $row['cycle_id']
                ?? 0
            );

        if (
            $cycleId < 1
            ||
            !isset(
                $eligibleCycleMap[
                    $cycleId
                ]
            )
        ) {
            throw new RuntimeException(
                'Stock allocation current projection target is no longer compatible with its source.'
            );
        }

        $currentCycles[] =
            $eligibleCycleMap[
                $cycleId
            ];
    }

    /*
     * Reuse canonical provenance consistency checks before displaying current
     * allocation state. This prevents the UI from normalising around malformed
     * or out-of-band allocation rows.
     */
    $lockedShape = [
        'movement' =>
            $movement,

        'resolution' =>
            $resolution,

        'parent' =>
            $parent,
    ];

    stock_consumption_allocation_persistence_assert_current_consistency(
        $lockedShape,
        $currentRows,
        $latestRevision,
        $currentCycles
    );

    /*
     * Reader totals are derived by the same service used by the writer.
     */
    $summary =
        stock_consumption_allocation_service_validate_desired_rows(
            $movement,
            $eligibleCycles,
            $currentRows,
            $resolution[
                'authoritative_source'
            ]
            ?? null
        );

    $currentMap = [];

    foreach ($currentRows as $row) {
        $currentMap[
            (int)$row['cycle_id']
        ] =
            $row;
    }

    $cycleRows = [];

    foreach ($eligibleCycles as $cycle) {
        $cycleId =
            (int)$cycle['id'];

        $current =
            $currentMap[
                $cycleId
            ]
            ?? null;

        $cycleRows[] = [
            'id' =>
                $cycleId,

            'cycle_code' =>
                (string)(
                    $cycle['cycle_code']
                    ?? (
                        'Cycle '
                        . $cycleId
                    )
                ),

            'farm_type' =>
                strtolower(
                    trim(
                        (string)(
                            $cycle['farm_type']
                            ?? ''
                        )
                    )
                ),

            'production_type' =>
                strtolower(
                    trim(
                        (string)(
                            $cycle['production_type']
                            ?? ''
                        )
                    )
                ),

            'status' =>
                strtolower(
                    trim(
                        (string)(
                            $cycle['status']
                            ?? ''
                        )
                    )
                ),

            'start_date' =>
                $cycle['start_date']
                ?? null,

            'allocated_amount' =>
                $current
                    ? stock_consumption_allocation_service_money_string(
                        stock_consumption_allocation_service_money_cents(
                            $current[
                                'allocated_amount'
                            ]
                        )
                    )
                    : '0.00',

            'allocation_percent' =>
                $current
                    ? number_format(
                        (float)$current[
                            'allocation_percent'
                        ],
                        4,
                        '.',
                        ''
                    )
                    : '0.0000',

            'notes' =>
                $current['notes']
                ?? null,
        ];
    }

    return [
        'movement' =>
            $movement,

        'item' =>
            $item,

        'parent_contract' =>
            $parent,

        'source_resolution' =>
            $resolution,

        'allocation_rows' =>
            $summary['rows'],

        'cycles' =>
            $cycleRows,

        'parent_amount' =>
            $summary[
                'parent_amount'
            ],

        'allocated_amount' =>
            $summary[
                'allocated_amount'
            ],

        'remaining_amount' =>
            $summary[
                'remaining_amount'
            ],

        'fully_allocated' =>
            !empty(
                $summary[
                    'fully_allocated'
                ]
            ),

        'latest_revision' =>
            $latestRevision,
    ];
}
}
