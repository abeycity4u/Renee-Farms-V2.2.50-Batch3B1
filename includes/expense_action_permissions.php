<?php
/**
 * Granular visibility and scope bridge for legacy expense pages.
 *
 * The Layer, Broiler and Ruminant expense pages still render Edit/Delete from a
 * broad legacy management flag. The expense APIs already enforce the exact
 * row-scoped permission. This bridge prevents restricted controls from being
 * painted while those large pages are migrated gradually.
 *
 * A dedicated Sales Representative may optionally receive the general Expense &
 * Cost Report permissions, but that must never expose livestock operating costs.
 * Force that role's report/PDF scope to General before the legacy pages resolve
 * their filters.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) return;

$path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET') return;

$privileged = isPlatformOwner() || hasRole('farm_admin');
$dedicatedSalesRep = hasRole('sales_rep')
    && !$privileged
    && !hasRole('poultry_manager')
    && !hasRole('ruminant_manager');

$isGeneralExpenseReport = $path === '/management/expenses.php'
    || str_ends_with($path, '/management/expenses.php')
    || $path === '/management/expense_report_pdf.php'
    || str_ends_with($path, '/management/expense_report_pdf.php');

if ($dedicatedSalesRep && $isGeneralExpenseReport) {
    // Sales Representatives can be granted General/commercial expense reporting
    // without gaining visibility into Layer, Broiler or Ruminant operating costs.
    $_GET['farm_type'] = 'general';
}

$permissionPair = null;
if ($path === '/poultry/layer_expenses.php' || str_ends_with($path, '/poultry/layer_expenses.php')) {
    $permissionPair = ['poultry_layer_expenses_edit', 'poultry_layer_expenses_delete'];
} elseif ($path === '/poultry/broiler_expenses.php' || str_ends_with($path, '/poultry/broiler_expenses.php')) {
    $permissionPair = ['poultry_broiler_expenses_edit', 'poultry_broiler_expenses_delete'];
} elseif ($path === '/ruminant/ruminant_expenses.php' || str_ends_with($path, '/ruminant/ruminant_expenses.php')) {
    $permissionPair = ['ruminant_expenses_edit', 'ruminant_expenses_delete'];
}

if ($permissionPair === null) return;

$canEdit = $privileged || hasPermission(getUserType(), $permissionPair[0]);
$canDelete = $privileged || hasPermission(getUserType(), $permissionPair[1]);

$rules = [];
if (!$canEdit) {
    // Poultry page theme rules use more-specific !important button display rules.
    // Scope through body/table so this permission rule wins without touching page CSS.
    $rules[] = 'html body table .edit-expense-btn{display:none!important;}';
}
if (!$canDelete) {
    $rules[] = 'html body table button[onclick^="deleteExpense("],html body table button[onclick*="deleteExpense("]{display:none!important;}';
}
if (!$rules) return;

$css = '<style id="expense-action-permission-prepaint">' . implode('', $rules) . '</style>';
ob_start(static function (string $html) use ($css): string {
    if (stripos($html, '</head>') === false) return $html;
    return str_ireplace('</head>', $css . '</head>', $html);
});
