<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../lib/farm_intelligence.php');
requireLogin();
requireBusinessReportAccess();

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'profit_loss';
$period = $_GET['period'] ?? 'month';

switch ($type) {
    case 'profit_loss':
        echo json_encode(getProfitLossData($period));
        break;
    
    case 'sales':
        echo json_encode(getSalesData($period));
        break;
    
    case 'expenses':
        echo json_encode(getExpenseData($period));
        break;
    
    case 'stock':
        echo json_encode(getStockData($period));
        break;
    
    case 'production':
        echo json_encode(getProductionData($period));
        break;
    
    default:
        echo json_encode(['error' => 'Invalid chart type']);
}

function chartFarmScope(): string {
    $access = getUserFarmType();
    $canChoose = isPlatformOwner() || hasRole('farm_admin','sales_rep');
    $requested = $canChoose ? ($_GET['farm_type'] ?? ($access === 'all' ? 'all' : $access)) : $access;
    if ($requested === 'both') $requested = count(accessibleFarmTypes()) === 2 ? 'all' : '';
    if ($requested === 'general' && farmHasModule('sales')) return 'general';
    return normalizeFarmType($requested, true, false, $canChoose);
}

function getProfitLossData($period) {
    global $pdo;
    $farmId = requireCurrentFarmId();
    $scope = chartFarmScope();
    if ($scope === '') return ['labels'=>[], 'values'=>[], 'revenue'=>[], 'cost'=>[]];
    $months = $period === 'year' ? 12 : 6;
    $rows = farm_intelligence_rolling_months($pdo, $farmId, $months, $scope);
    return [
        'labels'=>array_column($rows,'label'),
        'values'=>array_column($rows,'profit'),
        'revenue'=>array_column($rows,'revenue'),
        'cost'=>array_column($rows,'cost'),
    ];
}

function getSalesData($period) {
    global $pdo;
    $farmId = requireCurrentFarmId();
    $scope = chartFarmScope();
    if ($scope === '') return ['labels'=>[], 'poultry'=>[], 'ruminant'=>[], 'general'=>[]];
    $months = $period === 'year' ? 12 : 6;
    $anchor = new DateTimeImmutable(date('Y-m-01'));
    $labels=[]; $poultry=[]; $ruminant=[]; $general=[];
    for ($i=$months-1; $i>=0; $i--) {
        $m=$anchor->modify("-{$i} months"); $start=$m->format('Y-m-01'); $end=$m->format('Y-m-t');
        $labels[]=$m->format('M Y');
        foreach (['poultry','ruminant','general'] as $type) {
            $allowed = $scope === 'all' || $scope === $type;
            $value = $allowed ? (float)farm_intelligence_summary($pdo,$farmId,$start,$end,$type)['revenue'] : 0.0;
            if ($type==='poultry') $poultry[]=$value;
            elseif ($type==='ruminant') $ruminant[]=$value;
            else $general[]=$value;
        }
    }
    return compact('labels','poultry','ruminant','general');
}

function getExpenseData($period) {
    global $pdo;
    $farmId=requireCurrentFarmId(); $scope=chartFarmScope();
    if ($scope==='') return ['labels'=>[],'values'=>[]];
    $months=$period==='year'?12:6;
    $start=(new DateTimeImmutable(date('Y-m-01')))->modify('-'.($months-1).' months')->format('Y-m-01');
    $end=date('Y-m-t');
    $rows=farm_intelligence_expense_breakdown($pdo,$farmId,$start,$end,$scope);
    return ['labels'=>array_column($rows,'category'),'values'=>array_column($rows,'total_amount')];
}

function getStockData($period) {
    global $pdo;
    $farmId = requireCurrentFarmId();
    $scope = chartFarmScope();
    if ($scope === '') return ['labels'=>[], 'datasets'=>[]];
    $limit = $period == 'week' ? 7 : 30;
    $dateLimit = date('Y-m-d', strtotime("-{$limit} days"));

    // Reconstruct physical stock from the complete posted event stream.
    // Reversed originals remain in the ledger because their linked reversal
    // cancels them; filtering them out would double-count restorations.
    $query = "SELECT t.stock_item_id, s.item_name, t.transaction_type, t.quantity,
                     t.transaction_date, t.created_at, t.id
              FROM stock_transactions t
              JOIN stock_items s ON t.stock_item_id = s.id AND s.farm_id = t.farm_id
              WHERE t.farm_id = ?";
    $params = [$farmId];
    if ($scope === 'poultry' || $scope === 'ruminant') {
        $query .= " AND s.farm_type IN (?, 'both')";
        $params[] = $scope;
    } elseif ($scope === 'general') {
        $query .= " AND s.farm_type = 'general'";
    }
    $query .= " ORDER BY t.created_at, t.id";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $running = [];
    $points = [];
    $names = [];
    foreach ($rows as $row) {
        $itemId = (int)$row['stock_item_id'];
        if (!isset($running[$itemId])) $running[$itemId] = 0.0;
        $running[$itemId] += $row['transaction_type'] === 'received' ? (float)$row['quantity'] : -(float)$row['quantity'];
        $names[$itemId] = $row['item_name'];
        $date = (string)$row['transaction_date'];
        if ($date >= $dateLimit) {
            $points[$itemId][$date] = round($running[$itemId], 2);
        }
    }

    $dates = [];
    foreach ($points as $itemPoints) {
        foreach (array_keys($itemPoints) as $date) $dates[$date] = true;
    }
    $dates = array_keys($dates);
    sort($dates);

    $datasets = [];
    foreach ($points as $itemId => $itemPoints) {
        $series = [];
        $last = null;
        foreach ($dates as $date) {
            if (array_key_exists($date, $itemPoints)) $last = $itemPoints[$date];
            $series[] = $last;
        }
        $datasets[] = [
            'label' => $names[$itemId],
            'data' => $series,
            'fill' => false
        ];
    }

    return [
        'labels' => array_map(static fn($date) => date('d M', strtotime($date)), $dates),
        'datasets' => $datasets
    ];
}

function getProductionData($period) {
    global $pdo;
    if (!isPlatformOwner() && !checkAccess('poultry')) return ['labels'=>[], 'eggs'=>[], 'rates'=>[]];
    $farmId = requireCurrentFarmId();
    
    $limit = $period == 'month' ? 30 : 7;
    
    $query = "SELECT 
                record_date,
                egg_production,
                laying_rate
              FROM layer_daily_records
              WHERE farm_id = ? AND record_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              ORDER BY record_date";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$farmId, $limit]);
    $data = $stmt->fetchAll();
    
    $labels = [];
    $eggs = [];
    $rates = [];
    
    foreach ($data as $row) {
        $labels[] = date('d M', strtotime($row['record_date']));
        $eggs[] = $row['egg_production'];
        $rates[] = $row['laying_rate'];
    }
    
    return ['labels' => $labels, 'eggs' => $eggs, 'rates' => $rates];
}
?>
