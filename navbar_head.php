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

// Legacy livestock expense pages still use one broad action-column flag.
// Resolve that flag from the exact Edit/Delete permissions before rendering.
// Add remains independent and is handled by permission_runtime.php.
$operationalExpensePagePermissions = [
    '/poultry/layer_expenses.php' => [
        'poultry_layer_expenses_edit',
        'poultry_layer_expenses_delete',
    ],
    '/poultry/broiler_expenses.php' => [
        'poultry_broiler_expenses_edit',
        'poultry_broiler_expenses_delete',
    ],
    '/ruminant/ruminant_expenses.php' => [
        'ruminant_expenses_edit',
        'ruminant_expenses_delete',
    ],
];

foreach ($operationalExpensePagePermissions as $expenseSuffix => $expensePermissions) {
    if (($headPath === $expenseSuffix || str_ends_with($headPath, $expenseSuffix))
        && isset($canManageExpenses)) {
        $expensePrivileged = isPlatformOwner() || hasRole('farm_admin');
        $canManageExpenses = $expensePrivileged
            || hasPermission(getUserType(), $expensePermissions[0])
            || hasPermission(getUserType(), $expensePermissions[1]);
        break;
    }
}

// Customer Debt Management still renders its ledger actions from one legacy
// admin-only flag. Keep View/Edit/Delete independent without reconstructing the
// large Sales Records page: View controls whether the debt section renders,
// while Edit/Delete independently control their existing action buttons.
if (($headPath === '/management/sales_records.php' || str_ends_with($headPath, '/management/sales_records.php')) && isset($debtFeatureEnabled, $canManageLedger)) {
    $receivablePrivileged = isPlatformOwner() || hasRole('farm_admin');
    $canViewReceivables = $receivablePrivileged || hasPermission(getUserType(), 'sales_receivables');
    $canEditReceivables = $receivablePrivileged || hasPermission(getUserType(), 'sales_receivables_edit');
    $canDeleteReceivables = $receivablePrivileged || hasPermission(getUserType(), 'sales_receivables_delete');

    if (!$canViewReceivables) {
        $debtFeatureEnabled = false;
    }

    // The ledger template already renders Edit/Delete independently.
    // Align those canonical booleans directly instead of hiding controls with CSS.
    $canEditLedger = $canViewReceivables && $canEditReceivables;
    $canDeleteLedger = $canViewReceivables && $canDeleteReceivables;
    $canManageLedger = $canEditLedger || $canDeleteLedger;
}

// The consolidated Expense Report has its own View/Edit/Delete permission
// family. Those permissions are deliberately independent from the operational
// Layer/Broiler/Ruminant expense pages.
$managementExpenseActionPermissions = [];
if (($headPath === '/management/expenses.php' || str_ends_with($headPath, '/management/expenses.php')) && isset($expenses, $canManageExpenses) && is_array($expenses)) {
    $expensePrivileged = isPlatformOwner() || hasRole('farm_admin');
    $canViewExpenseReport = $expensePrivileged || hasPermission(getUserType(), 'expenses');
    $canEditExpenseReport = $canViewExpenseReport
        && ($expensePrivileged || hasPermission(getUserType(), 'expenses_edit'));
    $canDeleteExpenseReport = $canViewExpenseReport
        && ($expensePrivileged || hasPermission(getUserType(), 'expenses_delete'));
    $canManageAnyExpenseAction = $canEditExpenseReport || $canDeleteExpenseReport;

    foreach ($expenses as $expenseRow) {
        $expenseId = (int)($expenseRow['id'] ?? 0);
        if ($expenseId <= 0) continue;

        $canEditExpenseRow = $canEditExpenseReport;
        $canDeleteExpenseRow = $canDeleteExpenseReport;

        $managementExpenseActionPermissions[$expenseId] = [
            'edit' => $canEditExpenseRow,
            'delete' => $canDeleteExpenseRow,
        ];
    }

    // View alone stays read-only. Edit/Delete independently expose only their
    // corresponding report actions.
    $canManageExpenses = $canManageAnyExpenseAction;
}
?>
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
<!-- Bootstrap CSS (local fallback for offline environments) -->
<link href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/css/bootstrap.min.css'); ?>" rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap-icons-1.11.3/font/bootstrap-icons.css'); ?>">

<!-- Custom CSS -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/style.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/dashboard.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/theme.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/responsive.css'); ?>">
<script defer src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/navigation.js'); ?>"></script>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/tenant_theme.css.php">



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

