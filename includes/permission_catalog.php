<?php

if (!function_exists('permission_catalog')) {
function permission_catalog(): array
{
    return [
        'Poultry Operations' => [
            'poultry_overview' => ['label' => 'Poultry Overview', 'action' => 'View', 'description' => 'View the Poultry dashboard and overall summary.', 'roles' => ['poultry_manager']],
            'poultry_daily_layer' => ['label' => 'Layer Daily Records', 'action' => 'View / Record', 'description' => 'Access Layer daily records using the current production workflow.', 'roles' => ['poultry_manager']],
            'poultry_daily_layer_edit' => ['label' => 'Layer Daily Records', 'action' => 'Edit', 'description' => 'Modify existing Layer daily records.', 'roles' => ['poultry_manager']],
            'poultry_daily_layer_delete' => ['label' => 'Layer Daily Records', 'action' => 'Delete', 'description' => 'Delete Layer daily records and trigger linked restoration/rebuild logic.', 'roles' => ['poultry_manager']],
            'poultry_daily_broiler' => ['label' => 'Broiler Daily Records', 'action' => 'View / Record', 'description' => 'Access Broiler daily records using the current production workflow.', 'roles' => ['poultry_manager']],
            'poultry_daily_broiler_edit' => ['label' => 'Broiler Daily Records', 'action' => 'Edit', 'description' => 'Modify existing Broiler daily records.', 'roles' => ['poultry_manager']],
            'poultry_daily_broiler_delete' => ['label' => 'Broiler Daily Records', 'action' => 'Delete', 'description' => 'Delete Broiler daily records and restore linked stock usage.', 'roles' => ['poultry_manager']],
            'poultry_feeds' => ['label' => 'Poultry Feed Records', 'action' => 'View / Record', 'description' => 'Access Layer and Broiler feed records and usage.', 'roles' => ['poultry_manager']],
            'poultry_feeds_edit' => ['label' => 'Poultry Feed Records', 'action' => 'Edit', 'description' => 'Modify existing Poultry feed records where supported.', 'roles' => ['poultry_manager']],
            'poultry_feeds_delete' => ['label' => 'Poultry Feed Records', 'action' => 'Delete', 'description' => 'Delete Poultry feed records where supported.', 'roles' => ['poultry_manager']],
            'poultry_health' => ['label' => 'Poultry Health & Treatment', 'action' => 'View', 'description' => 'View structured flock health and treatment history.', 'roles' => ['poultry_manager']],
            'poultry_health_add' => ['label' => 'Poultry Health & Treatment', 'action' => 'Add', 'description' => 'Record new health and treatment events.', 'roles' => ['poultry_manager']],
            'poultry_health_edit' => ['label' => 'Poultry Health & Treatment', 'action' => 'Edit', 'description' => 'Modify existing health and treatment events.', 'roles' => ['poultry_manager']],
            'poultry_health_delete' => ['label' => 'Poultry Health & Treatment', 'action' => 'Delete', 'description' => 'Delete health and treatment events.', 'roles' => ['poultry_manager']],
        ],
        'Poultry Expenses' => [
            'poultry_expenses' => ['label' => 'Poultry Expense Access', 'action' => 'Current Access', 'description' => 'Existing shared Poultry expense access retained during granular rollout.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_layer_expenses_add' => ['label' => 'Layer Expenses', 'action' => 'Add', 'description' => 'Record new Layer non-stock expenses.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_layer_expenses_edit' => ['label' => 'Layer Expenses', 'action' => 'Edit', 'description' => 'Modify existing Layer expense records.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_layer_expenses_delete' => ['label' => 'Layer Expenses', 'action' => 'Delete', 'description' => 'Delete Layer expense records.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_broiler_expenses_add' => ['label' => 'Broiler Expenses', 'action' => 'Add', 'description' => 'Record new Broiler non-stock expenses.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_broiler_expenses_edit' => ['label' => 'Broiler Expenses', 'action' => 'Edit', 'description' => 'Modify existing Broiler expense records.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_broiler_expenses_delete' => ['label' => 'Broiler Expenses', 'action' => 'Delete', 'description' => 'Delete Broiler expense records.', 'roles' => ['poultry_manager','sales_rep']],
        ],
        'Ruminant Operations' => [
            'ruminant_overview' => ['label' => 'Ruminant Overview', 'action' => 'View', 'description' => 'View the Ruminant dashboard and overall summary.', 'roles' => ['ruminant_manager']],
            'ruminant_daily' => ['label' => 'Ruminant Daily Records', 'action' => 'View / Record', 'description' => 'Access Ruminant daily records using the current workflow.', 'roles' => ['ruminant_manager']],
            'ruminant_daily_edit' => ['label' => 'Ruminant Daily Records', 'action' => 'Edit', 'description' => 'Modify existing Ruminant daily records.', 'roles' => ['ruminant_manager']],
            'ruminant_daily_delete' => ['label' => 'Ruminant Daily Records', 'action' => 'Delete', 'description' => 'Delete existing Ruminant daily records.', 'roles' => ['ruminant_manager']],
            'ruminant_feeds' => ['label' => 'Ruminant Feed Records', 'action' => 'View / Record', 'description' => 'Access Ruminant feed records and usage.', 'roles' => ['ruminant_manager']],
            'ruminant_feeds_edit' => ['label' => 'Ruminant Feed Records', 'action' => 'Edit', 'description' => 'Modify existing Ruminant feed records where supported.', 'roles' => ['ruminant_manager']],
            'ruminant_feeds_delete' => ['label' => 'Ruminant Feed Records', 'action' => 'Delete', 'description' => 'Delete Ruminant feed records where supported.', 'roles' => ['ruminant_manager']],
            'ruminant_expenses' => ['label' => 'Ruminant Expense Access', 'action' => 'Current Access', 'description' => 'Existing Ruminant expense access retained during granular rollout.', 'roles' => ['ruminant_manager','sales_rep']],
            'ruminant_expenses_add' => ['label' => 'Ruminant Expenses', 'action' => 'Add', 'description' => 'Record new Ruminant non-stock expenses.', 'roles' => ['ruminant_manager','sales_rep']],
            'ruminant_expenses_edit' => ['label' => 'Ruminant Expenses', 'action' => 'Edit', 'description' => 'Modify existing Ruminant expense records.', 'roles' => ['ruminant_manager','sales_rep']],
            'ruminant_expenses_delete' => ['label' => 'Ruminant Expenses', 'action' => 'Delete', 'description' => 'Delete Ruminant expense records.', 'roles' => ['ruminant_manager','sales_rep']],
        ],
        'Inventory & Stock' => [
            'inventory' => ['label' => 'Inventory', 'action' => 'View', 'description' => 'View inventory items, balances and stock history.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'inventory_add_new_item' => ['label' => 'Inventory', 'action' => 'Add Item', 'description' => 'Create new inventory items.', 'roles' => ['poultry_manager','ruminant_manager']],
            'update_stock' => ['label' => 'Inventory', 'action' => 'Update Stock', 'description' => 'Post received/used stock quantities and adjustments.', 'roles' => ['poultry_manager','ruminant_manager']],
        ],
        'Sales & General Expenses' => [
            'sales' => ['label' => 'Sales Records', 'action' => 'View / Record', 'description' => 'Access sales entry and sales records.', 'roles' => ['sales_rep']],
            'sales_edit' => ['label' => 'Sales Records', 'action' => 'Edit', 'description' => 'Modify existing sales records where supported.', 'roles' => ['sales_rep']],
            'sales_delete' => ['label' => 'Sales Records', 'action' => 'Delete', 'description' => 'Delete sales records where supported.', 'roles' => ['sales_rep']],
            'expenses' => ['label' => 'General Expense Report', 'action' => 'View / Manage', 'description' => 'Existing general expense access retained during granular rollout.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'expenses_add' => ['label' => 'General Expenses', 'action' => 'Add', 'description' => 'Record new general non-stock expenses.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'expenses_edit' => ['label' => 'General Expense Report', 'action' => 'Edit', 'description' => 'Modify expense records from the general expense workflow.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'expenses_delete' => ['label' => 'General Expense Report', 'action' => 'Delete', 'description' => 'Delete expense records from the general expense workflow.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
        ],
        'Cycles, Reports & Team' => [
            'production_cycles' => ['label' => 'Production Cycles', 'action' => 'View', 'description' => 'View production cycles. Creation and lifecycle management remain Farm Admin controlled during this audit.', 'roles' => ['poultry_manager','ruminant_manager']],
            'reports' => ['label' => 'Reports', 'action' => 'View / Export', 'description' => 'View and generate farm reports available to the role.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'users' => ['label' => 'Team Users', 'action' => 'Manage (Legacy)', 'description' => 'Existing delegated team-user management permission retained until the user-management audit is completed.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
        ],
    ];
}
}

if (!function_exists('permission_catalog_flat')) {
function permission_catalog_flat(): array
{
    $flat = [];
    foreach (permission_catalog() as $group => $entries) {
        foreach ($entries as $code => $meta) {
            $meta['group'] = $group;
            $flat[$code] = $meta;
        }
    }
    return $flat;
}
}

if (!function_exists('permission_catalog_codes')) {
function permission_catalog_codes(): array
{
    return array_keys(permission_catalog_flat());
}
}

if (!function_exists('permission_catalog_applicable')) {
function permission_catalog_applicable(string $role, string $code): bool
{
    $catalog = permission_catalog_flat();
    if (!isset($catalog[$code])) return false;
    return in_array($role, $catalog[$code]['roles'] ?? [], true);
}
}
