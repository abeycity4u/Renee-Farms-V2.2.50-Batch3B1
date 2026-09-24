<?php

require_once __DIR__ . '/stock_service.php';
require_once __DIR__ . '/inventory_category_role.php';

/**
 * Shared slaughter-output Inventory boundary.
 *
 * Species/domain services retain ownership of:
 * - slaughter eligibility;
 * - lifecycle/population facts;
 * - frozen batch economics;
 * - source-specific output-lot tables.
 *
 * This helper owns the common physical Inventory rules:
 * - output allocation arithmetic;
 * - slaughter-output Inventory item eligibility;
 * - canonical stock receipt posting.
 */

if (!function_exists('slaughter_output_inventory_domain_farm_types')) {
function slaughter_output_inventory_domain_farm_types(): array
{
    return [
        'poultry',
        'ruminant',
    ];
}
}


if (!function_exists('slaughter_output_inventory_domain_farm_type')) {
function slaughter_output_inventory_domain_farm_type(
    string $farmType
): string {
    $farmType =
        strtolower(
            trim($farmType)
        );

    if (
        !in_array(
            $farmType,
            slaughter_output_inventory_domain_farm_types(),
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Slaughter Output Inventory requires Poultry or Ruminant ownership.'
        );
    }

    return $farmType;
}
}


if (!function_exists('slaughter_output_inventory_receipt_source')) {
function slaughter_output_inventory_receipt_source(
    string $farmType
): string {
    $farmType =
        slaughter_output_inventory_domain_farm_type(
            $farmType
        );

    return
        $farmType
        .
        '_slaughter_output';
}
}


/**
 * Shared cost-share conservation.
 *
 * The output that brings cumulative allocation to effectively 100% receives
 * the exact remaining cents so repeated percentage rounding never loses or
 * invents batch value.
 */
if (!function_exists('slaughter_output_inventory_allocation')) {
function slaughter_output_inventory_allocation(
    float $batchCost,
    float $alreadyPercent,
    float $alreadyCost,
    float $quantity,
    float $costSharePercent
): array {
    if (
        !is_finite($batchCost)
        ||
        !is_finite($alreadyPercent)
        ||
        !is_finite($alreadyCost)
        ||
        !is_finite($quantity)
        ||
        !is_finite($costSharePercent)
    ) {
        throw new InvalidArgumentException(
            'Slaughter Output allocation contains an invalid numeric value.'
        );
    }

    $batchCost =
        round(
            $batchCost,
            2
        );

    $alreadyPercent =
        round(
            $alreadyPercent,
            4
        );

    $alreadyCost =
        round(
            $alreadyCost,
            2
        );

    $quantity =
        round(
            $quantity,
            2
        );

    $costSharePercent =
        round(
            $costSharePercent,
            4
        );

    if (
        $batchCost < 0
        ||
        $alreadyPercent < 0
        ||
        $alreadyPercent > 100.0001
        ||
        $alreadyCost < 0
        ||
        $alreadyCost > $batchCost + 0.01
        ||
        $quantity <= 0
        ||
        $costSharePercent <= 0
        ||
        $costSharePercent > 100
    ) {
        throw new InvalidArgumentException(
            'Choose a valid slaughter-output quantity and cost allocation.'
        );
    }

    $newPercent =
        round(
            $alreadyPercent
            +
            $costSharePercent,
            4
        );

    if ($newPercent > 100.0001) {
        throw new RuntimeException(
            'Output cost shares cannot exceed 100% of the slaughter batch cost basis.'
        );
    }

    $allocatedCost =
        round(
            $batchCost
            *
            $costSharePercent
            /
            100,
            2
        );

    if ($newPercent >= 99.9999) {
        $allocatedCost =
            round(
                max(
                    0,
                    $batchCost
                    -
                    $alreadyCost
                ),
                2
            );
    }

    $unitCostSnapshot =
        round(
            $allocatedCost
            /
            $quantity,
            4
        );

    return [
        'new_percent' =>
            $newPercent,

        'allocated_cost' =>
            $allocatedCost,

        'unit_cost_snapshot' =>
            $unitCostSnapshot,
    ];
}
}


/**
 * Lock and prove one slaughter-output item belongs to the requested domain.
 */
