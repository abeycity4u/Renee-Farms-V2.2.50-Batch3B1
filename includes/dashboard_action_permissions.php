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

ob_start(static function (string $html) use (
    $canViewInventory,
    $canViewSales,
    $canViewExpenses,
    $canViewProfitability,
    $canViewIntelligence,
    $canUpdateStock,
    $dedicatedSalesRep
): string {
    if (!$canViewExpenses) {
        $html = dashboard_action_remove_div_containing($html, '<span>Total Operating Cost</span>', 'col-');
    }

    if (!$canViewProfitability) {
        $html = dashboard_action_remove_div_containing($html, '<span>Net Profit (This Month)</span>', 'col-');
    }

    if (!$canViewIntelligence) {
        $html = preg_replace(
            '~\s*<!-- Management Intelligence -->.*?(?=\s*<!-- Main Content Area -->)~s',
            "\n        <!-- Management Intelligence hidden by permission -->\n        ",
            $html,
            1
        ) ?? $html;
    }

    if (!$canViewSales) {
        $html = dashboard_action_remove_card_containing($html, 'Recent Sales');
    }

    if (!$canViewInventory) {
        // Inventory summary figures belong to Inventory View.
        $html = dashboard_action_remove_div_containing($html, '<span>Items in Stock</span>', 'col-');
        $html = dashboard_action_remove_div_containing($html, '<span>Today\'s Activities</span>', 'col-');
        $html = dashboard_action_remove_div_containing($html, '<span>Inventory Coverage</span>', 'col-');

        // Inventory monitoring surfaces on the dashboard.
        $html = dashboard_action_remove_card_containing($html, 'Smart Stock Control');
        $html = dashboard_action_remove_card_containing($html, 'Today\'s Transactions');
        $html = dashboard_action_remove_card_containing($html, 'Critical Inventory Snapshot');
        $html = dashboard_action_remove_card_containing($html, 'Low Stock Alerts');

        // Non-Sales specialist dashboards have native Quick Actions; do not leave an
        // Inventory shortcut visible when Inventory View itself is denied.
        $html = preg_replace(
            '~\s*<a href="inventory\.php" class="smart-action-card[^>]*>.*?</a>~s',
            '',
            $html
        ) ?? $html;

        // A dedicated Sales Representative has no native Quick Actions. Once the
        // stock card is removed, the left 8-column region is intentionally empty.
        // Remove that empty layout column and let the native Recent Sales panel use
        // the available width instead of inventing a second Sales workspace.
        if ($dedicatedSalesRep) {
            $html = dashboard_action_remove_div_containing($html, '<!-- Current Stock Levels -->', 'col-xl-8');
            $html = preg_replace(
                '~(<!-- Right Column: Recent Activity & Alerts -->\s*)<div class="col-xl-4">~',
                '$1<div class="col-12">',
                $html,
                1
            ) ?? $html;
        }
    }

    // Stock mutation controls are meaningful only when Inventory itself is visible.
    if ($canViewInventory && !$canUpdateStock) {
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
    } elseif ($canViewInventory && $canUpdateStock) {
        // Keep the Dashboard deliberately lighter than Inventory: it records only
        // operational consumption. Receiving stock and economic basis belong to
        // the full Inventory workflow.
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

        // The legacy Dashboard submits JSON without the X-CSRF-Token required by
        // api/update_stock.php and reads only data.message even though API failures
        // use data.error. Intercept only this Dashboard form and leave the API as the
        // authoritative permission/tenant/validation boundary.
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
