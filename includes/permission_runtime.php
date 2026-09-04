<?php
/**
 * Runtime permission enforcement for routes that still contain legacy role/module
 * checks. Farm Admin and Platform Owner retain their administrative bypass, while
 * delegated operational roles must match the tenant permission matrix.
 */

require_once __DIR__ . '/functions.php';
require_once dirname(__DIR__) . '/lib/daily_feed_sync.php';

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
    $config = ['daily' => $dailyCapability, 'nav' => $navCapability, 'extra' => $extraCapability];
    $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) $json = '{}';

    return '<script>(function(){const cfg=' . $json . ';'
        . 'function blockCalendar(btn){btn.classList.remove("add-record-btn","edit-record-btn");btn.classList.add("no-action");btn.style.cursor="default";btn.addEventListener("click",function(e){e.preventDefault();e.stopImmediatePropagation();},true);}'
        . 'function stripActionColumn(){document.querySelectorAll("table").forEach(function(table){const headers=Array.from(table.querySelectorAll("thead th"));headers.forEach(function(th,index){if(th.textContent.trim().toLowerCase()==="actions"){table.querySelectorAll("tr").forEach(function(row){const cells=row.children;if(cells[index])cells[index].remove();});}});});}'
        . 'document.addEventListener("DOMContentLoaded",function(){'
        . 'const d=cfg.daily||null;if(d){'
        . 'if(!d.add){document.querySelectorAll("button[onclick*=\"openRecordModal\"]").forEach(function(el){el.remove();});}'
        . 'document.querySelectorAll(".calendar-day").forEach(function(btn){const text=btn.textContent||"";const hasRecord=btn.classList.contains("has-record")||btn.hasAttribute("data-opening-stock")||/record\\(s\\)/i.test(text);if((hasRecord&&!d.edit)||(!hasRecord&&!d.add))blockCalendar(btn);});'
        . 'if(!d.edit){document.querySelectorAll("table .edit-record-btn").forEach(function(el){el.remove();});}'
        . 'if(!d.delete){document.querySelectorAll("table button.btn-outline-danger,table button[onclick*=\"deleteLayerDailyRecord\"],table button[onclick*=\"deleteBroilerDailyRecord\"]").forEach(function(el){el.remove();});}'
        . 'if(!d.edit&&!d.delete)stripActionColumn();'
        . '}'
        . 'const x=cfg.extra||{};'
        . 'if(x.expenseAdd===false){document.querySelectorAll("button[data-bs-target=\"#addExpenseModal\"]").forEach(function(el){el.remove();});}'
        . 'if(x.feedAdd===false){document.querySelectorAll("button[data-bs-target=\"#addTransactionModal\"]").forEach(function(el){el.remove();});}'
        . 'if(x.salesAdd===false){document.querySelectorAll("button[data-bs-target=\"#addSaleModal\"],button[onclick*=\"addSale\"]").forEach(function(el){el.remove();});}'
        . 'if(x.salesPayment===false){document.querySelectorAll("button[data-bs-target*=\"payment\" i],button[onclick*=\"payment\" i],form button[name=\"record_payment\"]").forEach(function(el){el.remove();});}'
        . 'if(x.animalAdd===false){document.querySelectorAll("button[onclick*=\"newAnimal\"]").forEach(function(el){el.remove();});}'
        . 'if(x.animalEdit===false){document.querySelectorAll("button[onclick*=\"editAnimal\"]").forEach(function(el){el.remove();});}'
        . 'if(x.animalExit===false){document.querySelectorAll("button[onclick*=\"exitAnimal\"]").forEach(function(el){el.remove();});}'
        . 'const nav=cfg.nav||{};Object.keys(nav).forEach(function(suffix){if(nav[suffix])return;document.querySelectorAll("#appNavbar a[href]").forEach(function(a){try{const p=new URL(a.href,window.location.origin).pathname;if(p.endsWith(suffix))a.closest("li")?.remove();}catch(e){}});});'
        . 'document.querySelectorAll("#appNavbar .dropdown").forEach(function(drop){if(drop.querySelector("#manageMenu")&&!drop.querySelector(".dropdown-item[href]"))drop.remove();});'
        . '});})();</script>';
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
    '/poultry/layer_expenses.php' => 'poultry_layer_expenses',
    '/poultry/broiler_expenses.php' => 'poultry_broiler_expenses',
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
        $required = permission_runtime_existing_daily_record($pdo, 'layer') ? 'poultry_daily_layer_edit' : 'poultry_daily_layer_add';
        if (!permission_runtime_has($required)) permission_runtime_deny('You do not have permission to save this Layer daily record.');
    }
} elseif (permission_runtime_ends_with($path, '/poultry/broiler_daily_record.php')) {
    $dailyCapability = [
        'add' => permission_runtime_has('poultry_daily_broiler_add'),
        'edit' => permission_runtime_has('poultry_daily_broiler_edit'),
        'delete' => permission_runtime_has('poultry_daily_broiler_delete'),
    ];
    if ($method === 'POST' && isset($_POST['save_record'])) {
        $required = permission_runtime_existing_daily_record($pdo, 'broiler') ? 'poultry_daily_broiler_edit' : 'poultry_daily_broiler_add';
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
        $required = permission_runtime_existing_daily_record($pdo, 'ruminant') ? 'ruminant_daily_edit' : 'ruminant_daily_add';
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

            $recordId = (int)($_POST['record_id'] ?? 0);
            if ($recordId <= 0) {
                $_SESSION['error'] = 'Invalid daily record.';
            } else {
                $farmId = requireCurrentFarmId();
                $managedTypes = ['cattle', 'goat', 'sheep', 'other'];
                $recordStmt = $pdo->prepare('SELECT animal_type FROM ruminant_daily_records WHERE id = ? AND farm_id = ? LIMIT 1');
                $recordStmt->execute([$recordId, $farmId]);
                $animalType = strtolower((string)($recordStmt->fetchColumn() ?: ''));

                if (!in_array($animalType, $managedTypes, true)) {
                    $_SESSION['error'] = 'Daily record not found or you do not have permission to delete it.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        delete_daily_feed_usage($pdo, $farmId, $recordId, 'daily_ruminant_record');
                        $deleteStmt = $pdo->prepare('DELETE FROM ruminant_daily_records WHERE id = ? AND farm_id = ?');
                        $deleteStmt->execute([$recordId, $farmId]);
                        if ($deleteStmt->rowCount() !== 1) {
                            throw new RuntimeException('The daily record could not be deleted.');
                        }
                        $pdo->commit();
                        $_SESSION['success'] = 'Ruminant daily record deleted successfully.';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $_SESSION['error'] = safeUserExceptionMessage($e, 'The daily record could not be deleted.');
                    }
                }
            }

            $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? (string)$_GET['month'] : date('Y-m');
            $cycleId = max(0, (int)($_GET['cycle_id'] ?? 0));
            header('Location: ' . BASE_URL . '/ruminant/ruminant_daily_record.php?month=' . urlencode($month) . '&cycle_id=' . $cycleId);
            exit();
        }
    }
} elseif (permission_runtime_ends_with($path, '/poultry/layer_expenses.php')) {
    $extraCapability['expenseAdd'] = permission_runtime_has('poultry_layer_expenses_add');
    if ($method === 'POST' && isset($_POST['add_expense']) && !$extraCapability['expenseAdd']) permission_runtime_deny('You do not have permission to add Layer expenses.');
} elseif (permission_runtime_ends_with($path, '/poultry/broiler_expenses.php')) {
    $extraCapability['expenseAdd'] = permission_runtime_has('poultry_broiler_expenses_add');
    if ($method === 'POST' && isset($_POST['add_expense']) && !$extraCapability['expenseAdd']) permission_runtime_deny('You do not have permission to add Broiler expenses.');
} elseif (permission_runtime_ends_with($path, '/ruminant/ruminant_expenses.php')) {
    $extraCapability['expenseAdd'] = permission_runtime_has('ruminant_expenses_add');
    if ($method === 'POST' && isset($_POST['add_expense']) && !$extraCapability['expenseAdd']) permission_runtime_deny('You do not have permission to add Ruminant expenses.');
} elseif (permission_runtime_ends_with($path, '/management/sales_records.php')) {
    $extraCapability['salesAdd'] = permission_runtime_has('sales_add');
    $extraCapability['salesPayment'] = permission_runtime_has('sales_payment');
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
    '/poultry/layer_expenses.php' => permission_runtime_has('poultry_layer_expenses'),
    '/poultry/broiler_expenses.php' => permission_runtime_has('poultry_broiler_expenses'),
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
