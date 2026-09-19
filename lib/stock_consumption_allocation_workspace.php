<?php

require_once __DIR__
    . '/stock_consumption_allocation_service.php';

require_once __DIR__
    . '/stock_consumption_source_resolver.php';

require_once __DIR__
    . '/stock_consumption_allocation_persistence.php';

require_once __DIR__
    . '/production_cycle_service.php';

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
        PDOException
        $e
    ) {
        /*
         * PDOException extends RuntimeException. Database failures must
         * escape this business-eligibility adapter so the outer API boundary
         * can replace database diagnostics with its safe generic message.
         */
        throw $e;

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
    'stock_consumption_allocation_workspace_source_state'
)) {
function stock_consumption_allocation_workspace_source_state(
    array $parent,
    array $resolution
): array {
    $status =
        strtoupper(
            trim(
                (string)(
                    $parent[
                        'source_attribution_status'
                    ]
                    ?? ''
                )
            )
        );

    $resolverMode =
        strtolower(
            trim(
                (string)(
                    $resolution['mode']
                    ?? ''
                )
            )
        );

    $label =
        'Source attribution verified';

    $message =
        'The canonical source-attribution contract has accepted this consumed-stock source.';

    if (
        $status === 'NO_AUTHORITATIVE_SOURCE'
        &&
        $resolverMode === 'movement_authority'
    ) {
        $label =
            'Movement-defined shared source';

        $message =
            'This stock movement owns its attribution. It passed the canonical shared-cost contract as a pooled source and may be explicitly assigned only to compatible production cycles.';

    } elseif (
        $status === 'SOURCE_HAS_NO_CYCLE'
    ) {
        $label =
            'Linked source has no cycle';

        $message =
            'The authoritative linked source does not identify a production cycle, so this eligible pooled cost may be explicitly assigned only to compatible production cycles.';

    } elseif (
        $status === 'SOURCE_ATTRIBUTION_MATCH'
    ) {
        $label =
            'Source attribution matches';

        $message =
            'The authoritative linked source and the stock movement identify the same production-cycle attribution.';
    }

    return [
        'status' =>
            $status,

        'resolver_mode' =>
            $resolverMode,

        'label' =>
            $label,

        'message' =>
            $message,
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

    $sourceState =
        stock_consumption_allocation_workspace_source_state(
            $parent,
            $resolution
        );

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
     * Candidate discovery is shared across allocation workspaces.
     * Each allocation service still owns target compatibility policy.
     */
    $candidateCycles =
        production_cycle_list_for_farm(
            $pdo,
            $farmId
        );

    $eligibleCycles = [];
    $incompatibleCycles = [];

    foreach ($candidateCycles as $cycle) {
        try {
            stock_consumption_allocation_service_target_contract(
                $parent,
                $cycle
            );

            $eligibleCycles[] =
                $cycle;

        } catch (Throwable $e) {
            $reason =
                trim(
                    (string)$e->getMessage()
                );

            $incompatibleCycles[] = [
                'id' =>
                    (int)(
                        $cycle['id']
                        ?? 0
                    ),

                'cycle_code' =>
                    (string)(
                        $cycle['cycle_code']
                        ?? (
                            'Cycle '
                            . (int)(
                                $cycle['id']
                                ?? 0
                            )
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

                'reason' =>
                    $reason !== ''
                        ? $reason
                        : 'This cycle is not compatible with the consumed-stock source.',
            ];
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

        'source_state' =>
            $sourceState,

        'allocation_rows' =>
            $summary['rows'],

        'cycles' =>
            $cycleRows,

        'incompatible_cycles' =>
            $incompatibleCycles,

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
