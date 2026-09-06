<?php
/**
 * Canonical runtime subscription boundary.
 *
 * Subscription entitlement is evaluated before legacy role/permission bypasses.
 * Platform Owner is exempt because customer data is accessed through explicit
 * tenant-view context rather than the owner workspace's farm_modules rows.
 */

if (!isset($_SESSION['user_id']) || !function_exists('user_can_access_entitled_module')) {
    return;
}

if (function_exists('isPlatformOwner') && isPlatformOwner()) {
    return;
}

if (!function_exists('farm_entitlement_runtime_path')) {
    function farm_entitlement_runtime_path(): string
    {
        return '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    }
}

if (!function_exists('farm_entitlement_runtime_ends_with')) {
    function farm_entitlement_runtime_ends_with(string $path, string $suffix): bool
    {
        return $path === $suffix || str_ends_with($path, $suffix);
    }
}

if (!function_exists('farm_entitlement_runtime_deny')) {
    function farm_entitlement_runtime_deny(string $module): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'GET' && defined('BASE_URL') && !headers_sent()) {
            header('Location: ' . BASE_URL . '/no_access.php');
            exit();
        }
        http_response_code(403);
        exit(ucfirst($module) . ' subscription access required.');
    }
}

if (!function_exists('farm_entitlement_runtime_remove_nav_dropdown')) {
    function farm_entitlement_runtime_remove_nav_dropdown(string $html, string $menuId): string
    {
        $pattern = '~<li class="nav-item dropdown">\s*<button[^>]*id="' . preg_quote($menuId, '~') . '"[^>]*>.*?</li>~s';
        return preg_replace($pattern, '', $html, 1) ?? $html;
    }
}

if (!function_exists('farm_entitlement_runtime_delegated_expense_access')) {
    function farm_entitlement_runtime_delegated_expense_access(string $module, string $permission): bool
    {
        if (!hasRole('sales_rep')) return false;
        if (!function_exists('current_farm_has_entitlement') || !current_farm_has_entitlement($module)) return false;
        return hasPermission(getUserType(), $permission);
    }
}

$path = farm_entitlement_runtime_path();
$routeModules = [
    '/poultry/layers_daily_record.php' => 'poultry',
    '/poultry/broiler_daily_record.php' => 'poultry',
    '/poultry/layer_feeds.php' => 'poultry',
    '/poultry/broiler_feeds.php' => 'poultry',
    '/poultry/health.php' => 'poultry',
    '/poultry/layer_expenses.php' => 'poultry',
    '/poultry/broiler_expenses.php' => 'poultry',
    '/ruminant/ruminant_daily_record.php' => 'ruminant',
    '/ruminant/animal_registry.php' => 'ruminant',
    '/ruminant/animal_view.php' => 'ruminant',
    '/ruminant/ruminant_feeds_record.php' => 'ruminant',
    '/ruminant/ruminant_expenses.php' => 'ruminant',
    '/management/sales_records.php' => 'sales',
];

$delegatedExpenseRoutes = [
    '/poultry/layer_expenses.php' => ['module' => 'poultry', 'permission' => 'poultry_layer_expenses'],
    '/poultry/broiler_expenses.php' => ['module' => 'poultry', 'permission' => 'poultry_broiler_expenses'],
    '/ruminant/ruminant_expenses.php' => ['module' => 'ruminant', 'permission' => 'ruminant_expenses'],
];

foreach ($routeModules as $suffix => $module) {
    if (!farm_entitlement_runtime_ends_with($path, $suffix)) continue;
    if (user_can_access_entitled_module($module)) continue;

    $delegated = $delegatedExpenseRoutes[$suffix] ?? null;
    if ($delegated
        && farm_entitlement_runtime_delegated_expense_access($delegated['module'], $delegated['permission'])) {
        continue;
    }

    farm_entitlement_runtime_deny($module);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
    $allowPoultry = user_can_access_entitled_module('poultry');
    $allowRuminant = user_can_access_entitled_module('ruminant');

    if (!$allowPoultry) {
        $allowPoultry = farm_entitlement_runtime_delegated_expense_access('poultry', 'poultry_layer_expenses')
            || farm_entitlement_runtime_delegated_expense_access('poultry', 'poultry_broiler_expenses');
    }
    if (!$allowRuminant) {
        $allowRuminant = farm_entitlement_runtime_delegated_expense_access('ruminant', 'ruminant_expenses');
    }

    if (!$allowPoultry || !$allowRuminant) {
        ob_start(static function (string $html) use ($allowPoultry, $allowRuminant): string {
            if (!$allowPoultry) $html = farm_entitlement_runtime_remove_nav_dropdown($html, 'poultryMenu');
            if (!$allowRuminant) $html = farm_entitlement_runtime_remove_nav_dropdown($html, 'ruminantMenu');
            return $html;
        });
    }
}
