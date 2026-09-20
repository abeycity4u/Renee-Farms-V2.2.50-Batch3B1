<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../lib/attribution.php');
require_once(__DIR__ . '/../lib/transaction_actor_display.php');
require_once(__DIR__ . '/../lib/inventory_financial.php');
require_once(__DIR__ . '/../lib/poultry_expense_entry.php');
require_once(__DIR__ . '/../lib/financial_allocation_workspace.php');
requireLogin();
$pdfRequested = pdf_report_is_requested();
if ($pdfRequested) { pdf_report_begin(); }

// Check access
if (!checkAccess('poultry') && !hasPermission($_SESSION['user_type'], 'poultry_expenses')) {
    header('Location: ' . BASE_URL . '/no_access.php');
    exit();
}

$canAddExpenses = poultry_expense_entry_can('broiler', 'add');
$tenantFarmId = requireCurrentFarmId();
$expenseCycleStmt = $pdo->prepare("SELECT id,cycle_code,status FROM production_cycles WHERE farm_id=? AND farm_type='poultry' AND production_type='broiler' ORDER BY start_date DESC,id DESC");
$expenseCycleStmt->execute([$tenantFarmId]);
$expenseCycles = $expenseCycleStmt->fetchAll(PDO::FETCH_ASSOC);

$month = $_GET['month'] ?? date('Y-m');
$yearMonth = date('Y-m', strtotime($month));
$monthSelectorDate = date('Y-m-d', strtotime($yearMonth . '-' . min((int)date('d'), (int)date('t', strtotime($yearMonth . '-01')))));
$startDate = date('Y-m-01', strtotime($yearMonth . '-01'));
$endDate = date('Y-m-t', strtotime($yearMonth . '-01'));

// Get expenses for the month
$query = "SELECT e.*, u.full_name AS recorded_by_name, u.user_type AS recorded_by_user_type, pc.cycle_code AS expense_cycle_code
          FROM farm_expenses e
          LEFT JOIN users u ON e.user_id = u.id AND u.farm_id = e.farm_id
          LEFT JOIN production_cycles pc ON pc.id = e.cycle_id AND pc.farm_id = e.farm_id
          WHERE e.farm_id = ? AND e.expense_date BETWEEN ? AND ?
          AND e.farm_type IN ('poultry', 'both')
          AND e.poultry_category = 'broiler'
          ORDER BY e.expense_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$tenantFarmId, $startDate, $endDate]);
$expenses = $stmt->fetchAll();


/*
 * Legacy filtered pages remain familiar Layer/Broiler views, but action
 * visibility must use the same canonical operational authority as the
 * consolidated Poultry Expenses hub.
 */
$expenseActionCapabilities = [];
$hasAnyExpenseActions = false;
$hasAnyEditableExpenses = false;

foreach ($expenses as $expense) {
    $expenseId =
        (int)(
            $expense[
                'id'
            ]
            ?? 0
        );

    $canAllocateExpense =
        financial_allocation_workspace_parent_is_eligible(
            $expense
        )
        &&
        financial_allocation_workspace_can_access(
            $expense,
            'operational'
        );

    $canEditExpense =
        permission_catalog_expense_operational_can(
            $expense,
            'edit'
        );

    $canDeleteExpense =
        permission_catalog_expense_operational_can(
            $expense,
            'delete'
        );

    $expenseActionCapabilities[
        $expenseId
    ] = [
        'allocate' =>
            $canAllocateExpense,

        'edit' =>
            $canEditExpense,

        'delete' =>
            $canDeleteExpense,
    ];

    if ($canEditExpense) {
        $hasAnyEditableExpenses =
            true;
    }

    if (
        $canAllocateExpense
        ||
        $canEditExpense
        ||
        $canDeleteExpense
    ) {
        $hasAnyExpenseActions =
            true;
    }
}


// Calculate manual non-stock expense totals. Historical feed expenses remain
// visible for audit, but Feed is no longer accepted for new manual entries.
$categoryTotals = [];
foreach ($expenses as $expense) {
    $lineTotal = (float)($expense['amount'] ?? 0) * (float)($expense['unit'] ?? 1);
    $key = (string)($expense['category'] ?? 'misc');
    $categoryTotals[$key] = ($categoryTotals[$key] ?? 0) + $lineTotal;
}
$manualExpenseTotal = round(array_sum($categoryTotals), 2);

