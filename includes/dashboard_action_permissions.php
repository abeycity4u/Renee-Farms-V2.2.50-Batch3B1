<?php
/**
 * Align Dashboard intelligence and stock quick-actions with delegated permissions.
 *
 * The underlying destination routes/APIs remain the authorization boundary.
 * This bridge prevents the legacy Dashboard from exposing Farm Intelligence
 * content or Update Stock controls when those permissions are not granted, and
 * keeps the Dashboard quick-stock request aligned with the CSRF-protected API.
 * Dashboard stock control is intentionally a quick operational deduction flow;
 * full receiving/cost/attribution work remains on Inventory.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) return;

$path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($path === '/dashboard.php' || str_ends_with($path, '/dashboard.php'))) return;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return;

$privileged = isPlatformOwner() || hasRole('farm_admin');
$canViewIntelligence = $privileged || hasPermission(getUserType(), 'farm_intelligence');
$canUpdateStock = $privileged || hasPermission(getUserType(), 'update_stock');

ob_start(static function (string $html) use ($canViewIntelligence, $canUpdateStock): string {
    if (!$canViewIntelligence) {
        $html = preg_replace(
            '~\s*<!-- Management Intelligence -->.*?(?=\s*<!-- Main Content Area -->)~s',
            "\n        <!-- Management Intelligence hidden by permission -->\n        ",
            $html,
            1
        ) ?? $html;
    }

    if (!$canUpdateStock) {
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
    } else {
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