<?php
$appEnv = getenv('APP_ENV') ?: 'production';
if ($appEnv === 'local' || $appEnv === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}
?>

<?php require_once(__DIR__ . '/init.php'); ?>
<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/includes/functions.php');
require_once(__DIR__ . '/includes/dashboard_livestock_snapshot.php');
require_once(__DIR__ . '/lib/farm_intelligence.php');
require_once(__DIR__ . '/lib/transaction_actor_display.php');
requireLogin();

$userType = getUserType();
$farmAccess = getUserFarmType();
$farmAccessLabel = currentAccessLabel();
if ($farmAccess === 'all') {
    $farmAccess = 'both';
}
// General sales are a durable, neutral classification, but only an active
// livestock scope may inherit them. A stale specialist role must not expose
// neutral sales after that livestock module has been disabled for the farm.
// The combined scope inherits them whenever both livestock modules are
// enabled. Historical neutral sales remain part of its totals even if the
// farm subsequently disables the Sales module.
$includeGeneralSales = in_array($farmAccess, enabledFarmTypes(), true)
    || ($farmAccess === 'both' && count(enabledFarmTypes()) === 2);

// Get current stock levels
$tenantFarmId = requireCurrentFarmId();
if ($farmAccess === 'both') {
    $stockQuery = "SELECT * FROM stock_items 
                   WHERE farm_id = ? AND farm_type IN ('poultry', 'ruminant', 'both')
                   AND is_active = 1 
                   ORDER BY current_stock ASC";
    $stockStmt = $pdo->prepare($stockQuery);
    $stockStmt->execute([$tenantFarmId]);
} else {
    $stockQuery = "SELECT * FROM stock_items WHERE farm_id = ? AND farm_type IN (?, 'both') AND is_active = 1 ORDER BY current_stock ASC";
    $stockStmt = $pdo->prepare($stockQuery);
    $stockStmt->execute([$tenantFarmId, $farmAccess]);
}
$stockItems = $stockStmt->fetchAll();

// Get today's transactions
$today = date('Y-m-d');
if ($farmAccess === 'both') {
    $transQuery = "SELECT t.*, s.item_name, s.unit FROM stock_transactions t
                   JOIN stock_items s ON t.stock_item_id = s.id AND s.is_active = 1
                   WHERE t.farm_id = ? AND s.farm_id = ? AND t.transaction_date = ? AND t.is_reversed = 0
                   ORDER BY t.id DESC LIMIT 10";
    $transStmt = $pdo->prepare($transQuery);
    $transStmt->execute([$tenantFarmId, $tenantFarmId, $today]);
} else {
    $transQuery = "SELECT t.*, s.item_name, s.unit FROM stock_transactions t
                   JOIN stock_items s ON t.stock_item_id = s.id AND s.is_active = 1
                   WHERE t.farm_id = ? AND s.farm_id = ? AND t.farm_type = ? AND t.transaction_date = ? AND t.is_reversed = 0
                   ORDER BY t.id DESC LIMIT 10";
    $transStmt = $pdo->prepare($transQuery);
    $transStmt->execute([$tenantFarmId, $tenantFarmId, $farmAccess, $today]);
}
$todayTransactions = $transStmt->fetchAll();

// Get low stock items
if ($farmAccess === 'both') {
    $lowStockQuery = "SELECT * FROM stock_items
                      WHERE farm_id = ? AND farm_type IN ('poultry', 'ruminant', 'both')
                      AND is_active = 1
                      AND current_stock <= min_stock_level";
    $lowStockStmt = $pdo->prepare($lowStockQuery);
    $lowStockStmt->execute([$tenantFarmId]);
} else {
    $lowStockQuery = "SELECT * FROM stock_items
                      WHERE farm_id = ? AND farm_type IN (?, 'both')
                      AND is_active = 1
                      AND current_stock <= min_stock_level";
    $lowStockStmt = $pdo->prepare($lowStockQuery);
    $lowStockStmt->execute([$tenantFarmId, $farmAccess]);
}
$lowStockItems = $lowStockStmt->fetchAll();