$inventoryPurchases = inventory_financial_receipts($pdo, $tenantFarmId, $startDate, $endDate, 'poultry', 'broiler');
$inventoryPurchaseTotal = inventory_financial_receipt_total($inventoryPurchases);
$inventoryCategoryTotals = inventory_financial_receipt_category_totals($inventoryPurchases);
$spendingCategoryTotals = inventory_financial_combined_spending_totals($categoryTotals, $inventoryCategoryTotals);
$totalSpending = round($manualExpenseTotal + $inventoryPurchaseTotal, 2);

// Add Expense creation is centralized in poultry/expenses.php.
// This production-specific page remains a filtered view/action surface.

$pdfReportUrl = pdf_report_current_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <?php include(__DIR__ . '/../navbar_head.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broiler Expenses Record - Renee Farms</title>
</head>
<body class="poultry-page">
    <?php include(__DIR__ . '/../navbar.php'); ?>

    <div class="container-fluid mt-4 poultry-shell">

        <div class="row">
            <div class="col-12">
                <div class="card poultry-panel">
                    <div class="card-header poultry-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h4 class="mb-0">
                            <i class="bi bi-cash-stack"></i>
                            Broiler Expenses - <?php echo date('F Y', strtotime($yearMonth)); ?>
                        </h4>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="date" class="form-control js-calendar-input app-month-selector" id="monthSelector"
                                   value="<?php echo $monthSelectorDate; ?>">
                            <a class="btn btn-light" href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Report</a>
                            <?php if ($canAddExpenses): ?>
                            <a
                                class="btn btn-primary"
                                href="<?php
                                    echo app_attr(
                                        rtrim(BASE_URL, '/')
                                        . '/poultry/expenses.php?'
                                        . http_build_query([
                                            'month' =>
                                                $yearMonth,

                                            'tab' =>
                                                'broiler',

                                            'add' =>
                                                '1',

                                            'production_type' =>
                                                'broiler',
                                        ])
                                    );
                                ?>"
                            >
                                <i class="bi bi-plus-circle"></i> Add Expense
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="smart-poultry-note p-3 mb-4 d-flex gap-3 align-items-start">
                            <i class="bi bi-stars fs-4"></i>
                            <div>
                                <div class="fw-bold">Cost intelligence</div>
                                <div class="small">Expense totals can drive cost-per-bird, margin forecasts and abnormal-spend alerts.</div>
                            </div>
                        </div>
                        <!-- Summary -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h2>TOTAL BROILER SPENDING: ₦<?php echo number_format($totalSpending, 2); ?></h2>
                                        <small>Inventory Purchases ₦<?php echo number_format($inventoryPurchaseTotal, 2); ?> + Non-stock Expenses ₦<?php echo number_format($manualExpenseTotal, 2); ?></small>
                                        <h5>For <?php echo date('F Y', strtotime($yearMonth)); ?></h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Category Breakdown -->
                        <div class="row mb-4">
                            <?php foreach ($spendingCategoryTotals as $category => $total): ?>
                            <div class="col-md-2 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-uppercase"><?php echo htmlspecialchars(inventory_financial_spending_label((string)$category)); ?></h6>
                                        <h4 class="text-danger">₦<?php echo number_format($total, 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>


                        <div class="card border-primary-subtle mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <strong><i class="bi bi-box-arrow-in-down"></i> Inventory Purchases</strong>
                                    <div class="small text-muted">Received stock shown here from the Inventory ledger. These are purchase/cash records, not a second operating-expense entry.</div>
                                </div>
                                <span class="badge text-bg-primary">Total ₦<?php echo number_format($inventoryPurchaseTotal, 2); ?></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 poultry-table">
                                    <thead><tr><th>Date</th><th>Item</th><th>Qty</th><th>Unit Cost</th><th>Total</th><th>Attribution</th><th>Source</th></tr></thead>
                                    <tbody>
                                    <?php if (!$inventoryPurchases): ?>
                                        <tr><td colspan="7" class="text-center text-muted py-3">No inventory purchases recorded for this month.</td></tr>
                                    <?php else: foreach ($inventoryPurchases as $purchase): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($purchase['transaction_date'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($purchase['item_name']); ?></strong><?php if (!empty($purchase['category_name'])): ?><div class="small text-muted"><?php echo htmlspecialchars($purchase['category_name']); ?></div><?php endif; ?></td>
                                            <td><?php echo number_format((float)$purchase['quantity'], 2); ?> <?php echo htmlspecialchars($purchase['unit']); ?></td>
                                            <td>₦<?php echo number_format((float)$purchase['unit_cost'], 2); ?></td>
                                            <td class="fw-semibold">₦<?php echo number_format((float)$purchase['total_cost'], 2); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst((string)($purchase['production_type'] ?: 'shared'))); ?><?php if (!empty($purchase['cycle_code'])): ?><div class="small text-muted"><?php echo htmlspecialchars($purchase['cycle_code']); ?></div><?php endif; ?></td>
                                            <td><span class="badge text-bg-light border">Inventory</span></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer small text-muted">Inventory purchases are included in Total Spending, but remain separate from profitability cost recognition so stocked items are not charged again when consumed.</div>
                        </div>

                        <!-- Detailed Expenses -->
                        <h5>Detailed Expenses</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover poultry-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th>Amount (₦/unit)</th>
                                        <th>Total (₦)</th>
                                        <th>Production / Cycle</th>
                                        <th>Description</th>
                                        <th>Recorded By</th>
                                        <?php if ($hasAnyExpenseActions): ?>
                                        <th class="no-print">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="<?php echo $hasAnyExpenseActions ? 10 : 9; ?>" class="text-center text-muted py-4">
                                            No expenses recorded for this month
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                        <?php
                                            $rowActions =
                                                $expenseActionCapabilities[
                                                    (int)(
                                                        $expense[
                                                            'id'
                                                        ]
                                                        ?? 0
                                                    )
                                                ]
                                                ?? [
                                                    'allocate' =>
                                                        false,

                                                    'edit' =>
                                                        false,

                                                    'delete' =>
                                                        false,
                                                ];

                                            $canAllocateExpense =
                                                (bool)$rowActions[
                                                    'allocate'
                                                ];

                                            $canEditExpense =
                                                (bool)$rowActions[
                                                    'edit'
                                                ];

                                            $canDeleteExpense =
                                                (bool)$rowActions[
                                                    'delete'
                                                ];
                                        ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                                            <td class="text-nowrap"><code><?php echo htmlspecialchars((string)($expense['public_reference'] ?? '—')); ?></code></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo ucfirst($expense['category']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($expense['unit'] ?? 1, 2); ?></td>
                                            <td class="text-danger fw-bold">
                                                ₦<?php echo number_format($expense['amount'], 2); ?>
                                            </td>
                                            <td class="text-danger fw-bold">
                                                ₦<?php echo number_format(($expense['amount'] ?? 0) * ($expense['unit'] ?? 1), 2); ?>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars(attribution_production_label('poultry', ($expense['production_type'] ?? 'broiler'))); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars(attribution_cycle_label('poultry', ($expense['production_type'] ?? 'broiler'), $expense['expense_cycle_code'] ?? null)); ?></div>
                                            </td>
                                            <td><?php echo app_html($expense['description'] ?: '--'); ?></td>
                                            <td><small><?php echo app_html(transaction_recorded_by_label_from_row($pdo, $tenantFarmId, $expense)); ?></small></td>
                                            <?php if ($hasAnyExpenseActions): ?>
                                            <td class="no-print">
                                                <?php if ($canAllocateExpense): ?>
                                                <a class="btn btn-sm btn-outline-secondary"
                                                   href="<?php echo htmlspecialchars(financial_allocation_workspace_url((int)$expense['id'], 'operational'), ENT_QUOTES); ?>"
                                                   title="Allocate shared cost"
                                                   aria-label="Allocate shared cost">
                                                    <i class="bi bi-diagram-3"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if ($canEditExpense): ?>
                                                <button class="btn btn-sm btn-outline-primary edit-expense-btn"
                                                        data-id="<?php echo $expense['id']; ?>"
                                                        data-date="<?php echo $expense['expense_date']; ?>"
                                                        data-category="<?php echo $expense['category']; ?>"
                                                        data-amount="<?php echo $expense['amount']; ?>"
                                                        data-unit="<?php echo $expense['unit'] ?? 1; ?>"
                                                        data-description="<?php echo htmlspecialchars($expense['description'] ?? '', ENT_QUOTES); ?>"
                                                        data-production-type="<?php echo htmlspecialchars($expense['production_type'] ?? 'broiler', ENT_QUOTES); ?>"
                                                        data-cycle="<?php echo (int)($expense['cycle_id'] ?? 0); ?>"
                                                        data-poultry="<?php echo $expense['poultry_category'] ?? 'broiler'; ?>"
                                                        >
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php endif; ?>

                                                <?php if ($canDeleteExpense): ?>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        data-delete-expense-id="<?php echo (int)$expense['id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>

                                                <?php if (
                                                    !$canAllocateExpense
                                                    &&
                                                    !$canEditExpense
                                                    &&
                                                    !$canDeleteExpense
                                                ): ?>
                                                <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <td colspan="5"><strong>TOTAL</strong></td>
                                        <td class="text-danger fw-bold">₦<?php echo number_format($manualExpenseTotal, 2); ?></td>
                                        <td colspan="<?php echo $hasAnyExpenseActions ? 4 : 3; ?>"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($hasAnyEditableExpenses): ?>
    <!-- Edit Expense Modal -->
    <div class="modal fade" id="editExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editExpenseForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="expense_id" id="editExpenseId">
                        <input type="hidden" name="farm_type" value="poultry">
                        <input type="hidden" name="poultry_category" id="editPoultryCategory" value="broiler">
                        <div class="mb-3">
                            <label>Date</label>
                            <input type="date" name="expense_date" id="editExpenseDate" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Production Type</label>
                            <select class="form-select" disabled aria-label="Production Type">
                                <option selected>Broiler</option>
                            </select>
                            <input type="hidden" name="production_type" value="broiler">
                            <small class="text-muted">This expense page is scoped to Broiler; use the matching module for another poultry production type.</small>
                        </div>
                        <div class="mb-3">
                            <label>Production Cycle (optional)</label>
                            <select name="cycle_id" id="editExpenseCycle" class="form-select">
                                <option value="0">Shared between Broiler cycles</option>
                                <?php foreach($expenseCycles as $cycle): ?><option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'].' — '.$cycle['status']); ?></option><?php endforeach; ?>
                            </select>
                            <small class="text-muted">Choose a cycle only when this expense belongs directly to it.</small>
                        </div>
                        <div class="mb-3">
                            <label>Category</label>
                            <select name="category" id="editCategory" class="form-select" required>
                                <option value="feeds">Feeds (legacy historical record)</option>
                                <option value="medication">Medication</option>
                                <option value="salary">Salary</option>
                                <option value="logistic">Logistic</option>
                                <option value="fuel">Fuel</option>
                                <option value="misc">Misc</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Unit</label>
                            <input type="number" name="unit" id="editUnit" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label>Amount (₦)</label>
                            <input type="number" name="amount" id="editAmount" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Expense entry is centralized in the Poultry Expenses hub. -->

     <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/jquery/jquery.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/jquery.dataTables.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables/js/dataTables.bootstrap5.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/datatables-responsive/js/dataTables.responsive.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/main.js'); ?>"></script>
 <script src="<?php echo BASE_URL; ?>/assets/js/edit-modal.js"></script>

    <div
    class="d-none"
    id="broilerExpensesConfig"
    data-csrf-token="<?php echo app_attr(csrf_token()); ?>"
></div>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/broiler-expenses.js'); ?>"></script>
</body>
</html>
<?php
if ($pdfRequested) {
    pdf_report_finish('broiler-expenses-' . $yearMonth . '.pdf', 'landscape', 'Broiler Expenses Record - ' . date('F Y', strtotime($yearMonth)));
}
?>
