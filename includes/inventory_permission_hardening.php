<?php
/**
 * Inventory authorization hardening bridge.
 *
 * Keeps the large legacy Inventory page intact while enforcing V2.3 granular
 * permissions at the request boundary:
 * - Inventory View is the parent/upper gate.
 * - Add Item and Update Stock require their exact action permission.
 * - non-admin managers remain limited to inventory belonging to their own
 *   Poultry/Ruminant operational scope.
 * - browser Inventory POSTs require CSRF tokens.
 * - legacy Inventory read endpoints cannot bypass Inventory View.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) return;

if (!function_exists('inventory_permission_privileged')) {
function inventory_permission_privileged(): bool
{
    return isPlatformOwner() || hasRole('farm_admin');
}
}

if (!function_exists('inventory_permission_has')) {
function inventory_permission_has(string $permission): bool
{
    return inventory_permission_privileged() || hasPermission(getUserType(), $permission);
}
}

if (!function_exists('inventory_permission_user_can_access_farm_type')) {
function inventory_permission_user_can_access_farm_type(string $farmType): bool
{
    if (inventory_permission_privileged()) return true;

    $farmType = strtolower(trim($farmType));
    if ($farmType === 'poultry') return checkAccess('poultry');
    if ($farmType === 'ruminant') return checkAccess('ruminant');
    if ($farmType === 'both') return checkAccess('poultry') || checkAccess('ruminant');

    return false;
}
}

if (!function_exists('inventory_permission_path')) {
function inventory_permission_path(): string
{
    return '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
}
}

if (!function_exists('inventory_permission_ends_with')) {
function inventory_permission_ends_with(string $path, string $suffix): bool
{
    return $path === $suffix || str_ends_with($path, $suffix);
}
}

if (!function_exists('inventory_permission_deny_json')) {
function inventory_permission_deny_json(string $message, int $status = 403): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
}

if (!function_exists('inventory_permission_deny_page')) {
function inventory_permission_deny_page(string $message = 'You do not have permission to access Inventory.'): void
{
    $_SESSION['error'] = $message;
    if (defined('BASE_URL') && !headers_sent()) {
        header('Location: ' . BASE_URL . '/no_access.php');
        exit();
    }
    http_response_code(403);
    exit($message);
}
}

if (!function_exists('inventory_permission_handle_delegated_add_item')) {
function inventory_permission_handle_delegated_add_item(PDO $pdo): void
{
    require_once dirname(__DIR__) . '/lib/stock_service.php';
    require_once dirname(__DIR__) . '/lib/inventory_financial.php';

    $farmId = requireCurrentFarmId();
    $farmType = trim((string)($_POST['farm_type'] ?? ''));
    $feedCategory = trim((string)($_POST['feed_category'] ?? 'general'));

    if ($feedCategory === 'ruminant') {
        $farmType = 'ruminant';
    } elseif (in_array($feedCategory, ['layer', 'broiler'], true)) {
        $farmType = 'poultry';
    }

    if (!inventory_permission_user_can_access_farm_type($farmType)) {
        $_SESSION['error'] = 'You do not have permission to add inventory items for that farm area.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }

    // A single-module manager may read shared/Both stock, but creating a new
    // Both item changes both operational areas and therefore requires both roles.
    if ($farmType === 'both' && !(checkAccess('poultry') && checkAccess('ruminant'))) {
        $_SESSION['error'] = 'Creating shared Poultry/Ruminant inventory requires access to both farm areas.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }

    $defaultProductionType = inventory_normalize_default_production_type(
        $farmType,
        $feedCategory,
        $_POST['default_production_type'] ?? 'shared'
    );

    if (!in_array($feedCategory, allowedFeedCategories(), true) || !in_array($farmType, allowedFarmTypes(), true)) {
        $_SESSION['error'] = 'That farm or usage classification is not enabled for this farm.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $categoryStmt = $pdo->prepare('SELECT id, financial_type FROM inventory_categories WHERE id = ? AND farm_id = ?');
    $categoryStmt->execute([$categoryId, $farmId]);
    $selectedCategory = $categoryStmt->fetch(PDO::FETCH_ASSOC);
    if (!$selectedCategory) {
        $_SESSION['error'] = 'The selected category does not belong to this farm.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }

    $financialClassification = (string)($selectedCategory['financial_type'] ?? 'other_stock');
    if (!inventory_financial_classification_is_valid($financialClassification)) {
        $_SESSION['error'] = 'The selected category needs a valid Financial Type before items can be added.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }
    if ($feedCategory !== 'general' && $financialClassification !== 'feed') {
        $_SESSION['error'] = 'Layer, Broiler and Ruminant Feed usage must use an Inventory Category whose Financial Type is Feed.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }

    $itemName = trim((string)($_POST['item_name'] ?? ''));
    $unit = trim((string)($_POST['unit'] ?? ''));
    $initialStockRaw = str_replace(',', '', trim((string)($_POST['initial_stock'] ?? '')));
    $minStockRaw = str_replace(',', '', trim((string)($_POST['min_stock'] ?? '')));
    $unitCostRaw = str_replace(',', '', trim((string)($_POST['unit_cost'] ?? '0')));
    $receivedDate = trim((string)($_POST['received_date'] ?? ''));
    $receivedDateObject = DateTime::createFromFormat('Y-m-d', $receivedDate);

    if ($itemName === '' || $unit === '') {
        $_SESSION['error'] = 'Item name and unit are required.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }
    if ($initialStockRaw === '' || !is_numeric($initialStockRaw) || (float)$initialStockRaw < 0) {
        $_SESSION['error'] = 'Initial stock must be a valid non-negative number.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }
    if ($minStockRaw === '' || !is_numeric($minStockRaw) || (float)$minStockRaw < 0) {
        $_SESSION['error'] = 'Minimum stock level must be a valid non-negative number.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }
    if ($unitCostRaw === '' || !is_numeric($unitCostRaw) || (float)$unitCostRaw < 0) {
        $_SESSION['error'] = 'Unit cost must be a valid non-negative number.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }
    if (!$receivedDateObject || $receivedDateObject->format('Y-m-d') !== $receivedDate || $receivedDate > date('Y-m-d')) {
        $_SESSION['error'] = 'Please provide a valid received date that is not in the future.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }

    $initialStock = (float)$initialStockRaw;
    $minStock = (float)$minStockRaw;
    $unitCost = (float)$unitCostRaw;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO stock_items
            (farm_id, item_name, category_id, current_stock, min_stock_level, unit, farm_type, feed_category, default_production_type, financial_classification, unit_cost)
            VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$farmId, $itemName, $categoryId, $minStock, $unit, $farmType, $feedCategory, $defaultProductionType, $financialClassification]);
        $itemId = (int)$pdo->lastInsertId();

        if ($initialStock > 0) {
            stock_apply_movement(
                $pdo,
                $farmId,
                $itemId,
                'received',
                $initialStock,
                $receivedDate,
                'Initial stock entry',
                (int)($_SESSION['user_id'] ?? 0),
                $farmType,
                $feedCategory,
                null,
                'inventory_manual',
                null,
                $unitCost,
                $defaultProductionType
            );
        }

        $pdo->commit();
        $_SESSION['success'] = 'Item added successfully!';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = safeUserExceptionMessage($e, 'The inventory item could not be added.');
    }

    header('Location: ' . BASE_URL . '/inventory.php');
    exit();
}
}

$inventoryPath = inventory_permission_path();
$inventoryMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$inventoryPrivileged = inventory_permission_privileged();

// Main Inventory page: View is always the upper gate.
if (inventory_permission_ends_with($inventoryPath, '/inventory.php')) {
    if (!$inventoryPrivileged && !inventory_permission_has('inventory')) {
        inventory_permission_deny_page();
    }

    if ($inventoryMethod === 'GET') {
        // Legacy Inventory forms were inconsistent about rendering CSRF fields.
        // Add the same current-session token to every POST form before first paint.
        ob_start(static function (string $html): string {
            if (!function_exists('csrf_field')) return $html;
            $tokenField = csrf_field();
            return preg_replace_callback(
                '~<form\b([^>]*)method=(["\x27])POST\2([^>]*)>~i',
                static fn(array $m): string => $m[0] . $tokenField,
                $html
            ) ?? $html;
        });
    } elseif ($inventoryMethod === 'POST') {
        require_valid_csrf_post();

        if (!$inventoryPrivileged) {
            if (isset($_POST['add_item'])) {
                if (!inventory_permission_has('inventory_add_new_item')) {
                    inventory_permission_deny_page('You do not have permission to add Inventory items.');
                }
                inventory_permission_handle_delegated_add_item($pdo);
            }

            if (isset($_POST['update_stock'])) {
                if (!inventory_permission_has('update_stock')) {
                    inventory_permission_deny_page('You do not have permission to update Inventory stock.');
                }

                $itemId = (int)($_POST['item_id'] ?? 0);
                $itemStmt = $pdo->prepare('SELECT farm_type FROM stock_items WHERE id = ? AND farm_id = ? LIMIT 1');
                $itemStmt->execute([$itemId, requireCurrentFarmId()]);
                $itemFarmType = $itemStmt->fetchColumn();
                if ($itemFarmType === false) {
                    inventory_permission_deny_page('Inventory item not found.');
                }
                if (!inventory_permission_user_can_access_farm_type((string)$itemFarmType)) {
                    inventory_permission_deny_page('You do not have permission to update this Inventory item.');
                }
            }

            // Category administration, archive/restore and purge remain Farm Admin
            // responsibilities; no granular specialist permission exists for them.
            $specialistAllowedPost = isset($_POST['add_item']) || isset($_POST['update_stock']);
            if (!$specialistAllowedPost) {
                inventory_permission_deny_page('This Inventory administration action requires Farm Admin access.');
            }
        }
    }
}

// Legacy Inventory reads: View is mandatory and item-level module scope remains
// narrow even when the user has the generic Inventory View permission.
$inventoryItemReadRoute = null;
if (inventory_permission_ends_with($inventoryPath, '/api/get_item_details.php')) {
    $inventoryItemReadRoute = ['param' => 'id', 'json' => true];
} elseif (inventory_permission_ends_with($inventoryPath, '/api/get_stock_history.php')) {
    $inventoryItemReadRoute = ['param' => 'item_id', 'json' => true];
} elseif (inventory_permission_ends_with($inventoryPath, '/api/stock_history.php')) {
    $inventoryItemReadRoute = ['param' => 'item_id', 'json' => false];
}

if ($inventoryItemReadRoute !== null) {
    if (!$inventoryPrivileged && !inventory_permission_has('inventory')) {
        if ($inventoryItemReadRoute['json']) inventory_permission_deny_json('You do not have permission to view Inventory data.');
        inventory_permission_deny_page('You do not have permission to view Inventory stock history.');
    }

    $itemId = (int)($_GET[$inventoryItemReadRoute['param']] ?? 0);
    if ($itemId > 0) {
        $itemStmt = $pdo->prepare('SELECT farm_type FROM stock_items WHERE id = ? AND farm_id = ? LIMIT 1');
        $itemStmt->execute([$itemId, requireCurrentFarmId()]);
        $itemFarmType = $itemStmt->fetchColumn();

        if ($itemFarmType !== false && !$inventoryPrivileged && !inventory_permission_user_can_access_farm_type((string)$itemFarmType)) {
            if ($inventoryItemReadRoute['json']) inventory_permission_deny_json('You do not have access to this Inventory item.');
            inventory_permission_deny_page('You do not have access to this Inventory item.');
        }
    }
}

// Stock Summary is read-only but previously treated livestock module access as a
// substitute for Inventory View. Keep it permission-gated and narrow "both" to the
// manager's actual operational modules.
if (inventory_permission_ends_with($inventoryPath, '/api/get_stock_summary.php')) {
    if (!$inventoryPrivileged && !inventory_permission_has('inventory')) {
        inventory_permission_deny_json('You do not have permission to view Inventory stock summary data.');
    }

    if (!$inventoryPrivileged) {
        $canPoultry = checkAccess('poultry');
        $canRuminant = checkAccess('ruminant');
        $requestedType = strtolower(trim((string)($_GET['farm_type'] ?? 'both')));

        if ($requestedType === 'poultry' && !$canPoultry) {
            inventory_permission_deny_json('You do not have access to Poultry inventory data.');
        }
        if ($requestedType === 'ruminant' && !$canRuminant) {
            inventory_permission_deny_json('You do not have access to Ruminant inventory data.');
        }
        if ($requestedType === 'both') {
            if ($canPoultry && !$canRuminant) $_GET['farm_type'] = 'poultry';
            elseif ($canRuminant && !$canPoultry) $_GET['farm_type'] = 'ruminant';
            elseif (!$canPoultry && !$canRuminant) inventory_permission_deny_json('You do not have access to Inventory stock summary data.');
        }
    }
}
