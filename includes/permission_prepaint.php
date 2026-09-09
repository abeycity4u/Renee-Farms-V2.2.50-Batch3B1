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
$tokens = [];

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
    if (!permission_prepaint_has($addPermission)) $tokens[] = 'daily-add';
    if (!permission_prepaint_has($editPermission)) $tokens[] = 'daily-edit';
    if (!permission_prepaint_has($deletePermission)) $tokens[] = 'daily-delete';
}

if ($path === '/management/expenses.php' || str_ends_with($path, '/management/expenses.php')) {
    if (!permission_prepaint_has('expenses_edit')) $tokens[] = 'expense-edit';
    if (!permission_prepaint_has('expenses_delete')) $tokens[] = 'expense-delete';
}

if ($path === '/poultry/layer_expenses.php' || str_ends_with($path, '/poultry/layer_expenses.php')) {
    if (!permission_prepaint_has('poultry_layer_expenses_add')) $tokens[] = 'expense-add';
}
if ($path === '/poultry/broiler_expenses.php' || str_ends_with($path, '/poultry/broiler_expenses.php')) {
    if (!permission_prepaint_has('poultry_broiler_expenses_add')) $tokens[] = 'expense-add';
}
if ($path === '/ruminant/ruminant_expenses.php' || str_ends_with($path, '/ruminant/ruminant_expenses.php')) {
    if (!permission_prepaint_has('ruminant_expenses_add')) $tokens[] = 'expense-add';
}

if ($path === '/poultry/layer_feeds.php' || str_ends_with($path, '/poultry/layer_feeds.php') || $path === '/poultry/broiler_feeds.php' || str_ends_with($path, '/poultry/broiler_feeds.php')) {
    if (!permission_prepaint_has('poultry_feeds_add')) $tokens[] = 'feed-add';
}

if ($path === '/management/sales_records.php' || str_ends_with($path, '/management/sales_records.php')) {
    if (!permission_prepaint_has('sales_add')) {
        $tokens[] = 'sales-add';
    }
    if (!permission_prepaint_has('sales_payment')) {
        $tokens[] = 'sales-payment';
    }
    if (!permission_prepaint_has('sales_edit')) $tokens[] = 'sales-edit';
    if (!permission_prepaint_has('sales_delete')) $tokens[] = 'sales-delete';
}

if ($path === '/ruminant/animal_registry.php' || str_ends_with($path, '/ruminant/animal_registry.php')) {
    if (!permission_prepaint_has('ruminant_animals_add')) $tokens[] = 'animal-add';
    if (!permission_prepaint_has('ruminant_animals_edit')) $tokens[] = 'animal-edit';
    if (!permission_prepaint_has('ruminant_animals_exit')) $tokens[] = 'animal-exit';
}

// Production Cycles is intentionally a View-only delegated permission during
// commercial hardening. The page already rejects every POST from non-admins;
// hide those management forms before first paint so delegated viewers get a
// genuinely read-only workspace rather than controls they cannot use.
if (($path === '/management/production_cycles.php' || str_ends_with($path, '/management/production_cycles.php')) && !permission_prepaint_privileged()) {
    $tokens[] = 'production-cycle-readonly';
}

// Subscription entitlement is the outer navigation boundary. Reuse the runtime
// delegated-expense helper so a Sales Representative with an exact expense View
// permission can see the corresponding Poultry/Ruminant menu without gaining the
// underlying operational manager role.
if (!isPlatformOwner()) {
    $allowPoultryMenu = user_can_access_entitled_module('poultry');
    $allowRuminantMenu = user_can_access_entitled_module('ruminant');

    if (!$allowPoultryMenu && function_exists('farm_entitlement_runtime_delegated_expense_access')) {
        $allowPoultryMenu = farm_entitlement_runtime_delegated_expense_access('poultry', 'poultry_layer_expenses')
            || farm_entitlement_runtime_delegated_expense_access('poultry', 'poultry_broiler_expenses');
    }
    if (!$allowRuminantMenu && function_exists('farm_entitlement_runtime_delegated_expense_access')) {
        $allowRuminantMenu = farm_entitlement_runtime_delegated_expense_access('ruminant', 'ruminant_expenses');
    }

    if (!$allowPoultryMenu) {
        $tokens[] = 'hide-poultry-menu';
    }
    if (!$allowRuminantMenu) {
        $tokens[] = 'hide-ruminant-menu';
    }
}

// Hide unauthorized top-level/module links before first paint.
$navLinks = [
    '/inventory.php' => 'inventory',
    '/poultry/layer_expenses.php' => 'poultry_layer_expenses',
    '/poultry/broiler_expenses.php' => 'poultry_broiler_expenses',
    '/ruminant/animal_registry.php' => 'ruminant_animals',
    '/ruminant/ruminant_expenses.php' => 'ruminant_expenses',
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
    $navToken = 'hide-nav-' . str_replace('_', '-', $permission);
    $tokens[] = $navToken;
}
if (!$anyManagement && !permission_prepaint_privileged()) {
    $tokens[] = 'hide-manage-menu';
}

if (!$tokens) return;

$tokens = array_values(array_unique($tokens));
$tokenValue = implode(' ', $tokens);

$asset = BASE_URL . '/assets/css/permission-prepaint.css';
if (function_exists('versioned_asset')) {
    $asset = BASE_URL . versioned_asset(
        '/assets/css/permission-prepaint.css'
    );
}

$css = '<link rel="stylesheet" href="'
    . htmlspecialchars(
        $asset,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . '">';

ob_start(static function (string $html) use ($css, $tokenValue): string {
    if (stripos($html, '</head>') === false) return $html;

    $attribute = ' data-permission-prepaint="'
        . htmlspecialchars(
            $tokenValue,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
        . '"';

    $html = preg_replace(
        '/<html\b/i',
        '<html' . $attribute,
        $html,
        1
    ) ?? $html;

    return str_ireplace('</head>', $css . '</head>', $html);
});