// Get recent sales
if ($farmAccess === 'both') {
    $salesQuery = "SELECT s.*, u.full_name as seller, u.user_type AS seller_user_type
                   FROM sales_records s
                   LEFT JOIN users u ON s.user_id = u.id AND u.farm_id = s.farm_id
                   WHERE s.farm_id = ? ORDER BY s.sale_date DESC, s.id DESC
                   LIMIT 5";
    $salesStmt = $pdo->prepare($salesQuery);
    $salesStmt->execute([$tenantFarmId]);
} else {
    $salesFarmTypePredicate = $includeGeneralSales
        ? "(s.farm_type = ? OR s.farm_type = 'general')"
        : 's.farm_type = ?';
    $salesQuery = "SELECT s.*, u.full_name as seller, u.user_type AS seller_user_type
                   FROM sales_records s
                   LEFT JOIN users u ON s.user_id = u.id AND u.farm_id = s.farm_id
                   WHERE s.farm_id = ? AND {$salesFarmTypePredicate}
                   ORDER BY s.sale_date DESC, s.id DESC
                   LIMIT 5";
    $salesStmt = $pdo->prepare($salesQuery);
    $salesStmt->execute([$tenantFarmId, $farmAccess]);
}
$recentSales = $salesStmt->fetchAll();

// Get recent expenses
$expenseQuery = "SELECT e.*, u.full_name
                 FROM farm_expenses e
                 LEFT JOIN users u ON e.user_id = u.id AND u.farm_id = e.farm_id WHERE e.farm_id = ?";
$expenseParams = [$tenantFarmId];

if ($farmAccess !== 'both') {
    $expenseQuery .= " AND e.farm_type = ?";
    $expenseParams[] = $farmAccess;
}

$expenseQuery .= " ORDER BY e.expense_date DESC, e.id DESC
                 LIMIT 5";

$expenseStmt = $pdo->prepare($expenseQuery);
$expenseStmt->execute($expenseParams);
$recentExpenses = $expenseStmt->fetchAll();

// Get recent daily records
if ($farmAccess === 'poultry' || $farmAccess === 'both') {
    $layerQuery = "SELECT * FROM layer_daily_records 
                   WHERE farm_id = ?
                   ORDER BY record_date DESC, id DESC LIMIT 1";
    $layerStmt = $pdo->prepare($layerQuery);
    $layerStmt->execute([$tenantFarmId]);
    $latestLayerRecord = $layerStmt->fetch();
    
    $broilerQuery = "SELECT * FROM broiler_daily_records 
                     WHERE farm_id = ?
                     ORDER BY record_date DESC, id DESC LIMIT 1";
    $broilerStmt = $pdo->prepare($broilerQuery);
    $broilerStmt->execute([$tenantFarmId]);
    $latestBroilerRecord = $broilerStmt->fetch();
}

if ($farmAccess === 'ruminant' || $farmAccess === 'both') {
    $ruminantQuery = "SELECT * FROM ruminant_daily_records
                      WHERE farm_id = ?
                      ORDER BY record_date DESC, id DESC LIMIT 1";
    $ruminantStmt = $pdo->prepare($ruminantQuery);
    $ruminantStmt->execute([$tenantFarmId]);
    $latestRuminantRecord = $ruminantStmt->fetch();
}

// Get active-cycle livestock totals for the dashboard ticker with constant query count.
$livestockSnapshot = dashboard_livestock_snapshot($pdo, $tenantFarmId, $farmAccess);
$poultryCurrentStock = $livestockSnapshot['poultry'];
$ruminantCurrentStock = $livestockSnapshot['ruminant'];

// Load the user's previous login time (before the current session)
$lastLoginAt = $_SESSION['last_login_at'] ?? null;
if (!$lastLoginAt && isset($_SESSION['user_id'])) {
    $lastLoginStmt = $pdo->prepare("SELECT last_login_at FROM users WHERE id = ?");
    $lastLoginStmt->execute([$_SESSION['user_id']]);
    $lastLoginAt = $lastLoginStmt->fetchColumn();
    $_SESSION['last_login_at'] = $lastLoginAt;
}
$lastLoginDisplay = $lastLoginAt ? date('M j, g:i a', strtotime($lastLoginAt)) : 'First login';
$currentHour = (int) date('G');
$greetingText = $currentHour < 12 ? 'Good morning' : ($currentHour < 18 ? 'Good afternoon' : 'Good evening');

