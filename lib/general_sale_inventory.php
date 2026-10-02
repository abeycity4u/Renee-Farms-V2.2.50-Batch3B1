<?php

require_once __DIR__ . '/stock_service.php';
require_once __DIR__ . '/sales_units.php';

/**
 * Canonical source identity for a normal General Inventory-backed sale.
 */
function general_sale_inventory_source_type(): string
{
    return 'general_sale';
}

/**
 * Resolve General Inventory Sales input from POST-like form data.
 *
 * Physical Inventory provenance is authoritative. Browser-supplied product,
 * unit and attribution values are replaced from the selected Inventory item.
 */
function general_sale_inventory_selection_from_post(
    PDO $pdo,
    int $farmId,
    array &$input,
    bool $allowGeneralInventory = true
): array {
    $source =
        strtolower(
            trim(
                (string)(
                    $input['sale_stock_source']
                    ?? 'financial_only'
                )
            )
        );

    if ($source !== 'general_inventory') {
        return [
            'mode' => 'financial_only',
            'stock_item_id' => null,
        ];
    }

    if (!$allowGeneralInventory) {
        throw new RuntimeException(
            'General Inventory sales are available only in a Sales-only workspace.'
        );
    }

    $itemId =
        (int)(
            $input['stock_item_id']
            ?? 0
        );

    $item =
        general_sale_inventory_item(
            $pdo,
            $farmId,
            $itemId
        );

    /*
     * General Inventory owns the physical identity of this sale.
     * Never trust browser equivalents when a stock item is selected.
     */
    $input['farm_type'] =
        'general';

    $input['production_type'] =
        'general';

    $input['cycle_id'] =
        '0';

    $input['product_type'] =
        (string)$item['item_name'];

    $unit =
        trim(
            (string)(
                $item['unit']
                ?? ''
            )
        );

    if ($unit === '') {
        throw new RuntimeException(
            'The selected General Inventory item does not have a sales unit.'
        );
    }

    if (
        array_key_exists(
            $unit,
            sales_unit_presets()
        )
    ) {
        $input['unit_preset'] =
            $unit;

        $input['unit_custom'] =
            '';
    } else {
        $input['unit_preset'] =
            '__custom__';

        $input['unit_custom'] =
            $unit;
    }

    /*
     * General Inventory sales consume stock, not livestock population.
     * Any stale browser livestock controls are discarded.
     */
    $input['population_effect_mode'] =
        'financial_only';

    $input['sale_animal_allocation_mode'] =
        'shared';

    unset(
        $input['population_cycle_ids'],
        $input['population_quantities'],
        $input['sale_animal_ids'],
        $input['sale_animal_amounts'],
        $input['sale_animal_exit_outcomes'],
        $input['slaughter_output_ids'],
        $input['slaughter_output_quantities'],
        $input['slaughter_output_domain']
    );

    return [
        'mode' => 'general_inventory',
        'stock_item_id' => $itemId,
        'item' => $item,
    ];
}


/**
 * General Inventory items that may be offered to the Sales workspace.
 *
 * Slaughter Output inventory is deliberately excluded because its provenance
 * remains owned by the existing slaughter-output Sales workflow.
 */
