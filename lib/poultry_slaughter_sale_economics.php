<?php

require_once __DIR__ . '/slaughter_output_sale_economics_common.php';
require_once __DIR__ . '/slaughter_output_sale_common.php';

/**
 * Canonical read-only economics for Poultry slaughter-output sales.
 *
 * Recognition boundary:
 *
 * full sold physical valuation
 *   = capital basis released
 *   + embedded operating cost already recognised elsewhere.
 *
 * Poultry capital basis is not otherwise consumed by canonical Profitability,
 * so its proportional sold share becomes P&L COGS.
 *
 * Embedded pre-slaughter operating cost and processing operating expenses have
 * already been recognised by their canonical operating sources. They remain
 * valuation disclosure only and are never deducted a second time here.
 */

if (!function_exists('poultry_slaughter_sale_economics_sales_unit')) {
function poultry_slaughter_sale_economics_sales_unit(
    string $inventoryUnit
): string {
    return
        slaughter_output_sale_common_sales_unit(
            $inventoryUnit,
            static function (
                string $message
            ): Throwable {
                return
                    new RuntimeException(
                        $message
                    );
            }
        );
}
}


if (!function_exists('poultry_slaughter_sale_economics_rows')) {
function poultry_slaughter_sale_economics_rows(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $farmType = 'all',
    ?string $productionType = null,
    ?int $cycleId = null
): array {
    if ($farmId < 1) {
        throw new InvalidArgumentException(
            'Poultry slaughter-sale COGS requires a valid farm.'
        );
    }

    foreach (
        [
            $startDate,
            $endDate,
        ]
        as $date
    ) {
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $date
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Poultry slaughter-sale COGS requires valid business dates.'
            );
        }
    }

    $farmType =
        strtolower(
            trim(
                $farmType
            )
        );

    if ($farmType === '') {
        $farmType = 'all';
    }

    if (
        !in_array(
            $farmType,
            [
                'all',
                'poultry',
                'ruminant',
                'general',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Poultry slaughter-sale COGS farm scope is invalid.'
        );
    }

    if (
        $farmType !== 'all'
        &&
        $farmType !== 'poultry'
    ) {
        return [];
    }

    $productionType =
        strtolower(
            trim(
                (string)$productionType
            )
        );

    if ($productionType === 'all') {
        $productionType = '';
    }

    $cycleId =
        (int)(
            $cycleId
            ?? 0
        );

    $sql =
        "SELECT
             a.id AS allocation_id,
             a.sale_id,
             a.output_id,
             a.quantity AS allocation_quantity,
             a.unit AS allocation_unit,
             a.unit_cost_snapshot,
             a.total_cost_snapshot,
             a.stock_transaction_id,

             s.sale_date,
             s.farm_type AS sale_farm_type,
             s.production_type AS sale_production_type,
             s.cycle_id AS sale_cycle_id,
             s.quantity AS sale_quantity,
             s.unit_of_measure AS sale_unit,

             o.stock_item_id,
             o.batch_id,
             o.allocated_cost AS output_allocated_cost,

             b.cycle_id AS batch_cycle_id,
             b.status AS batch_status,
             b.cost_basis_finalized_at,
             b.capital_basis_transferred,
             b.embedded_operating_basis_transferred,
             b.processing_operating_cost,
             b.full_cost_basis_amount,

             pc.farm_type AS cycle_farm_type,
             pc.production_type AS cycle_production_type,
             pc.cycle_code,

             t.id AS tx_id,
             t.stock_item_id AS tx_stock_item_id,
             t.transaction_type AS tx_type,
             t.quantity AS tx_quantity,
             t.unit_cost AS tx_unit_cost,
             t.total_cost AS tx_total_cost,
             t.farm_type AS tx_farm_type,
             t.production_type AS tx_production_type,
             t.cycle_id AS tx_cycle_id,
             t.source_type,
             t.source_id,
             t.is_reversed,
             t.reversal_of_id

         FROM poultry_slaughter_sale_allocations a

         INNER JOIN sales_records s
             ON s.id=a.sale_id
            AND s.farm_id=a.farm_id

         INNER JOIN poultry_slaughter_outputs o
             ON o.id=a.output_id
            AND o.farm_id=a.farm_id

         INNER JOIN poultry_slaughter_batches b
             ON b.id=o.batch_id
            AND b.farm_id=o.farm_id

         INNER JOIN production_cycles pc
             ON pc.id=b.cycle_id
            AND pc.farm_id=b.farm_id

         INNER JOIN stock_transactions t
             ON t.id=a.stock_transaction_id
            AND t.farm_id=a.farm_id

         WHERE a.farm_id=?
           AND a.is_active=1
           AND s.sale_date BETWEEN ? AND ?

         ORDER BY
             s.sale_date,
             a.sale_id,
             a.id";

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $startDate,
        $endDate,
    ]);

    $sourceRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    $validated = [];
    $saleQuantityTotals = [];
    $saleRecordedQuantities = [];
    $outputSoldCostTotals = [];
    $outputAllocatedCosts = [];

    foreach (
        $sourceRows
        as $row
    ) {
        $allocationId =
            (int)$row[
                'allocation_id'
            ];

        $saleId =
            (int)$row[
                'sale_id'
            ];

        $outputId =
            (int)$row[
                'output_id'
            ];

        $batchCycleId =
            (int)$row[
                'batch_cycle_id'
            ];

        $saleCycleId =
            (int)$row[
                'sale_cycle_id'
            ];

        $txCycleId =
            (int)$row[
                'tx_cycle_id'
            ];

        $cycleFarmType =
            strtolower(
                trim(
                    (string)$row[
                        'cycle_farm_type'
                    ]
                )
            );

        $saleFarmType =
            strtolower(
                trim(
                    (string)$row[
                        'sale_farm_type'
                    ]
                )
            );

        $txFarmType =
            strtolower(
                trim(
                    (string)$row[
                        'tx_farm_type'
                    ]
                )
            );

        if (
            $cycleFarmType !== 'poultry'
            ||
            $saleFarmType !== 'poultry'
            ||
            $txFarmType !== 'poultry'
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS farm provenance is inconsistent.'
            );
        }

        if (
            $batchCycleId < 1
            ||
            $saleCycleId !== $batchCycleId
            ||
            $txCycleId !== $batchCycleId
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS cycle provenance is inconsistent.'
            );
        }

        $cycleProductionType =
            strtolower(
                trim(
                    (string)$row[
                        'cycle_production_type'
                    ]
                )
            );

        $saleProductionType =
            strtolower(
                trim(
                    (string)$row[
                        'sale_production_type'
                    ]
                )
            );

        $txProductionType =
            strtolower(
                trim(
                    (string)$row[
                        'tx_production_type'
                    ]
                )
            );

        if (
            !in_array(
                $cycleProductionType,
                [
                    'layer',
                    'broiler',
                ],
                true
            )
            ||
            $saleProductionType !== $cycleProductionType
            ||
            $txProductionType !== $cycleProductionType
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS production provenance is inconsistent.'
            );
        }

        if (
            empty(
                $row[
                    'cost_basis_finalized_at'
                ]
            )
            ||
            (string)$row[
                'batch_status'
            ] === 'reversed'
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS requires an effective finalized slaughter batch.'
            );
        }

        if (
            (string)$row[
                'tx_type'
            ] !== 'used'
            ||
            (string)$row[
                'source_type'
            ] !== 'poultry_slaughter_sale'
            ||
            (int)$row[
                'source_id'
            ] !== $allocationId
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS stock provenance is invalid.'
            );
        }

        if (
            (int)$row[
                'is_reversed'
            ] !== 0
            ||
            $row[
                'reversal_of_id'
            ] !== null
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS points to a non-effective stock movement.'
            );
        }

        if (
            (int)$row[
                'tx_stock_item_id'
            ]
            !==
            (int)$row[
                'stock_item_id'
            ]
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS Inventory item provenance is inconsistent.'
            );
        }

        $allocationQuantity =
            round(
                (float)$row[
                    'allocation_quantity'
                ],
                4
            );

        $txQuantity =
            round(
                (float)$row[
                    'tx_quantity'
                ],
                4
            );

        if (
            $allocationQuantity <= 0
            ||
            abs(
                $allocationQuantity
                -
                $txQuantity
            ) > 0.00005
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS quantity provenance is inconsistent.'
            );
        }

        $allocationSalesUnit =
            strtolower(
                trim(
                    poultry_slaughter_sale_economics_sales_unit(
                        (string)$row[
                            'allocation_unit'
                        ]
                    )
                )
            );

        $saleUnit =
            strtolower(
                trim(
                    (string)$row[
                        'sale_unit'
                    ]
                )
            );

        if (
            $allocationSalesUnit === ''
            ||
            $saleUnit === ''
            ||
            $allocationSalesUnit !== $saleUnit
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS unit provenance is inconsistent.'
            );
        }

        $snapshotUnitCost =
            (float)$row[
                'unit_cost_snapshot'
            ];

        $txUnitCost =
            (float)$row[
                'tx_unit_cost'
            ];

        if (
            abs(
                $snapshotUnitCost
                -
                $txUnitCost
            ) > 0.00005
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS unit-cost provenance is inconsistent.'
            );
        }

        $snapshotCents =
            slaughter_output_sale_economics_money_cents(
                $row[
                    'total_cost_snapshot'
                ]
            );

        $txCents =
            slaughter_output_sale_economics_money_cents(
                $row[
                    'tx_total_cost'
                ]
            );

        if (
            $snapshotCents < 0
            ||
            $snapshotCents !== $txCents
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS monetary provenance is inconsistent.'
            );
        }

        $basisCapitalCents =
            slaughter_output_sale_economics_money_cents(
                $row[
                    'capital_basis_transferred'
                ]
            );

        $basisEmbeddedOperatingCents =
            slaughter_output_sale_economics_money_cents(
                $row[
                    'embedded_operating_basis_transferred'
                ]
            );

        $basisProcessingOperatingCents =
            slaughter_output_sale_economics_money_cents(
                $row[
                    'processing_operating_cost'
                ]
            );

        $basisFullCostCents =
            slaughter_output_sale_economics_money_cents(
                $row[
                    'full_cost_basis_amount'
                ]
            );

        if (
            $basisCapitalCents
            +
            $basisEmbeddedOperatingCents
            +
            $basisProcessingOperatingCents
            !==
            $basisFullCostCents
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale frozen cost basis does not conserve full cost.'
            );
        }

        /*
         * Only capital is new P&L COGS.
         *
         * Feed / operating economics and processing expenses were already
         * recognised by their canonical operating sources.
         */
        $recognizedCogsCents =
            slaughter_output_sale_economics_proportional_cents(
                $snapshotCents,
                $basisCapitalCents,
                $basisFullCostCents
            );

        $embeddedOperatingCents =
            $snapshotCents
            -
            $recognizedCogsCents;

        if ($embeddedOperatingCents < 0) {
            throw new RuntimeException(
                'Poultry slaughter-sale embedded operating cost became negative.'
            );
        }

        $row[
            'full_cost_valuation_cents'
        ] =
            $snapshotCents;

        $row[
            'full_cost_valuation'
        ] =
            round(
                $snapshotCents / 100,
                2
            );

        $row[
            'recognized_cogs_cents'
        ] =
            $recognizedCogsCents;

        $row[
            'recognized_cogs'
        ] =
            round(
                $recognizedCogsCents / 100,
                2
            );

        $row[
            'embedded_operating_cost_cents'
        ] =
            $embeddedOperatingCents;

        $row[
            'embedded_operating_cost'
        ] =
            round(
                $embeddedOperatingCents / 100,
                2
            );

        $saleQuantityTotals[
            $saleId
        ] =
            (
                $saleQuantityTotals[
                    $saleId
                ]
                ?? 0.0
            )
            +
            $allocationQuantity;

        $saleRecordedQuantities[
            $saleId
        ] =
            (float)$row[
                'sale_quantity'
            ];

        $outputSoldCostTotals[
            $outputId
        ] =
            (
                $outputSoldCostTotals[
                    $outputId
                ]
                ?? 0
            )
            +
            $snapshotCents;

        $outputAllocatedCosts[
            $outputId
        ] =
            slaughter_output_sale_economics_money_cents(
                $row[
                    'output_allocated_cost'
                ]
            );

        $validated[] =
            $row;
    }

    foreach (
        $saleQuantityTotals
        as $saleId => $allocatedQuantity
    ) {
        $recordedQuantity =
            round(
                (float)(
                    $saleRecordedQuantities[
                        $saleId
                    ]
                    ?? 0
                ),
                4
            );

        if (
            abs(
                round(
                    $allocatedQuantity,
                    4
                )
                -
                $recordedQuantity
            ) > 0.00005
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS active lot quantity does not conserve its sale.'
            );
        }
    }

    foreach (
        $outputSoldCostTotals
        as $outputId => $soldCents
    ) {
        $allocatedCents =
            (int)(
                $outputAllocatedCosts[
                    $outputId
                ]
                ?? -1
            );

        if (
            $allocatedCents < 0
            ||
            $soldCents > $allocatedCents
        ) {
            throw new RuntimeException(
                'Poultry slaughter-sale COGS exceeds the frozen source-output valuation.'
            );
        }
    }

    $selected = [];

    foreach (
        $validated
        as $row
    ) {
        if (
            $productionType !== ''
            &&
            strtolower(
                trim(
                    (string)$row[
                        'cycle_production_type'
                    ]
                )
            )
            !==
            $productionType
        ) {
            continue;
        }

        if (
            $cycleId > 0
            &&
            (int)$row[
                'batch_cycle_id'
            ]
            !==
            $cycleId
        ) {
            continue;
        }

        $selected[] =
            $row;
    }

    return $selected;
}
}


