<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
requireLogin();

header('Content-Type: application/json');

$isOwnerOrAdmin = isPlatformOwner() || hasRole('farm_admin');
if (!$isOwnerOrAdmin && !checkAccess('poultry')) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have access to the poultry module.']);
    exit;
}

if (isset($_GET['type']) && isset($_GET['date'])) {
    $type = $_GET['type'];
    $date = $_GET['date'];
    $cycleId = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : 0;
    $exists = false;
    
    if ($type === 'layer') {
        $sql = "SELECT id FROM layer_daily_records WHERE record_date = ? AND farm_id = ?";
        $params = [$date, requireCurrentFarmId()];
        if ($cycleId > 0) {
            $sql .= " AND cycle_id = ?";
            $params[] = $cycleId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exists = $stmt->fetch() ? true : false;
    } elseif ($type === 'broiler') {
        $sql = "SELECT id FROM broiler_daily_records WHERE record_date = ? AND farm_id = ?";
        $params = [$date, requireCurrentFarmId()];
        if ($cycleId > 0) {
            $sql .= " AND cycle_id = ?";
            $params[] = $cycleId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exists = $stmt->fetch() ? true : false;
    }
    
    echo json_encode(['exists' => $exists]);
}
?>
