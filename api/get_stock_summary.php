<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
requireLogin();

header('Content-Type: application/json');

$farmType = strtolower(trim((string)($_GET['farm_type'] ?? 'both')));
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
            echo json_encode(['error' => 'You do not have access to stock summary data.']);
            exit;
        }
    } elseif (!checkAccess($farmType)) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have access to this farm module.']);
        exit;
    }
}

// Get low stock count
if ($farmType === 'both') {
    $lowStockQuery = "SELECT COUNT(*) as low_stock_count
                      FROM stock_items
                      WHERE farm_id = ? AND farm_type IN ('poultry', 'ruminant', 'both')
                      AND current_stock <= min_stock_level";
    $lowStockStmt = $pdo->prepare($lowStockQuery);
    $lowStockStmt->execute([$tenantFarmId]);
} else {
    $lowStockQuery = "SELECT COUNT(*) as low_stock_count
                      FROM stock_items
                      WHERE farm_id = ? AND farm_type IN (?, 'both')
                      AND current_stock <= min_stock_level";
    $lowStockStmt = $pdo->prepare($lowStockQuery);
    $lowStockStmt->execute([$tenantFarmId, $farmType]);
}
$lowStockCount = $lowStockStmt->fetchColumn();

// Get total stock value
$valueQuery = "SELECT SUM(current_stock * 100) as total_value FROM stock_items 
               WHERE farm_id = ? AND farm_type IN (?, 'both')";
$valueStmt = $pdo->prepare($valueQuery);
$valueStmt->execute([$tenantFarmId, $farmType]);
$totalValue = $valueStmt->fetchColumn();

// Get recent stock changes
$changesQuery = "SELECT COUNT(*) as recent_changes FROM stock_transactions 
                 WHERE farm_id = ? AND farm_type = ? AND is_reversed = 0 AND transaction_date = CURDATE()";
$changesStmt = $pdo->prepare($changesQuery);
$changesStmt->execute([$tenantFarmId, $farmType]);
$recentChanges = $changesStmt->fetchColumn();

echo json_encode([
    'updated' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'low_stock_count' => $lowStockCount,
    'total_stock_value' => $totalValue,
    'recent_changes' => $recentChanges
]);
?>
