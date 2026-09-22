<?php

/**
 * Canonical Inventory Category role policy.
 *
 * Financial Type describes purchase/spending classification.
 * Inventory Role describes what kind of physical stock the category may hold.
 * Keep these concerns separate so slaughter products never become eligible
 * merely because they are "General / Non-feed" inventory.
 */

function inventory_category_slaughter_output_role(): string
{
    return 'slaughter_output';
}

function inventory_category_roles(): array
{
    return [
        'operational' =>
            'Operational Inventory',

        'slaughter_output' =>
            'Slaughter Output',
    ];
}

function inventory_category_role_is_valid(
    string $value
): bool {
    return array_key_exists(
        strtolower(trim($value)),
        inventory_category_roles()
    );
}

function inventory_category_role_label(
    string $value
): string {
    $roles = inventory_category_roles();

    return $roles[
        strtolower(trim($value))
    ] ?? 'Operational Inventory';
}

function inventory_category_role_guidance(): array
{
    return [
        'operational' =>
            'Normal farm stock such as feed, medicines, consumables, tools and maintenance materials.',

        'slaughter_output' =>
            'Physical products created from a slaughtered tagged ruminant, such as meat, hide, head or offal. These items can be received only through Slaughter Processing provenance.',
    ];
}

function inventory_category_role_guidance_text(
    string $value
): string {
    $guidance =
        inventory_category_role_guidance();

    return $guidance[
        strtolower(trim($value))
    ] ?? $guidance['operational'];
}

/**
 * Category-level role boundary.
 */
function inventory_category_role_contract_errors(
    string $inventoryRole,
    string $categoryFarmType,
    string $financialType
): array {
    $inventoryRole =
        strtolower(trim($inventoryRole));

    $categoryFarmType =
        strtolower(trim($categoryFarmType));

    $financialType =
        strtolower(trim($financialType));

    $errors = [];

    if (
        !inventory_category_role_is_valid(
            $inventoryRole
        )
    ) {
        $errors[] =
            'Choose a valid Inventory Role.';
    }

    if ($inventoryRole === 'slaughter_output') {
        if ($categoryFarmType !== 'ruminant') {
            $errors[] =
                'Slaughter Output categories must use Farm Type Ruminant.';
        }

        if ($financialType !== 'other_stock') {
            $errors[] =
                'Slaughter Output categories must use Financial Type General / Other Stock.';
        }
    }

    return array_values(
        array_unique($errors)
    );
}

/**
 * Item-level role boundary.
 */
function inventory_category_role_item_contract_errors(
    string $inventoryRole,
    string $itemFarmType,
    string $feedCategory
): array {
    $inventoryRole =
        strtolower(trim($inventoryRole));

    $itemFarmType =
        strtolower(trim($itemFarmType));

    $feedCategory =
        strtolower(trim($feedCategory));

    $errors = [];

    if ($inventoryRole === 'slaughter_output') {
        if ($itemFarmType !== 'ruminant') {
            $errors[] =
                'Items in a Slaughter Output category must use Farm Type Ruminant.';
        }

        if ($feedCategory !== 'general') {
            $errors[] =
                'Items in a Slaughter Output category must use General / Non-feed usage.';
        }
    }

    return array_values(
        array_unique($errors)
    );
}

/**
 * A category role becomes part of item provenance once the category contains
 * stock items. Prevent role reclassification from silently changing the
 * meaning of existing inventory/history.
 */
function inventory_category_role_transition_errors(
    string $currentRole,
    string $newRole,
    int $existingItemCount
): array {
    $currentRole = strtolower(trim($currentRole));
    $newRole = strtolower(trim($newRole));

    if (
        $currentRole !== $newRole
        && $existingItemCount > 0
    ) {
        return [
            'Inventory Role cannot be changed while this category contains inventory items. Move or remove the items first.',
        ];
    }

    return [];
}

/**
 * Slaughter-output items begin at zero. Their first physical receipt must be
 * created by Slaughter Processing so animal/batch provenance is never guessed.
 */
function inventory_category_role_initial_stock_errors(
    string $inventoryRole,
    float $initialStock
): array {
    $inventoryRole = strtolower(trim($inventoryRole));

    if (
        $inventoryRole === inventory_category_slaughter_output_role()
        && $initialStock > 0.00001
    ) {
        return [
            'Initial Stock must be 0 for a Slaughter Output item. Receive produced quantity through Slaughter Processing.',
        ];
    }

    return [];
}

/**
 * Canonical stock-movement boundary for role-controlled inventory.
 *
 * Slaughter output may only enter stock from an already-created slaughter
 * output row. Consumption is intentionally blocked until the linked Sales lot
 * workflow owns the decrement.
 */
function inventory_category_role_stock_movement_errors(
    string $inventoryRole,
    string $movementType,
    ?string $sourceType,
    ?int $sourceId
): array {
    $inventoryRole = strtolower(trim($inventoryRole));
    $movementType = strtolower(trim($movementType));
    $sourceType = strtolower(trim((string)$sourceType));

    if (!inventory_category_role_is_valid($inventoryRole)) {
        return [
            'The Inventory Category has an invalid Inventory Role.',
        ];
    }

    if (
        $inventoryRole
        !== inventory_category_slaughter_output_role()
    ) {
        return [];
    }

    if (
        $movementType === 'received'
        && $sourceType === 'ruminant_slaughter_output'
        && $sourceId !== null
        && $sourceId > 0
    ) {
        return [];
    }

    if ($movementType === 'received') {
        return [
            'Slaughter Output stock can only be received through Slaughter Processing.',
        ];
    }

    if ($movementType === 'used') {
        return [
            'Slaughter Output stock cannot be adjusted through generic Inventory usage. Use the linked slaughter-output workflow.',
        ];
    }

    return [
        'That stock movement is not valid for Slaughter Output inventory.',
    ];
}

/**
 * Generic ledger reversal cannot safely change a slaughter lot because the
 * source-specific remaining balance must be corrected in the same transaction.
 */
function inventory_category_role_reversal_errors(
    string $inventoryRole
): array {
    $inventoryRole = strtolower(trim($inventoryRole));

    if (
        $inventoryRole
        === inventory_category_slaughter_output_role()
    ) {
        return [
            'Slaughter Output stock cannot be reversed through the generic Inventory ledger. Use a slaughter-output correction workflow.',
        ];
    }

    return [];
}
