<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');
require_once(__DIR__ . '/../lib/farm_intelligence.php');
requireLogin();
requireBusinessReportAccess();
$pdfRequested = pdf_report_is_requested();
if ($pdfRequested) { pdf_report_begin(); }
$tenantFarmId = requireCurrentFarmId();

$userType = getUserType();
$userFarmType = getUserFarmType();
$canChooseFarmType = isPlatformOwner() || hasRole('farm_admin', 'sales_rep');

$year = $_GET['year'] ?? date('Y');
$requestedFarmType = $canChooseFarmType ? ($_GET['farm_type'] ?? null) : $userFarmType;
// User access represents a dual-module assignment as "both", while report
// filters represent the same combined read scope as "all".
if ($requestedFarmType === 'both' && count(accessibleFarmTypes()) === 2) {
    $requestedFarmType = 'all';
}

// Sales-only farms have no livestock scope to normalize, but their neutral
// general sales must remain available to the analytics dashboard and export.
$salesOnlyScope = enabledFarmTypes() === []
    && farmHasModule('sales')
    && (isPlatformOwner() || hasRole('farm_admin', 'sales_rep', 'viewer'));
$farmType = $salesOnlyScope
    ? 'general'
    : normalizeFarmType($requestedFarmType, true, false, $canChooseFarmType);
$startDate = $year . '-01-01';
$endDate = $year . '-12-31';