// Current-month management financials come from the same canonical consumed-cost
// engine as Profitability, Reports and Farm Intelligence. Legacy
// profit_loss_summary rows are intentionally no longer a dashboard source of truth.
$month = date('Y-m');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$financialScope = $farmAccess === 'both' ? 'all' : $farmAccess;
$profitData = farm_intelligence_summary($pdo, $tenantFarmId, $monthStart, $monthEnd, $financialScope);
$profitData['total_sales'] = $profitData['revenue'];
$profitData['total_expenses'] = $profitData['total_operating_cost'];
$profitData['net_profit'] = $profitData['profit'];

// Calculate dashboard statistics
$totalStockItems = count($stockItems);
$lowStockCount = count($lowStockItems);
$netProfit = 0;
$monthlyExpenses = 0;

if ($profitData) {
    $netProfit = $profitData['net_profit'] ?? ($profitData['profit'] ?? 0);
    $monthlyExpenses = (float) ($profitData['total_expenses'] ?? 0);
}

// Get activity count for today
if ($farmAccess === 'both') {
    $activityQuery = "SELECT COUNT(*) as activity_count FROM (
                      SELECT id FROM stock_transactions WHERE farm_id = ? AND transaction_date = ?
                      UNION ALL
                      SELECT id FROM layer_daily_records WHERE farm_id = ? AND record_date = ?
                      UNION ALL
                      SELECT id FROM broiler_daily_records WHERE farm_id = ? AND record_date = ?
                      UNION ALL
                      SELECT id FROM ruminant_daily_records WHERE farm_id = ? AND record_date = ?
                      UNION ALL
                      SELECT id FROM farm_expenses WHERE farm_id = ? AND expense_date = ?
                      UNION ALL
                      SELECT id FROM sales_records WHERE farm_id = ? AND sale_date = ?
                      ) as activities";
    $activityStmt = $pdo->prepare($activityQuery);
    $activityStmt->execute([
        $tenantFarmId, $today,
        $tenantFarmId, $today,
        $tenantFarmId, $today,
        $tenantFarmId, $today,
        $tenantFarmId, $today,
        $tenantFarmId, $today
    ]);
} else {
    $activitySalesFarmTypePredicate = $includeGeneralSales
        ? "(farm_type = ? OR farm_type = 'general')"
        : 'farm_type = ?';
    $activityQuery = "SELECT COUNT(*) as activity_count FROM (
                      SELECT id FROM stock_transactions WHERE farm_id = ? AND farm_type = ? AND transaction_date = ?
                      UNION ALL
                      SELECT id FROM layer_daily_records WHERE farm_id = ? AND record_date = ?
                      UNION ALL
                      SELECT id FROM broiler_daily_records WHERE farm_id = ? AND record_date = ?
                      UNION ALL
                      SELECT id FROM ruminant_daily_records WHERE farm_id = ? AND record_date = ?
                      UNION ALL
                      SELECT id FROM farm_expenses WHERE farm_id = ? AND farm_type = ? AND expense_date = ?
                      UNION ALL
                      SELECT id FROM sales_records WHERE farm_id = ? AND {$activitySalesFarmTypePredicate} AND sale_date = ?
                      ) as activities";
    $activityStmt = $pdo->prepare($activityQuery);
    $activityStmt->execute([
        $tenantFarmId, $farmAccess, $today,
        $tenantFarmId, $today,
        $tenantFarmId, $today,
        $tenantFarmId, $today,
        $tenantFarmId, $farmAccess, $today,
        $tenantFarmId, $farmAccess, $today
    ]);
}
$todayActivity = $activityStmt->fetchColumn();
$topLowStockItems = array_slice($lowStockItems, 0, 3);

$explainableIntelligence = farm_intelligence_explainable_signals($pdo, $tenantFarmId, $financialScope, $today);
$smartInsights = array_slice(array_values(array_filter($explainableIntelligence['signals'], static fn($signal) => in_array($signal['severity'], ['danger','warning'], true))), 0, 3);
$smartAttentionStatus = $explainableIntelligence['status'];
$smartAttentionClass = $explainableIntelligence['status_class'];
$smartActionCount = (int)$explainableIntelligence['action_count'];
$smartSignalCounts = $explainableIntelligence['counts'];

$statCardCount = 0;
if ($farmAccess === 'poultry' || $farmAccess === 'both') {
    $statCardCount++;
}
if ($farmAccess === 'ruminant' || $farmAccess === 'both') {
    $statCardCount++;
}
$statCardCountClass = 'stats-count-' . $statCardCount;

