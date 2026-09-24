<?php

require_once __DIR__ . '/slaughter_output_sale_economics_common.php';

/**
 * Canonical read-only economics for slaughter-output sales.
 *
 * This reader recognises COGS only from explicit, currently-active
 * ruminant_slaughter_sale_allocations whose canonical USED Inventory
 * movement still matches the frozen sale-lot cost snapshot.
 *
 * It does not mutate Inventory, population, lifecycle or financial rows.
 * It never infers a slaughter lot from product text.
 */

if (!function_exists(
    'ruminant_slaughter_sale_economics_money_cents'
)) {
function ruminant_slaughter_sale_economics_money_cents(
    $value
): int {
    return
        slaughter_output_sale_economics_money_cents(
            $value
        );
}
}

if (!function_exists(
    'ruminant_slaughter_sale_economics_proportional_cents'
)) {
function ruminant_slaughter_sale_economics_proportional_cents(
    int $amountCents,
    int $componentCents,
    int $totalBasisCents
): int {
    return
        slaughter_output_sale_economics_proportional_cents(
            $amountCents,
            $componentCents,
            $totalBasisCents
        );
}
}

if (!function_exists(
    'ruminant_slaughter_sale_economics_rows'
)) {
function ruminant_slaughter_sale_economics_rows(
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
            'Slaughter-sale COGS requires a valid farm.'
        );
    }

    foreach ([$startDate, $endDate] as $date) {
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $date
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Slaughter-sale COGS requires valid business dates.'
            );
        }
    }

    $farmType = strtolower(
        trim($farmType)
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
            'Slaughter-sale COGS farm scope is invalid.'
        );
    }

    /*
     * Slaughter outputs belong only to Ruminant economics.
     * Do not reinterpret them into another module.
     */
    if (
        $farmType !== 'all'
        &&
        $farmType !== 'ruminant'
    ) {
        return [];
    }

    $productionType = strtolower(
        trim(
            (string)$productionType
        )
    );

    if ($productionType === 'all') {
        $productionType = '';
    }

    $cycleId = (int)($cycleId ?? 0);

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

             b.cycle_id AS batch_cycle_id,
             b.cost_basis_amount AS batch_cost_basis_amount,
             b.cost_basis_purchase AS batch_cost_basis_purchase,
             b.cost_basis_direct_expense AS batch_cost_basis_direct_expense,
             b.cost_basis_shared AS batch_cost_basis_shared,

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

         FROM ruminant_slaughter_sale_allocations a

         INNER JOIN sales_records s
             ON s.id=a.sale_id
            AND s.farm_id=a.farm_id

         INNER JOIN ruminant_slaughter_outputs o
             ON o.id=a.output_id
            AND o.farm_id=a.farm_id

         INNER JOIN ruminant_slaughter_batches b
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

    $stmt = $pdo->prepare($sql);

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

    foreach ($sourceRows as $row) {
        $allocationId =
            (int)$row['allocation_id'];

        $saleId =
            (int)$row['sale_id'];

        $batchCycleId =
            (int)$row['batch_cycle_id'];

        $saleCycleId =
            (int)$row['sale_cycle_id'];

        $txCycleId =
            (int)$row['tx_cycle_id'];

        $cycleFarmType =
            strtolower(
                trim(
                    (string)$row['cycle_farm_type']
                )
            );

        $cycleProductionType =
            strtolower(
                trim(
                    (string)$row['cycle_production_type']
                )
            );

        $saleFarmType =
            strtolower(
                trim(
                    (string)$row['sale_farm_type']
                )
            );

        $saleProductionType =
            strtolower(
                trim(
                    (string)$row['sale_production_type']
                )
            );

        $txFarmType =
            strtolower(
                trim(
                    (string)$row['tx_farm_type']
                )
            );

        $txProductionType =
            strtolower(
                trim(
                    (string)$row['tx_production_type']
                )
            );

        /*
         * Fail closed if persisted provenance no longer agrees.
         */
        if (
            $cycleFarmType !== 'ruminant'
            ||
            $saleFarmType !== 'ruminant'
            ||
            $txFarmType !== 'ruminant'
        ) {
            throw new RuntimeException(
                'Slaughter-sale COGS farm provenance is inconsistent.'
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
                'Slaughter-sale COGS cycle provenance is inconsistent.'
            );
        }

        if (
            $cycleProductionType === ''
            ||
            $saleProductionType !== $cycleProductionType
            ||
            $txProductionType !== $cycleProductionType
        ) {
            throw new RuntimeException(
                'Slaughter-sale COGS production provenance is inconsistent.'
            );
        }

        if (
            (string)$row['tx_type'] !== 'used'
            ||
            (string)$row['source_type'] !== 'ruminant_slaughter_sale'
            ||
            (int)$row['source_id'] !== $allocationId
        ) {
            throw new RuntimeException(
                'Slaughter-sale COGS stock provenance is invalid.'
            );
        }

        if (
            (int)$row['is_reversed'] !== 0
            ||
            $row['reversal_of_id'] !== null
        ) {
            throw new RuntimeException(
                'Slaughter-sale COGS points to a non-effective stock movement.'
            );
        }

        if (
            (int)$row['tx_stock_item_id']
            !==
            (int)$row['stock_item_id']
        ) {
            throw new RuntimeException(
                'Slaughter-sale COGS Inventory item provenance is inconsistent.'
            );
        }

        $allocationQuantity =
            round(
                (float)$row['allocation_quantity'],
                4
            );

        $txQuantity =
            round(
                (float)$row['tx_quantity'],
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
                'Slaughter-sale COGS quantity provenance is inconsistent.'
            );
        }

        $allocationUnit =
            strtolower(
                trim(
                    (string)$row['allocation_unit']
                )
            );

        $saleUnit =
            strtolower(
                trim(
                    (string)$row['sale_unit']
                )
            );

        if (
            $allocationUnit === ''
            ||
            $saleUnit === ''
            ||
            $allocationUnit !== $saleUnit
        ) {
            throw new RuntimeException(
                'Slaughter-sale COGS unit provenance is inconsistent.'
            );
        }

        $snapshotUnitCost =
            (float)$row['unit_cost_snapshot'];

        $txUnitCost =
            (float)$row['tx_unit_cost'];

        if (
            abs(
                $snapshotUnitCost
                -
                $txUnitCost
            ) > 0.00005
        ) {
            throw new RuntimeException(
                'Slaughter-sale COGS unit-cost provenance is inconsistent.'
            );
        }

        $snapshotCents =
            ruminant_slaughter_sale_economics_money_cents(
                $row['total_cost_snapshot']
            );

        $txCents =
            ruminant_slaughter_sale_economics_money_cents(
                $row['tx_total_cost']
            );

        if (
            $snapshotCents < 0
            ||
            $snapshotCents !== $txCents
        ) {
            throw new RuntimeException(
                'Slaughter-sale COGS monetary provenance is inconsistent.'
            );
        }

        /*
         * Two economic truths are deliberately kept separate:
         *
         * 1. total_cost_snapshot is the immutable FULL-COST valuation
         *    carried by the physical slaughter-output lot.
         *
         * 2. Profit/Loss COGS releases only the frozen purchase/capital
         *    portion. Direct/shared operating components already originate
         *    from canonical expense / consumed-stock economics and must not
         *    be charged to Profit/Loss a second time.
         */
        $basisTotalCents =
            ruminant_slaughter_sale_economics_money_cents(
                $row['batch_cost_basis_amount']
            );

        $basisPurchaseCents =
            ruminant_slaughter_sale_economics_money_cents(
                $row['batch_cost_basis_purchase']
            );

        $basisDirectCents =
            ruminant_slaughter_sale_economics_money_cents(
                $row['batch_cost_basis_direct_expense']
            );

        $basisSharedCents =
            ruminant_slaughter_sale_economics_money_cents(
                $row['batch_cost_basis_shared']
            );

        if (
            $basisPurchaseCents
            +
            $basisDirectCents
            +
            $basisSharedCents
            !==
            $basisTotalCents
        ) {
            throw new RuntimeException(
                'Slaughter-sale frozen cost-basis components do not conserve the batch total.'
            );
        }

        $recognizedCogsCents =
            ruminant_slaughter_sale_economics_proportional_cents(
                $snapshotCents,
                $basisPurchaseCents,
                $basisTotalCents
            );

        $embeddedOperatingCents =
            $snapshotCents
            -
            $recognizedCogsCents;

        if ($embeddedOperatingCents < 0) {
            throw new RuntimeException(
                'Slaughter-sale embedded operating cost became negative.'
            );
        }

        $row['full_cost_valuation_cents'] =
            $snapshotCents;

        $row['full_cost_valuation'] =
            round(
                $snapshotCents / 100,
                2
            );

        $row['recognized_cogs_cents'] =
            $recognizedCogsCents;

        $row['recognized_cogs'] =
            round(
                $recognizedCogsCents / 100,
                2
            );

        $row['embedded_operating_cost_cents'] =
            $embeddedOperatingCents;

        $row['embedded_operating_cost'] =
            round(
                $embeddedOperatingCents / 100,
                2
            );

        $saleQuantityTotals[$saleId] =
            (
                $saleQuantityTotals[$saleId]
                ?? 0.0
            )
            +
            $allocationQuantity;

        $saleRecordedQuantities[$saleId] =
            (float)$row['sale_quantity'];

        $validated[] = $row;
    }

    /*
     * Active physical lots must still conserve the current financial
     * sale quantity. Historical inactive rows are audit history only.
     */
    foreach (
        $saleQuantityTotals
        as $saleId => $allocatedQuantity
    ) {
        $recordedQuantity =
            round(
                (float)(
                    $saleRecordedQuantities[$saleId]
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
                'Slaughter-sale COGS active lot quantity does not conserve its sale.'
            );
        }
    }

    $selected = [];

    foreach ($validated as $row) {
        if (
            $productionType !== ''
            &&
            strtolower(
                trim(
                    (string)$row['cycle_production_type']
                )
            ) !== $productionType
        ) {
            continue;
        }

        if (
            $cycleId > 0
            &&
            (int)$row['batch_cycle_id']
                !== $cycleId
        ) {
            continue;
        }

        $selected[] = $row;
    }

    return $selected;
}
}

