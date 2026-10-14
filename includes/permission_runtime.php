<?php
/**
 * Runtime permission enforcement for routes that still contain legacy role/module
 * checks. Farm Admin and Platform Owner retain their administrative bypass, while
 * delegated operational roles must match the tenant permission matrix.
 */

require_once __DIR__ . '/functions.php';
require_once dirname(__DIR__) . '/lib/daily_feed_sync.php';
require_once dirname(__DIR__) . '/lib/daily_record_revision_cutover_gate.php';

if (!function_exists('permission_runtime_privileged')) {
function permission_runtime_privileged(): bool
{
    return isPlatformOwner() || hasRole('farm_admin');
}
}

if (!function_exists('permission_runtime_has')) {
function permission_runtime_has(string $permission): bool
{
    return permission_runtime_privileged() || hasPermission(getUserType(), $permission);
}
}

if (!function_exists('permission_runtime_path')) {
function permission_runtime_path(): string
{
    $path = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    return '/' . ltrim(str_replace('\\', '/', $path), '/');
}
}

if (!function_exists('permission_runtime_ends_with')) {
function permission_runtime_ends_with(string $path, string $suffix): bool
{
    return $path === $suffix || str_ends_with($path, $suffix);
}
}

if (!function_exists('permission_runtime_deny')) {
function permission_runtime_deny(string $message = 'You do not have permission to perform this action.'): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
        header('Location: ' . BASE_URL . '/no_access.php');
        exit();
    }
    http_response_code(403);
    exit($message);
}
}

/**
 * Central B12 future operation-permission decision. With the safety fuse off,
 * preserve today's legacy POST-time lookup unchanged. Once the full cutover
 * is separately approved, derive permission ONLY from explicit browser
 * intent; the transaction-bound save gate then verifies immutable identity.
 */
if (!function_exists('permission_runtime_daily_save_permission')) {
function permission_runtime_daily_save_permission(PDO $pdo, string $type): string
{
    if (daily_record_revision_cutover_enabled()) {
        require_once dirname(__DIR__) . '/lib/daily_record_revision_capabilities.php';
        try {
            $intent = daily_record_revision_parse_write_intent(
                $_POST['write_intent'] ?? null,
                $_POST['record_id'] ?? null,
                $_POST['expected_revision'] ?? null
            );
            return daily_record_revision_operation_permission($type, $intent['mode']);
        } catch (DailyRecordRevisionConflict $error) {
            permission_runtime_deny('Daily Record form identity is stale. Reload the selection.');
        }
    }
    $capabilities = [
        'layer' => ['add'=>'poultry_daily_layer_add', 'edit'=>'poultry_daily_layer_edit'],
        'broiler' => ['add'=>'poultry_daily_broiler_add', 'edit'=>'poultry_daily_broiler_edit'],
        'ruminant' => ['add'=>'ruminant_daily_add', 'edit'=>'ruminant_daily_edit'],
    ];
    if (!isset($capabilities[$type])) {
        permission_runtime_deny('Invalid Daily Record operation.');
    }
    return permission_runtime_existing_daily_record($pdo, $type)
        ? $capabilities[$type]['edit'] : $capabilities[$type]['add'];
}
}

