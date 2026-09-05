<?php
/**
 * Platform Owner tenant-view commercial drill-down.
 *
 * Adds read-only tenant-scoped Inventory, Sales and Receivables detail to the
 * dedicated Platform Tenant View. This never changes session farm identity and
 * exposes no tenant mutation controls.
 */

$platformTenantCommercialPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($platformTenantCommercialPath === '/management/platform_tenant_view.php'
    || str_ends_with($platformTenantCommercialPath, '/management/platform_tenant_view.php'))) return;
if (!isset($_SESSION['user_id'])) return;
if (!function_exists('isPlatformOwner') || !isPlatformOwner()) return;
if (!isset($pdo) || !($pdo instanceof PDO)) return;

require_once __DIR__ . '/platform_owner_tenant_view.php';

$commercialTenant = platform_owner_tenant_from_request($pdo, 'farm_id');
$commercialTenantId = $commercialTenant ? (int)$commercialTenant['id'] : 0;
$commercialInventory = [];
$commercialSales = [];
$commercialReceivables = [];

if ($commercialTenantId > 0) {
    $stmt = $pdo->prepare(
        "SELECT item_name, current_stock, unit, min_stock_level, farm_type
         FROM stock_items
         WHERE farm_id = ? AND is_active = 1
         ORDER BY (current_stock <= min_stock_level) DESC, item_name ASC, id ASC
         LIMIT 12"
    );
    $stmt->execute([$commercialTenantId]);
    $commercialInventory = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        "SELECT sale_date, farm_type, product_type, quantity, unit_of_measure,
                total_amount, payment_received, customer_name
         FROM sales_records
         WHERE farm_id = ?
         ORDER BY sale_date DESC, id DESC
         LIMIT 12"
    );
    $stmt->execute([$commercialTenantId]);
    $commercialSales = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    try {
        $stmt = $pdo->prepare(
            "SELECT customer_name, SUM(amount) AS balance
             FROM customer_ledger_entries
             WHERE farm_id = ?
             GROUP BY customer_name
             HAVING SUM(amount) > 0
             ORDER BY balance DESC, customer_name ASC
             LIMIT 10"
        );
        $stmt->execute([$commercialTenantId]);
        $commercialReceivables = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $commercialReceivables = [];
    }
}

$commercialMoney = static fn($value): string => '₦' . number_format((float)$value, 2);
$commercialSection = '';
if ($commercialTenantId > 0) {
    ob_start();
    ?>
    <div id="platformTenantCommercialDrilldown" class="row g-3 mt-1">
        <div class="col-xl-6">
            <div class="card tenant-view-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-semibold"><i class="bi bi-box-seam"></i> Inventory Detail</div>
                    <span class="badge text-bg-light border">Read only · latest 12 items</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm tenant-view-table mb-0">
                        <thead><tr><th>Item</th><th>Type</th><th>Stock</th><th>Minimum</th></tr></thead>
                        <tbody>
                        <?php if (!$commercialInventory): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No active inventory items.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($commercialInventory as $item):
                            $low = (float)$item['current_stock'] <= (float)$item['min_stock_level'];
                        ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars((string)$item['item_name']); ?></td>
                                <td class="text-capitalize"><?php echo htmlspecialchars((string)$item['farm_type']); ?></td>
                                <td><span class="badge text-bg-<?php echo $low ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars((string)$item['current_stock']); ?> <?php echo htmlspecialchars((string)$item['unit']); ?></span></td>
                                <td><?php echo htmlspecialchars((string)$item['min_stock_level']); ?> <?php echo htmlspecialchars((string)$item['unit']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card tenant-view-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-semibold"><i class="bi bi-cart-check"></i> Sales Detail</div>
                    <span class="badge text-bg-light border">Read only · latest 12</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm tenant-view-table mb-0">
                        <thead><tr><th>Date</th><th>Product</th><th>Customer</th><th>Total</th><th>Received</th></tr></thead>
                        <tbody>
                        <?php if (!$commercialSales): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No sales records.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($commercialSales as $sale): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$sale['sale_date']); ?></td>
                                <td><div class="fw-semibold"><?php echo htmlspecialchars((string)$sale['product_type']); ?></div><div class="tenant-view-meta text-capitalize"><?php echo htmlspecialchars((string)$sale['farm_type']); ?></div></td>
                                <td><?php echo htmlspecialchars((string)($sale['customer_name'] ?: '—')); ?></td>
                                <td><?php echo $commercialMoney($sale['total_amount']); ?></td>
                                <td><?php echo $commercialMoney($sale['payment_received'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card tenant-view-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-semibold"><i class="bi bi-wallet2"></i> Open Receivables</div>
                    <span class="badge text-bg-light border">Read only · top 10 balances</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm tenant-view-table mb-0">
                        <thead><tr><th>Customer</th><th>Outstanding Balance</th></tr></thead>
                        <tbody>
                        <?php if (!$commercialReceivables): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No open receivables.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($commercialReceivables as $receivable): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars((string)$receivable['customer_name']); ?></td>
                                <td><span class="badge text-bg-warning"><?php echo $commercialMoney($receivable['balance']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
    $commercialSection = (string)ob_get_clean();
}

ob_start(static function (string $html) use ($commercialTenantId, $commercialSection): string {
    if ($commercialTenantId < 1 || $commercialSection === '' || stripos($html, 'id="platformTenantCommercialDrilldown"') !== false) {
        return $html;
    }

    $needle = '<div class="alert alert-secondary mt-3 mb-0">';
    $position = strpos($html, $needle);
    if ($position === false) return $html;

    return substr($html, 0, $position) . $commercialSection . substr($html, $position);
});
