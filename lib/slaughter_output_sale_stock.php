<?php

require_once __DIR__ . '/stock_service.php';
require_once __DIR__ . '/slaughter_output_inventory.php';

/**
 * Shared physical Inventory boundary for slaughter-output Sales.
 *
 * Domain-specific sale services still own:
 * - explicit lot selection;
 * - sale/lot semantic compatibility;
 * - source-lot remaining quantity;
 * - durable sale-allocation rows;
 * - batch status;
 * - hard-delete protection.
 *
 * This helper owns only the physical stock mutation provenance:
 *
 *   {domain}_slaughter_sale
 *   {domain}_slaughter_sale_reversal
 *
 * It never mutates live livestock population and never creates Sales revenue.
 */

if (!function_exists('slaughter_output_sale_stock_source')) {
function slaughter_output_sale_stock_source(
    string $farmType
): string {
    $farmType =
        slaughter_output_inventory_domain_farm_type(
            $farmType
        );

    return
        $farmType
        .
        '_slaughter_sale';
}
}


if (!function_exists('slaughter_output_sale_stock_reversal_source')) {
function slaughter_output_sale_stock_reversal_source(
    string $farmType
): string {
    $farmType =
        slaughter_output_inventory_domain_farm_type(
            $farmType
        );

    return
        $farmType
        .
        '_slaughter_sale_reversal';
}
}


/**
 * Consume one frozen slaughter-output lot through canonical stock_service.php.
 */
if (!function_exists('slaughter_output_sale_stock_consume')) {
function slaughter_output_sale_stock_consume(
    PDO $pdo,
    int $farmId,
    string $farmType,
    int $cycleId,
    string $productionType,
    int $stockItemId,
    int $allocationId,
    int $saleId,
    string $batchCode,
    float $quantity,
    string $saleDate,
    float $unitCostSnapshot,
    float $totalCostSnapshot,
    ?int $userId
): int {
    $farmType =
        slaughter_output_inventory_domain_farm_type(
            $farmType
        );

    $quantity =
        round(
            $quantity,
            2
        );

    $unitCostSnapshot =
        round(
            $unitCostSnapshot,
            4
        );

    $totalCostSnapshot =
        round(
            $totalCostSnapshot,
            2
        );

    if (
        $farmId <= 0
        ||
        $cycleId <= 0
        ||
        $stockItemId <= 0
        ||
        $allocationId <= 0
        ||
        $saleId <= 0
        ||
        $quantity <= 0
        ||
        !is_finite($quantity)
        ||
        $unitCostSnapshot < 0
        ||
        !is_finite($unitCostSnapshot)
        ||
        $totalCostSnapshot < 0
        ||
        !is_finite($totalCostSnapshot)
    ) {
        throw new InvalidArgumentException(
            'Slaughter-output sale stock consumption contains invalid source, quantity or cost details.'
        );
    }

    return
        stock_apply_movement(
            $pdo,
            $farmId,
            $stockItemId,
            'used',
            $quantity,
            $saleDate,
            'Slaughter output consumed by Sale #'
                .
                $saleId
                .
                ' · '
                .
                trim($batchCode),
            $userId,
            $farmType,
            'general',
            $cycleId,
            slaughter_output_sale_stock_source(
                $farmType
            ),
            $allocationId,
            null,
            $productionType,
            null,
            $unitCostSnapshot,
            $totalCostSnapshot
        );
}
}


/**
 * Reverse one source-owned slaughter-sale stock decrement.
 *
 * stock_reverse_transaction() remains the canonical append-only reversal
 * writer. The provenance-aware Inventory-role gate permits only the explicit
 * domain slaughter-sale reversal source and positive allocation identity.
 */
if (!function_exists('slaughter_output_sale_stock_reverse')) {
function slaughter_output_sale_stock_reverse(
    PDO $pdo,
    int $farmId,
    string $farmType,
    int $allocationId,
    int $stockTransactionId,
    ?int $userId
): int {
    $farmType =
        slaughter_output_inventory_domain_farm_type(
            $farmType
        );

    if (
        $farmId <= 0
        ||
        $allocationId <= 0
        ||
        $stockTransactionId <= 0
    ) {
        throw new InvalidArgumentException(
            'Slaughter-output sale reversal requires valid stock and allocation provenance.'
        );
    }

    return
        stock_reverse_transaction(
            $pdo,
            $farmId,
            $stockTransactionId,
            'Restore slaughter output after Sale lot correction',
            $userId,
            slaughter_output_sale_stock_reversal_source(
                $farmType
            ),
            $allocationId
        );
}
}