if (!function_exists('permission_runtime_existing_daily_record')) {
function permission_runtime_existing_daily_record(PDO $pdo, string $kind): bool
{
    $farmId = requireCurrentFarmId();
    $date = trim((string)($_POST['record_date'] ?? ''));
    $cycleId = (int)($_POST['cycle_id'] ?? $_GET['cycle_id'] ?? 0);
    if ($date === '') return false;

    if ($kind === 'layer' || $kind === 'broiler') {
        $table = $kind === 'layer' ? 'layer_daily_records' : 'broiler_daily_records';
        $sql = "SELECT id FROM {$table} WHERE farm_id = ? AND record_date = ?";
        $params = [$farmId, $date];
        if ($cycleId > 0) {
            $sql .= ' AND cycle_id = ?';
            $params[] = $cycleId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    if ($kind === 'ruminant') {
        $animalType = strtolower(trim((string)($_POST['animal_type'] ?? '')));
        if ($animalType === '') return false;
        $sql = 'SELECT id FROM ruminant_daily_records WHERE farm_id = ? AND record_date = ? AND LOWER(animal_type) = ?';
        $params = [$farmId, $date, $animalType];
        if ($cycleId > 0) {
            $sql .= ' AND cycle_id = ?';
            $params[] = $cycleId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    return false;
}
}

if (!function_exists('permission_runtime_client_script')) {
function permission_runtime_client_script(array $dailyCapability, array $navCapability, array $extraCapability = []): string
{
    $config = [
        'daily' => $dailyCapability,
        'nav' => $navCapability,
        'extra' => $extraCapability,
    ];

    $json = json_encode(
        $config,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        $json = '{}';
    }

    $encodedConfig = htmlspecialchars(
        $json,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $asset = BASE_URL . '/assets/js/permission-runtime.js';

    if (function_exists('versioned_asset')) {
        $asset = BASE_URL . versioned_asset('/assets/js/permission-runtime.js');
    }

    return '<div id="permissionRuntimeConfig" hidden data-config="'
        . $encodedConfig
        . '"></div>'
        . '<script src="'
        . htmlspecialchars($asset, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"></script>';
}
}

if (!isset($_SESSION['user_id'])) return;

$path = permission_runtime_path();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$routeViews = [
    '/poultry/layers_daily_record.php' => 'poultry_daily_layer',
    '/poultry/broiler_daily_record.php' => 'poultry_daily_broiler',
    '/poultry/layer_feeds.php' => 'poultry_feeds',
    '/poultry/broiler_feeds.php' => 'poultry_feeds',
    '/poultry/health.php' => 'poultry_health',
    '/ruminant/ruminant_daily_record.php' => 'ruminant_daily',
    '/ruminant/animal_registry.php' => 'ruminant_animals',
    '/ruminant/animal_view.php' => 'ruminant_animals',
    '/ruminant/ruminant_feeds_record.php' => 'ruminant_feeds',
    '/ruminant/ruminant_expenses.php' => 'ruminant_expenses',
    '/inventory.php' => 'inventory',
    '/management/sales_records.php' => 'sales',
    '/management/expenses.php' => 'expenses',
    '/management/poultry_ruminant_report.php' => 'reports',
    '/management/reports.php' => 'reports',
    '/management/intelligence.php' => 'farm_intelligence',
    '/management/profitability.php' => 'profitability',
    '/management/production_cycles.php' => 'production_cycles',
    '/management/users.php' => 'users',
];

foreach ($routeViews as $suffix => $permission) {
    if (permission_runtime_ends_with($path, $suffix) && !permission_runtime_has($permission)) {
        permission_runtime_deny('You do not have permission to view this page.');
    }
}

$dailyCapability = [];
$extraCapability = [];
if (permission_runtime_ends_with($path, '/poultry/layers_daily_record.php')) {
    $dailyCapability = [
        'add' => permission_runtime_has('poultry_daily_layer_add'),
        'edit' => permission_runtime_has('poultry_daily_layer_edit'),
        'delete' => permission_runtime_has('poultry_daily_layer_delete'),
    ];
    if ($method === 'POST' && isset($_POST['save_record'])) {
        $required = permission_runtime_daily_save_permission($pdo, 'layer');
        if (!permission_runtime_has($required)) permission_runtime_deny('You do not have permission to save this Layer daily record.');
    }
} elseif (permission_runtime_ends_with($path, '/poultry/broiler_daily_record.php')) {
    $dailyCapability = [
        'add' => permission_runtime_has('poultry_daily_broiler_add'),
        'edit' => permission_runtime_has('poultry_daily_broiler_edit'),
        'delete' => permission_runtime_has('poultry_daily_broiler_delete'),
    ];
    if ($method === 'POST' && isset($_POST['save_record'])) {
        $required = permission_runtime_daily_save_permission($pdo, 'broiler');
        if (!permission_runtime_has($required)) permission_runtime_deny('You do not have permission to save this Broiler daily record.');
    }
} elseif (permission_runtime_ends_with($path, '/poultry/layer_feeds.php') || permission_runtime_ends_with($path, '/poultry/broiler_feeds.php')) {
    $extraCapability['feedAdd'] = permission_runtime_has('poultry_feeds_add');
    if ($method === 'POST' && isset($_POST['add_transaction']) && !$extraCapability['feedAdd']) {
        permission_runtime_deny('You do not have permission to record Poultry feed transactions.');
    }
} elseif (permission_runtime_ends_with($path, '/ruminant/ruminant_daily_record.php')) {
    $dailyCapability = [
        'add' => permission_runtime_has('ruminant_daily_add'),
        'edit' => permission_runtime_has('ruminant_daily_edit'),
        'delete' => permission_runtime_has('ruminant_daily_delete'),
    ];
    if ($method === 'POST' && isset($_POST['save_record'])) {
        $required = permission_runtime_daily_save_permission($pdo, 'ruminant');
        if (!permission_runtime_has($required)) permission_runtime_deny('You do not have permission to save this Ruminant daily record.');
    }
    if ($method === 'POST' && isset($_POST['delete_record'])) {
        if (!$dailyCapability['delete']) {
            permission_runtime_deny('You do not have permission to delete Ruminant daily records.');
        }

        // The legacy page still executes delegated deletes only for Farm Admin.
        // Handle an explicitly permitted Ruminant Manager here before that old
        // guard runs, while preserving the page's tenant/CSRF/feed-restoration rules.
        if (!permission_runtime_privileged()) {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                exit('Invalid request token.');
            }

            // B12 FUTURE: preserve the same 409 JSON contract as the
            // privileged Ruminant page; never redirect stale AJAX deletes.
            $b12DeleteAjax = daily_record_revision_cutover_enabled();
            if ($b12DeleteAjax) {
                require_once dirname(__DIR__) . '/lib/daily_record_revision_delete_http.php';
                daily_record_revision_delete_require_ajax($_SERVER);
            }

            $recordId = (int)($_POST['record_id'] ?? 0);
            if ($recordId <= 0) {
                if ($b12DeleteAjax) {
                    daily_record_revision_delete_http_send(
                        new InvalidArgumentException('Invalid daily record ID.')
                    );
                }
                $_SESSION['error'] = 'Invalid daily record.';
            } else {
                $farmId = requireCurrentFarmId();
                try {
                    /*
                     * Delegated managers use the exact same source -> stock
                     * -> population -> deletion transaction as Farm Admin.
                     * Permission and CSRF remain enforced before this call.
                     */
                    require_once dirname(__DIR__) . '/lib/ruminant_daily_record_deletion.php';
                    ruminant_daily_record_delete_canonical(
                        $pdo,
                        $farmId,
                        $recordId,
                        isset($_SESSION['user_id'])
                            ? (int)$_SESSION['user_id']
                            : null,
                        $_POST
                    );
                    if ($b12DeleteAjax) {
                        daily_record_revision_delete_http_send(null, $recordId);
                    }
                    $_SESSION['success'] = 'Ruminant daily record deleted successfully.';
                } catch (Throwable $e) {
                    if ($b12DeleteAjax) {
                        daily_record_revision_delete_http_send($e);
                    }
                    $_SESSION['error'] = safeUserExceptionMessage(
                        $e,
                        'The daily record could not be deleted.'
                    );
                }
            }

            $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? (string)$_GET['month'] : date('Y-m');
            $cycleId = max(0, (int)($_GET['cycle_id'] ?? 0));
            header('Location: ' . BASE_URL . '/ruminant/ruminant_daily_record.php?month=' . urlencode($month) . '&cycle_id=' . $cycleId);
            exit();
        }
    }
} elseif (permission_runtime_ends_with($path, '/ruminant/ruminant_expenses.php')) {
    $extraCapability['expenseAdd'] = permission_runtime_has('ruminant_expenses_add');
    $extraCapability['expenseEdit'] = permission_runtime_has('ruminant_expenses_edit');
    $extraCapability['expenseDelete'] = permission_runtime_has('ruminant_expenses_delete');
    if ($method === 'POST' && isset($_POST['add_expense']) && !$extraCapability['expenseAdd']) permission_runtime_deny('You do not have permission to add Ruminant expenses.');
} elseif (permission_runtime_ends_with($path, '/management/sales_records.php')) {
    $extraCapability['salesAdd'] = permission_runtime_has('sales_add');
    $extraCapability['salesPayment'] = permission_runtime_has('sales_payment');
    $extraCapability['salesEdit'] = permission_runtime_has('sales_edit');
    $extraCapability['salesDelete'] = permission_runtime_has('sales_delete');
    if ($method === 'POST' && isset($_POST['add_sale']) && !$extraCapability['salesAdd']) permission_runtime_deny('You do not have permission to add sales.');
    if ($method === 'POST' && isset($_POST['record_payment']) && !$extraCapability['salesPayment']) permission_runtime_deny('You do not have permission to record customer payments.');
} elseif (permission_runtime_ends_with($path, '/ruminant/animal_registry.php')) {
    $extraCapability['animalAdd'] = permission_runtime_has('ruminant_animals_add');
    $extraCapability['animalEdit'] = permission_runtime_has('ruminant_animals_edit');
    $extraCapability['animalExit'] = permission_runtime_has('ruminant_animals_exit');
    if ($method === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            $required = ((int)($_POST['id'] ?? 0) > 0) ? 'ruminant_animals_edit' : 'ruminant_animals_add';
            if (!permission_runtime_has($required)) permission_runtime_deny('You do not have permission to save this animal record.');
        } elseif ($action === 'manual_exit' && !permission_runtime_has('ruminant_animals_exit')) {
            permission_runtime_deny('You do not have permission to record animal exits.');
        }
    }
}

$navCapability = [
    '/inventory.php' => permission_runtime_has('inventory'),
    '/ruminant/animal_registry.php' => permission_runtime_has('ruminant_animals'),
    '/management/sales_records.php' => permission_runtime_has('sales'),
    '/management/expenses.php' => permission_runtime_has('expenses'),
    '/management/poultry_ruminant_report.php' => permission_runtime_has('reports'),
    '/management/reports.php' => permission_runtime_has('reports'),
    '/management/intelligence.php' => permission_runtime_has('farm_intelligence'),
    '/management/profitability.php' => permission_runtime_has('profitability'),
    '/management/production_cycles.php' => permission_runtime_has('production_cycles'),
    '/management/users.php' => permission_runtime_has('users'),
];

if ($method === 'GET' && ($dailyCapability || $navCapability || $extraCapability)) {
    ob_start(static function (string $html) use ($dailyCapability, $navCapability, $extraCapability): string {
        if (stripos($html, '</body>') === false) return $html;
        return str_ireplace('</body>', permission_runtime_client_script($dailyCapability, $navCapability, $extraCapability) . '</body>', $html);
    });
}
