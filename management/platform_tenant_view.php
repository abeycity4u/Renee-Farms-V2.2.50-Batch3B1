<?php require_once dirname(__DIR__) . '/init.php'; ?>
<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/platform_owner_tenant_view.php';
require_once dirname(__DIR__) . '/lib/farm_intelligence.php';

requireLogin();
requirePlatformOwner();

$tenants = platform_owner_tenant_list($pdo);
$tenant = platform_owner_tenant_from_request($pdo, 'farm_id');

if (!$tenant) {
    http_response_code(404);
    $pageTitle = 'Tenant View';
    $tenantId = 0;
    $tenantModules = [];
} else {
    $tenantId = (int)$tenant['id'];
    $tenantModules = platform_owner_tenant_modules($pdo, $tenantId);
}

function platform_tenant_money($value): string
{
    return '₦' . number_format((float)$value, 2);
}

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthLabel = date('F Y');
$summary = [
    'revenue' => 0,
    'total_operating_cost' => 0,
    'profit' => 0,
];
$inventoryCount = 0;
$lowStockCount = 0;
$activeCycleCount = 0;
$userCount = 0;
$recentStock = [];
$recentSales = [];
$recentExpenses = [];

if ($tenantId > 0) {
    $summary = farm_intelligence_summary($pdo, $tenantId, $monthStart, $monthEnd, 'all');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM stock_items WHERE farm_id = ? AND is_active = 1');
    $stmt->execute([$tenantId]);
    $inventoryCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM stock_items WHERE farm_id = ? AND is_active = 1 AND current_stock <= min_stock_level');
    $stmt->execute([$tenantId]);
    $lowStockCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM production_cycles WHERE farm_id = ? AND status = 'active'");
    $stmt->execute([$tenantId]);
    $activeCycleCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE farm_id = ?');
    $stmt->execute([$tenantId]);
    $userCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT t.transaction_date, t.transaction_type, t.quantity, t.new_stock,
                i.item_name, i.unit, u.full_name
         FROM stock_transactions t
         INNER JOIN stock_items i ON i.id = t.stock_item_id AND i.farm_id = t.farm_id
         LEFT JOIN users u ON u.id = t.user_id AND u.farm_id = t.farm_id
         WHERE t.farm_id = ? AND t.is_reversed = 0
         ORDER BY t.transaction_date DESC, t.id DESC
         LIMIT 6"
    );
    $stmt->execute([$tenantId]);
    $recentStock = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        "SELECT sale_date, product_type AS product, quantity, total_amount, customer_name
         FROM sales_records
         WHERE farm_id = ?
         ORDER BY sale_date DESC, id DESC
         LIMIT 6"
    );
    $stmt->execute([$tenantId]);
    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        "SELECT expense_date, category AS expense_type, amount, description
         FROM farm_expenses
         WHERE farm_id = ?
         ORDER BY expense_date DESC, id DESC
         LIMIT 6"
    );
    $stmt->execute([$tenantId]);
    $recentExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include dirname(__DIR__) . '/navbar_head.php'; ?>
    <title>Tenant View - Renee Farms Platform</title>
    <style>
        .tenant-view-shell{max-width:1500px;margin:0 auto}
        .tenant-view-banner{border-left:4px solid #0d6efd}
        .tenant-view-card{border:1px solid var(--bs-border-color);border-radius:14px}
        .tenant-view-stat{font-size:1.35rem;font-weight:800}
        .tenant-view-meta{font-size:.82rem;color:var(--bs-secondary-color)}
        .tenant-view-table td,.tenant-view-table th{vertical-align:middle}
        .tenant-view-readonly{font-weight:700;letter-spacing:.02em}
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/navbar.php'; ?>
<div class="container-fluid py-3">
<div class="tenant-view-shell">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-buildings"></i> Platform Tenant View</h3>
            <div class="text-muted">Support visibility across tenant farms without changing your Platform Owner workspace.</div>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>/management/farms.php"><i class="bi bi-arrow-left"></i> Farm Accounts</a>
    </div>

    <?php if (!$tenants): ?>
        <div class="alert alert-info">No customer farms are available.</div>
    <?php else: ?>
        <form method="get" class="card card-body tenant-view-card mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-8 col-lg-6">
                    <label class="form-label fw-semibold" for="tenantFarmSelect">View tenant farm</label>
                    <select class="form-select" id="tenantFarmSelect" name="farm_id" onchange="this.form.submit()">
                        <?php foreach ($tenants as $farm): ?>
                            <option value="<?php echo (int)$farm['id']; ?>" <?php echo $tenantId === (int)$farm['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($farm['name']); ?> — <?php echo htmlspecialchars($farm['subscription_status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-eye"></i> View Farm</button>
                </div>
            </div>
        </form>

        <?php if ($tenant): ?>
        <div class="alert alert-primary tenant-view-banner d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="tenant-view-readonly"><i class="bi bi-eye"></i> Platform Owner · Read-only operational view</div>
                <div>Viewing <strong><?php echo htmlspecialchars($tenant['name']); ?></strong>. Your Platform Owner identity remains in the dedicated owner workspace.</div>
            </div>
            <span class="badge text-bg-light border">Tenant #<?php echo $tenantId; ?></span>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3"><div class="card tenant-view-card h-100"><div class="card-body"><div class="tenant-view-meta">Revenue · <?php echo $monthLabel; ?></div><div class="tenant-view-stat"><?php echo platform_tenant_money($summary['revenue'] ?? 0); ?></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card tenant-view-card h-100"><div class="card-body"><div class="tenant-view-meta">Operating Cost · <?php echo $monthLabel; ?></div><div class="tenant-view-stat"><?php echo platform_tenant_money($summary['total_operating_cost'] ?? 0); ?></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card tenant-view-card h-100"><div class="card-body"><div class="tenant-view-meta">Operating P/L · <?php echo $monthLabel; ?></div><div class="tenant-view-stat <?php echo (float)($summary['profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo platform_tenant_money($summary['profit'] ?? 0); ?></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card tenant-view-card h-100"><div class="card-body"><div class="tenant-view-meta">Subscription</div><div class="tenant-view-stat text-capitalize"><?php echo htmlspecialchars($tenant['subscription_status']); ?></div><div class="tenant-view-meta text-capitalize"><?php echo htmlspecialchars($tenant['subscription_plan']); ?> plan</div></div></div></div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-lg-3"><div class="card tenant-view-card h-100"><div class="card-body"><div class="tenant-view-meta">Inventory Items</div><div class="tenant-view-stat"><?php echo number_format($inventoryCount); ?></div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card tenant-view-card h-100"><div class="card-body"><div class="tenant-view-meta">Low Stock</div><div class="tenant-view-stat <?php echo $lowStockCount ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($lowStockCount); ?></div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card tenant-view-card h-100"><div class="card-body"><div class="tenant-view-meta">Active Cycles</div><div class="tenant-view-stat"><?php echo number_format($activeCycleCount); ?></div></div></div></div>
            <div class="col-sm-6 col-lg-3"><div class="card tenant-view-card h-100"><div class="card-body"><div class="tenant-view-meta">Tenant Users</div><div class="tenant-view-stat"><?php echo number_format($userCount); ?></div></div></div></div>
        </div>

        <div class="card tenant-view-card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>Enabled modules</strong>
                    <div class="tenant-view-meta">Commercial entitlements for this tenant.</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($tenantModules): foreach ($tenantModules as $module): ?>
                        <span class="badge text-bg-primary text-capitalize"><?php echo htmlspecialchars($module); ?></span>
                    <?php endforeach; else: ?>
                        <span class="badge text-bg-secondary">No modules enabled</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-4">
                <div class="card tenant-view-card h-100">
                    <div class="card-header fw-semibold"><i class="bi bi-box-seam"></i> Recent Stock Activity</div>
                    <div class="table-responsive"><table class="table table-sm tenant-view-table mb-0"><thead><tr><th>Date</th><th>Item</th><th>Movement</th></tr></thead><tbody>
                    <?php if (!$recentStock): ?><tr><td colspan="3" class="text-center text-muted py-3">No stock activity.</td></tr><?php endif; ?>
                    <?php foreach ($recentStock as $row): ?><tr><td><?php echo htmlspecialchars($row['transaction_date']); ?></td><td><?php echo htmlspecialchars($row['item_name']); ?></td><td><span class="badge <?php echo $row['transaction_type']==='received'?'text-bg-success':'text-bg-danger'; ?>"><?php echo htmlspecialchars(ucfirst($row['transaction_type'])); ?> <?php echo htmlspecialchars((string)$row['quantity']); ?> <?php echo htmlspecialchars($row['unit']); ?></span></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card tenant-view-card h-100">
                    <div class="card-header fw-semibold"><i class="bi bi-cart-check"></i> Recent Sales</div>
                    <div class="table-responsive"><table class="table table-sm tenant-view-table mb-0"><thead><tr><th>Date</th><th>Product</th><th>Amount</th></tr></thead><tbody>
                    <?php if (!$recentSales): ?><tr><td colspan="3" class="text-center text-muted py-3">No sales records.</td></tr><?php endif; ?>
                    <?php foreach ($recentSales as $row): ?><tr><td><?php echo htmlspecialchars($row['sale_date']); ?></td><td><?php echo htmlspecialchars($row['product']); ?></td><td><?php echo platform_tenant_money($row['total_amount']); ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card tenant-view-card h-100">
                    <div class="card-header fw-semibold"><i class="bi bi-cash-coin"></i> Recent Expenses</div>
                    <div class="table-responsive"><table class="table table-sm tenant-view-table mb-0"><thead><tr><th>Date</th><th>Type</th><th>Amount</th></tr></thead><tbody>
                    <?php if (!$recentExpenses): ?><tr><td colspan="3" class="text-center text-muted py-3">No expense records.</td></tr><?php endif; ?>
                    <?php foreach ($recentExpenses as $row): ?><tr><td><?php echo htmlspecialchars($row['expense_date']); ?></td><td><?php echo htmlspecialchars($row['expense_type']); ?></td><td><?php echo platform_tenant_money($row['amount']); ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
            </div>
        </div>

        <div class="alert alert-secondary mt-3 mb-0">
            <i class="bi bi-shield-lock"></i> This first support-view batch intentionally contains no operational Add, Edit, Delete, Stock Update, Sale, Expense, or Cycle mutation controls.
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>
</body>
</html>