// V2.2.49: Analytics delegates to the canonical consumed-cost intelligence layer.
// There is deliberately no local Sales - farm_expenses profit formula here.
$profitData = farm_intelligence_monthly_series($pdo, $tenantFarmId, (int)$year, $farmType);
$topProducts = farm_intelligence_top_products($pdo, $tenantFarmId, $startDate, $endDate, $farmType, 10);
$expenses = farm_intelligence_expense_breakdown($pdo, $tenantFarmId, $startDate, $endDate, $farmType);

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $fileFarmType = preg_replace('/[^a-z_]/i', '', (string) $farmType);
    $fileYear = preg_replace('/[^0-9]/', '', (string) $year);
    $filename = 'farm_reports_' . ($fileFarmType ?: 'all') . '_' . ($fileYear ?: date('Y')) . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        exit();
    }

    fputcsv($output, ['Farm Reports & Analytics']);
    fputcsv($output, ['Year', $year]);
    fputcsv($output, ['Farm Type', ucfirst($farmType)]);
    fputcsv($output, []);

    fputcsv($output, [
        'Month',
        'Farm Type',
        'Revenue',
        'Feed Consumed',
        'Other Operating Cost',
        'Total Operating Cost',
        'Operating Profit / Loss'
    ]);

    foreach ($profitData as $data) {
        fputcsv($output, [
            date('M Y', strtotime($data['month'] . '-01')),
            ucfirst($data['farm_type']),
            number_format((float) $data['total_sales'], 2, '.', ''),
            number_format((float) $data['feed_consumed'], 2, '.', ''),
            number_format((float) $data['other_operating_cost'], 2, '.', ''),
            number_format((float) $data['total_expenses'], 2, '.', ''),
            number_format((float) $data['net_profit'], 2, '.', ''),
        ]);
    }

    if (!empty($topProducts)) {
        fputcsv($output, []);
        fputcsv($output, ['Top Selling Products']);
        fputcsv($output, ['Product Type', 'Unit', 'Total Quantity', 'Total Revenue']);
        foreach ($topProducts as $product) {
            fputcsv($output, [
                $product['product_type'],
                $product['unit_label'],
                number_format((float) $product['total_quantity'], 2, '.', ''),
                number_format((float) $product['total_revenue'], 2, '.', ''),
            ]);
        }
    }

    if (!empty($expenses)) {
        fputcsv($output, []);
        fputcsv($output, ['Expense Breakdown']);
        fputcsv($output, ['Category', 'Total Amount']);
        foreach ($expenses as $expense) {
            fputcsv($output, [
                ucfirst($expense['category']),
                number_format((float) $expense['total_amount'], 2, '.', ''),
            ]);
        }
    }

    fclose($output);
    exit();
}
$pdfReportUrl = pdf_report_current_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Farm Management System</title>
    <script
        src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/reports-chart-fallback.js'); ?>"
        data-fallback-src="<?php echo htmlspecialchars(
            BASE_URL . versioned_asset('/assets/js/chart-fallback.js'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
    ></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous" data-chart-fallback></script>
</head>
<body>
    <?php include(__DIR__ . '/../navbar.php'); ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="bi bi-graph-up-arrow"></i> Farm Reports & Analytics</h4>
                        <div class="d-flex gap-2 mt-2 report-controls">
                            <select class="form-select" id="yearFilter" style="width: 150px;">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                            <select class="form-select" id="farmTypeFilter" style="width: 200px;">
                                <?php if ($canChooseFarmType): ?>
                                <?php if (count(accessibleFarmTypes()) === 2): ?><option value="all" <?php echo $farmType == 'all' ? 'selected' : ''; ?>>All Farms</option><?php endif; ?>
                                <?php foreach (accessibleFarmTypes() as $type): ?><option value="<?php echo $type; ?>" <?php echo $farmType === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?> Only</option><?php endforeach; ?>
                                <?php else: ?>
                                <option value="<?php echo $farmType; ?>" selected><?php echo ucfirst($farmType); ?> Only</option>
                                <?php endif; ?>
                            </select>
                            <a class="btn btn-primary" href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank">
                                <i class="bi bi-file-earmark-pdf"></i> PDF Report
                            </a>
                            <button class="btn btn-success" data-report-export-excel>
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Profit/Loss Chart -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Monthly Profit/Loss Analysis</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($pdfRequested): ?>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Month</th><th>Farm Type</th><th>Net Profit</th></tr></thead><tbody><?php foreach ($profitData as $row): ?><tr><td><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td><td><?php echo ucfirst($row['farm_type']); ?></td><td>₦<?php echo number_format($row['net_profit'], 2); ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?><canvas id="profitChart" height="100"></canvas><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Yearly Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $yearlyTotals = [
                                            'sales' => 0,
                                            'expenses' => 0,
                                            'profit' => 0
                                        ];

                                        foreach ($profitData as $data) {
                                            $yearlyTotals['sales'] += $data['total_sales'];
                                            $yearlyTotals['expenses'] += $data['total_expenses'];
                                            $yearlyTotals['profit'] += $data['net_profit'];
                                        }
                                        ?>
                                        <div class="mb-3">
                                            <h6>Total Revenue</h6>
                                            <h3 class="text-success">₦<?php echo number_format($yearlyTotals['sales'], 2); ?></h3>
                                        </div>
                                        <div class="mb-3">
                                            <h6>Total Operating Cost</h6>
                                            <h3 class="text-danger">₦<?php echo number_format($yearlyTotals['expenses'], 2); ?></h3>
                                        </div>
                                        <div class="mb-3">
                                            <h6>Operating Profit / Loss</h6>
                                            <h3 class="<?php echo $yearlyTotals['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                ₦<?php echo number_format($yearlyTotals['profit'], 2); ?>
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detailed Profit/Loss Table -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Detailed Profit/Loss Statement</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Month</th>
                                                <th>Farm Type</th>
                                                <th>Revenue</th>
                                                <th>Feed Consumed</th>
                                                <th>Other Operating Cost</th>
                                                <th>Total Operating Cost</th>
                                                <th>Operating Profit / Loss</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($profitData as $data): ?>
                                            <tr>
                                                <td><?php echo date('M Y', strtotime($data['month'] . '-01')); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $data['farm_type'] == 'poultry' ? 'info' : 'warning'; ?>">
                                                        <?php echo ucfirst($data['farm_type']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-success">₦<?php echo number_format($data['total_sales'], 2); ?></td>
                                                <td>₦<?php echo number_format($data['feed_consumed'], 2); ?></td>
                                                <td>₦<?php echo number_format($data['other_operating_cost'], 2); ?></td>
                                                <td class="text-danger">₦<?php echo number_format($data['total_expenses'], 2); ?></td>
                                                <td class="fw-bold <?php echo $data['net_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    ₦<?php echo number_format($data['net_profit'], 2); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Charts -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Top Selling Products</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($pdfRequested): ?>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Product</th><th>Unit</th><th>Quantity</th><th>Revenue</th></tr></thead><tbody><?php foreach ($topProducts as $product): ?><tr><td><?php echo htmlspecialchars($product['product_type']); ?></td><td><?php echo htmlspecialchars($product['unit_label']); ?></td><td><?php echo number_format((float)$product['total_quantity'], 2); ?></td><td>₦<?php echo number_format((float)$product['total_revenue'], 2); ?></td></tr><?php endforeach; ?><?php if (empty($topProducts)): ?><tr><td colspan="4">No sales data for this period.</td></tr><?php endif; ?></tbody></table></div>
<?php else: ?><canvas id="productsChart"></canvas><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Expense Breakdown</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($pdfRequested): ?>
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Category</th><th>Total Amount</th></tr></thead><tbody><?php foreach ($expenses as $expense): ?><tr><td><?php echo ucfirst(htmlspecialchars($expense['category'])); ?></td><td>₦<?php echo number_format((float)$expense['total_amount'], 2); ?></td></tr><?php endforeach; ?><?php if (empty($expenses)): ?><tr><td colspan="2">No expense data for this period.</td></tr><?php endif; ?></tbody></table></div>
<?php else: ?><canvas id="expensesChart"></canvas><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
 <script src="<?php echo BASE_URL; ?>/assets/js/edit-modal.js"></script>
    <div
        id="managementReportsConfig"
        hidden
        data-profit-labels="<?php echo htmlspecialchars(
            app_json_script(array_map(function($d) {
                return date('M', strtotime($d['month'] . '-01'));
            }, $profitData)),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-profit-values="<?php echo htmlspecialchars(
            app_json_script(array_column($profitData, 'net_profit')),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-product-labels="<?php echo htmlspecialchars(
            app_json_script(array_column($topProducts, 'display_product')),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-product-values="<?php echo htmlspecialchars(
            app_json_script(array_column($topProducts, 'total_revenue')),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-expense-labels="<?php echo htmlspecialchars(
            app_json_script(array_column($expenses, 'category')),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
        data-expense-values="<?php echo htmlspecialchars(
            app_json_script(array_column($expenses, 'total_amount')),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ); ?>"
    ></div>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/management-reports.js'); ?>"></script>
</body>
</html>
<?php
if ($pdfRequested) {
    pdf_report_finish('farm-reports-' . preg_replace('/[^0-9A-Za-z-]+/', '-', strtolower((string)$year)) . '.pdf', 'landscape', 'Farm Reports & Analytics - ' . $year);
}
?>
