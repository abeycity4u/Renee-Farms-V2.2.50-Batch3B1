<?php
/**
 * Align Dashboard summary surfaces and stock quick-actions with delegated permissions.
 *
 * Dashboard is a monitoring surface. Module pages remain the place for operational
 * actions, and their routes/APIs remain the authorization boundary. Keep the legacy
 * dashboard readable without exposing Inventory, Sales, expense/profit figures or
 * Farm Intelligence when the corresponding View permission is not granted.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) return;

$path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($path === '/dashboard.php' || str_ends_with($path, '/dashboard.php'))) return;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return;

if (!function_exists('dashboard_action_remove_div_containing')) {
function dashboard_action_remove_div_containing(string $html, string $needle, string $requiredClass): string
{
    $needlePos = strpos($html, $needle);
    if ($needlePos === false) return $html;

    $searchPos = $needlePos;
    $start = false;
    while ($searchPos > 0) {
        $candidate = strrpos(substr($html, 0, $searchPos), '<div');
        if ($candidate === false) break;
        $tagEnd = strpos($html, '>', $candidate);
        if ($tagEnd === false || $tagEnd >= $needlePos) break;
        $tag = substr($html, $candidate, $tagEnd - $candidate + 1);
        if (str_contains($tag, $requiredClass)) {
            $start = $candidate;
            break;
        }
        $searchPos = $candidate;
    }
    if ($start === false) return $html;

    if (!preg_match_all('~<div\b|</div>~i', substr($html, $start), $matches, PREG_OFFSET_CAPTURE)) return $html;
    $depth = 0;
    foreach ($matches[0] as [$token, $offset]) {
        if (stripos($token, '<div') === 0) {
            $depth++;
            continue;
        }
        $depth--;
        if ($depth === 0) {
            $end = $start + $offset + strlen($token);
            return substr($html, 0, $start) . substr($html, $end);
        }
    }

    return $html;
}
}

if (!function_exists('dashboard_action_remove_card_containing')) {
function dashboard_action_remove_card_containing(string $html, string $needle): string
{
    return dashboard_action_remove_div_containing($html, $needle, 'card dashboard-card');
}
}

$privileged = isPlatformOwner() || hasRole('farm_admin');
$canViewInventory = $privileged || hasPermission(getUserType(), 'inventory');
$canViewSales = $privileged || (
    user_can_access_entitled_module('sales')
    && hasPermission(getUserType(), 'sales')
);
$canViewExpenses = $privileged || hasPermission(getUserType(), 'expenses');
$canViewProfitability = $privileged || hasPermission(getUserType(), 'profitability');
$canViewIntelligence = $privileged || hasPermission(getUserType(), 'farm_intelligence');
$canUpdateStock = $canViewInventory && ($privileged || hasPermission(getUserType(), 'update_stock'));
$dedicatedSalesRep = hasRole('sales_rep')
    && !$privileged
    && !hasRole('poultry_manager')
    && !hasRole('ruminant_manager');
$canViewReceivables = $canViewSales
    && ($privileged || hasPermission(getUserType(), 'sales_receivables'));

$salesMonthTotal = 0.0;
$salesMonthCount = 0;
$receivablesOutstanding = 0.0;
$receivablesCustomerCount = 0;
$receivableCustomers = [];

if ($dedicatedSalesRep && $canViewSales) {
    try {
        $salesSummaryStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(total_amount), 0) AS total_sales, COUNT(*) AS transaction_count
             FROM sales_records
             WHERE farm_id = ? AND sale_date BETWEEN ? AND ?'
        );
        $salesSummaryStmt->execute([
            requireCurrentFarmId(),
            date('Y-m-01'),
            date('Y-m-t'),
        ]);
        $salesSummary = $salesSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $salesMonthTotal = (float)($salesSummary['total_sales'] ?? 0);
        $salesMonthCount = (int)($salesSummary['transaction_count'] ?? 0);
    } catch (Throwable $e) {
        // Keep Dashboard available if a summary query cannot run.
    }
}

if ($dedicatedSalesRep && $canViewReceivables) {
    try {
        $receivableSummaryStmt = $pdo->prepare(
            'SELECT customer_name, SUM(amount) AS balance
             FROM customer_ledger_entries
             WHERE farm_id = ?
             GROUP BY customer_name'
        );
        $receivableSummaryStmt->execute([requireCurrentFarmId()]);
        foreach ($receivableSummaryStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $balance = (float)($row['balance'] ?? 0);
            if ($balance <= 0) continue;
            $receivablesOutstanding += $balance;
            $receivablesCustomerCount++;
            $receivableCustomers[] = [
                'customer_name' => (string)($row['customer_name'] ?? 'Customer'),
                'balance' => $balance,
            ];
        }
        usort($receivableCustomers, static fn(array $a, array $b): int => $b['balance'] <=> $a['balance']);
        $receivableCustomers = array_slice($receivableCustomers, 0, 5);
    } catch (Throwable $e) {
        // Keep Dashboard available if the receivables feature is unavailable.
        $canViewReceivables = false;
        $receivablesOutstanding = 0.0;
        $receivablesCustomerCount = 0;
        $receivableCustomers = [];
    }
}

ob_start(static function (string $html) use (
    $canViewInventory,
    $canViewSales,
    $canViewExpenses,
    $canViewProfitability,
    $canViewIntelligence,
    $canUpdateStock,
    $dedicatedSalesRep,
    $canViewReceivables,
    $salesMonthTotal,
    $salesMonthCount,
    $receivablesOutstanding,
    $receivablesCustomerCount,
    $receivableCustomers
): string {
    // A dedicated Sales Representative dashboard stays Sales/Receivables-only.
    // Optional Expense/Profitability permissions affect their destination pages,
    // not the dashboard composition.
    $showExpenseDashboard = !$dedicatedSalesRep && $canViewExpenses;
    $showProfitabilityDashboard = !$dedicatedSalesRep && $canViewProfitability;
    $showIntelligenceDashboard = !$dedicatedSalesRep && $canViewIntelligence;
    $showInventoryDashboard = !$dedicatedSalesRep && $canViewInventory;

    if (!$showExpenseDashboard) {
        $html = dashboard_action_remove_div_containing($html, '<span>Total Operating Cost</span>', 'col-');
    }

    if (!$showProfitabilityDashboard) {
        $html = dashboard_action_remove_div_containing($html, '<span>Net Profit (This Month)</span>', 'col-');
    }

    if (!$showIntelligenceDashboard) {
        $html = preg_replace(
            '~\s*<!-- Management Intelligence -->.*?(?=\s*<!-- Main Content Area -->)~s',
            "\n        <!-- Management Intelligence hidden by permission -->\n        ",
            $html,
            1
        ) ?? $html;
    }

    if (!$canViewSales) {
        // Anchor inside the rendered card, not on the preceding <!-- Recent Sales --> comment.
        $html = dashboard_action_remove_card_containing($html, '<i class="bi bi-graph-up text-success"></i>');
    }

    if (!$showInventoryDashboard) {
        $html = dashboard_action_remove_div_containing($html, '<span>Items in Stock</span>', 'col-');
        $html = dashboard_action_remove_div_containing($html, '<span>Today\'s Activities</span>', 'col-');
        $html = dashboard_action_remove_div_containing($html, '<span>Inventory Coverage</span>', 'col-');

        $html = dashboard_action_remove_card_containing($html, 'Smart Stock Control');
        // Use an inner-card icon so the preceding <!-- Today\'s Transactions --> comment cannot become the anchor.
        $html = dashboard_action_remove_card_containing($html, '<i class="bi bi-clock-history text-info"></i>');
        $html = dashboard_action_remove_card_containing($html, '<i class="bi bi-speedometer2 text-danger"></i>');
        $html = dashboard_action_remove_card_containing($html, '<i class="bi bi-exclamation-triangle"></i>');

        $html = preg_replace(
            '~\s*<a href="inventory\.php" class="smart-action-card[^>]*>.*?</a>~s',
            '',
            $html
        ) ?? $html;

        if ($dedicatedSalesRep) {
            $html = dashboard_action_remove_div_containing($html, '<!-- Current Stock Levels -->', 'col-xl-8');
            $html = preg_replace(
                '~(<!-- Right Column: Recent Activity & Alerts -->\s*)<div class="col-xl-4">~',
                '$1<div class="col-12 sales-rep-monitoring-column">',
                $html,
                1
            ) ?? $html;
        }
    }

    if ($dedicatedSalesRep && $canViewSales) {
        $monthLabel = htmlspecialchars(date('F Y'), ENT_QUOTES, 'UTF-8');
        $salesTotal = htmlspecialchars(number_format($salesMonthTotal, 2), ENT_QUOTES, 'UTF-8');
        $salesCount = number_format($salesMonthCount);
        $summaryCards = <<<HTML
        <div class="row g-3 mb-4 sales-rep-summary-grid">
            <div class="col-md-6 col-xl-3">
                <div class="card dashboard-card sales-summary-card h-100">
                    <div class="card-body">
                        <span class="sales-summary-icon text-success"><i class="bi bi-cash-stack"></i></span>
                        <div class="sales-summary-label">Sales this month</div>
                        <div class="sales-summary-value">₦{$salesTotal}</div>
                        <div class="sales-summary-subtext">{$monthLabel}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card dashboard-card sales-summary-card h-100">
                    <div class="card-body">
                        <span class="sales-summary-icon text-info"><i class="bi bi-receipt"></i></span>
                        <div class="sales-summary-label">Sales transactions</div>
                        <div class="sales-summary-value">{$salesCount}</div>
                        <div class="sales-summary-subtext">Recorded this month</div>
                    </div>
                </div>
            </div>
HTML;

        if ($canViewReceivables) {
            $outstanding = htmlspecialchars(number_format($receivablesOutstanding, 2), ENT_QUOTES, 'UTF-8');
            $customerCount = number_format($receivablesCustomerCount);
            $summaryCards .= <<<HTML
            <div class="col-md-6 col-xl-3">
                <div class="card dashboard-card sales-summary-card h-100">
                    <div class="card-body">
                        <span class="sales-summary-icon text-warning"><i class="bi bi-wallet2"></i></span>
                        <div class="sales-summary-label">Outstanding receivables</div>
                        <div class="sales-summary-value">₦{$outstanding}</div>
                        <div class="sales-summary-subtext">Customer debt balance</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card dashboard-card sales-summary-card h-100">
                    <div class="card-body">
                        <span class="sales-summary-icon text-warning"><i class="bi bi-people"></i></span>
                        <div class="sales-summary-label">Customers owing</div>
                        <div class="sales-summary-value">{$customerCount}</div>
                        <div class="sales-summary-subtext">With outstanding balances</div>
                    </div>
                </div>
            </div>
HTML;
        }

        $summaryCards .= "        </div>\n";
        $html = preg_replace(
            '~(\s*<!-- Dashboard Statistics -->)~',
            "\n" . $summaryCards . '$1',
            $html,
            1
        ) ?? $html;

        $salesCss = <<<'HTML'
<style id="sales-rep-dashboard-polish">
body.dashboard-role-sales_rep .dashboard-hero .card-body{padding:1.15rem 1.25rem;}
body.dashboard-role-sales_rep .dashboard-container{max-width:1680px;margin-left:auto;margin-right:auto;}
body.dashboard-role-sales_rep .sales-rep-summary-grid{margin-top:-.35rem;}
body.dashboard-role-sales_rep .sales-summary-card{border:1px solid rgba(148,163,184,.16);box-shadow:0 10px 28px rgba(2,8,23,.12);}
body.dashboard-role-sales_rep .sales-summary-card .card-body{position:relative;padding:1rem 1.05rem;min-height:128px;}
body.dashboard-role-sales_rep .sales-summary-icon{position:absolute;right:1rem;top:1rem;width:38px;height:38px;border-radius:12px;background:rgba(148,163,184,.10);display:grid;place-items:center;font-size:1.05rem;}
body.dashboard-role-sales_rep .sales-summary-label{font-size:.76rem;text-transform:uppercase;letter-spacing:.045em;color:#94a3b8;margin-bottom:.45rem;padding-right:48px;}
body.dashboard-role-sales_rep .sales-summary-value{font-size:1.45rem;font-weight:750;line-height:1.15;}
body.dashboard-role-sales_rep .sales-summary-subtext{font-size:.78rem;color:#94a3b8;margin-top:.45rem;}
body.dashboard-role-sales_rep #salesReceivablesSummary .receivable-total{font-size:1.55rem;font-weight:750;}
body.dashboard-role-sales_rep #salesReceivablesSummary .receivable-row{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.7rem 0;border-top:1px solid rgba(148,163,184,.14);}
body.dashboard-role-sales_rep #salesReceivablesSummary .receivable-row:first-of-type{border-top:0;}
body.dashboard-role-sales_rep .sales-rep-monitoring-column>.dashboard-card{margin-bottom:1rem!important;}
@media(max-width:767.98px){body.dashboard-role-sales_rep .sales-summary-card .card-body{min-height:112px;}body.dashboard-role-sales_rep .sales-summary-value{font-size:1.25rem;}}
</style>
HTML;
        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $salesCss . '</head>', $html, 1) ?? $html;
        }
    }

    if ($dedicatedSalesRep && $canViewReceivables) {
        $outstanding = htmlspecialchars(number_format($receivablesOutstanding, 2), ENT_QUOTES, 'UTF-8');
        $customerCount = (int)$receivablesCustomerCount;
        $customerLabel = $customerCount === 1 ? 'customer owing' : 'customers owing';
        $rows = '';
        foreach ($receivableCustomers as $customer) {
            $name = htmlspecialchars($customer['customer_name'], ENT_QUOTES, 'UTF-8');
            $balance = htmlspecialchars(number_format((float)$customer['balance'], 2), ENT_QUOTES, 'UTF-8');
            $rows .= '<div class="receivable-row"><span class="fw-semibold">' . $name . '</span><span class="fw-bold text-warning">₦' . $balance . '</span></div>';
        }
        if ($rows === '') {
            $rows = '<div class="text-muted small py-2">No outstanding customer balances.</div>';
        }

        $receivableCard = <<<HTML
                <div class="card dashboard-card ops-card side-panel-card mb-4" id="salesReceivablesSummary">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="section-eyebrow">Receivables</span>
                            <h5 class="mb-0"><i class="bi bi-wallet2 text-warning"></i> Customer Balances</h5>
                        </div>
                        <span class="badge bg-warning-subtle text-warning">{$customerCount} {$customerLabel}</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-3">
                            <div>
                                <div class="text-muted small">Outstanding customer balance</div>
                                <div class="receivable-total">₦{$outstanding}</div>
                            </div>
                            <div class="text-muted small">Top outstanding customers</div>
                        </div>
                        {$rows}
                    </div>
                </div>

HTML;
        $html = preg_replace(
            '~(\s*<!-- Recent Sales -->)~',
            "\n" . $receivableCard . '$1',
            $html,
            1
        ) ?? $html;
    }

    // Stock mutation controls are meaningful only when Inventory itself is visible.
    if ($showInventoryDashboard && !$canUpdateStock) {
        $style = '<style id="dashboard-stock-update-prepaint">#stockTable button[onclick^="quickStockUpdate("]{display:none!important}</style>';
        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $style . '</head>', $html, 1) ?? $html;
        }

        $script = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded',function(){
  const table=document.getElementById('stockTable');
  if(!table)return;
  table.querySelectorAll('button[onclick^="quickStockUpdate("]').forEach(function(btn){btn.remove();});
  const headers=Array.from(table.querySelectorAll('thead th'));
  const actionIndex=headers.findIndex(function(th){return th.textContent.trim().toLowerCase()==='actions';});
  if(actionIndex<0)return;
  table.querySelectorAll('tr').forEach(function(row){
    const cells=row.children;
    if(cells[actionIndex])cells[actionIndex].remove();
  });
});
</script>
HTML;
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', $script . '</body>', $html, 1) ?? $html;
        }
    } elseif ($showInventoryDashboard && $canUpdateStock) {
        $html = str_replace(
            '<h5 class="modal-title">Quick Stock Update</h5>',
            '<h5 class="modal-title">Quick Stock Use</h5>',
            $html
        );
        $transactionMarkup = <<<'HTML'
<label>Transaction</label>
                                    <input type="text" class="form-control" value="Use / Deduct Stock" readonly>
                                    <input type="hidden" id="transType" value="used">
HTML;
        $html = preg_replace(
            '~<label>Transaction Type</label>\s*<select class="form-select" id="transType" required>.*?</select>~s',
            $transactionMarkup,
            $html,
            1
        ) ?? $html;
        $html = str_replace(
            '<small>This will update stock in real-time and record the transaction.</small>',
            '<small>This quick action deducts stock for operational use. Use Inventory for receiving stock or full stock management.</small>',
            $html
        );
        $html = str_replace(
            '<button type="submit" class="btn btn-primary">Update Stock</button>',
            '<button type="submit" class="btn btn-primary">Deduct Stock</button>',
            $html
        );

        $script = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded',function(){
  const form=document.getElementById('quickStockForm');
  if(!form)return;

  document.querySelectorAll('#stockTable button[onclick^="quickStockUpdate("]').forEach(function(btn){
    btn.title='Quick Stock Use';
    btn.innerHTML='<i class="bi bi-dash-circle me-1"></i> Use Stock';
  });

  form.addEventListener('submit',async function(event){
    event.preventDefault();
    event.stopImmediatePropagation();

    const submitBtn=form.querySelector('button[type="submit"]');
    if(!submitBtn)return;
    const originalText=submitBtn.innerHTML;
    submitBtn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Deducting...';
    submitBtn.disabled=true;

    try {
      const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
      const transactionDate=document.querySelector('meta[name="app-today"]')?.content||'';
      const payload={
        item_id:document.getElementById('stockItemId')?.value||'',
        type:'used',
        quantity:document.getElementById('quantity')?.value||'',
        remarks:document.getElementById('remarks')?.value||''
      };
      if(transactionDate)payload.transaction_date=transactionDate;

      const response=await fetch('api/update_stock.php',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
        body:JSON.stringify(payload)
      });
      const data=await response.json();
      if(response.ok&&data.success){
        showAlert('success',data.message||'Stock deducted successfully!');
        bootstrap.Modal.getInstance(document.getElementById('quickStockModal'))?.hide();
        setTimeout(function(){location.reload();},1000);
        return;
      }
      showAlert('danger','Error: '+(data.error||data.message||'Action could not be completed.'));
    } catch(error) {
      showAlert('danger','Network error: '+error.message);
    }

    submitBtn.innerHTML=originalText;
    submitBtn.disabled=false;
  },true);
});
</script>
HTML;
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', $script . '</body>', $html, 1) ?? $html;
        }
    }

    return $html;
});
