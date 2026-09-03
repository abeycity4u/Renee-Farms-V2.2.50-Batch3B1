<?php require_once(dirname(__DIR__) . '/init.php'); ?>
<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../includes/pdf/PdfReportService.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../lib/attribution.php');
require_once(__DIR__ . '/../lib/inventory_financial.php');
requireLogin();
$pdfRequested = pdf_report_is_requested();
if ($pdfRequested) { pdf_report_begin(); }

// Check access
if (!checkAccess('poultry') && !hasPermission($_SESSION['user_type'], 'poultry_expenses')) {
    header('Location: ' . BASE_URL . '/no_access.php');
    exit();
}

$canManageExpenses = isPlatformOwner() || hasRole('farm_admin') || hasPermission($_SESSION['user_type'], 'poultry_expenses');
$tenantFarmId = requireCurrentFarmId();
$expenseCycleStmt = $pdo->prepare("SELECT id,cycle_code,status FROM production_cycles WHERE farm_id=? AND farm_type='poultry' AND production_type='layer' ORDER BY start_date DESC,id DESC");
$expenseCycleStmt->execute([$tenantFarmId]);
$expenseCycles = $expenseCycleStmt->fetchAll(PDO::FETCH_ASSOC);

$month = $_GET['month'] ?? date('Y-m');
$yearMonth = date('Y-m', strtotime($month));
$monthSelectorDate = date('Y-m-d', strtotime($yearMonth . '-' . min((int)date('d'), (int)date('t', strtotime($yearMonth . '-01')))));
$startDate = date('Y-m-01', strtotime($yearMonth . '-01'));
$endDate = date('Y-m-t', strtotime($yearMonth . '-01'));

// Get expenses for the month (inclusive of the selected month range)
$query = "SELECT e.*, u.full_name, pc.cycle_code AS expense_cycle_code
          FROM farm_expenses e
          LEFT JOIN users u ON e.user_id = u.id AND u.farm_id = e.farm_id
          LEFT JOIN production_cycles pc ON pc.id = e.cycle_id AND pc.farm_id = e.farm_id
          WHERE e.farm_id = ? AND e.expense_date BETWEEN ? AND ?
          AND e.farm_type IN ('poultry', 'both')
          AND e.poultry_category = 'layer'
          ORDER BY e.expense_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$tenantFarmId, $startDate, $endDate]);
$expenses = $stmt->fetchAll();

// Calculate manual non-stock expense totals. Historical feed expenses remain
// visible for audit, but Feed is no longer accepted for new manual entries.
$categoryTotals = [];
foreach ($expenses as $expense) {
    $lineTotal = (float)($expense['amount'] ?? 0) * (float)($expense['unit'] ?? 1);
    $key = (string)($expense['category'] ?? 'misc');
    $categoryTotals[$key] = ($categoryTotals[$key] ?? 0) + $lineTotal;
}
$manualExpenseTotal = round(array_sum($categoryTotals), 2);

