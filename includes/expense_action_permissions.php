<?php
/**
 * Granular visibility bridge for legacy operational expense pages.
 *
 * The Layer, Broiler and Ruminant expense pages still render Edit/Delete from a
 * broad legacy management flag. The expense APIs already enforce the exact
 * row-scoped permission. This bridge prevents restricted controls from being
 * painted while those large pages are migrated gradually.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) return;

$path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET') return;

$permissionPair = null;
if ($path === '/poultry/layer_expenses.php' || str_ends_with($path, '/poultry/layer_expenses.php')) {
    $permissionPair = ['poultry_layer_expenses_edit', 'poultry_layer_expenses_delete'];
} elseif ($path === '/poultry/broiler_expenses.php' || str_ends_with($path, '/poultry/broiler_expenses.php')) {
    $permissionPair = ['poultry_broiler_expenses_edit', 'poultry_broiler_expenses_delete'];
} elseif ($path === '/ruminant/ruminant_expenses.php' || str_ends_with($path, '/ruminant/ruminant_expenses.php')) {
    $permissionPair = ['ruminant_expenses_edit', 'ruminant_expenses_delete'];
}

if ($permissionPair === null) return;

$privileged = isPlatformOwner() || hasRole('farm_admin');
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
