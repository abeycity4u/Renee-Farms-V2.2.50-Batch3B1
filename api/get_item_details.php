<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/functions.php');
requireLogin();

header('Content-Type: application/json');

$userType = getUserType();
$isOwnerOrAdmin = isPlatformOwner() || hasRole('farm_admin');
$hasInventoryPermission = hasPermission($userType, 'inventory');
$hasPoultryAccess = checkAccess('poultry');
$hasRuminantAccess = checkAccess('ruminant');

if (!($isOwnerOrAdmin || $hasInventoryPermission || $hasPoultryAccess || $hasRuminantAccess)) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have access to inventory data.']);
    exit;
}

if (isset($_GET['id'])) {
    $itemId = $_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ? AND farm_id = ?");
    $stmt->execute([$itemId, requireCurrentFarmId()]);
    $item = $stmt->fetch();
    
    if ($item) {
        if (!($isOwnerOrAdmin || $hasInventoryPermission)) {
            $itemFarmType = strtolower((string)($item['farm_type'] ?? ''));
            $canReadItem = ($itemFarmType === 'poultry' && $hasPoultryAccess)
                || ($itemFarmType === 'ruminant' && $hasRuminantAccess)
                || ($itemFarmType === 'both' && ($hasPoultryAccess || $hasRuminantAccess));

            if (!$canReadItem) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have access to this inventory item.']);
                exit;
            }
        }

        echo json_encode($item);
    } else {
        echo json_encode(['error' => 'Item not found']);
    }
} else {
    echo json_encode(['error' => 'Item ID required']);
}
?>