if (!function_exists('poultry_slaughter_sale_economics_summary')) {
function poultry_slaughter_sale_economics_summary(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $farmType = 'all',
    ?string $productionType = null,
    ?int $cycleId = null
): array {
    $rows =
        poultry_slaughter_sale_economics_rows(
            $pdo,
            $farmId,
            $startDate,
            $endDate,
            $farmType,
            $productionType,
            $cycleId
        );

    $recognizedCogsCents = 0;
    $fullCostCents = 0;
    $embeddedOperatingCents = 0;

    foreach (
        $rows
        as $row
    ) {
        $recognizedCogsCents +=
            (int)$row[
                'recognized_cogs_cents'
            ];

        $fullCostCents +=
            (int)$row[
                'full_cost_valuation_cents'
            ];

        $embeddedOperatingCents +=
            (int)$row[
                'embedded_operating_cost_cents'
            ];
    }

    if (
        $recognizedCogsCents
        +
        $embeddedOperatingCents
        !==
        $fullCostCents
    ) {
        throw new RuntimeException(
            'Poultry slaughter-sale valuation decomposition does not conserve full cost.'
        );
    }

    return [
        'slaughter_output_cogs' =>
            round(
                $recognizedCogsCents / 100,
                2
            ),

        'slaughter_output_full_cost_valuation' =>
            round(
                $fullCostCents / 100,
                2
            ),

        'slaughter_output_embedded_operating_cost' =>
            round(
                $embeddedOperatingCents / 100,
                2
            ),

        'rows' =>
            $rows,
    ];
}
}
