<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(dirname(__DIR__) . '/includes/functions.php');
requireLogin();

$itemId = $_GET['item_id'] ?? null;

if (!$itemId) {
    $_SESSION['error'] = 'No inventory item selected.';
    header('Location: ' . BASE_URL . '/inventory.php');
    exit();
}

$itemStmt = $pdo->prepare("SELECT si.*, ic.category_name FROM stock_items si JOIN inventory_categories ic ON si.category_id = ic.id AND ic.farm_id = si.farm_id WHERE si.id = ? AND si.farm_id = ?");
$itemStmt->execute([$itemId, requireCurrentFarmId()]);
$item = $itemStmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    $_SESSION['error'] = 'Inventory item not found.';
    header('Location: ' . BASE_URL . '/inventory.php');
    exit();
}

$userType = getUserType();
$isOwnerOrAdmin = isPlatformOwner() || hasRole('farm_admin');
$hasInventoryPermission = hasPermission($userType, 'inventory');
$hasPoultryAccess = checkAccess('poultry');
$hasRuminantAccess = checkAccess('ruminant');

if (!($isOwnerOrAdmin || $hasInventoryPermission)) {
    $itemFarmType = strtolower((string)($item['farm_type'] ?? ''));
    $canReadItem = ($itemFarmType === 'poultry' && $hasPoultryAccess)
        || ($itemFarmType === 'ruminant' && $hasRuminantAccess)
        || ($itemFarmType === 'both' && ($hasPoultryAccess || $hasRuminantAccess));

    if (!$canReadItem) {
        $_SESSION['error'] = 'You do not have access to this inventory item.';
        header('Location: ' . BASE_URL . '/inventory.php');
        exit();
    }
}

$pageTitle = 'Stock History - ' . htmlspecialchars($item['item_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include(dirname(__DIR__) . '/navbar_head.php'); ?>
    <script
        src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/reports-chart-fallback.js'); ?>"
        data-fallback-src="<?php echo htmlspecialchars(
            BASE_URL . versioned_asset('/assets/js/chart-fallback.js'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
    ></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous" data-chart-fallback></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/stock-history-page.css'); ?>">
</head>
<body>
<?php include(dirname(__DIR__) . '/navbar.php'); ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Stock History</h3>
            <p class="text-muted mb-0">Tracking history for <strong><?php echo htmlspecialchars($item['item_name']); ?></strong> (<?php echo htmlspecialchars($item['unit']); ?>)</p>
        </div>
        <div>
            <a href="<?php echo BASE_URL; ?>/inventory.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Inventory</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card summary-card p-3">
                <small class="text-muted">Current Stock</small>
                <div id="currentStock" class="summary-value">--</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card p-3">
                <small class="text-muted">Total Received</small>
                <div id="totalReceived" class="summary-value text-success">--</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card p-3">
                <small class="text-muted">Total Used</small>
                <div id="totalUsed" class="summary-value text-danger">--</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card p-3">
                <small class="text-muted">Transactions</small>
                <div id="transactionCount" class="summary-value">--</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="card-title mb-0">Stock Trend</h5>
                <select id="daysFilter" class="form-select form-select-sm stock-history-days-filter">
                    <option value="30">Last 30 days</option>
                    <option value="60">Last 60 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="180">Last 180 days</option>
                    <option value="365">Last 365 days</option>
                </select>
            </div>
            <canvas id="stockChart" height="120"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="card-title mb-0">Transaction History</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-striped history-table mb-0" id="historyTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Previous Stock</th>
                            <th class="text-end">New Stock</th>
                            <th>Attributed To</th>
                            <th>Production Cycle</th>
                            <th>Remarks</th>
                            <th>Recorded By</th>
                            <th>Status</th>
                            <th>Posted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="11" class="text-center text-muted">Loading history...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/jquery/jquery.min.js'); ?>"></script>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
<div
    id="stockHistoryConfig"
    hidden
    data-item-id="<?php echo htmlspecialchars(
        (string)$itemId,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
    data-history-url="<?php echo htmlspecialchars(
        BASE_URL . '/api/get_stock_history.php',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
></div>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/stock-history.js'); ?>"></script>
</body>
</html>
