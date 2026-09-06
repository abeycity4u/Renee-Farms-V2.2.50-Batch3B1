<?php

if (!function_exists('permission_catalog')) {
function permission_catalog(): array
{
    $sharedSalesRoles = ['poultry_manager','ruminant_manager','sales_rep'];

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
            'poultry_feeds' => ['label' => 'Poultry Feed Records', 'action' => 'View', 'description' => 'View Layer and Broiler feed records.', 'roles' => ['poultry_manager']],
            'poultry_feeds_add' => ['label' => 'Poultry Feed Records', 'action' => 'Add', 'description' => 'Record new Layer and Broiler feed usage transactions.', 'roles' => ['poultry_manager']],
            'poultry_health' => ['label' => 'Poultry Health & Treatment', 'action' => 'View', 'description' => 'View structured flock health and treatment history.', 'roles' => ['poultry_manager']],
            'poultry_health_add' => ['label' => 'Poultry Health & Treatment', 'action' => 'Add', 'description' => 'Record new health and treatment events.', 'roles' => ['poultry_manager']],
            'poultry_health_edit' => ['label' => 'Poultry Health & Treatment', 'action' => 'Edit', 'description' => 'Modify existing health and treatment events.', 'roles' => ['poultry_manager']],
            'poultry_health_delete' => ['label' => 'Poultry Health & Treatment', 'action' => 'Delete', 'description' => 'Delete health and treatment events.', 'roles' => ['poultry_manager']],
        ],
        'Poultry Expenses' => [
            'poultry_layer_expenses' => ['label' => 'Layer Expenses', 'action' => 'View', 'description' => 'View Layer expense records.', 'roles' => ['poultry_manager']],
            'poultry_layer_expenses_add' => ['label' => 'Layer Expenses', 'action' => 'Add', 'description' => 'Record new Layer non-stock expenses.', 'roles' => ['poultry_manager']],
            'poultry_layer_expenses_edit' => ['label' => 'Layer Expenses', 'action' => 'Edit', 'description' => 'Modify existing Layer expense records.', 'roles' => ['poultry_manager']],
            'poultry_layer_expenses_delete' => ['label' => 'Layer Expenses', 'action' => 'Delete', 'description' => 'Delete Layer expense records.', 'roles' => ['poultry_manager']],
            'poultry_broiler_expenses' => ['label' => 'Broiler Expenses', 'action' => 'View', 'description' => 'View Broiler expense records.', 'roles' => ['poultry_manager']],
            'poultry_broiler_expenses_add' => ['label' => 'Broiler Expenses', 'action' => 'Add', 'description' => 'Record new Broiler non-stock expenses.', 'roles' => ['poultry_manager']],
            'poultry_broiler_expenses_edit' => ['label' => 'Broiler Expenses', 'action' => 'Edit', 'description' => 'Modify existing Broiler expense records.', 'roles' => ['poultry_manager']],
            'poultry_broiler_expenses_delete' => ['label' => 'Broiler Expenses', 'action' => 'Delete', 'description' => 'Delete Broiler expense records.', 'roles' => ['poultry_manager']],
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
            'ruminant_feeds' => ['label' => 'Ruminant Feed Records', 'action' => 'View', 'description' => 'View Ruminant feed records.', 'roles' => ['ruminant_manager']],
            'ruminant_feeds_add' => ['label' => 'Ruminant Feed Records', 'action' => 'Add', 'description' => 'Record new Ruminant feed usage transactions.', 'roles' => ['ruminant_manager']],
            'ruminant_expenses' => ['label' => 'Ruminant Expenses', 'action' => 'View', 'description' => 'View Ruminant expense records available to the role.', 'roles' => ['ruminant_manager']],
            'ruminant_expenses_add' => ['label' => 'Ruminant Expenses', 'action' => 'Add', 'description' => 'Record new Ruminant non-stock expenses.', 'roles' => ['ruminant_manager']],
            'ruminant_expenses_edit' => ['label' => 'Ruminant Expenses', 'action' => 'Edit', 'description' => 'Modify existing Ruminant expense records.', 'roles' => ['ruminant_manager']],
            'ruminant_expenses_delete' => ['label' => 'Ruminant Expenses', 'action' => 'Delete', 'description' => 'Delete Ruminant expense records.', 'roles' => ['ruminant_manager']],
        ],
        'Inventory & Stock' => [
            'inventory' => ['label' => 'Inventory', 'action' => 'View', 'description' => 'View inventory items, balances and stock history.', 'roles' => ['poultry_manager','ruminant_manager']],
            'inventory_add_new_item' => ['label' => 'Inventory', 'action' => 'Add Item', 'description' => 'Create new inventory items.', 'roles' => ['poultry_manager','ruminant_manager']],
            'update_stock' => ['label' => 'Inventory', 'action' => 'Update Stock', 'description' => 'Post received/used stock quantities and adjustments.', 'roles' => ['poultry_manager','ruminant_manager']],
        ],
        'Sales & General Expenses' => [
            'sales' => ['label' => 'Sales Records', 'action' => 'View', 'description' => 'View sales records and receivable history.', 'roles' => $sharedSalesRoles],
            'sales_add' => ['label' => 'Sales Records', 'action' => 'Add Sale', 'description' => 'Record a new sale.', 'roles' => $sharedSalesRoles],
            'sales_edit' => ['label' => 'Sales Records', 'action' => 'Edit', 'description' => 'Modify existing sales records, including linked receivable and allocation synchronization.', 'roles' => $sharedSalesRoles],
            'sales_delete' => ['label' => 'Sales Records', 'action' => 'Delete', 'description' => 'Delete sales records using the existing receivable, allocation and lifecycle safety checks.', 'roles' => $sharedSalesRoles],
            'sales_receivables' => ['label' => 'Sales Receivables', 'action' => 'View', 'description' => 'View customer debt balances, credit sales and payment history.', 'roles' => $sharedSalesRoles],
            'sales_payment' => ['label' => 'Sales Receivables', 'action' => 'Record Payment', 'description' => 'Record payments against outstanding customer debt.', 'roles' => $sharedSalesRoles],
            'sales_receivables_edit' => ['label' => 'Sales Receivables', 'action' => 'Edit', 'description' => 'Modify eligible sales receivable and payment records.', 'roles' => $sharedSalesRoles],
            'sales_receivables_delete' => ['label' => 'Sales Receivables', 'action' => 'Delete', 'description' => 'Delete eligible sales receivable and payment records.', 'roles' => $sharedSalesRoles],
            'expenses' => ['label' => 'General Expense Report', 'action' => 'View', 'description' => 'View General/commercial expense records. Dedicated Sales Representatives never receive Poultry or Ruminant operating expenses.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'expenses_edit' => ['label' => 'General Expense Report', 'action' => 'Edit', 'description' => 'Modify eligible General/commercial expense records. Dedicated Sales Representatives remain limited to General expenses.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
            'expenses_delete' => ['label' => 'General Expense Report', 'action' => 'Delete', 'description' => 'Delete eligible General/commercial expense records. Dedicated Sales Representatives remain limited to General expenses.', 'roles' => ['poultry_manager','ruminant_manager','sales_rep']],
        ],
        'Management Insights' => [
            'profitability' => ['label' => 'Profitability', 'action' => 'View', 'description' => 'View farm profitability, revenue, cost and profit/loss analysis available to the role.', 'roles' => ['poultry_manager','ruminant_manager']],
            'farm_intelligence' => ['label' => 'Farm Intelligence', 'action' => 'View', 'description' => 'View management signals and explainable farm intelligence.', 'roles' => ['poultry_manager','ruminant_manager']],
        ],
        'Cycles, Reports & Team' => [
            'production_cycles' => ['label' => 'Production Cycles', 'action' => 'View', 'description' => 'View production cycles. Creation and lifecycle management remain Farm Admin controlled during this audit.', 'roles' => ['poultry_manager','ruminant_manager']],
            'reports' => ['label' => 'Operational Reports', 'action' => 'View / Export', 'description' => 'View and generate Poultry and Ruminant operational reports available to the role.', 'roles' => ['poultry_manager','ruminant_manager']],
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
