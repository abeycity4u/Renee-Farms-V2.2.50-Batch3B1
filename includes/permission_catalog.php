<?php

if (!function_exists('permission_catalog')) {
function permission_catalog(): array
{
    return [
        'Poultry Operations' => [
            'poultry_overview' => ['label' => 'Poultry Overview', 'action' => 'View', 'description' => 'View the Poultry dashboard and overall summary.', 'roles' => ['poultry_manager']],
            'poultry_daily_layer' => ['label' => 'Layer Daily Records', 'action' => 'View', 'description' => 'View existing Layer daily records.', 'roles' => ['poultry_manager']],
            'poultry_daily_layer_add' => ['label' => 'Layer Daily Records', 'action' => 'Add', 'description' => 'Record new Layer daily entries.', 'roles' => ['poultry_manager']],
            'poultry_daily_layer_edit' => ['label' => 'Layer Daily Records', 'action' => 'Edit', 'description' => 'Modify existing Layer daily records.', 'roles' => ['poultry_manager']],
            'poultry_daily_layer_delete' => ['label' => 'Layer Daily Records', 'action' => 'Delete', 'description' => 'Delete Layer daily records and trigger linked restoration/rebuild logic.', 'roles' => ['poultry_manager']],
            'poultry_daily_broiler' => ['label' => 'Broiler Daily Records', 'action' => 'View', 'description' => 'View existing Broiler daily records.', 'roles' => ['poultry_manager']],
            'poultry_daily_broiler_add' => ['label' => 'Broiler Daily Records', 'action' => 'Add', 'description' => 'Record new Broiler daily entries.', 'roles' => ['poultry_manager']],
            'poultry_daily_broiler_edit' => ['label' => 'Broiler Daily Records', 'action' => 'Edit', 'description' => 'Modify existing Broiler daily records.', 'roles' => ['poultry_manager']],
            'poultry_daily_broiler_delete' => ['label' => 'Broiler Daily Records', 'action' => 'Delete', 'description' => 'Delete Broiler daily records and restore linked stock usage.', 'roles' => ['poultry_manager']],
            'poultry_feeds' => ['label' => 'Poultry Feed Records', 'action' => 'View / Record', 'description' => 'View Layer and Broiler feed records and record feed usage.', 'roles' => ['poultry_manager']],
            'poultry_feeds_edit' => ['label' => 'Poultry Feed Records', 'action' => 'Edit', 'description' => 'Modify existing Poultry feed records where supported.', 'roles' => ['poultry_manager']],
            'poultry_feeds_delete' => ['label' => 'Poultry Feed Records', 'action' => 'Delete', 'description' => 'Delete Poultry feed records where supported.', 'roles' => ['poultry_manager']],
            'poultry_health' => ['label' => 'Poultry Health & Treatment', 'action' => 'View', 'description' => 'View structured flock health and treatment history.', 'roles' => ['poultry_manager']],
            'poultry_health_add' => ['label' => 'Poultry Health & Treatment', 'action' => 'Add', 'description' => 'Record new health and treatment events.', 'roles' => ['poultry_manager']],
            'poultry_health_edit' => ['label' => 'Poultry Health & Treatment', 'action' => 'Edit', 'description' => 'Modify existing health and treatment events.', 'roles' => ['poultry_manager']],
            'poultry_health_delete' => ['label' => 'Poultry Health & Treatment', 'action' => 'Delete', 'description' => 'Delete health and treatment events.', 'roles' => ['poultry_manager']],
        ],
        'Poultry Expenses' => [
            'poultry_layer_expenses' => ['label' => 'Layer Expenses', 'action' => 'View', 'description' => 'View Layer expense records.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_layer_expenses_add' => ['label' => 'Layer Expenses', 'action' => 'Add', 'description' => 'Record new Layer non-stock expenses.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_layer_expenses_edit' => ['label' => 'Layer Expenses', 'action' => 'Edit', 'description' => 'Modify existing Layer expense records.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_layer_expenses_delete' => ['label' => 'Layer Expenses', 'action' => 'Delete', 'description' => 'Delete Layer expense records.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_broiler_expenses' => ['label' => 'Broiler Expenses', 'action' => 'View', 'description' => 'View Broiler expense records.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_broiler_expenses_add' => ['label' => 'Broiler Expenses', 'action' => 'Add', 'description' => 'Record new Broiler non-stock expenses.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_broiler_expenses_edit' => ['label' => 'Broiler Expenses', 'action' => 'Edit', 'description' => 'Modify existing Broiler expense records.', 'roles' => ['poultry_manager','sales_rep']],
            'poultry_broiler_expenses_delete' => ['label' => 'Broiler Expenses', 'action' => 'Delete', 'description' => 'Delete Broiler expense records.', 'roles' => ['poultry_manager','sales_rep']],
        ],
        'Ruminant Operations' => [
            'ruminant_overview' => ['label' => 'Ruminant Overview', 'action' => 'View', 'description' => 'View the Ruminant dashboard and overall summary.', 'roles' => ['ruminant_manager']],
            'ruminant_daily' => ['label' => 'Ruminant Daily Records', 'action' => 'View', 'description' => 'View existing Ruminant daily records.', 'roles' => ['ruminant_manager']],
            'ruminant_daily_add' => ['label' => 'Ruminant Daily Records', 'action' => 'Add', 'description' => 'Record new Ruminant daily entries.', 'roles' => ['ruminant_manager']],
            'ruminant_daily_edit' => ['label' => 'Ruminant Daily Records', 'action' => 'Edit', 'description' => 'Modify existing Ruminant daily records.', 'roles' => ['ruminant_manager']],
            'ruminant_daily_delete' => ['label' => 'Ruminant Daily Records', 'action' => 'Delete', 'description' => 'Delete existing Ruminant daily records.', 'roles' => ['ruminant_manager']],
            'ruminant_animals' => ['label' => 'Ruminant Animal Registry', 'action' => 'View', 'description' => 'View registered ruminant animals and their lifecycle status.', 'roles' => ['ruminant_manager']],
            'ruminant_animals_add' => ['label' => 'Ruminant Animal Registry', 'action' => 'Add', 'description' => 'Register new ruminant animals.', 'roles' => ['ruminant_manager']],
            'ruminant_animals_edit' => ['label' => 'Ruminant Animal Registry', 'action' => 'Edit', 'description' => 'Modify existing animal registry details.', 'roles' => ['ruminant_manager']],
            'ruminant_animals_exit' => ['label' => 'Ruminant Animal Registry', 'action' => 'Record Exit', 'description' => 'Record dead, culled or transferred lifecycle exits.', 'roles' => ['ruminant_manager']],
            'ruminant_feeds' => ['label' => 'Ruminant Feed Records', 'action' => 'View / Record', 'description' => 'View Ruminant feed records and record feed usage.', 'roles' => ['ruminant_manager']],
            'ruminant_feeds_edit' => ['label' => 'Ruminant Feed Records', 'action' => 'Edit', 'description' => 'Modify existing Ruminant feed records where supported.', 'roles' => ['ruminant_manager']],
            'ruminant_feeds_delete' => ['label' => 'Ruminant Feed Records', 'action' => 'Delete', 'description' => 'Delete Ruminant feed records where supported.', 'roles' => ['ruminant_manager']],
            'ruminant_expenses' => ['label' => 'Ruminant Expenses', 'action' => 'View', 'description' => 'View Ruminant expense records available to the role.', 'roles' => ['ruminant_manager','sales_rep']],
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
            'sales' => ['label' => 'Sales Records', 'action' => 'View', 'description' => 'View sales records and receivable history.', 'roles' => ['sales_rep']],
            'sales_add' => ['label' => 'Sales Records', 'action' => 'Add Sale', 'description' => 'Record a new sale.', 'roles' => ['sales_rep']],
            'sales_payment' => ['label' => 'Sales Receivables', 'action' => 'Record Payment', 'description' => 'Record customer payments against outstanding sales.', 'roles' => ['sales_rep']],
            'sales_edit' => ['label' => 'Sales Records', 'action' => 'Edit', 'description' => 'Modify existing sales records where supported.', 'roles' => ['sales_rep']],
            'sales_delete' => ['label' => 'Sales Records', 'action' => 'Delete', 'description' => 'Delete sales records where supported.', 'roles' => ['sales_rep']],
            'expenses' => ['label' => 'Expense Report', 'action' => 'View', 'description' => 'View expense records and reports.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'expenses_edit' => ['label' => 'Expense Report', 'action' => 'Edit', 'description' => 'Modify expense records from the Expense Report page.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'expenses_delete' => ['label' => 'Expense Report', 'action' => 'Delete', 'description' => 'Delete expense records from the Expense Report page.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
        ],
        'Management Insights' => [
            'profitability' => ['label' => 'Profitability', 'action' => 'View', 'description' => 'View farm profitability, revenue, cost and profit/loss analysis available to the role.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'farm_intelligence' => ['label' => 'Farm Intelligence', 'action' => 'View', 'description' => 'View management signals and explainable farm intelligence.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
        ],
        'Cycles, Reports & Team' => [
            'production_cycles' => ['label' => 'Production Cycles', 'action' => 'View', 'description' => 'View production cycles. Creation and lifecycle management remain Farm Admin controlled during this audit.', 'roles' => ['poultry_manager','ruminant_manager']],
            'reports' => ['label' => 'Reports', 'action' => 'View / Export', 'description' => 'View and generate farm reports available to the role.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'users' => ['label' => 'Team Users', 'action' => 'Manage', 'description' => 'Manage tenant team-user accounts where delegated.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
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

if (!function_exists('permission_catalog_expense_action_code')) {
function permission_catalog_expense_action_code(array $expense, string $action): ?string
{
    if (!in_array($action, ['edit', 'delete'], true)) return null;

    $farmType = strtolower((string)($expense['farm_type'] ?? ''));
    $productionType = strtolower((string)($expense['production_type'] ?? ''));
    $poultryCategory = strtolower((string)($expense['poultry_category'] ?? ''));

    if ($farmType === 'poultry') {
        $poultryType = in_array($productionType, ['layer', 'broiler'], true) ? $productionType : $poultryCategory;
        if (in_array($poultryType, ['layer', 'broiler'], true)) {
            return 'poultry_' . $poultryType . '_expenses_' . $action;
        }
        return null;
    }
    if ($farmType === 'ruminant') return 'ruminant_expenses_' . $action;
    if ($farmType === 'general' || $farmType === 'both') return 'expenses_' . $action;

    return null;
}
}