// Set page title
$pageTitle = "Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/navbar_head.php'); ?>
    <title>Dashboard - Renee Farms</title>

    <!-- Chart.js with fallback to local stub to keep page functional when CDN is blocked -->
    <script
        src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/reports-chart-fallback.js'); ?>"
        data-fallback-src="<?php echo htmlspecialchars(
            BASE_URL . versioned_asset('/assets/js/chart-fallback.js'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
    ></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/chartjs-4.4.1/chart.umd.min.js'); ?>" crossorigin="anonymous" data-chart-fallback></script>
    
    <!-- Dashboard Specific CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/dashboard-page.css'); ?>">
</head>
<body class="dashboard-role-<?php echo htmlspecialchars($userType); ?>">
    <?php include(__DIR__ . '/navbar.php'); ?>
    
    <div class="container-fluid dashboard-container">
        <!-- Welcome Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card dashboard-hero">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 position-relative app-z-1">
                            <div>
                                <span class="hero-pill mb-2">
                                    <i class="bi bi-stars"></i> <?php echo $greetingText; ?>
                                </span>
                                <h2 class="mb-1 fw-bold">Welcome back, <?php echo app_html($_SESSION['full_name']); ?> 👋</h2>
                                <p class="mb-0 opacity-75">
                                    <?php echo date('l, F j, Y'); ?> • 
                                    Last login: <?php echo htmlspecialchars($lastLoginDisplay); ?>
                                </p>
                            </div>
                            <div class="text-end d-flex flex-column gap-2">
                                <span class="badge bg-light text-dark fs-6">
                                    <?php echo ucfirst(str_replace('_', ' ', $userType)); ?>
                                </span>
                                <div>
                                    <small class="opacity-75">
                                        Farm Access: 
                                         <span class="badge bg-info text-dark">
                                             <?php echo htmlspecialchars($farmAccessLabel); ?>
                                         </span>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 mt-3 position-relative app-z-1">
                            <div class="col-sm-6 col-lg-4 col-xl">
                                <div class="hero-metric">
                                    <span>Total Operating Cost</span>
                                    <strong>₦<?php echo number_format($monthlyExpenses, 2); ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4 col-xl">
                                <div class="hero-metric">
                                    <span>Items in Stock</span>
                                    <strong><?php echo number_format($totalStockItems); ?> Items</strong>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4 col-xl">
                                <div class="hero-metric">
                                    <span>Today's Activities</span>
                                    <strong><?php echo number_format((int) $todayActivity); ?> Updates</strong>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-6 col-xl">
                                <div class="hero-metric">
                                    <span>Net Profit (This Month)</span>
                                    <strong class="<?php echo ($netProfit) >= 0 ? 'text-warning' : 'text-light'; ?>">
                                        ₦<?php echo number_format($netProfit, 2); ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-6 col-xl">
                                <div class="hero-metric">
                                    <span>Inventory Coverage</span>
                                    <strong><?php echo $lowStockCount === 0 ? 'Healthy' : 'Review Needed'; ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Statistics -->
        <?php if (in_array($farmAccess, ['poultry', 'ruminant', 'both'], true)): ?>
        <div class="livestock-ticker-section mb-4">
            <div class="livestock-ticker-row">
                <div class="livestock-ticker-track">
                    <?php if ($farmAccess === 'poultry' || $farmAccess === 'both'): ?>
                    <span class="livestock-ticker-title"><i class="bi bi-egg-fried text-primary me-1"></i>Poultry Active Cycle Stock</span>
                    <?php foreach ($poultryCurrentStock as $label => $value): ?>
                    <span class="livestock-pill">
                        <span class="text-primary">●</span>
                        <span class="fw-semibold"><?php echo $label; ?></span>
                        <span class="fw-bold"><?php echo $value !== null ? number_format($value) : 'No data'; ?></span>
                    </span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($farmAccess === 'both'): ?>
                    <span class="livestock-pill">•</span>
                    <?php endif; ?>
                    <?php if ($farmAccess === 'ruminant' || $farmAccess === 'both'): ?>
                    <span class="livestock-ticker-title"><i class="bi bi-shield-check text-success me-1"></i>Ruminant Active Cycle Stock</span>
                    <?php foreach ($ruminantCurrentStock as $type => $value): ?>
                    <span class="livestock-pill">
                        <span class="text-success">●</span>
                        <span class="fw-semibold"><?php echo $type; ?></span>
                        <span class="fw-bold"><?php echo $value !== null ? number_format($value) : 'No data'; ?></span>
                    </span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Management Intelligence -->
        <div class="card dashboard-card smart-command-card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-2">
                    <div class="smart-intel-summary">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="section-eyebrow mb-0"><i class="bi bi-lightbulb me-1"></i>Management intelligence</span>
                            <span class="fw-bold text-<?php echo htmlspecialchars($smartAttentionClass); ?>"><?php echo htmlspecialchars($smartAttentionStatus); ?></span>
                            <?php if ($smartSignalCounts['danger']): ?><span class="badge bg-danger"><?php echo (int)$smartSignalCounts['danger']; ?> danger</span><?php endif; ?>
                            <?php if ($smartSignalCounts['warning']): ?><span class="badge bg-warning text-dark"><?php echo (int)$smartSignalCounts['warning']; ?> warning<?php echo $smartSignalCounts['warning']===1?'':'s'; ?></span><?php endif; ?>
                        </div>
                    </div>
                    <a class="small fw-semibold text-decoration-none" href="<?php echo BASE_URL; ?>/management/intelligence.php">View all insights <i class="bi bi-arrow-right"></i></a>
                </div>
                <?php if ($smartInsights): ?>
                <div class="row g-2">
                    <?php foreach ($smartInsights as $insight): ?>
                    <div class="col-lg-4">
                        <div class="smart-insight h-100">
                            <div class="d-flex gap-2 align-items-start">
                                <span class="smart-insight-icon bg-<?php echo htmlspecialchars($insight['severity']); ?>-subtle text-<?php echo htmlspecialchars($insight['severity']); ?>"><i class="bi <?php echo htmlspecialchars($insight['icon']); ?>"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <h6 class="mb-0 text-truncate"><?php echo htmlspecialchars($insight['title']); ?></h6>
                                        <span class="badge bg-<?php echo htmlspecialchars($insight['severity']); ?><?php echo in_array($insight['severity'],['warning','info'],true)?' text-dark':''; ?>"><?php echo ucfirst(htmlspecialchars($insight['severity'])); ?></span>
                                    </div>
                                    <div class="small fw-semibold mt-1"><?php echo htmlspecialchars($insight['measured_value']); ?></div>
                                    <div class="small text-muted intel-reason mt-1"><?php echo htmlspecialchars($insight['reason']); ?></div>
                                    <?php if(!empty($insight['followup_status'])): $dashResolved=$insight['followup_status']==='resolved'; $dashNew=!empty($insight['followup_new_evidence']); ?><div class="mt-1"><span class="badge <?php echo $dashNew?'bg-danger-subtle text-danger-emphasis':($dashResolved?'bg-success-subtle text-success-emphasis':'bg-warning-subtle text-warning-emphasis'); ?>"><i class="bi <?php echo $dashNew?'bi-arrow-repeat':($dashResolved?'bi-check-circle':'bi-clock-history'); ?>"></i> <?php echo $dashNew?'New activity since review':($dashResolved?'Previously resolved':'Follow-up open'); ?></span></div><?php endif; ?>
                                    <a class="small fw-semibold text-decoration-none d-inline-block mt-1" href="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/' . ltrim($insight['action_url'], '/')); ?>"><?php echo htmlspecialchars($insight['action_label']); ?> <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="small text-muted">No current danger or warning signals.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="row">
            <!-- Left Column: Stock & Quick Actions -->
            <div class="col-xl-8">
                <!-- Current Stock Levels -->
                <div class="card dashboard-card ops-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="section-eyebrow">Inventory command center</span>
                            <h5 class="mb-0">
                                <i class="bi bi-box-seam text-primary"></i>
                                Smart Stock Control
                            </h5>
                        </div>
                        <div class="dropdown ops-toolbar" id="stockFilterDropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="stockFilterButton"
                                    aria-expanded="false">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                            <ul class="dropdown-menu" id="stockFilterMenu">
                                <li><a class="dropdown-item" href="#" data-stock-filter="all">All Items</a></li>
                                <li><a class="dropdown-item" href="#" data-stock-filter="low">Low Stock Only</a></li>
                                <li><a class="dropdown-item" href="#" data-stock-filter="poultry">Poultry Only</a></li>
                                <li><a class="dropdown-item" href="#" data-stock-filter="ruminant">Ruminant Only</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table stock-control-table" id="stockTable">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Current Stock</th>
                                        <th>Min Level</th>
                                        <th>Unit</th>
                                        <th>Status</th>
                                        <th>Farm Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stockItems as $item):
                                        $minStockLevel = max(1, (float) $item['min_stock_level']);
                                        $stockPercent = ($item['current_stock'] / $minStockLevel) * 100;
                                        if ($item['current_stock'] <= $item['min_stock_level']) {
                                            $statusClass = 'danger';
                                            $statusText = 'Low Stock';
                                            $indicatorClass = 'stock-low';
                                        } elseif ($stockPercent <= 150) {
                                            $statusClass = 'warning';
                                            $statusText = 'Moderate';
                                            $indicatorClass = 'stock-moderate';
                                        } else {
                                            $statusClass = 'success';
                                            $statusText = 'Good';
                                            $indicatorClass = 'stock-good';
                                        }
                                    ?>
                                    <tr data-farm-type="<?php echo $item['farm_type']; ?>" 
                                        data-stock-status="<?php echo $statusClass; ?>">
                                        <td>
                                            <strong><?php echo app_html($item['item_name']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="fw-bold <?php echo "text-$statusClass"; ?>">
                                                <?php echo number_format((float) $item['current_stock'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                                            </div>
                                            <div class="progress inventory-progress mt-1">
                                                <div class="progress-bar bg-<?php echo $statusClass; ?> <?php echo app_percent_class($stockPercent); ?>" role="progressbar"></div>
                                            </div>
                                        </td>
                                        <td><?php echo number_format((float) $item['min_stock_level'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td>
                                            <span class="stock-indicator <?php echo $indicatorClass; ?>"></span>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['farm_type'] == 'poultry' ? 'info' : 'warning'; ?>">
                                                <?php echo ucfirst($item['farm_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary rounded-pill px-3"
                                                    data-quick-stock-id="<?php echo (int)$item['id']; ?>"
                                                    title="Quick Update">
                                                <i class="bi bi-arrow-up-down me-1"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($stockItems)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                            No stock items found
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php if (!hasRole('sales_rep')): ?>
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card dashboard-card ops-card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <span class="section-eyebrow">Daily operations</span>
                                    <h5 class="mb-0">
                                        <i class="bi bi-lightning-charge text-warning"></i>
                                        Smart Quick Actions
                                    </h5>
                                </div>
                                <span class="badge bg-warning-subtle text-warning">Fast entry</span>
                            </div>
                            <div class="card-body">
                                <div class="action-grid">
                                    <?php if ($farmAccess === 'poultry' || $farmAccess === 'both'): ?>
                                    <a href="poultry/layers_daily_record.php" class="smart-action-card text-decoration-none text-dark">
                                        <span class="smart-action-icon bg-primary-subtle text-primary"><i class="bi bi-egg-fried"></i></span>
                                        <span><strong class="d-block">Layer Daily</strong><small class="text-muted">Record eggs, mortality, feed and water.</small></span>
                                    </a>

                                    <a href="poultry/broiler_daily_record.php" class="smart-action-card text-decoration-none text-dark">
                                        <span class="smart-action-icon bg-info-subtle text-info"><i class="bi bi-basket"></i></span>
                                        <span><strong class="d-block">Broiler Daily</strong><small class="text-muted">Track age, stock, health, feed and weight.</small></span>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($farmAccess === 'ruminant' || $farmAccess === 'both'): ?>
                                    <a href="ruminant/ruminant_daily_record.php" class="smart-action-card text-decoration-none text-dark">
                                        <span class="smart-action-icon bg-warning-subtle text-warning"><i class="bi bi-shield-plus"></i></span>
                                        <span><strong class="d-block">Ruminant Daily</strong><small class="text-muted">Update livestock, treatment and mortality.</small></span>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="inventory.php" class="smart-action-card text-decoration-none text-dark">
                                        <span class="smart-action-icon bg-success-subtle text-success"><i class="bi bi-box-arrow-in-down"></i></span>
                                        <span><strong class="d-block">Update Stock</strong><small class="text-muted">Receive, consume, and reconcile inventory.</small></span>
                                    </a>
                                    
                                    <?php if (isPlatformOwner() || hasRole('farm_admin')): ?>
                                    <a href="management/sales_records.php" class="smart-action-card text-decoration-none text-dark">
                                        <span class="smart-action-icon bg-danger-subtle text-danger"><i class="bi bi-cart-plus"></i></span>
                                        <span><strong class="d-block">Record Sale</strong><small class="text-muted">Capture revenue and product quantities.</small></span>
                                    </a>

                                    <a href="management/expenses.php" class="smart-action-card text-decoration-none text-dark">
                                        <span class="smart-action-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-cash-coin"></i></span>
                                        <span><strong class="d-block">Add Expense</strong><small class="text-muted">Log costs for cleaner profit reports.</small></span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Recent Activity & Alerts -->
            <div class="col-xl-4">
                <!-- Today's Transactions -->
                <div class="card dashboard-card ops-card side-panel-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <span class="section-eyebrow">Live audit trail</span>
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history text-info"></i>
                                Today's Transactions
                            </h5>
                        </div>
                        <span class="badge bg-info-subtle text-info"><?php echo count($todayTransactions); ?> today</span>
                    </div>
                    <div class="card-body app-scroll-max-340">
                        <?php if (empty($todayTransactions)): ?>
                        <div class="empty-state-smart">
                            <div>
                                <i class="bi bi-check2-circle display-5 d-block mb-2 text-success"></i>
                                <strong>No transactions today</strong>
                                <div class="small">Stock movement will appear here in real time.</div>
                            </div>
                        </div>
                        <?php else: ?>
                            <?php foreach ($todayTransactions as $trans): ?>
                            <div class="timeline-item">
                                <div class="d-flex align-items-center">
                                    <div class="activity-icon bg-<?php echo $trans['transaction_type'] == 'received' ? 'success' : 'danger'; ?> text-white">
                                        <i class="bi bi-<?php echo $trans['transaction_type'] == 'received' ? 'arrow-down-left' : 'arrow-up-right'; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo app_html($trans['item_name']); ?></strong>
                                            <span class="fw-bold <?php echo $trans['transaction_type'] == 'received' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $trans['transaction_type'] == 'received' ? '+' : '-'; ?>
                                                <?php echo $trans['quantity']; ?> <?php echo app_html($trans['unit']); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            Stock: <?php echo $trans['new_stock']; ?> <?php echo app_html($trans['unit']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($topLowStockItems)): ?>
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-speedometer2 text-danger"></i>
                            Critical Inventory Snapshot
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($topLowStockItems as $item): ?>
                            <?php
                                $progress = 0;
                                if ((float) $item['min_stock_level'] > 0) {
                                    $progress = min(100, ((float) $item['current_stock'] / (float) $item['min_stock_level']) * 100);
                                }
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                    <small class="text-muted"><?php echo number_format((float) $item['current_stock'], 2); ?> / <?php echo number_format((float) $item['min_stock_level'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></small>
                                </div>
                                <div class="progress app-progress-h-8">
                                    <div class="progress-bar bg-danger <?php echo app_percent_class($progress); ?>" role="progressbar"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Low Stock Alerts -->
                <?php if (!empty($lowStockItems)): ?>
                <div class="card dashboard-card border-danger mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle"></i> 
                            Low Stock Alerts
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($lowStockItems as $item): ?>
                        <div class="alert alert-warning d-flex align-items-center mb-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div class="flex-grow-1">
                                <strong><?php echo app_html($item['item_name']); ?></strong><br>
                                <small>
                                    Current: <?php echo $item['current_stock']; ?> <?php echo app_html($item['unit']); ?> •
                                    Min: <?php echo $item['min_stock_level']; ?> <?php echo app_html($item['unit']); ?>
                                </small>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" 
                                    data-quick-stock-id="<?php echo (int)$item['id']; ?>">
                                Reorder
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Recent Sales -->
                <?php if (!empty($recentSales)): ?>
                <div class="card dashboard-card ops-card side-panel-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <span class="section-eyebrow">Revenue pulse</span>
                            <h5 class="mb-0">
                                <i class="bi bi-graph-up text-success"></i>
                                Recent Sales
                            </h5>
                        </div>
                        <span class="badge bg-success-subtle text-success"><?php echo count($recentSales); ?> latest</span>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentSales as $sale): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <div>
                                <strong><?php echo app_html($sale['product_type']); ?></strong>
                                <div class="small text-muted">
                                    <?php echo date('M d', strtotime($sale['sale_date'])); ?> • 
                                    <?php echo $sale['quantity']; ?> units
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold text-success">
                                    ₦<?php echo number_format($sale['total_amount'], 2); ?>
                                </span>
                                <div class="small text-muted">
                                    <?php echo app_html(transaction_recorded_by_label(transaction_actor_farm_name($pdo, $tenantFarmId), $sale['seller'] ?? null, $sale['seller_user_type'] ?? null)); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Latest Production Summary -->
                <div class="card dashboard-card ops-card side-panel-card">
                    <div class="card-header">
                        <span class="section-eyebrow">Animal performance</span>
                        <h5 class="mb-0">
                            <i class="bi bi-bar-chart text-primary"></i>
                            Latest Production
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($farmAccess === 'poultry' || $farmAccess === 'both'): ?>
                            <?php if ($latestLayerRecord): ?>
                            <div class="production-tile">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-egg-fried text-primary me-2"></i>
                                        <strong>Layers</strong>
                                    </span>
                                    <span class="badge bg-primary">
                                        <?php echo date('M d', strtotime($latestLayerRecord['record_date'])); ?>
                                    </span>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Eggs</small>
                                        <div class="fw-bold text-success"><?php echo $latestLayerRecord['egg_production']; ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Rate</small>
                                        <div class="fw-bold <?php echo $latestLayerRecord['laying_rate'] > 80 ? 'text-success' : ($latestLayerRecord['laying_rate'] > 60 ? 'text-warning' : 'text-danger'); ?>">
                                            <?php echo $latestLayerRecord['laying_rate']; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($latestBroilerRecord): ?>
                            <div class="production-tile">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-basket text-info me-2"></i>
                                        <strong>Broilers</strong>
                                    </span>
                                    <span class="badge bg-info">
                                        <?php echo date('M d', strtotime($latestBroilerRecord['record_date'])); ?>
                                    </span>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Stock</small>
                                        <div class="fw-bold"><?php echo $latestBroilerRecord['opening_stock']; ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Age</small>
                                        <div class="fw-bold"><?php echo $latestBroilerRecord['birds_age']; ?> days</div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($farmAccess === 'ruminant' || $farmAccess === 'both'): ?>
                            <?php if ($latestRuminantRecord): ?>
                            <div class="production-tile mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-shield-plus text-warning me-2"></i>
                                        <strong>Ruminant</strong>
                                    </span>
                                    <span class="badge bg-warning">
                                        <?php echo date('M d', strtotime($latestRuminantRecord['record_date'])); ?>
                                    </span>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Stock</small>
                                        <div class="fw-bold"><?php echo $latestRuminantRecord['opening_stock']; ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Type</small>
                                        <div class="fw-bold"><?php echo $latestRuminantRecord['animal_type']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stock Update Modal -->
        <div class="modal fade" id="quickStockModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="quickStockForm">
                        <div class="modal-header">
                            <h5 class="modal-title">Quick Stock Update</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="stockItemId">
                            
                            <div class="mb-3">
                                <label>Item</label>
                                <input type="text" class="form-control" id="stockItemName" readonly>
                                <small class="text-muted" id="stockItemDetails"></small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Transaction Type</label>
                                    <select class="form-select" id="transType" required>
                                        <option value="received">⬆ Received Stock (+)</option>
                                        <option value="used">⬇ Used Stock (-)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Quantity</label>
                                    <input type="number" class="form-control" id="quantity" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label>Remarks (Optional)</label>
                                <input type="text" class="form-control" id="remarks" placeholder="Enter remarks">
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <small>This will update stock in real-time and record the transaction.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/jquery/jquery.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/jquery.dataTables.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/dataTables.bootstrap5.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables-responsive/js/dataTables.responsive.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/main.js'); ?>"></script>
    
    <div
    id="dashboardConfig"
    hidden
    data-low-stock-count="<?php echo (int)$lowStockCount; ?>"
    data-farm-access="<?php echo htmlspecialchars(
        (string)$farmAccess,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
    data-low-stock-items="<?php echo htmlspecialchars(
        app_json_script($lowStockItems),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
    data-today="<?php echo htmlspecialchars(
        date('Y-m-d'),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
></div>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/dashboard.js'); ?>"></script>
</body>
</html>