if (!function_exists(
    'ruminant_slaughter_sale_economics_summary'
)) {
function ruminant_slaughter_sale_economics_summary(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    string $farmType = 'all',
    ?string $productionType = null,
    ?int $cycleId = null
): array {
    $rows =
        ruminant_slaughter_sale_economics_rows(
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

    foreach ($rows as $row) {
        $recognizedCogsCents +=
            (int)$row['recognized_cogs_cents'];

        $fullCostCents +=
            (int)$row['full_cost_valuation_cents'];

        $embeddedOperatingCents +=
            (int)$row['embedded_operating_cost_cents'];
    }

    if (
        $recognizedCogsCents
        +
        $embeddedOperatingCents
        !==
        $fullCostCents
    ) {
        throw new RuntimeException(
            'Slaughter-sale valuation decomposition does not conserve full cost.'
        );
    }

    return [
        /*
         * P&L-recognisable COGS only.
         */
        'slaughter_output_cogs' =>
            round(
                $recognizedCogsCents / 100,
                2
            ),

        /*
         * Management / Inventory valuation disclosure.
         * This value is NOT deducted again from Profit/Loss.
         */
        'slaughter_output_full_cost_valuation' =>
            round(
                $fullCostCents / 100,
                2
            ),

        /*
         * Direct/shared operating economics already represented by
         * their authoritative expense/consumption sources.
         */
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
