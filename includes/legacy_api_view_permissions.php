<?php
/**
 * Legacy API/read-route permission bridge.
 *
 * Some older read endpoints historically enforced only module-role access even
 * after the V2.3 permission catalog introduced explicit View permissions. Keep
 * their existing tenant-scoped queries intact, but require the matching View
 * permission before endpoint code can return operational data.
 */

if (!isset($_SESSION['user_id'])) return;
if (!function_exists('isPlatformOwner') || !function_exists('hasRole') || !function_exists('hasPermission')) return;
if (isPlatformOwner() || hasRole('farm_admin')) return;

$legacyApiPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$legacyApiEndsWith = static fn(string $suffix): bool => $legacyApiPath === $suffix || str_ends_with($legacyApiPath, $suffix);

$legacyApiDenyJson = static function (string $message): void {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
};

$legacyApiRequiredPermission = null;

if ($legacyApiEndsWith('/api/check_ruminant_record.php')) {
    $legacyApiRequiredPermission = 'ruminant_daily';
} elseif ($legacyApiEndsWith('/api/check_record.php')) {
    $type = strtolower(trim((string)($_GET['type'] ?? '')));
    if ($type === 'layer') $legacyApiRequiredPermission = 'poultry_daily_layer';
    elseif ($type === 'broiler') $legacyApiRequiredPermission = 'poultry_daily_broiler';
} elseif ($legacyApiEndsWith('/api/get_record.php') || $legacyApiEndsWith('/api/get_previous_stock.php')) {
    $type = strtolower(trim((string)($_GET['type'] ?? '')));
    if ($type === 'layer') $legacyApiRequiredPermission = 'poultry_daily_layer';
    elseif ($type === 'broiler') $legacyApiRequiredPermission = 'poultry_daily_broiler';
    elseif ($type === 'ruminant') $legacyApiRequiredPermission = 'ruminant_daily';
} elseif ($legacyApiEndsWith('/api/get_item_details.php') || $legacyApiEndsWith('/api/get_stock_history.php')) {
    $legacyApiRequiredPermission = 'inventory';
} elseif ($legacyApiEndsWith('/api/get_chart_data.php')) {
    $type = strtolower(trim((string)($_GET['type'] ?? 'profit_loss')));
    $legacyApiRequiredPermission = match ($type) {
        'profit_loss' => 'profitability',
        'sales' => 'sales',
        'expenses' => 'expenses',
        'stock' => 'inventory',
        'production' => 'poultry_overview',
        default => null,
    };
} elseif ($legacyApiEndsWith('/api/stock_history.php')) {
    if (!hasPermission(getUserType(), 'inventory')) {
        $_SESSION['error'] = 'You do not have permission to view Inventory stock history.';
        header('Location: ' . BASE_URL . '/no_access.php');
        exit();
    }
    return;
}

if ($legacyApiRequiredPermission !== null && !hasPermission(getUserType(), $legacyApiRequiredPermission)) {
    $legacyApiDenyJson('You do not have permission to view this data.');
}
