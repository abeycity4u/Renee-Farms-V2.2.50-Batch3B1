<?php require_once(__DIR__ . '/init.php'); ?>
<?php
// navbar_head.php - Head assets only
// Temporary V2.3 permission bridge: the large Daily Record pages still define
// delete visibility with legacy admin-only flags before rendering. Align those
// flags here, after the page has initialized them but before the tables render.
$headPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (($headPath === '/poultry/layers_daily_record.php' || str_ends_with($headPath, '/poultry/layers_daily_record.php')) && isset($canDelete)) {
    $canDelete = isPlatformOwner() || hasRole('farm_admin') || hasPermission(getUserType(), 'poultry_daily_layer_delete');
}
if (($headPath === '/poultry/broiler_daily_record.php' || str_ends_with($headPath, '/poultry/broiler_daily_record.php')) && isset($canDelete)) {
    $canDelete = isPlatformOwner() || hasRole('farm_admin') || hasPermission(getUserType(), 'poultry_daily_broiler_delete');
}
if (($headPath === '/ruminant/ruminant_daily_record.php' || str_ends_with($headPath, '/ruminant/ruminant_daily_record.php')) && isset($canDeleteRecords)) {
    $canDeleteRecords = isPlatformOwner() || hasRole('farm_admin') || hasPermission(getUserType(), 'ruminant_daily_delete');
}

// Customer Debt Management still renders its ledger actions from one legacy
// admin-only flag. Keep View/Edit/Delete independent without reconstructing the
// large Sales Records page: View controls whether the debt section renders,
// while Edit/Delete independently control their existing action buttons.
$salesReceivableActionRules = [];
if (($headPath === '/management/sales_records.php' || str_ends_with($headPath, '/management/sales_records.php')) && isset($debtFeatureEnabled, $canManageLedger)) {
    $receivablePrivileged = isPlatformOwner() || hasRole('farm_admin');
    $canViewReceivables = $receivablePrivileged || hasPermission(getUserType(), 'sales_receivables');
    $canEditReceivables = $receivablePrivileged || hasPermission(getUserType(), 'sales_receivables_edit');
    $canDeleteReceivables = $receivablePrivileged || hasPermission(getUserType(), 'sales_receivables_delete');

    if (!$canViewReceivables) {
        $debtFeatureEnabled = false;
    }
    $canManageLedger = $canViewReceivables && ($canEditReceivables || $canDeleteReceivables);
    if ($canViewReceivables && !$canEditReceivables) {
        $salesReceivableActionRules[] = '.edit-ledger-btn{display:none!important;}';
    }
    if ($canViewReceivables && !$canDeleteReceivables) {
        $salesReceivableActionRules[] = 'button[name="delete_ledger_entry"]{display:none!important;}';
    }
}

// The consolidated Expense & Cost Report contains Layer, Broiler, Ruminant and
// general rows. Its legacy page uses one broad Actions flag, while the APIs
// already enforce the exact row permission. Mirror that canonical mapping here
// so only the Edit/Delete controls authorized for each rendered row are visible.
$managementExpenseActionRules = [];
if (($headPath === '/management/expenses.php' || str_ends_with($headPath, '/management/expenses.php')) && isset($expenses, $canManageExpenses) && is_array($expenses)) {
    require_once __DIR__ . '/includes/permission_catalog.php';
    $expensePrivileged = isPlatformOwner() || hasRole('farm_admin');
    $canManageAnyExpenseAction = false;

    foreach ($expenses as $expenseRow) {
        $expenseId = (int)($expenseRow['id'] ?? 0);
        if ($expenseId <= 0) continue;

        $editPermission = permission_catalog_expense_action_code($expenseRow, 'edit');
        $deletePermission = permission_catalog_expense_action_code($expenseRow, 'delete');
        $canEditExpenseRow = $expensePrivileged || ($editPermission && hasPermission(getUserType(), $editPermission));
        $canDeleteExpenseRow = $expensePrivileged || ($deletePermission && hasPermission(getUserType(), $deletePermission));
        $canManageAnyExpenseAction = $canManageAnyExpenseAction || $canEditExpenseRow || $canDeleteExpenseRow;

        $editSelector = 'body .edit-expense-btn[data-id="' . $expenseId . '"]';
        $deleteSelector = 'body button[onclick="deleteExpense(' . $expenseId . ')"]';
        $managementExpenseActionRules[] = $editSelector . '{display:' . ($canEditExpenseRow ? 'inline-flex' : 'none') . '!important;}';
        $managementExpenseActionRules[] = $deleteSelector . '{display:' . ($canDeleteExpenseRow ? 'inline-flex' : 'none') . '!important;}';
    }

    // If none of the visible rows carries an authorized action, let the legacy
    // template omit its Actions column and edit modal completely.
    $canManageExpenses = $canManageAnyExpenseAction;
}
?>
<?php if ($salesReceivableActionRules || $managementExpenseActionRules): ?>
<style><?php echo implode("\n", array_merge($salesReceivableActionRules, $managementExpenseActionRules)); ?></style>
<?php endif; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
<meta name="app-today" content="<?php echo htmlspecialchars(app_today(), ENT_QUOTES); ?>">
<meta name="app-timezone" content="<?php echo htmlspecialchars(app_timezone_name(), ENT_QUOTES); ?>">
<script
    src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/calendar-runtime.js'); ?>"
    data-today="<?php echo htmlspecialchars(
        app_today(),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
    data-timezone="<?php echo htmlspecialchars(
        app_timezone_name(),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
    data-current-month="<?php echo htmlspecialchars(
        app_current_month(),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ); ?>"
></script>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/theme-bootstrap.js'); ?>"></script>
<?php
$tenantPrimaryColor = currentFarm()['primary_color'] ?? '#198754';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $tenantPrimaryColor)) $tenantPrimaryColor = '#198754';
?>
<!-- Bootstrap CSS (local fallback for offline environments) -->
<link href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/css/bootstrap.min.css'); ?>" rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Custom CSS -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/style.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/dashboard.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/theme.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/responsive.css'); ?>">
<script defer src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/navigation.js'); ?>"></script>

<style>
:root { --farm-primary: <?php echo htmlspecialchars($tenantPrimaryColor, ENT_QUOTES, 'UTF-8'); ?>; --bs-primary: var(--farm-primary); --bs-link-color: var(--farm-primary); --bs-link-hover-color: var(--farm-primary); }
.btn-primary { --bs-btn-bg: var(--farm-primary); --bs-btn-border-color: var(--farm-primary); --bs-btn-hover-bg: var(--farm-primary); --bs-btn-hover-border-color: var(--farm-primary); --bs-btn-active-bg: var(--farm-primary); --bs-btn-active-border-color: var(--farm-primary); }
.text-primary { color: var(--farm-primary) !important; }
</style>



<!-- Platform-wide notification system -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/notifications.css'); ?>">
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/app-notify.js'); ?>"></script>

<!-- Shared CSP-friendly application behaviors -->
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/app-behaviors.js'); ?>"></script>

<!-- Platform-wide confirmation system -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/confirmations.css'); ?>">
<script defer src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/confirmations.js'); ?>"></script>

<!-- Lightweight JS debug helper (shows runtime errors when ?debug=1 or localStorage app-debug=1) -->
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/debug.js'); ?>" defer></script>

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">

