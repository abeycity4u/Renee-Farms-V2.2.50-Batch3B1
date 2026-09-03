<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
requireLogin();

header('Content-Type: application/json');

$farmType = strtolower(trim((string)($_GET['farm_type'] ?? 'both')));
$date = $_GET['date'] ?? date('Y-m-d');
$tenantFarmId = requireCurrentFarmId();

if (!in_array($farmType, ['poultry', 'ruminant', 'both'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid farm type']);
    exit;
}

if (!(isPlatformOwner() || hasRole('farm_admin'))) {
    if ($farmType === 'both') {
        $accessibleTypes = array_values(array_intersect(accessibleFarmTypes(), ['poultry', 'ruminant']));
        if (count($accessibleTypes) === 1) {
            $farmType = $accessibleTypes[0];
        } elseif (count($accessibleTypes) === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'You do not have access to pending-task data.']);
            exit;
        }
    } elseif (!checkAccess($farmType)) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have access to this farm module.']);
        exit;
    }
}

$pendingTasks = 0;

// Check for missing daily records
if ($farmType === 'poultry' || $farmType === 'both') {
    $layerCheck = $pdo->prepare("SELECT COUNT(*) FROM layer_daily_records WHERE record_date = ? AND farm_id = ?");
    $layerCheck->execute([$date, $tenantFarmId]);
    if ($layerCheck->fetchColumn() == 0) {
        $pendingTasks++;
    }
    
    $broilerCheck = $pdo->prepare("SELECT COUNT(*) FROM broiler_daily_records WHERE record_date = ? AND farm_id = ?");
    $broilerCheck->execute([$date, $tenantFarmId]);
    if ($broilerCheck->fetchColumn() == 0) {
        $pendingTasks++;
    }
}

if ($farmType === 'ruminant' || $farmType === 'both') {
    $ruminantCheck = $pdo->prepare("SELECT COUNT(*) FROM ruminant_daily_records WHERE record_date = ? AND farm_id = ?");
    $ruminantCheck->execute([$date, $tenantFarmId]);
    if ($ruminantCheck->fetchColumn() == 0) {
        $pendingTasks++;
    }
}

// Check for low stock items
if ($farmType === 'both') {
    $lowStockCheck = $pdo->prepare("SELECT COUNT(*) FROM stock_items
                               WHERE farm_id = ? AND farm_type IN ('poultry', 'ruminant', 'both')
                               AND current_stock <= min_stock_level");
    $lowStockCheck->execute([$tenantFarmId]);
} else {
    $lowStockCheck = $pdo->prepare("SELECT COUNT(*) FROM stock_items
                               WHERE farm_id = ? AND farm_type IN (?, 'both')
                               AND current_stock <= min_stock_level");
    $lowStockCheck->execute([$tenantFarmId, $farmType]);
}
$lowStockCount = $lowStockCheck->fetchColumn();

echo json_encode([
    'pending_tasks' => $pendingTasks,
    'low_stock_items' => $lowStockCount,
    'date' => $date,
    'farm_type' => $farmType
]);
?>
