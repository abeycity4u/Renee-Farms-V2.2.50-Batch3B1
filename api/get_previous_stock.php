<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../lib/daily_population_continuity.php');
requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['type'], $_GET['date'])) {
    echo json_encode(['closing_stock' => null, 'error' => 'Missing parameters']);
    exit;
}

$type = $_GET['type'];
$date = $_GET['date'];
$cycleId = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : 0;
$animalType = isset($_GET['animal_type']) ? strtolower(trim($_GET['animal_type'])) : '';

$tableMap = [
    'layer' => ['table' => 'layer_daily_records', 'animal' => false, 'module' => 'poultry'],
    'broiler' => ['table' => 'broiler_daily_records', 'animal' => false, 'module' => 'poultry'],
    'ruminant' => ['table' => 'ruminant_daily_records', 'animal' => true, 'module' => 'ruminant'],
];

if (!isset($tableMap[$type]) || ($tableMap[$type]['animal'] && $animalType === '')) {
    echo json_encode(['closing_stock' => null, 'error' => 'Invalid parameters']);
    exit;
}

$isOwnerOrAdmin = isPlatformOwner() || hasRole('farm_admin');
$requiredModule = $tableMap[$type]['module'];
if (!$isOwnerOrAdmin && !checkAccess($requiredModule)) {
    http_response_code(403);
    echo json_encode(['closing_stock' => null, 'error' => 'You do not have access to this farm module.']);
    exit;
}

$farmId = requireCurrentFarmId();

/*
 * V3 population contract: the modal needs the authoritative population
 * immediately before movements on the selected target date. Keep the
 * historical response key "closing_stock" so existing Layer/Broiler/Ruminant
 * JavaScript clients remain unchanged.
 *
 * Legacy/no-baseline cycles fall through to the previous Daily Record chain.
 */
if ($cycleId > 0) {
    try {
        $canonicalOpening =
            daily_population_continuity_expected_opening(
                $pdo,
                $farmId,
                $cycleId,
                $type,
                $date,
                $tableMap[$type]['animal']
                    ? $animalType
                    : null
            );

        if ($canonicalOpening !== null) {
            echo json_encode([
                'closing_stock' =>
                    (int)$canonicalOpening['opening_stock'],
                'opening_stock' =>
                    (int)$canonicalOpening['opening_stock'],
                'tracking_status' => 'canonical',
                'source' => 'v3_population_ledger',
                'movement_totals' =>
                    $canonicalOpening['movement_totals'] ?? [],
            ]);
            exit;
        }
    } catch (Throwable $error) {
        http_response_code(400);
        echo json_encode([
            'closing_stock' => null,
            'error' =>
                $error instanceof InvalidArgumentException
                || $error instanceof DailyPopulationContinuityException
                    ? $error->getMessage()
                    : 'Unable to resolve canonical opening stock.',
        ]);
        exit;
    }
}

$sql = "SELECT * FROM {$tableMap[$type]['table']} WHERE record_date < ? AND farm_id = ?";
$params = [$date, $farmId];
if ($cycleId > 0) {
    $sql .= " AND cycle_id = ?";
    $params[] = $cycleId;
}
if ($tableMap[$type]['animal']) {
    $sql .= " AND LOWER(animal_type) = ?";
    $params[] = $animalType;
}
$sql .= " ORDER BY record_date DESC, id DESC LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    $closingStock = max(0, (float)$record['opening_stock'] - (float)$record['mortality']);
    echo json_encode(['closing_stock' => $closingStock, 'previous_record' => $record]);
} else {
    echo json_encode(['closing_stock' => null, 'message' => 'No earlier record found']);
}
?>