function general_sale_inventory_available_items(
    PDO $pdo,
    int $farmId
): array {
    if ($farmId <= 0) {
        return [];
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 si.id,
                 si.item_name,
                 si.unit,
                 si.current_stock,
                 si.unit_cost,
                 si.min_stock_level,
                 si.farm_type,
                 si.feed_category,
                 COALESCE(
                     NULLIF(ic.inventory_role,''),
                     'operational'
                 ) AS inventory_role,
                 ic.category_name
             FROM stock_items si
             INNER JOIN inventory_categories ic
                 ON ic.id=si.category_id
                AND ic.farm_id=si.farm_id
             WHERE si.farm_id=?
               AND si.is_active=1
               AND si.farm_type='general'
               AND si.feed_category='general'
               AND COALESCE(
                     NULLIF(ic.inventory_role,''),
                     'operational'
                   )='operational'
             ORDER BY
                 ic.category_name,
                 si.item_name,
                 si.id"
        );

    $stmt->execute([
        $farmId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}

/**
 * Resolve and validate one General Inventory item.
 */
function general_sale_inventory_item(
    PDO $pdo,
    int $farmId,
    int $itemId
): array {
    if (
        $farmId <= 0
        || $itemId <= 0
    ) {
        throw new InvalidArgumentException(
            'Choose a valid General Inventory item.'
        );
    }

    $stmt =
        $pdo->prepare(
            "SELECT
                 si.*,
                 ic.category_name,
                 COALESCE(
                     NULLIF(ic.inventory_role,''),
                     'operational'
                 ) AS inventory_role,
                 ic.farm_type AS category_farm_type
             FROM stock_items si
             INNER JOIN inventory_categories ic
                 ON ic.id=si.category_id
                AND ic.farm_id=si.farm_id
             WHERE si.id=?
               AND si.farm_id=?
             LIMIT 1"
        );

    $stmt->execute([
        $itemId,
        $farmId,
    ]);

    $item =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$item) {
        throw new RuntimeException(
            'The selected General Inventory item was not found.'
        );
    }

    if ((int)($item['is_active'] ?? 0) !== 1) {
        throw new RuntimeException(
            'The selected General Inventory item is inactive.'
        );
    }

    if (
        strtolower(
            trim(
                (string)($item['farm_type'] ?? '')
            )
        ) !== 'general'
    ) {
        throw new RuntimeException(
            'Only General Inventory items can be used by a General sale.'
        );
    }

    if (
        strtolower(
            trim(
                (string)($item['category_farm_type'] ?? '')
            )
        ) !== 'general'
    ) {
        throw new RuntimeException(
            'The selected item must belong to a General Inventory category.'
        );
    }

    if (
        strtolower(
            trim(
                (string)($item['feed_category'] ?? '')
            )
        ) !== 'general'
    ) {
        throw new RuntimeException(
            'Feed inventory cannot be consumed by the General Sales workflow.'
        );
    }

    if (
        strtolower(
            trim(
                (string)($item['inventory_role'] ?? '')
            )
        ) !== 'operational'
    ) {
        throw new RuntimeException(
            'That inventory item belongs to a source-controlled workflow and cannot be sold through General Inventory.'
        );
    }

    return $item;
}

/**
 * Read the one active General-sale stock movement.
 *
 * Corrections remain append-only: older movements remain in history with
 * is_reversed=1, while at most one unreversed USED movement may be active.
 */
function general_sale_inventory_active_movement(
    PDO $pdo,
    int $farmId,
    int $saleId
): ?array {
    if (
        $farmId <= 0
        || $saleId <= 0
    ) {
        throw new InvalidArgumentException(
            'General sale inventory identity is invalid.'
        );
    }

    record_reference_persistence_require_transaction(
        $pdo
    );

    $stmt =
        $pdo->prepare(
            "SELECT *
             FROM stock_transactions
             WHERE farm_id=?
               AND source_type=?
               AND source_id=?
               AND transaction_type='used'
               AND COALESCE(is_reversed,0)=0
               AND COALESCE(reversal_of_id,0)=0
             ORDER BY id
             FOR UPDATE"
        );

    $stmt->execute([
        $farmId,
        general_sale_inventory_source_type(),
        $saleId,
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    if (count($rows) > 1) {
        throw new RuntimeException(
            'This sale has more than one active General Inventory movement and requires reconciliation before it can be changed.'
        );
    }

    return
        $rows[0]
        ?? null;
}

function general_sale_inventory_validate_business_date(
    string $saleDate
): void {
    $saleDate =
        trim(
            $saleDate
        );

    $date =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $saleDate
        );

    $errors =
        DateTimeImmutable::getLastErrors();

    if (
        !$date
        || (
            is_array($errors)
            && (
                ($errors['warning_count'] ?? 0) > 0
                || ($errors['error_count'] ?? 0) > 0
            )
        )
        || $date->format('Y-m-d') !== $saleDate
    ) {
        throw new InvalidArgumentException(
            'A valid sale date is required for Inventory movement.'
        );
    }
}

/**
 * Synchronize the physical General Inventory effect of one sale.
 *
 * Caller owns the database transaction.
 *
 * No physical change:
 *   current item + quantity + business date unchanged -> no ledger rewrite.
 *
 * Physical correction:
 *   reverse the previous immutable stock movement, then append the replacement.
 *
 * Switching to financial-only:
 *   reverse the previous movement and leave no active replacement.
 */
function general_sale_inventory_sync(
    PDO $pdo,
    int $farmId,
    int $saleId,
    ?int $currentItemId,
    ?int $desiredItemId,
    float $quantity,
    string $saleDate,
    ?int $userId
): ?int {
    record_reference_persistence_require_transaction(
        $pdo
    );

    if (
        $farmId <= 0
        || $saleId <= 0
    ) {
        throw new InvalidArgumentException(
            'General sale inventory identity is invalid.'
        );
    }

    $currentItemId =
        ($currentItemId ?? 0) > 0
            ? (int)$currentItemId
            : null;

    $desiredItemId =
        ($desiredItemId ?? 0) > 0
            ? (int)$desiredItemId
            : null;

    $quantity =
        round(
            $quantity,
            2
        );

    if (
        $desiredItemId !== null
        && (
            !is_finite($quantity)
            || $quantity <= 0
        )
    ) {
        throw new InvalidArgumentException(
            'General Inventory sale quantity must be greater than zero.'
        );
    }

    general_sale_inventory_validate_business_date(
        $saleDate
    );

    $active =
        general_sale_inventory_active_movement(
            $pdo,
            $farmId,
            $saleId
        );

    if (
        $currentItemId !== null
        && !$active
    ) {
        throw new RuntimeException(
            'The sale is linked to General Inventory but its active stock movement is missing.'
        );
    }

    if (
        $currentItemId === null
        && $active
    ) {
        throw new RuntimeException(
            'An active General Inventory movement exists without a matching sale link.'
        );
    }

    if (
        $active
        && (int)$active['stock_item_id']
            !== $currentItemId
    ) {
        throw new RuntimeException(
            'The sale and its active General Inventory movement reference different stock items.'
        );
    }

    $desiredItem =
        $desiredItemId !== null
            ? general_sale_inventory_item(
                $pdo,
                $farmId,
                $desiredItemId
            )
            : null;

    if (
        $active
        && $desiredItemId !== null
        && (int)$active['stock_item_id']
            === $desiredItemId
        && abs(
            (float)$active['quantity']
            - $quantity
        ) < 0.00001
        && (string)$active['transaction_date']
            === $saleDate
    ) {
        return
            (int)$active['id'];
    }

    if ($active) {
        stock_reverse_transaction(
            $pdo,
            $farmId,
            (int)$active['id'],
            'General sale Inventory correction',
            $userId,
            'general_sale_reversal',
            $saleId
        );
    }

    if (!$desiredItem) {
        return null;
    }

    return
        stock_apply_movement(
            $pdo,
            $farmId,
            $desiredItemId,
            'used',
            $quantity,
            $saleDate,
            'General sale Inventory deduction',
            $userId,
            'general',
            'general',
            null,
            general_sale_inventory_source_type(),
            $saleId,
            null,
            'general'
        );
}

/**
 * Restore General Inventory before a linked sale is hard-deleted.
 */
function general_sale_inventory_reverse_for_delete(
    PDO $pdo,
    int $farmId,
    array $sale,
    ?int $userId
): ?int {
    record_reference_persistence_require_transaction(
        $pdo
    );

    $saleId =
        (int)($sale['id'] ?? 0);

    if ($saleId <= 0) {
        throw new InvalidArgumentException(
            'General sale inventory deletion identity is invalid.'
        );
    }

    $linkedItemId =
        (int)($sale['stock_item_id'] ?? 0);

    $active =
        general_sale_inventory_active_movement(
            $pdo,
            $farmId,
            $saleId
        );

    if (
        $linkedItemId <= 0
        && !$active
    ) {
        return null;
    }

    if (
        $linkedItemId <= 0
        && $active
    ) {
        throw new RuntimeException(
            'An active General Inventory movement exists without a matching sale link.'
        );
    }

    if (
        $linkedItemId > 0
        && !$active
    ) {
        throw new RuntimeException(
            'The sale is linked to General Inventory but its active stock movement is missing.'
        );
    }

    if (
        (int)$active['stock_item_id']
        !== $linkedItemId
    ) {
        throw new RuntimeException(
            'The sale and its active General Inventory movement reference different stock items.'
        );
    }

    return
        stock_reverse_transaction(
            $pdo,
            $farmId,
            (int)$active['id'],
            'General sale deleted — restore Inventory',
            $userId,
            'general_sale_reversal',
            $saleId
        );
}