if (!function_exists('slaughter_output_inventory_lock_item')) {
function slaughter_output_inventory_lock_item(
    PDO $pdo,
    int $farmId,
    int $stockItemId,
    string $farmType
): array {
    if (
        $farmId <= 0
        ||
        $stockItemId <= 0
    ) {
        throw new InvalidArgumentException(
            'Choose a valid farm and Slaughter Output Inventory item.'
        );
    }

    $farmType =
        slaughter_output_inventory_domain_farm_type(
            $farmType
        );

    $stmt =
        $pdo->prepare(
            "SELECT
                 si.id,
                 si.item_name,
                 si.unit,
                 si.farm_type,
                 si.feed_category,
                 si.is_active,
                 ic.farm_type AS category_farm_type,
                 ic.financial_type AS category_financial_type,
                 COALESCE(
                     NULLIF(
                         ic.inventory_role,
                         ''
                     ),
                     'operational'
                 ) AS category_inventory_role
             FROM stock_items si
             INNER JOIN inventory_categories ic
               ON ic.id=si.category_id
              AND ic.farm_id=si.farm_id
             WHERE si.id=?
               AND si.farm_id=?
               AND COALESCE(
                       NULLIF(
                           ic.inventory_role,
                           ''
                       ),
                       'operational'
                   )=?
             LIMIT 1
             FOR UPDATE"
        );

    $stmt->execute([
        $stockItemId,
        $farmId,
        inventory_category_slaughter_output_role(),
    ]);

    $item =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$item) {
        throw new RuntimeException(
            'The selected Slaughter Output Inventory item could not be found.'
        );
    }

    if ((int)$item['is_active'] !== 1) {
        throw new RuntimeException(
            'The selected Slaughter Output Inventory item is inactive.'
        );
    }

    $categoryErrors =
        inventory_category_role_contract_errors(
            (string)$item[
                'category_inventory_role'
            ],
            (string)$item[
                'category_farm_type'
            ],
            (string)$item[
                'category_financial_type'
            ]
        );

    if ($categoryErrors) {
        throw new RuntimeException(
            $categoryErrors[0]
        );
    }

    $itemErrors =
        inventory_category_role_item_contract_errors(
            (string)$item[
                'category_inventory_role'
            ],
            (string)$item[
                'farm_type'
            ],
            (string)$item[
                'feed_category'
            ]
        );

    if ($itemErrors) {
        throw new RuntimeException(
            $itemErrors[0]
        );
    }

    if (
        strtolower(
            (string)$item[
                'category_farm_type'
            ]
        )
        !==
        $farmType
    ) {
        throw new RuntimeException(
            'The selected Slaughter Output category belongs to a different farm production domain.'
        );
    }

    if (
        !in_array(
            strtolower(
                (string)$item[
                    'farm_type'
                ]
            ),
            [
                $farmType,
                'both',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'The selected Slaughter Output item belongs to a different farm production domain.'
        );
    }

    if (
        strtolower(
            (string)$item[
                'feed_category'
            ]
        )
        !== 'general'
    ) {
        throw new RuntimeException(
            'Slaughter Output items must use General / Non-feed usage.'
        );
    }

    return $item;
}
}


/**
 * Post one produced slaughter-output lot through the canonical stock writer.
 *
 * Caller must already:
 * - own a transaction;
 * - create/lock the domain-specific output source row;
 * - pass that row ID as $sourceId.
 */
if (!function_exists('slaughter_output_inventory_receive')) {
function slaughter_output_inventory_receive(
    PDO $pdo,
    int $farmId,
    int $stockItemId,
    float $quantity,
    string $transactionDate,
    ?string $remarks,
    ?int $userId,
    string $farmType,
    int $cycleId,
    int $sourceId,
    float $unitCostSnapshot,
    string $productionType,
    float $allocatedCost
): int {
    if (
        !$pdo->inTransaction()
    ) {
        throw new RuntimeException(
            'Slaughter Output Inventory receipt requires an active transaction.'
        );
    }

    $farmType =
        slaughter_output_inventory_domain_farm_type(
            $farmType
        );

    if (
        $farmId <= 0
        ||
        $stockItemId <= 0
        ||
        $cycleId <= 0
        ||
        $sourceId <= 0
        ||
        $quantity <= 0
        ||
        !is_finite($quantity)
        ||
        $unitCostSnapshot < 0
        ||
        !is_finite($unitCostSnapshot)
        ||
        $allocatedCost < 0
        ||
        !is_finite($allocatedCost)
    ) {
        throw new InvalidArgumentException(
            'Slaughter Output Inventory receipt contains invalid source, quantity or cost details.'
        );
    }

    return
        stock_apply_movement(
            $pdo,
            $farmId,
            $stockItemId,
            'received',
            $quantity,
            $transactionDate,
            $remarks,
            $userId,
            $farmType,
            'general',
            $cycleId,
            slaughter_output_inventory_receipt_source(
                $farmType
            ),
            $sourceId,
            $unitCostSnapshot,
            $productionType,
            $allocatedCost
        );
}
}
