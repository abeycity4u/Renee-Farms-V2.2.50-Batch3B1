<?php
/**
 * Pre-paint permission visibility guard.
 *
 * Runtime/server-side permission checks remain the security boundary. This helper
 * prevents controls that will be removed by permission_runtime.php from flashing
 * briefly before DOMContentLoaded.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    return;
}

if (!function_exists('permission_prepaint_privileged')) {
function permission_prepaint_privileged(): bool
{
    return isPlatformOwner() || hasRole('farm_admin');
}
}

if (!function_exists('permission_prepaint_has')) {
function permission_prepaint_has(string $permission): bool
{
    return permission_prepaint_privileged() || hasPermission(getUserType(), $permission);
}
}

$path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$rules = [];

$dailyPermissions = null;
if ($path === '/poultry/layers_daily_record.php' || str_ends_with($path, '/poultry/layers_daily_record.php')) {
    $dailyPermissions = ['poultry_daily_layer_add', 'poultry_daily_layer_edit', 'poultry_daily_layer_delete'];
} elseif ($path === '/poultry/broiler_daily_record.php' || str_ends_with($path, '/poultry/broiler_daily_record.php')) {
    $dailyPermissions = ['poultry_daily_broiler_add', 'poultry_daily_broiler_edit', 'poultry_daily_broiler_delete'];
} elseif ($path === '/ruminant/ruminant_daily_record.php' || str_ends_with($path, '/ruminant/ruminant_daily_record.php')) {
    $dailyPermissions = ['ruminant_daily_add', 'ruminant_daily_edit', 'ruminant_daily_delete'];
}

if ($dailyPermissions) {
    [$addPermission, $editPermission, $deletePermission] = $dailyPermissions;
    if (!permission_prepaint_has($addPermission)) $rules[] = 'button[onclick*="openRecordModal"]{display:none!important;}';
    if (!permission_prepaint_has($editPermission)) $rules[] = 'table .edit-record-btn{display:none!important;}';
    if (!permission_prepaint_has($deletePermission)) $rules[] = 'table button.btn-outline-danger,table button[onclick*="deleteLayerDailyRecord"],table button[onclick*="deleteBroilerDailyRecord"]{display:none!important;}';
}

if ($path === '/management/expenses.php' || str_ends_with($path, '/management/expenses.php')) {
    if (!permission_prepaint_has('expenses_edit')) $rules[] = '.edit-expense-btn{display:none!important;}';
    if (!permission_prepaint_has('expenses_delete')) $rules[] = 'button[onclick*="deleteExpense"]{display:none!important;}';
}

if ($path === '/poultry/layer_expenses.php' || str_ends_with($path, '/poultry/layer_expenses.php')) {
    if (!permission_prepaint_has('poultry_layer_expenses_add')) $rules[] = 'button[data-bs-target="#addExpenseModal"]{display:none!important;}';
}
if ($path === '/poultry/broiler_expenses.php' || str_ends_with($path, '/poultry/broiler_expenses.php')) {
    if (!permission_prepaint_has('poultry_broiler_expenses_add')) $rules[] = 'button[data-bs-target="#addExpenseModal"]{display:none!important;}';
}
if ($path === '/ruminant/ruminant_expenses.php' || str_ends_with($path, '/ruminant/ruminant_expenses.php')) {
    if (!permission_prepaint_has('ruminant_expenses_add')) $rules[] = 'button[data-bs-target="#addExpenseModal"]{display:none!important;}';
}

if ($path === '/poultry/layer_feeds.php' || str_ends_with($path, '/poultry/layer_feeds.php') || $path === '/poultry/broiler_feeds.php' || str_ends_with($path, '/poultry/broiler_feeds.php')) {
    if (!permission_prepaint_has('poultry_feeds_add')) $rules[] = 'button[data-bs-target="#addTransactionModal"]{display:none!important;}';
}

if ($path === '/management/sales_records.php' || str_ends_with($path, '/management/sales_records.php')) {
    if (!permission_prepaint_has('sales_add')) {
        $rules[] = 'button[data-bs-target="#addSaleModal"],button[onclick*="addSale"]{display:none!important;}';
    }
    if (!permission_prepaint_has('sales_payment')) {
        $rules[] = 'button[data-bs-target*="payment" i],button[onclick*="payment" i],form button[name="record_payment"]{display:none!important;}';
    }
}

if ($path === '/ruminant/animal_registry.php' || str_ends_with($path, '/ruminant/animal_registry.php')) {
    if (!permission_prepaint_has('ruminant_animals_add')) $rules[] = 'button[onclick*="newAnimal"]{display:none!important;}';
    if (!permission_prepaint_has('ruminant_animals_edit')) $rules[] = 'button[onclick*="editAnimal"]{display:none!important;}';
    if (!permission_prepaint_has('ruminant_animals_exit')) $rules[] = 'button[onclick*="exitAnimal"]{display:none!important;}';
}

// Hide unauthorized top-level/module links before first paint.
$navLinks = [
    '/inventory.php' => 'inventory',
    '/poultry/layer_expenses.php' => 'poultry_layer_expenses',
    '/poultry/broiler_expenses.php' => 'poultry_broiler_expenses',
    '/ruminant/animal_registry.php' => 'ruminant_animals',
    '/management/sales_records.php' => 'sales',
    '/management/expenses.php' => 'expenses',
    '/management/poultry_ruminant_report.php' => 'reports',
    '/management/reports.php' => 'reports',
    '/management/intelligence.php' => 'farm_intelligence',
    '/management/profitability.php' => 'profitability',
    '/management/production_cycles.php' => 'production_cycles',
    '/management/users.php' => 'users',
];
$managementSuffixes = [
    '/management/sales_records.php',
    '/management/expenses.php',
    '/management/poultry_ruminant_report.php',
    '/management/reports.php',
    '/management/intelligence.php',
    '/management/profitability.php',
    '/management/production_cycles.php',
    '/management/users.php',
];
$anyManagement = false;
foreach ($navLinks as $hrefSuffix => $permission) {
    $allowed = permission_prepaint_has($permission);
    if ($allowed && in_array($hrefSuffix, $managementSuffixes, true)) $anyManagement = true;
    if ($allowed) continue;
    $escaped = str_replace('"', '\\"', $hrefSuffix);
    $rules[] = '#appNavbar a[href$="' . $escaped . '"]{display:none!important;}';
}
if (!$anyManagement && !permission_prepaint_privileged()) {
    $rules[] = '#manageMenu{display:none!important;}';
}

if (!$rules) return;
$css = '<style id="permission-prepaint">' . implode('', $rules) . '</style>';

ob_start(static function (string $html) use ($css): string {
    if (stripos($html, '</head>') === false) return $html;
    return str_ireplace('</head>', $css . '</head>', $html);
});
