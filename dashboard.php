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
    $salesQuery = "SELECT s.*, u.full_name as seller
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
    $salesQuery = "SELECT s.*, u.full_name as seller
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
    <script>
        function loadChartFallback() {
            if (window.fmChartFallbackLoaded) return;
            window.fmChartFallbackLoaded = true;
            var fallbackScript = document.createElement('script');
            fallbackScript.src = '<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/chart-fallback.js'); ?>';
            document.head.appendChild(fallbackScript);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous" onerror="loadChartFallback()"></script>
    
    <!-- Dashboard Specific CSS -->
    <style>
        :root {
            --brand-primary: #2d6cdf;
            --brand-primary-soft: #dce7ff;
            --brand-success: #1f9d66;
            --brand-danger: #d64858;
            --brand-warning: #f5a524;
            --brand-surface: #ffffff;
            --brand-muted: #6c7786;
            --brand-bg: #f4f7fc;
        }

        body {
            background: radial-gradient(circle at top right, rgba(45, 108, 223, 0.09), transparent 55%), var(--brand-bg);
        }

        .dashboard-card {
            transition: all 0.25s ease;
            border: none;
            border-radius: 16px;
            box-shadow: 0 14px 35px rgba(20, 35, 80, 0.08);
            background: var(--brand-surface);
        }

        .dashboard-card .card-body {
            padding: 1rem 1.1rem;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 36px rgba(20, 35, 80, 0.12);
        }

        .stat-icon {
            font-size: 1.85rem;
            opacity: 0.9;
            line-height: 1;
        }

        .stat-value {
            font-size: 1.6rem;
            line-height: 1.15;
            word-break: break-word;
        }

        .stat-value-currency {
            font-size: 1.35rem;
            line-height: 1.05;
        }

        .stat-subtext {
            display: block;
            line-height: 1.15;
            white-space: normal;
            word-break: break-word;
        }
        
        .activity-item {
            border-left: 3px solid transparent;
            padding: 10px 15px;
            margin-bottom: 10px;
            background: #f8faff;
            border-radius: 12px;
            transition: all 0.2s;
        }

        .activity-item:hover {
            background: #eef4ff;
            border-left-color: var(--brand-primary);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .stock-low {
            background-color: #dc3545;
        }
        
        .stock-moderate {
            background-color: #ffc107;
        }
        
        .stock-good {
            background-color: #28a745;
        }
        
        .quick-action-btn {
            padding: 10px 15px;
            border-radius: 14px;
            transition: all 0.2s;
            box-shadow: 0 10px 24px rgba(18, 38, 80, 0.08);
            background: linear-gradient(180deg, #fff, #f9fbff);
        }

        .quick-action-btn:hover {
            transform: translateY(-3px);
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .dashboard-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
            align-items: stretch;
        }

        .dashboard-stat-wrapper {
            flex: 1 1 100%;
        }

        @media (min-width: 768px) {
            .dashboard-stat-wrapper {
                flex: 1 1 45%;
            }
        }

        @media (min-width: 992px) {
            .dashboard-stat-wrapper {
                flex: 1 1 30%;
            }
        }

        @media (min-width: 1200px) {
            .dashboard-stats.stats-count-6 .dashboard-stat-wrapper {
                flex: 0 1 calc(25% - 0.75rem);
            }

            .dashboard-stats.stats-count-5 .dashboard-stat-wrapper {
                flex: 0 1 calc(33.333% - 0.75rem);
            }
        }

        .dashboard-stat-card {
            height: 100%;
            min-height: 130px;
            display: flex;
            flex-direction: column;
        }

        .dashboard-container {
            margin-top: 2rem;
            margin-bottom: 2.25rem;
        }

        .dashboard-hero {
            background: linear-gradient(135deg, #1f5ec9 0%, #2d6cdf 52%, #59a0ff 100%);
            color: #fff;
            border-radius: 18px;
            position: relative;
            overflow: hidden;
        }

        .dashboard-hero::after {
            content: '';
            position: absolute;
            width: 270px;
            height: 270px;
            border-radius: 50%;
            right: -80px;
            top: -130px;
            background: rgba(255, 255, 255, 0.15);
        }

        .dashboard-hero .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 999px;
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
        }

        .hero-metric {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 0.7rem 0.85rem;
            height: 100%;
        }

        .hero-metric span {
            display: block;
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .hero-metric strong {
            font-size: 1rem;
            line-height: 1.1;
        }

        .dashboard-stat-card .card-body {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0.45rem;
            height: 100%;
            padding: 0.85rem 0.95rem;
        }

        .dashboard-stat-card .stat-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            border-radius: 999px;
            padding: 0.2rem 0.55rem;
            background: #f1f5ff;
            color: #27498f;
            width: fit-content;
        }

        .dashboard-stat-card .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .dashboard-stat-card h6 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .dashboard-stat-card .stat-icon {
            font-size: 1.55rem;
        }

        .dashboard-stat-card .stat-value {
            font-size: 1.35rem;
        }

        .dashboard-stat-card .stat-value-currency {
            font-size: 1.2rem;
        }

        .dashboard-stat-card.livestock-center-card {
            min-height: 88px;
        }

        .dashboard-stat-card.livestock-center-card .card-body {
            justify-content: flex-start;
            text-align: center;
            padding: 0.65rem 0.8rem;
            gap: 0.2rem;
            height: auto;
        }

        .dashboard-stat-card.livestock-center-card .d-flex {
            text-align: left;
        }

        .dashboard-stat-card.livestock-center-card .mb-3 {
            margin-bottom: 0.45rem !important;
        }

        .dashboard-stat-card.livestock-center-card .row.g-3 {
            --bs-gutter-y: 0.25rem;
            --bs-gutter-x: 0.55rem;
            margin-bottom: 0.1rem !important;
        }

        .livestock-ticker-section {
            display: grid;
            gap: 0.75rem;
        }

        .livestock-ticker-row {
            overflow: hidden;
            border-radius: 12px;
            border: 1px solid #e6ecf7;
            background: #fff;
            padding: 0.55rem 0.6rem;
            position: relative;
        }

        .livestock-ticker-track {
            width: max-content;
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            white-space: nowrap;
            animation: livestock-marquee 32s linear infinite;
            will-change: transform;
            transform: translateX(-100%);
        }

        .livestock-ticker-title {
            font-size: 0.82rem;
            font-weight: 600;
            color: #5f6f8a;
            margin-right: 0.35rem;
        }

        .livestock-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            background: #f5f8ff;
            color: #243a64;
            padding: 0.2rem 0.65rem;
            font-size: 0.82rem;
            border: 1px solid #e3ebff;
        }

        @keyframes livestock-marquee {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100vw);
            }
        }

        .livestock-ticker-row:hover .livestock-ticker-track {
            animation-play-state: paused;
        }

        @media (prefers-reduced-motion: reduce) {
            .livestock-ticker-track {
                animation: none;
            }
        }

        body.dashboard-role-poultry_manager .dashboard-stats .dashboard-stat-wrapper,
        body.dashboard-role-ruminant_manager .dashboard-stats .dashboard-stat-wrapper {
            flex: 0 1 min(620px, 100%);
        }

        .current-month-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(155deg, #f7fbff 0%, #eef6ff 100%);
        }

        .current-month-card .floating-stat-icon {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: rgba(13, 202, 240, 0.15);
            color: #0dcaf0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .smart-command-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #e3ebff;
        }

        .smart-command-card .card-body { padding: .85rem 1rem; }
        .smart-intel-summary { min-width: 190px; }
        .smart-insight {
            border: 1px solid #edf1f7;
            border-radius: 10px;
            padding: .65rem .75rem;
            background: #fff;
        }
        .smart-insight-icon {
            width: 30px; height: 30px; border-radius: 8px; flex: 0 0 30px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .smart-insight .intel-reason { line-height: 1.25; }

        .bg-primary-subtle { background-color: rgba(13, 110, 253, 0.12) !important; }
        .bg-success-subtle { background-color: rgba(25, 135, 84, 0.12) !important; }
        .bg-info-subtle { background-color: rgba(13, 202, 240, 0.14) !important; }
        .bg-warning-subtle { background-color: rgba(255, 193, 7, 0.18) !important; }
        .bg-danger-subtle { background-color: rgba(220, 53, 69, 0.12) !important; }

        .ops-card {
            border: 1px solid #e6edf8;
            overflow: hidden;
        }

        .ops-card .card-header {
            background: linear-gradient(135deg, #ffffff 0%, #f6f9ff 100%);
            color: #1f2d45;
            border-bottom: 1px solid #e6edf8;
            padding: 0.95rem 1.1rem;
        }

        .ops-card .section-eyebrow {
            color: #6f7f95;
            display: block;
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .ops-toolbar .btn,
        .ops-toolbar .badge {
            border-radius: 999px;
        }

        .stock-control-table {
            border-collapse: separate;
            border-spacing: 0 0.55rem;
        }

        .stock-control-table thead th {
            background: transparent;
            color: #718096;
            font-size: 0.73rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border: 0;
            padding: 0.35rem 0.75rem;
        }

        .stock-control-table tbody tr {
            background: #ffffff;
            box-shadow: 0 8px 22px rgba(20, 35, 80, 0.06);
        }

        .stock-control-table tbody td {
            border-top: 1px solid #edf2f7;
            border-bottom: 1px solid #edf2f7;
            padding: 0.85rem 0.75rem;
            vertical-align: middle;
        }

        .stock-control-table tbody td:first-child {
            border-left: 1px solid #edf2f7;
            border-radius: 14px 0 0 14px;
        }

        .stock-control-table tbody td:last-child {
            border-right: 1px solid #edf2f7;
            border-radius: 0 14px 14px 0;
        }

        .inventory-progress {
            height: 7px;
            border-radius: 999px;
            background: #edf2f7;
            min-width: 120px;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 1rem;
        }

        .smart-action-card {
            border: 1px solid #e7eef9;
            border-radius: 18px;
            min-height: 126px;
            background: linear-gradient(145deg, #ffffff, #f9fbff);
            box-shadow: 0 14px 30px rgba(20, 35, 80, 0.07);
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 1rem;
            transition: all 0.22s ease;
        }

        .smart-action-card:hover {
            border-color: rgba(45, 108, 223, 0.35);
            box-shadow: 0 18px 36px rgba(20, 35, 80, 0.12);
            transform: translateY(-3px);
        }

        .smart-action-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            flex: 0 0 auto;
        }

        .side-panel-card .card-body {
            padding: 1rem;
        }

        .empty-state-smart {
            min-height: 132px;
            display: grid;
            place-items: center;
            text-align: center;
            color: #667085;
        }

        .timeline-item {
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 0.8rem;
            background: #fff;
            margin-bottom: 0.7rem;
        }

        .production-tile {
            border: 1px solid #edf2f7;
            border-radius: 16px;
            padding: 0.85rem;
            background: linear-gradient(135deg, #ffffff, #fbfcff);
            margin-bottom: 0.75rem;
        }

        @media (max-width: 768px) {
            .dashboard-card .card-body {
                padding: 15px;
            }

            .stat-icon {
                font-size: 1.6rem;
            }

            .dashboard-stat-card {
                min-height: 118px;
            }

            .dashboard-stat-card .card-body {
                padding: 0.75rem 0.85rem;
            }

            body.dashboard-role-poultry_manager .dashboard-stats .dashboard-stat-wrapper,
            body.dashboard-role-ruminant_manager .dashboard-stats .dashboard-stat-wrapper {
                flex-basis: 100%;
            }

            .dashboard-hero::after {
                width: 200px;
                height: 200px;
            }
        }
    </style>
</head>
<body class="dashboard-role-<?php echo htmlspecialchars($userType); ?>">
    <?php include(__DIR__ . '/navbar.php'); ?>
    
    <div class="container-fluid dashboard-container">
        <!-- Welcome Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card dashboard-hero">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 position-relative" style="z-index:1;">
                            <div>
                                <span class="hero-pill mb-2">
                                    <i class="bi bi-stars"></i> <?php echo $greetingText; ?>
                                </span>
                                <h2 class="mb-1 fw-bold">Welcome back, <?php echo $_SESSION['full_name']; ?> 👋</h2>
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
                        <div class="row g-2 mt-3 position-relative" style="z-index:1;">
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
                                            <strong><?php echo $item['item_name']; ?></strong>
                                        </td>
                                        <td>
                                            <div class="fw-bold <?php echo "text-$statusClass"; ?>">
                                                <?php echo number_format((float) $item['current_stock'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                                            </div>
                                            <div class="progress inventory-progress mt-1">
                                                <div class="progress-bar bg-<?php echo $statusClass; ?>" role="progressbar" style="width: <?php echo (int) min(100, round($stockPercent)); ?>%"></div>
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
                                                    onclick="quickStockUpdate(<?php echo $item['id']; ?>)"
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
                    <div class="card-body" style="max-height: 340px; overflow-y: auto;">
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
                                            <strong><?php echo $trans['item_name']; ?></strong>
                                            <span class="fw-bold <?php echo $trans['transaction_type'] == 'received' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $trans['transaction_type'] == 'received' ? '+' : '-'; ?>
                                                <?php echo $trans['quantity']; ?> <?php echo $trans['unit']; ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            Stock: <?php echo $trans['new_stock']; ?> <?php echo $trans['unit']; ?>
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
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo (int) round($progress); ?>%"></div>
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
                                <strong><?php echo $item['item_name']; ?></strong><br>
                                <small>
                                    Current: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?> • 
                                    Min: <?php echo $item['min_stock_level']; ?> <?php echo $item['unit']; ?>
                                </small>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="quickStockUpdate(<?php echo $item['id']; ?>)">
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
                                <strong><?php echo $sale['product_type']; ?></strong>
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
                                    <?php echo $sale['seller']; ?>
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
    
    <script>
    let dashboardLowStockCount = <?php echo (int)$lowStockCount; ?>;

    // Initialize dashboard
    $(document).ready(function() {
        // Initialize tooltips
        $('[title]').tooltip();
        
        // Auto-refresh stock every 30 seconds without reloading or moving the page.
        setInterval(refreshStockData, 30000);
        
        // Check for new notifications
        checkNotifications();
    });
    
    // Filter stock table
    function filterStock(filterType) {
        const rows = document.querySelectorAll('#stockTable tbody tr');

        rows.forEach(row => {
            let showRow = true;
            const farmType = row.getAttribute('data-farm-type');
            const stockStatus = row.getAttribute('data-stock-status');

            // Keep placeholder row visible only in "all" mode
            if (!farmType && !stockStatus) {
                row.style.display = filterType === 'all' ? '' : 'none';
                return;
            }

            if (filterType === 'low') {
                showRow = stockStatus === 'danger';
            } else if (filterType === 'poultry') {
                showRow = farmType === 'poultry' || farmType === 'both';
            } else if (filterType === 'ruminant') {
                showRow = farmType === 'ruminant' || farmType === 'both';
            }

            row.style.display = showRow ? '' : 'none';
        });
    }

    document.getElementById('stockFilterMenu')?.addEventListener('click', function(event) {
        const filterLink = event.target.closest('[data-stock-filter]');
        if (!filterLink) {
            return;
        }

        event.preventDefault();
        filterStock(filterLink.getAttribute('data-stock-filter'));

        const filterMenu = document.getElementById('stockFilterMenu');
        const filterButton = document.getElementById('stockFilterButton');
        filterMenu?.classList.remove('show');
        filterButton?.setAttribute('aria-expanded', 'false');
    });

    // Custom dropdown toggle for stock filter (avoids Bootstrap Popper dependency issues).
    (function initStockFilterDropdown() {
        const dropdown = document.getElementById('stockFilterDropdown');
        const button = document.getElementById('stockFilterButton');
        const menu = document.getElementById('stockFilterMenu');

        if (!dropdown || !button || !menu) {
            return;
        }

        button.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = menu.classList.contains('show');
            menu.classList.toggle('show', !isOpen);
            button.setAttribute('aria-expanded', String(!isOpen));
        });

        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target)) {
                menu.classList.remove('show');
                button.setAttribute('aria-expanded', 'false');
            }
        });
    })();
    
    // Quick stock update
    function quickStockUpdate(itemId) {
        // Fetch item details
        fetch(`api/get_item_details.php?id=${itemId}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    document.getElementById('stockItemId').value = itemId;
                    document.getElementById('stockItemName').value = data.item_name;
                    document.getElementById('stockItemDetails').textContent = 
                        `Current stock: ${data.current_stock} ${data.unit} • Min: ${data.min_stock_level} ${data.unit}`;
                    
                    const modal = new bootstrap.Modal(document.getElementById('quickStockModal'));
                    modal.show();
                }
            })
            .catch(error => {
                showAlert('danger', 'Error loading item details: ' + error.message);
            });
    }
    
    // Handle quick stock form submission
    document.getElementById('quickStockForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            item_id: document.getElementById('stockItemId').value,
            type: document.getElementById('transType').value,
            quantity: document.getElementById('quantity').value,
            remarks: document.getElementById('remarks').value,
            farm_type: '<?php echo $farmAccess; ?>'
        };
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
        submitBtn.disabled = true;
        
        // Send request
        fetch('api/update_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Stock updated successfully!');
                
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('quickStockModal')).hide();
                
                // Reload page after 1 second
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', 'Error: ' + data.message);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            showAlert('danger', 'Network error: ' + error.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Refresh low-stock status in the background without changing scroll position.
    function refreshStockData() {
        fetch(`api/get_stock_summary.php?farm_type=<?php echo $farmAccess; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data && data.updated) {
                    const lowStockCount = Number(data.low_stock_count || 0);
                    const previousLowStockCount = dashboardLowStockCount;

                    if (lowStockCount !== previousLowStockCount) {
                        updateNotificationBadge(lowStockCount);
                        if (lowStockCount > previousLowStockCount) {
                            const added = lowStockCount - previousLowStockCount;
                            showAlert('warning', `${added} new item${added > 1 ? 's are' : ' is'} now low on stock!`);
                        }
                        dashboardLowStockCount = lowStockCount;
                    }
                }
            });
    }
    
    // Check for notifications
    function checkNotifications() {
        // Check for low stock notifications
        const lowStockItems = <?php echo json_encode($lowStockItems); ?>;
        if (lowStockItems.length > 0) {
            const notificationCount = lowStockItems.length;
            if (notificationCount > 0) {
                // Show persistent notification badge
                updateNotificationBadge(notificationCount);
                
                // Show initial alert if first visit
                if (!sessionStorage.getItem('stockAlertShown')) {
                    showAlert('warning', 
                        `You have ${notificationCount} item${notificationCount > 1 ? 's' : ''} with low stock. ` +
                        `Please reorder soon.`, 
                        10000);
                    sessionStorage.setItem('stockAlertShown', 'true');
                }
            }
        }
        
        // Check for pending tasks
        const today = '<?php echo date('Y-m-d'); ?>';
        fetch(`api/check_pending_tasks.php?farm_type=<?php echo $farmAccess; ?>&date=${today}`)
            .then(response => response.json())
            .then(data => {
                if (data.pending_tasks > 0) {
                    showAlert('info', 
                        `You have ${data.pending_tasks} pending task${data.pending_tasks > 1 ? 's' : ''} for today.`, 
                        8000);
                }
            });
    }
    
    // Update notification badge
    function updateNotificationBadge(count) {
        let badge = document.getElementById('notificationBadge');
        if (!badge) {
            badge = document.createElement('span');
            badge.id = 'notificationBadge';
            badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
            badge.style.fontSize = '0.6rem';
            
            const bellIcon = document.querySelector('.bi-bell');
            if (bellIcon) {
                bellIcon.parentElement.style.position = 'relative';
                bellIcon.parentElement.appendChild(badge);
            }
        }
        
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = count > 0 ? 'block' : 'none';
    }
    
    // Use the platform-wide notification system for all dashboard pop notifications.
    function showAlert(type, message, duration = 5000) {
        const mapped = type === 'danger' ? 'error' : type;
        if (window.AppNotify) {
            return AppNotify.show(mapped, message, null, null, duration);
        }
    }
    
    // Auto-update time
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true
        });
        const dateString = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        const timeElement = document.querySelector('.current-time');
        if (timeElement) {
            timeElement.textContent = `${dateString} • ${timeString}`;
        }
    }
    
    // Update time every minute
    setInterval(updateCurrentTime, 60000);
    updateCurrentTime(); // Initial call
    </script>
</body>
</html>
