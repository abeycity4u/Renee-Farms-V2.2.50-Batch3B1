<?php
/**
 * Align Dashboard intelligence and stock quick-actions with delegated permissions.
 *
 * The underlying destination routes/APIs remain the authorization boundary.
 * This bridge prevents the legacy Dashboard from exposing Farm Intelligence
 * content or Update Stock controls when those permissions are not granted.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) return;

$path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($path === '/dashboard.php' || str_ends_with($path, '/dashboard.php'))) return;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') return;

$privileged = isPlatformOwner() || hasRole('farm_admin');
if ($privileged) return;

$canViewIntelligence = hasPermission(getUserType(), 'farm_intelligence');
$canUpdateStock = hasPermission(getUserType(), 'update_stock');

if ($canViewIntelligence && $canUpdateStock) return;

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
    }

    return $html;
});
