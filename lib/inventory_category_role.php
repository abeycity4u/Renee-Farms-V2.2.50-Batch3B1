<?php

/**
 * Canonical Inventory Category role policy.
 *
 * Financial Type describes purchase/spending classification.
 * Inventory Role describes what kind of physical stock the category may hold.
 * Keep these concerns separate so slaughter products never become eligible
 * merely because they are "General / Non-feed" inventory.
 */

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
