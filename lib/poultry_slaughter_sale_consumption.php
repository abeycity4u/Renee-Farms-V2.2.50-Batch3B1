<?php

require_once __DIR__ . '/inventory_category_role.php';
require_once __DIR__ . '/slaughter_output_sale_common.php';
require_once __DIR__ . '/slaughter_output_sale_stock.php';

/**
 * Poultry slaughter-output Sales lot consumption.
 *
 * Physical model:
 *
 *   live flock
 *      -> Poultry slaughter batch
 *      -> processed output Inventory lot
 *      -> financial Sale
 *
 * This service starts at the processed-output lot.
 *
 * It never:
 * - removes live Poultry population;
 * - infers a lot from product text;
 * - writes stock directly;
 * - creates the financial Sales row itself.
 *
 * The caller owns the transaction containing the Sales row plus this lot sync.
 */

if (!class_exists('PoultrySlaughterSaleException')) {
    class PoultrySlaughterSaleException extends RuntimeException
    {
    }
}


function poultry_slaughter_sale_exception_factory(): callable
{
    return
        static function (
            string $message
        ): Throwable {
            return
                new PoultrySlaughterSaleException(
                    $message
                );
        };
}


function poultry_slaughter_sale_require_transaction(
    PDO $pdo
): void {
    slaughter_output_sale_common_require_transaction(
        $pdo,
        poultry_slaughter_sale_exception_factory()
    );
}


function poultry_slaughter_sale_sales_unit(
    string $inventoryUnit
): string {
    return
        slaughter_output_sale_common_sales_unit(
            $inventoryUnit,
            poultry_slaughter_sale_exception_factory()
        );
}


function poultry_slaughter_sale_normalize_rows(
    array $rows
): array {
    return
        slaughter_output_sale_common_normalize_rows(
            $rows,
            poultry_slaughter_sale_exception_factory()
        );
}


function poultry_slaughter_sale_rows_from_post(
    array $input
): array {
    return
        slaughter_output_sale_common_rows_from_post(
            $input,
            poultry_slaughter_sale_exception_factory()
        );
}


function poultry_slaughter_sale_semantic_rows(
    array $rows
): array {
    return
        slaughter_output_sale_common_semantic_rows(
            $rows
        );
}


function poultry_slaughter_sale_current_active_rows(
    PDO $pdo,
    int $farmId,
    int $saleId,
    bool $forUpdate = false
): array {
    $sql =
        "SELECT
             a.*
         FROM poultry_slaughter_sale_allocations a
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


function poultry_slaughter_sale_history_for_sales(
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
                 o.stock_item_id,
                 o.initial_quantity,
                 o.remaining_quantity,
                 o.unit AS inventory_unit,
                 o.allocated_cost,
                 o.unit_cost_snapshot AS output_unit_cost_snapshot,
                 b.batch_code,
                 b.slaughter_date,
                 b.bird_count,
                 b.cycle_id,
                 b.status AS batch_status,
                 b.cost_basis_finalized_at,
                 pc.production_type,
                 pc.cycle_code,
                 si.item_name
             FROM poultry_slaughter_sale_allocations a
             INNER JOIN poultry_slaughter_outputs o
                 ON o.id=a.output_id
                AND o.farm_id=a.farm_id
             INNER JOIN poultry_slaughter_batches b
                 ON b.id=o.batch_id
                AND b.farm_id=o.farm_id
             INNER JOIN production_cycles pc
                 ON pc.id=b.cycle_id
                AND pc.farm_id=b.farm_id
             INNER JOIN stock_items si
                 ON si.id=o.stock_item_id
                AND si.farm_id=o.farm_id
             WHERE a.farm_id=?
               AND a.sale_id IN ({$placeholders})
             ORDER BY
                 a.sale_id,
                 a.is_active DESC,
                 a.id"
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
        ) ?: []
        as $row
    ) {
        $map[
            (int)$row['sale_id']
        ][] = $row;
    }

    return $map;
}


