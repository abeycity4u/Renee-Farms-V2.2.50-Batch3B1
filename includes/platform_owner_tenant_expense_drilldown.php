<?php
/**
 * Platform Owner tenant-view expense drill-down.
 *
 * Adds tenant-scoped, read-only expense detail to the dedicated Platform Tenant
 * View. It does not change session farm identity and exposes no mutation controls.
 */

$platformTenantExpensePath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($platformTenantExpensePath === '/management/platform_tenant_view.php'
    || str_ends_with($platformTenantExpensePath, '/management/platform_tenant_view.php'))) return;
if (!isset($_SESSION['user_id'])) return;
if (!function_exists('isPlatformOwner') || !isPlatformOwner()) return;
if (!isset($pdo) || !($pdo instanceof PDO)) return;

require_once __DIR__ . '/platform_owner_tenant_view.php';

$expenseTenant = platform_owner_tenant_from_request($pdo, 'farm_id');
$expenseTenantId = $expenseTenant ? (int)$expenseTenant['id'] : 0;
$tenantExpenses = [];
$expenseCategoryTotals = [];
$expenseGrandTotal = 0.0;

if ($expenseTenantId > 0) {
    $stmt = $pdo->prepare(
        "SELECT e.expense_date, e.farm_type, e.production_type, e.category,
                e.amount, e.unit, e.description, pc.cycle_code, u.full_name
         FROM farm_expenses e
         LEFT JOIN production_cycles pc ON pc.id = e.cycle_id AND pc.farm_id = e.farm_id
         LEFT JOIN users u ON u.id = e.user_id AND u.farm_id = e.farm_id
         WHERE e.farm_id = ?
         ORDER BY e.expense_date DESC, e.id DESC
         LIMIT 12"
    );
    $stmt->execute([$expenseTenantId]);
    $tenantExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        "SELECT category, COALESCE(SUM(amount * unit), 0) AS category_total
         FROM farm_expenses
         WHERE farm_id = ?
         GROUP BY category
         ORDER BY category_total DESC, category ASC"
    );
    $stmt->execute([$expenseTenantId]);
    $expenseCategoryTotals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount * unit), 0) FROM farm_expenses WHERE farm_id = ?');
    $stmt->execute([$expenseTenantId]);
    $expenseGrandTotal = (float)$stmt->fetchColumn();
}

$expenseMoney = static fn($value): string => '₦' . number_format((float)$value, 2);
$expenseSection = '';
if ($expenseTenantId > 0) {
    ob_start();
    ?>
    <div id="platformTenantExpenseDrilldown" class="row g-3 mt-1">
        <div class="col-xl-8">
            <div class="card tenant-view-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-semibold"><i class="bi bi-cash-stack"></i> Expense Detail</div>
                    <span class="badge text-bg-light border">Read only · latest 12</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm tenant-view-table mb-0">
                        <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Cycle</th><th>Description</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php if (!$tenantExpenses): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No expense records.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($tenantExpenses as $expense):
                            $lineTotal = (float)($expense['amount'] ?? 0) * (float)($expense['unit'] ?? 1);
                            $farmType = (string)($expense['farm_type'] ?? '');
                            $productionType = trim((string)($expense['production_type'] ?? ''));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$expense['expense_date']); ?></td>
                                <td class="text-capitalize">
                                    <?php echo htmlspecialchars($farmType ?: 'general'); ?>
                                    <?php if ($productionType !== ''): ?><div class="tenant-view-meta"><?php echo htmlspecialchars($productionType); ?></div><?php endif; ?>
                                </td>
                                <td class="text-capitalize"><?php echo htmlspecialchars((string)$expense['category']); ?></td>
                                <td><?php echo htmlspecialchars((string)($expense['cycle_code'] ?: '—')); ?></td>
                                <td><?php echo htmlspecialchars((string)($expense['description'] ?: '—')); ?></td>
                                <td class="fw-semibold"><?php echo $expenseMoney($lineTotal); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card tenant-view-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="fw-semibold"><i class="bi bi-pie-chart"></i> Expense Summary</div>
                    <span class="badge text-bg-light border">All recorded expenses</span>
                </div>
                <div class="card-body">
                    <div class="tenant-view-meta">Total recorded expenses</div>
                    <div class="tenant-view-stat mb-3"><?php echo $expenseMoney($expenseGrandTotal); ?></div>
                    <?php if (!$expenseCategoryTotals): ?>
                        <div class="text-muted">No expense categories.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($expenseCategoryTotals as $row): ?>
                                <div class="list-group-item px-0 d-flex justify-content-between gap-3">
                                    <span class="text-capitalize"><?php echo htmlspecialchars((string)$row['category']); ?></span>
                                    <strong><?php echo $expenseMoney($row['category_total']); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    $expenseSection = (string)ob_get_clean();
}

ob_start(static function (string $html) use ($expenseTenantId, $expenseSection): string {
    if ($expenseTenantId < 1 || $expenseSection === '' || stripos($html, 'id="platformTenantExpenseDrilldown"') !== false) {
        return $html;
    }

    $needle = '<div class="alert alert-secondary mt-3 mb-0">';
    $position = strpos($html, $needle);
    if ($position === false) return $html;

    return substr($html, 0, $position) . $expenseSection . substr($html, $position);
});
