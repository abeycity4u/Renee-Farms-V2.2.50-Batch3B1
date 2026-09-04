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
    $canAdd = permission_prepaint_has($addPermission);
    $canEdit = permission_prepaint_has($editPermission);
    $canDelete = permission_prepaint_has($deletePermission);

    if (!$canAdd) {
        $rules[] = 'button[onclick*="openRecordModal"]{display:none!important;}';
    }
    if (!$canEdit) {
        $rules[] = 'table .edit-record-btn{display:none!important;}';
    }
    if (!$canDelete) {
        $rules[] = 'table button.btn-outline-danger,table button[onclick*="deleteLayerDailyRecord"],table button[onclick*="deleteBroilerDailyRecord"]{display:none!important;}';
    }
}

// Expense Report contains edit/delete controls even though its primary purpose is
// reporting. Keep View independent from those actions and prevent controls from
// flashing before the granular API/runtime checks apply.
if ($path === '/management/expenses.php' || str_ends_with($path, '/management/expenses.php')) {
    $canEditExpense = permission_prepaint_has('expenses_edit');
    $canDeleteExpense = permission_prepaint_has('expenses_delete');
    if (!$canEditExpense) $rules[] = '.edit-expense-btn{display:none!important;}';
    if (!$canDeleteExpense) $rules[] = 'button[onclick*="deleteExpense"]{display:none!important;}';
}

// Hide unauthorized Management links before first paint. permission_runtime.php
// still removes their list items after DOM load and direct routes remain blocked.
$managementLinks = [
    '/management/sales_records.php' => 'sales',
    '/management/expenses.php' => 'expenses',
    '/management/poultry_ruminant_report.php' => 'reports',
    '/management/reports.php' => 'reports',
    '/management/intelligence.php' => 'farm_intelligence',
    '/management/profitability.php' => 'profitability',
    '/management/production_cycles.php' => 'production_cycles',
    '/management/users.php' => 'users',
];
$anyManagement = false;
foreach ($managementLinks as $hrefSuffix => $permission) {
    $allowed = permission_prepaint_has($permission);
    if ($allowed) {
        $anyManagement = true;
        continue;
    }
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