function poultry_slaughter_sale_available_lots(
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
                 b.bird_count,
                 b.cycle_id,
                 b.status AS batch_status,
                 b.cost_basis_finalized_at,
                 pc.production_type,
                 pc.cycle_code,
                 si.item_name
             FROM poultry_slaughter_outputs o
             INNER JOIN poultry_slaughter_batches b
                 ON b.id=o.batch_id
                AND b.farm_id=o.farm_id
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
               AND b.status<>'reversed'
               AND b.cost_basis_finalized_at IS NOT NULL
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
            poultry_slaughter_sale_sales_unit(
                (string)$row['unit']
            );
    }

    unset($row);

    return $rows;
}


function poultry_slaughter_sale_selection(
    PDO $pdo,
    int $farmId,
    string $saleDate,
    array $desiredRows,
    ?int $saleId = null
): array {
    $desired =
        poultry_slaughter_sale_normalize_rows(
            $desiredRows
        );

    if (!$desired) {
        return [
            'mode' =>
                'financial_only',

            'rows' =>
                [],
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
        throw new PoultrySlaughterSaleException(
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
                 b.bird_count,
                 b.cycle_id,
                 b.status AS batch_status,
                 b.cost_basis_finalized_at,
                 pc.production_type,
                 pc.cycle_code,
                 si.item_name,
                 si.is_active,
                 si.farm_type AS item_farm_type,
                 COALESCE(
                     NULLIF(ic.inventory_role,''),
                     'operational'
                 ) AS inventory_role
             FROM poultry_slaughter_outputs o
             INNER JOIN poultry_slaughter_batches b
                 ON b.id=o.batch_id
                AND b.farm_id=o.farm_id
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
             ORDER BY o.id"
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

    if (
        count($outputs)
        !==
        count($outputIds)
    ) {
        throw new PoultrySlaughterSaleException(
            'One or more selected Poultry slaughter output lots do not belong to this farm.'
        );
    }

    $currentByOutput = [];

    if (
        $saleId !== null
        &&
        $saleId > 0
    ) {
        foreach (
            poultry_slaughter_sale_current_active_rows(
                $pdo,
                $farmId,
                $saleId,
                false
            )
            as $row
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
                !==
                inventory_category_slaughter_output_role()
            ||
            (int)$output['is_active'] !== 1
            ||
            !in_array(
                strtolower(
                    (string)$output['item_farm_type']
                ),
                [
                    'poultry',
                    'both',
                ],
                true
            )
        ) {
            throw new PoultrySlaughterSaleException(
                'The selected lot is not an active Poultry Slaughter Output Inventory item.'
            );
        }

        if (
            empty(
                $output[
                    'cost_basis_finalized_at'
                ]
            )
        ) {
            throw new PoultrySlaughterSaleException(
                'The selected Poultry slaughter output does not have a finalized cost basis.'
            );
        }

        if (
            (string)$output['batch_status']
            === 'reversed'
        ) {
            throw new PoultrySlaughterSaleException(
                'A reversed Poultry slaughter batch cannot supply a processed-product sale.'
            );
        }

        if (
            (string)$output['batch_status']
            === 'completed'
            &&
            !isset(
                $currentByOutput[
                    $outputId
                ]
            )
        ) {
            throw new PoultrySlaughterSaleException(
                'A completed Poultry slaughter batch has no new quantity available for sale.'
            );
        }

        if (
            $saleDate
            <
            (string)$output[
                'slaughter_date'
            ]
        ) {
            throw new PoultrySlaughterSaleException(
                'Sale date cannot be earlier than the slaughter date of a selected Poultry output lot.'
            );
        }

        $available =
            round(
                (float)$output[
                    'remaining_quantity'
                ]
                +
                (float)(
                    $currentByOutput[
                        $outputId
                    ]
                    ?? 0
                ),
                2
            );

        if (
            $wanted
            >
            $available + 0.00001
        ) {
            throw new PoultrySlaughterSaleException(
                'Selected Poultry slaughter output quantity exceeds the available lot balance.'
            );
        }

        if ($stockItemId === null) {
            $stockItemId =
                (int)$output[
                    'stock_item_id'
                ];

            $cycleId =
                (int)$output[
                    'cycle_id'
                ];

            $productionType =
                strtolower(
                    trim(
                        (string)$output[
                            'production_type'
                        ]
                    )
                );

            $inventoryUnit =
                (string)$output[
                    'unit'
                ];

            $itemName =
                (string)$output[
                    'item_name'
                ];

        } elseif (
            $stockItemId
                !==
                (int)$output[
                    'stock_item_id'
                ]
            ||
            $cycleId
                !==
                (int)$output[
                    'cycle_id'
                ]
            ||
            $productionType
                !==
                strtolower(
                    trim(
                        (string)$output[
                            'production_type'
                        ]
                    )
                )
            ||
            strtolower(
                trim(
                    (string)$inventoryUnit
                )
            )
                !==
                strtolower(
                    trim(
                        (string)$output[
                            'unit'
                        ]
                    )
                )
        ) {
            throw new PoultrySlaughterSaleException(
                'One processed-product sale line may consume multiple lots only when they use the same item, unit, Poultry production type and production cycle.'
            );
        }

        $quantity =
            round(
                $quantity
                +
                $wanted,
                2
            );

        $rows[
            $outputId
        ] =
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
        'mode' =>
            'slaughter_output',

        'farm_type' =>
            'poultry',

        'production_type' =>
            $productionType,

        'cycle_id' =>
            $cycleId,

        'stock_item_id' =>
            $stockItemId,

        'product_type' =>
            $itemName,

        'inventory_unit' =>
            $inventoryUnit,

        'unit_of_measure' =>
            poultry_slaughter_sale_sales_unit(
                (string)$inventoryUnit
            ),

        'quantity' =>
            $quantity,

        'rows' =>
            $rows,
    ];
}


function poultry_slaughter_sale_refresh_batch_status(
    PDO $pdo,
    int $farmId,
    int $batchId
): string {
    poultry_slaughter_sale_require_transaction(
        $pdo
    );

    $batchStmt =
        $pdo->prepare(
            "SELECT
                 id,
                 status
             FROM poultry_slaughter_batches
             WHERE id=?
               AND farm_id=?
             LIMIT 1
             FOR UPDATE"
        );

    $batchStmt->execute([
        $batchId,
        $farmId,
    ]);

    $batch =
        $batchStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$batch) {
        throw new PoultrySlaughterSaleException(
            'The Poultry slaughter batch could not be found while refreshing its output status.'
        );
    }

    if (
        (string)$batch[
            'status'
        ]
        === 'reversed'
    ) {
        return 'reversed';
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 id,
                 remaining_quantity
             FROM poultry_slaughter_outputs
             WHERE farm_id=?
               AND batch_id=?
             ORDER BY id
             FOR UPDATE"
        );

    $stmt->execute([
        $farmId,
        $batchId,
    ]);

    $outputs =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    $remainingQuantity = 0.0;

    foreach ($outputs as $output) {
        $remainingQuantity +=
            (float)$output[
                'remaining_quantity'
            ];
    }

    $completed =
        count($outputs) > 0
        &&
        $remainingQuantity <= 0.00001;

    $status =
        $completed
            ? 'completed'
            : 'open';

    $update =
        $pdo->prepare(
            "UPDATE poultry_slaughter_batches
             SET status=?
             WHERE id=?
               AND farm_id=?
               AND status<>'reversed'"
        );

    $update->execute([
        $status,
        $batchId,
        $farmId,
    ]);

    return $status;
}


function poultry_slaughter_sale_output_cost_so_far(
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
             FROM poultry_slaughter_sale_allocations
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


function poultry_slaughter_sale_reverse_allocation(
    PDO $pdo,
    int $farmId,
    array $allocation,
    ?int $userId
): int {
    poultry_slaughter_sale_require_transaction(
        $pdo
    );

    $allocationId =
        (int)(
            $allocation[
                'id'
            ]
            ?? 0
        );

    $outputId =
        (int)(
            $allocation[
                'output_id'
            ]
            ?? 0
        );

    $stockTransactionId =
        (int)(
            $allocation[
                'stock_transaction_id'
            ]
            ?? 0
        );

    if (
        $allocationId <= 0
        ||
        $outputId <= 0
        ||
        $stockTransactionId <= 0
        ||
        (int)(
            $allocation[
                'is_active'
            ]
            ?? 0
        ) !== 1
    ) {
        throw new PoultrySlaughterSaleException(
            'The Poultry slaughter sale allocation cannot be reversed from its current state.'
        );
    }

    $outputStmt =
        $pdo->prepare(
            "SELECT
                 o.*,
                 b.batch_code,
                 b.cycle_id,
                 b.status AS batch_status,
                 pc.production_type,
                 si.item_name
             FROM poultry_slaughter_outputs o
             INNER JOIN poultry_slaughter_batches b
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
        throw new PoultrySlaughterSaleException(
            'The source Poultry slaughter output lot no longer exists.'
        );
    }

    if (
        (string)$output[
            'batch_status'
        ]
        === 'reversed'
    ) {
        throw new PoultrySlaughterSaleException(
            'A sale allocation from a reversed Poultry slaughter batch cannot be corrected through the ordinary sale workflow.'
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
        ||
        (string)$tx[
            'transaction_type'
        ] !== 'used'
        ||
        (string)$tx[
            'source_type'
        ] !== 'poultry_slaughter_sale'
        ||
        (int)$tx[
            'source_id'
        ] !== $allocationId
        ||
        (int)$tx[
            'is_reversed'
        ] !== 0
        ||
        !empty(
            $tx[
                'reversal_of_id'
            ]
        )
    ) {
        throw new PoultrySlaughterSaleException(
            'The Poultry slaughter sale stock movement is not in a reversible canonical state.'
        );
    }

    $quantity =
        round(
            (float)$allocation[
                'quantity'
            ],
            2
        );

    $restoredRemaining =
        round(
            (float)$output[
                'remaining_quantity'
            ]
            +
            $quantity,
            2
        );

    if (
        $restoredRemaining
        >
        (float)$output[
            'initial_quantity'
        ] + 0.00001
    ) {
        throw new PoultrySlaughterSaleException(
            'Reversing this sale would exceed the Poultry slaughter lot initial quantity.'
        );
    }

    $reversalId =
        slaughter_output_sale_stock_reverse(
            $pdo,
            $farmId,
            'poultry',
            $allocationId,
            $stockTransactionId,
            $userId
        );

    $outputUpdate =
        $pdo->prepare(
            "UPDATE poultry_slaughter_outputs
             SET remaining_quantity=?
             WHERE id=?
               AND farm_id=?"
        );

    $outputUpdate->execute([
        $restoredRemaining,
        $outputId,
        $farmId,
    ]);

    if (
        $outputUpdate->rowCount()
        !== 1
    ) {
        throw new PoultrySlaughterSaleException(
            'The Poultry slaughter output balance changed concurrently during sale correction.'
        );
    }

    $allocationUpdate =
        $pdo->prepare(
            "UPDATE poultry_slaughter_sale_allocations
             SET
                 is_active=0,
                 reversal_stock_transaction_id=?,
                 reversed_by=?,
                 reversed_at=NOW()
             WHERE id=?
               AND farm_id=?
               AND is_active=1"
        );

    $allocationUpdate->execute([
        $reversalId,
        $userId && $userId > 0
            ? $userId
            : null,
        $allocationId,
        $farmId,
    ]);

    if (
        $allocationUpdate->rowCount()
        !== 1
    ) {
        throw new PoultrySlaughterSaleException(
            'The Poultry slaughter sale allocation changed concurrently during correction.'
        );
    }

    poultry_slaughter_sale_refresh_batch_status(
        $pdo,
        $farmId,
        (int)$output[
            'batch_id'
        ]
    );

    return $reversalId;
}


function poultry_slaughter_sale_apply_output(
    PDO $pdo,
    int $farmId,
    int $saleId,
    string $saleDate,
    array $output,
    float $quantity,
    ?int $userId
): int {
    poultry_slaughter_sale_require_transaction(
        $pdo
    );

    $outputId =
        (int)(
            $output[
                'output_id'
            ]
            ?? 0
        );

    if (
        $outputId <= 0
        ||
        $saleId <= 0
    ) {
        throw new InvalidArgumentException(
            'Poultry slaughter-output sale requires valid Sale and output identities.'
        );
    }

    $lockedStmt =
        $pdo->prepare(
            "SELECT
                 o.*,
                 b.batch_code,
                 b.slaughter_date,
                 b.cycle_id,
                 b.status AS batch_status,
                 b.cost_basis_finalized_at,
                 pc.production_type,
                 si.item_name
             FROM poultry_slaughter_outputs o
             INNER JOIN poultry_slaughter_batches b
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
        throw new PoultrySlaughterSaleException(
            'The selected Poultry slaughter output lot could not be locked.'
        );
    }

    if (
        (string)$locked[
            'batch_status'
        ]
        !== 'open'
    ) {
        throw new PoultrySlaughterSaleException(
            'Only an open Poultry slaughter batch can supply a new processed-product sale allocation.'
        );
    }

    if (
        empty(
            $locked[
                'cost_basis_finalized_at'
            ]
        )
    ) {
        throw new PoultrySlaughterSaleException(
            'Finalize the Poultry slaughter batch cost basis before selling its processed output.'
        );
    }

    $quantity =
        round(
            $quantity,
            2
        );

    $remaining =
        round(
            (float)$locked[
                'remaining_quantity'
            ],
            2
        );

    if (
        $quantity <= 0
        ||
        !is_finite(
            $quantity
        )
        ||
        $quantity
            >
            $remaining + 0.00001
    ) {
        throw new PoultrySlaughterSaleException(
            'Poultry slaughter output sale quantity exceeds the remaining lot balance.'
        );
    }

    if (
        $saleDate
        <
        (string)$locked[
            'slaughter_date'
        ]
    ) {
        throw new PoultrySlaughterSaleException(
            'Sale date cannot be earlier than the Poultry slaughter date.'
        );
    }

    $newRemaining =
        round(
            $remaining
            -
            $quantity,
            2
        );

    $unitCost =
        round(
            (float)$locked[
                'unit_cost_snapshot'
            ],
            4
        );

    $alreadyCost =
        poultry_slaughter_sale_output_cost_so_far(
            $pdo,
            $farmId,
            $outputId
        );

    $allocatedCost =
        round(
            (float)$locked[
                'allocated_cost'
            ],
            2
        );

    /*
     * Preserve exact lot-value conservation.
     *
     * Final depletion absorbs any cent residual created by four-decimal
     * unit-cost representation.
     */
    $totalCost =
        $newRemaining <= 0.00001
            ? round(
                max(
                    0,
                    $allocatedCost
                    -
                    $alreadyCost
                ),
                2
            )
            : round(
                $quantity
                *
                $unitCost,
                2
            );

    $insert =
        $pdo->prepare(
            "INSERT INTO poultry_slaughter_sale_allocations
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
             VALUES
             (
                 ?, ?, ?, NULL, NULL,
                 ?, ?, ?, ?, 1, ?
             )"
        );

    $insert->execute([
        $farmId,
        $saleId,
        $outputId,
        $quantity,
        (string)$locked[
            'unit'
        ],
        $unitCost,
        $totalCost,
        $userId && $userId > 0
            ? $userId
            : null,
    ]);

    $allocationId =
        (int)$pdo->lastInsertId();

    $transactionId =
        slaughter_output_sale_stock_consume(
            $pdo,
            $farmId,
            'poultry',
            (int)$locked[
                'cycle_id'
            ],
            (string)$locked[
                'production_type'
            ],
            (int)$locked[
                'stock_item_id'
            ],
            $allocationId,
            $saleId,
            (string)$locked[
                'batch_code'
            ],
            $quantity,
            $saleDate,
            $unitCost,
            $totalCost,
            $userId
        );

    $allocationUpdate =
        $pdo->prepare(
            "UPDATE poultry_slaughter_sale_allocations
             SET stock_transaction_id=?
             WHERE id=?
               AND farm_id=?
               AND stock_transaction_id IS NULL
               AND is_active=1"
        );

    $allocationUpdate->execute([
        $transactionId,
        $allocationId,
        $farmId,
    ]);

    if (
        $allocationUpdate->rowCount()
        !== 1
    ) {
        throw new PoultrySlaughterSaleException(
            'Poultry slaughter sale stock provenance changed concurrently.'
        );
    }

    $outputUpdate =
        $pdo->prepare(
            "UPDATE poultry_slaughter_outputs
             SET remaining_quantity=?
             WHERE id=?
               AND farm_id=?"
        );

    $outputUpdate->execute([
        $newRemaining,
        $outputId,
        $farmId,
    ]);

    if (
        $outputUpdate->rowCount()
        !== 1
    ) {
        throw new PoultrySlaughterSaleException(
            'Poultry slaughter output balance changed concurrently.'
        );
    }

    poultry_slaughter_sale_refresh_batch_status(
        $pdo,
        $farmId,
        (int)$locked[
            'batch_id'
        ]
    );

    return $allocationId;
}


function poultry_slaughter_sale_sync(
    PDO $pdo,
    int $farmId,
    int $saleId,
    array $selection,
    ?int $userId
): array {
    poultry_slaughter_sale_require_transaction(
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
        throw new PoultrySlaughterSaleException(
            'The Sale could not be found for Poultry slaughter-output synchronization.'
        );
    }

    $current =
        poultry_slaughter_sale_current_active_rows(
            $pdo,
            $farmId,
            $saleId,
            true
        );

    $desiredRows =
        $selection[
            'rows'
        ]
        ?? [];

    if (!$desiredRows) {
        if (!$current) {
            return [
                'status' =>
                    'financial_only',

                'changed' =>
                    false,

                'allocation_count' =>
                    0,
            ];
        }

    } else {
        $expectedQuantity =
            round(
                (float)(
                    $selection[
                        'quantity'
                    ]
                    ?? 0
                ),
                2
            );

        if (
            strtolower(
                (string)$sale[
                    'farm_type'
                ]
            )
                !== 'poultry'
            ||
            strtolower(
                (string)$sale[
                    'production_type'
                ]
            )
                !==
                strtolower(
                    (string)$selection[
                        'production_type'
                    ]
                )
            ||
            (int)$sale[
                'cycle_id'
            ]
                !==
                (int)$selection[
                    'cycle_id'
                ]
            ||
            (string)$sale[
                'product_type'
            ]
                !==
                (string)$selection[
                    'product_type'
                ]
            ||
            strtolower(
                trim(
                    (string)$sale[
                        'unit_of_measure'
                    ]
                )
            )
                !==
                strtolower(
                    trim(
                        (string)$selection[
                            'unit_of_measure'
                        ]
                    )
                )
            ||
            abs(
                (float)$sale[
                    'quantity'
                ]
                -
                $expectedQuantity
            ) > 0.00001
        ) {
            throw new PoultrySlaughterSaleException(
                'The financial Sale does not match its explicit Poultry slaughter-output lot selection.'
            );
        }
    }

    $currentSemantic =
        poultry_slaughter_sale_semantic_rows(
            $current
        );

    $desiredSemantic =
        poultry_slaughter_sale_semantic_rows(
            $desiredRows
        );

    if (
        $currentSemantic
        ===
        $desiredSemantic
    ) {
        return [
            'status' =>
                $desiredSemantic
                    ? 'lot_consumption'
                    : 'financial_only',

            'changed' =>
                false,

            'allocation_count' =>
                count(
                    $desiredSemantic
                ),
        ];
    }

    /*
     * Corrections remain append-only:
     *
     * 1. reverse each old physical lot movement;
     * 2. close its old allocation row;
     * 3. append replacement allocations below.
     */
    foreach (
        $current
        as $allocation
    ) {
        poultry_slaughter_sale_reverse_allocation(
            $pdo,
            $farmId,
            $allocation,
            $userId
        );
    }

    $created = [];

    foreach (
        $desiredRows
        as $output
    ) {
        $created[] =
            poultry_slaughter_sale_apply_output(
                $pdo,
                $farmId,
                $saleId,
                (string)$sale[
                    'sale_date'
                ],
                $output,
                (float)$output[
                    'quantity'
                ],
                $userId
            );
    }

    return [
        'status' =>
            $created
                ? 'lot_consumption'
                : 'financial_only',

        'changed' =>
            true,

        'allocation_count' =>
            count(
                $created
            ),

        'allocation_ids' =>
            $created,
    ];
}


function poultry_slaughter_sale_assert_deletable(
    PDO $pdo,
    int $farmId,
    int $saleId
): void {
    $stmt =
        $pdo->prepare(
            "SELECT id
             FROM poultry_slaughter_sale_allocations
             WHERE farm_id=?
               AND sale_id=?
             LIMIT 1"
        );

    $stmt->execute([
        $farmId,
        $saleId,
    ]);

    if (
        $stmt->fetchColumn()
        !== false
    ) {
        throw new PoultrySlaughterSaleException(
            'This sale has Poultry slaughter-output Inventory history and cannot be hard-deleted. Edit/correct the sale so its lot and stock history remain auditable.'
        );
    }
}
