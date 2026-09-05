<?php
/**
 * Keep delegated Production Cycles access genuinely read-only.
 *
 * The legacy Production Cycles page already blocks every POST for non-admins.
 * Poultry Cycle Workspace historically allowed a delegated viewer to submit a
 * Production-Entry Economic Basis approval, so enforce the same admin-only
 * mutation boundary here and remove management controls from delegated views.
 */

if (!function_exists('production_cycle_view_permissions_path')) {
function production_cycle_view_permissions_path(): string
{
    return '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
}
}

if (!function_exists('production_cycle_view_permissions_privileged')) {
function production_cycle_view_permissions_privileged(): bool
{
    return isPlatformOwner() || hasRole('farm_admin');
}
}

if (!function_exists('production_cycle_view_permissions_filter')) {
function production_cycle_view_permissions_filter(string $html): string
{
    // This helper only buffers the two Production Cycle routes for a delegated
    // non-admin viewer, so hiding POST forms here is safely route-scoped.
    $style = '<style id="production-cycle-readonly-prepaint">form[method="post"],form[method="POST"]{display:none!important}</style>';
    $script = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('form[method="post"],form[method="POST"]').forEach(function(form){
    const action=form.querySelector('input[name="action"]')?.value||'';
    if(action==='create_cycle'||action==='close_cycle'){
      const col=form.closest('.col-lg-6'); if(col) col.remove();
    } else if(action==='update_bird_cost_basis'){
      const card=form.closest('.card'); if(card) card.remove();
    } else if(action==='record_poultry_acquisition'){
      const col=form.closest('.col-lg-5'); if(col) col.remove();
    }
  });
  document.querySelectorAll('a.btn').forEach(function(a){
    const text=a.textContent.trim();
    if(text==='Manage Cycle') a.textContent='View Cycle';
    else if(text==='Manage acquisition records on Production Cycles') a.textContent='View acquisition records on Production Cycles';
    else if(text==='Manage lifecycle on Production Cycles') a.textContent='View lifecycle on Production Cycles';
  });
});
</script>
HTML;
    if (stripos($html, '</head>') !== false) $html = preg_replace('/<\/head>/i', $style . '</head>', $html, 1) ?? $html;
    if (stripos($html, '</body>') !== false) $html = preg_replace('/<\/body>/i', $script . '</body>', $html, 1) ?? $html;
    return $html;
}
}

if (!isset($_SESSION['user_id'])) return;
$path = production_cycle_view_permissions_path();
$isProductionCycles = str_ends_with($path, '/management/production_cycles.php');
$isPoultryWorkspace = str_ends_with($path, '/management/poultry_cycle.php');
if (!$isProductionCycles && !$isPoultryWorkspace) return;
if (production_cycle_view_permissions_privileged()) return;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    http_response_code(403);
    exit('Production-cycle management access required.');
}

ob_start('production_cycle_view_permissions_filter');