$inventoryPurchases = inventory_financial_receipts($pdo, $tenantFarmId, $startDate, $endDate, 'poultry', 'layer');
$inventoryPurchaseTotal = inventory_financial_receipt_total($inventoryPurchases);
$inventoryCategoryTotals = inventory_financial_receipt_category_totals($inventoryPurchases);
$spendingCategoryTotals = inventory_financial_combined_spending_totals($categoryTotals, $inventoryCategoryTotals);
$totalSpending = round($manualExpenseTotal + $inventoryPurchaseTotal, 2);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('Invalid request token.');
    }
    $expenseDate = trim((string)($_POST['expense_date'] ?? ''));
    $dateObject = DateTime::createFromFormat('Y-m-d', $expenseDate);
    $amount = (float)($_POST['amount'] ?? 0);
    $unit = (float)($_POST['unit'] ?? 1);
    $expenseCategory = trim((string)($_POST['category'] ?? ''));
    $allowedCategories = ['salary', 'logistic', 'fuel', 'misc'];
    if (!$dateObject || $dateObject->format('Y-m-d') !== $expenseDate || $amount <= 0 || $unit <= 0 || !in_array($expenseCategory, $allowedCategories, true)) {
        $_SESSION['error'] = 'Please provide a valid date, category, amount and quantity greater than zero.';
        header("Location: layer_expenses.php?month=" . date('Y-m', strtotime($expenseDate ?: 'now')));
        exit();
    }

    $cycleId = (int)($_POST['cycle_id'] ?? 0);
    if ($cycleId > 0) {
        try { attribution_validate_cycle($pdo, $tenantFarmId, $cycleId, 'poultry', 'layer'); }
        catch (RuntimeException $e) {
            $_SESSION['error']=$e->getMessage();
            header("Location: layer_expenses.php?month=" . date('Y-m', strtotime($expenseDate)));
            exit();
        }
    }
    $scope = attribution_scope($cycleId > 0 ? $cycleId : null, 'poultry', 'layer');
    $stmt = $pdo->prepare("INSERT INTO farm_expenses
        (farm_id, expense_date, farm_type, production_type, attribution_scope, cycle_id, poultry_category, category, amount, unit, description, user_id)
        VALUES (?, ?, 'poultry', 'layer', ?, ?, 'layer', ?, ?, ?, ?, ?)");
    $stmt->execute([
        $tenantFarmId, $expenseDate, $scope, $cycleId > 0 ? $cycleId : null,
        $expenseCategory, $amount, $unit,
        trim((string)($_POST['description'] ?? '')), $_SESSION['user_id']
    ]);

    $_SESSION['success'] = "Layer expense recorded successfully!";
    $redirectMonth = date('Y-m', strtotime($expenseDate));
    header("Location: layer_expenses.php?month=" . $redirectMonth);
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
    <title>Layer Expenses Record - Renee Farms</title>
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
                            Layer Expenses Record - <?php echo date('F Y', strtotime($yearMonth)); ?>
                        </h4>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="date" class="form-control js-calendar-input" id="monthSelector" 
                                   value="<?php echo $monthSelectorDate; ?>" style="width: 200px;">
                            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($pdfReportUrl); ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF Report</a>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                <i class="bi bi-plus-circle"></i> Add Expense
                            </button>
                        </div>
                    </div>
                    
                    <!-- Expense Summary -->
                    <div class="card-body bg-light">
                        <div class="smart-poultry-note p-3 mb-4 d-flex gap-3 align-items-start">
                            <i class="bi bi-stars fs-4"></i>
                            <div>
                                <div class="fw-bold">Cost intelligence</div>
                                <div class="small">Expense categories can drive cost-per-crate, budget variance and abnormal-spend alerts.</div>
                            </div>
                        </div>
                        <h5>Expense Summary for <?php echo date('F Y', strtotime($yearMonth)); ?></h5>
                        <div class="row mb-4">
                            <?php foreach ($spendingCategoryTotals as $category => $total): 
                                if ($total > 0):
                                    $percentage = $totalSpending > 0 ? ($total / $totalSpending * 100) : 0;
                            ?>
                            <div class="col-md-2 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-uppercase"><?php echo htmlspecialchars(inventory_financial_spending_label((string)$category)); ?></h6>
                                        <h4 class="text-danger">₦<?php echo number_format($total, 2); ?></h4>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-danger" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small><?php echo number_format($percentage, 1); ?>% of total</small>
                                    </div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                            
                            <div class="col-md-12 mt-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h4>TOTAL SPENDING: ₦<?php echo number_format($totalSpending, 2); ?></h4>
                                        <small>Inventory Purchases ₦<?php echo number_format($inventoryPurchaseTotal, 2); ?> + Non-stock Expenses ₦<?php echo number_format($manualExpenseTotal, 2); ?></small>
                                    </div>
                                </div>
                            </div>
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
                                    <thead>
                                        <tr>
                                            <th>Date</th><th>Item</th><th>Qty</th><th>Unit Cost</th><th>Total</th><th>Attribution</th><th>Source</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!$inventoryPurchases): ?>
                                        <tr><td colspan="7" class="text-center text-muted py-3">No inventory purchases recorded for this month.</td></tr>
                                    <?php else: foreach ($inventoryPurchases as $purchase): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($purchase['transaction_date'])); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($purchase['item_name']); ?></strong>
                                                <?php if (!empty($purchase['category_name'])): ?><div class="small text-muted"><?php echo htmlspecialchars($purchase['category_name']); ?></div><?php endif; ?>
                                            </td>
                                            <td><?php echo number_format((float)$purchase['quantity'], 2); ?> <?php echo htmlspecialchars($purchase['unit']); ?></td>
                                            <td>₦<?php echo number_format((float)$purchase['unit_cost'], 2); ?></td>
                                            <td class="fw-semibold">₦<?php echo number_format((float)$purchase['total_cost'], 2); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars(ucfirst((string)($purchase['production_type'] ?: 'shared'))); ?>
                                                <?php if (!empty($purchase['cycle_code'])): ?><div class="small text-muted"><?php echo htmlspecialchars($purchase['cycle_code']); ?></div><?php endif; ?>
                                            </td>
                                            <td><span class="badge text-bg-light border">Inventory</span></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer small text-muted">Inventory purchases are included in Total Spending, but remain separate from profitability cost recognition so stocked items are not charged again when consumed.</div>
                        </div>

                        <!-- Detailed Expenses Table -->
                        <h5>Detailed Expenses</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover poultry-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th>Amount (₦/unit)</th>
                                        <th>Total (₦)</th>
                                        <th>Production / Cycle</th>
                                        <th>Description</th>
                                        <th>Recorded By</th>
                                        <?php if ($canManageExpenses): ?>
                                        <th class="no-print">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="<?php echo $canManageExpenses ? 9 : 8; ?>" class="text-center text-muted py-4">
                                            <i class="bi bi-receipt display-4 d-block mb-2"></i>
                                            No expenses recorded for this month
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($expense['category']) {
                                                        case 'feeds': echo 'primary'; break;
                                                        case 'medication': echo 'success'; break;
                                                        case 'salary': echo 'warning'; break;
                                                        case 'logistic': echo 'info'; break;
                                                        case 'fuel': echo 'secondary'; break;
                                                        default: echo 'dark';
                                                    }
                                                ?>">
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
                                                <div class="fw-semibold"><?php echo htmlspecialchars(attribution_production_label('poultry', ($expense['production_type'] ?? 'layer'))); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars(attribution_cycle_label('poultry', ($expense['production_type'] ?? 'layer'), $expense['expense_cycle_code'] ?? null)); ?></div>
                                            </td>
                                            <td>
                                                <?php if ($expense['description']): ?>
                                                <small><?php echo $expense['description']; ?></small>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo $expense['full_name']; ?></small>
                                            </td>
                                            <?php if ($canManageExpenses): ?>
                                            <td class="no-print">
                                                <button class="btn btn-sm btn-outline-primary edit-expense-btn"
                                                        data-id="<?php echo $expense['id']; ?>"
                                                        data-date="<?php echo $expense['expense_date']; ?>"
                                                        data-category="<?php echo $expense['category']; ?>"
                                                        data-amount="<?php echo $expense['amount']; ?>"
                                                        data-unit="<?php echo $expense['unit'] ?? 1; ?>"
                                                        data-description="<?php echo htmlspecialchars($expense['description'] ?? '', ENT_QUOTES); ?>"
                                                        data-production-type="<?php echo htmlspecialchars($expense['production_type'] ?? 'layer', ENT_QUOTES); ?>"
                                                        data-cycle="<?php echo (int)($expense['cycle_id'] ?? 0); ?>"
                                                        data-poultry="<?php echo $expense['poultry_category'] ?? 'layer'; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteExpense(<?php echo $expense['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <td colspan="4"><strong>TOTAL</strong></td>
                                        <td class="text-danger fw-bold">₦<?php echo number_format($manualExpenseTotal, 2); ?></td>
                                        <td colspan="<?php echo $canManageExpenses ? 4 : 3; ?>"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canManageExpenses): ?>
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
                        <input type="hidden" name="poultry_category" id="editPoultryCategory" value="layer">
                        <div class="mb-3">
                            <label>Date</label>
                            <input type="date" name="expense_date" id="editExpenseDate" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Production Type</label>
                            <select class="form-select" disabled aria-label="Production Type">
                                <option selected>Layer</option>
                            </select>
                            <input type="hidden" name="production_type" value="layer">
                            <small class="text-muted">This expense page is scoped to Layer; use the matching module for another poultry production type.</small>
                        </div>
                        <div class="mb-3">
                            <label>Production Cycle (optional)</label>
                            <select name="cycle_id" id="editExpenseCycle" class="form-select">
                                <option value="0">Shared between Layer cycles</option>
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

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Layer Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small"><strong>Non-stock costs only:</strong> physical items purchased into store should be received through Inventory. Their purchase activity appears automatically on this page.</div>
                        <div class="mb-3">
                            <label>Date</label>
                            <input type="date" name="expense_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Production Type</label>
                            <select class="form-select" disabled aria-label="Production Type">
                                <option selected>Layer</option>
                            </select>
                            <input type="hidden" name="production_type" value="layer">
                            <small class="text-muted">This expense page is scoped to Layer.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label>Production Cycle (optional)</label>
                            <select name="cycle_id" class="form-select">
                                <option value="0">Shared between Layer cycles</option>
                                <?php foreach($expenseCycles as $cycle): ?><option value="<?php echo (int)$cycle['id']; ?>"><?php echo htmlspecialchars($cycle['cycle_code'].' — '.$cycle['status']); ?></option><?php endforeach; ?>
                            </select>
                            <small class="text-muted">Choose a cycle only when this expense belongs directly to it.</small>
                        </div>
                        <div class="mb-3">
                            <label>Category</label>
                            <select name="category" class="form-select" required>
                                <option value="salary">Salary</option>
                                <option value="logistic">Logistic</option>
                                <option value="fuel">Fuel</option>
                                <option value="misc">Miscellaneous</option>
                            </select>
                            <small class="text-muted">Stock purchases such as feed, medication and vaccines are recorded through Inventory. Use this form for non-stock operating costs and services.</small>
                        </div>

                        <div class="mb-3">
                            <label>Unit</label>
                            <input type="number" name="unit" class="form-control"
                                   step="0.01" min="0.01" value="1" required>
                        </div>

                        <div class="mb-3">
                            <label>Amount (₦)</label>
                            <input type="number" name="amount" class="form-control" 
                                   step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Describe the expense (e.g., Layer mash purchase, Vaccine for layers, etc.)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_expense" class="btn btn-primary">Save Expense</button>
                    </div>
                </form>
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

    <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/edit-modal.js"></script> -->
    <script>
    // Month selector
    document.getElementById('monthSelector').addEventListener('change', function() {
        window.location.href = 'layer_expenses.php?month=' + this.value.substring(0, 7);
    });

    <?php if ($canManageExpenses): ?>
    attachEditModal({
        buttonSelector: '.edit-expense-btn',
        modalSelector: '#editExpenseModal',
        fieldMap: {
            id: '#editExpenseId',
            date: '#editExpenseDate',
            category: '#editCategory',
            amount: '#editAmount',
            unit: '#editUnit',
            description: '#editDescription',
            cycle: '#editExpenseCycle',
            poultry: '#editPoultryCategory'
        }
    });

    document.getElementById('editExpenseForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('csrf_token', '<?php echo csrf_token(); ?>');

        try {
            const response = await fetch('../api/update_expense.php', {
                method: 'POST',
                body: formData
            });
            const result = await parseJsonResponse(response);

            if (result.success) {
                location.reload();
            } else {
                AppNotify.error((result.error || result.message || 'Unable to update expense'));
            }
        } catch (error) {
            AppNotify.error('Network error: ' + error.message);
        }
    });
    <?php endif; ?>

    async function parseJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(text || 'Unexpected non-JSON response');
        }

        return response.json();
    }

    async function deleteExpense(expenseId) {
        const confirmed = await AppConfirm.ask('Are you sure you want to delete this expense record?', {title:'Delete expense record?', confirmText:'Delete'});
        if (!confirmed) return;

        try {
            const params = new URLSearchParams({ id: expenseId, csrf_token: '<?php echo csrf_token(); ?>' });
            const response = await fetch('../api/delete_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
            const data = await parseJsonResponse(response);

            if (data.success) {
                location.reload();
            } else {
                AppNotify.error(data.error || data.message || 'Unable to delete expense');
            }
        } catch (error) {
            AppNotify.error('Network error: ' + error.message);
        }
    }

    // Show messages
    </script>
</body>
</html>
<?php
if ($pdfRequested) {
    pdf_report_finish('layer-expenses-' . $yearMonth . '.pdf', 'landscape', 'Layer Expenses Record - ' . date('F Y', strtotime($yearMonth)));
}
?>
