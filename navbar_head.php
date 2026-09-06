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
<script>
window.ReneeCalendar = window.ReneeCalendar || {
    today: <?php echo json_encode(app_today()); ?>,
    timezone: <?php echo json_encode(app_timezone_name()); ?>,
    currentMonth: <?php echo json_encode(app_current_month()); ?>
};
</script>
<script>(function(){var t='light';try{t=localStorage.getItem('farm-theme')||'light';}catch(e){}if(t!=='dark'&&t!=='light')t='light';document.documentElement.setAttribute('data-theme',t);document.documentElement.setAttribute('data-bs-theme',t);})();</script>
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
<style>
.app-notifications {
    position: fixed;
    top: 72px;
    left: 50%;
    transform: translateX(-50%);
    width: min(620px, calc(100% - 24px));
    max-height: calc(100dvh - 88px);
    z-index: 20000;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
    overflow: hidden;
    contain: layout paint;
    overscroll-behavior: contain;
}
.app-notification {
    --notify-accent: #dc3545;
    --notify-bg: #fff1f2;
    --notify-border: #ffb4bb;
    --notify-title: #b4232f;
    display: grid;
    grid-template-columns: 40px minmax(0, 1fr) 28px;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--notify-border);
    border-left: 4px solid var(--notify-accent);
    border-radius: 12px;
    background: linear-gradient(105deg, var(--notify-bg), #fff 78%);
    box-shadow: 0 8px 22px rgba(15, 23, 42, .12);
    color: #172033;
    pointer-events: auto;
    animation: appNotifyIn .16s ease-out;
    will-change: opacity, transform;
}
.app-notification-success { --notify-accent:#198754; --notify-bg:#effaf3; --notify-border:#a9dfbd; --notify-title:#137443; }
.app-notification-warning { --notify-accent:#f59e0b; --notify-bg:#fff8e8; --notify-border:#ffd58a; --notify-title:#a86600; }
.app-notification-info { --notify-accent:#0d6efd; --notify-bg:#eef6ff; --notify-border:#9fc5ff; --notify-title:#0759bb; }
.app-notification-error { --notify-accent:#dc3545; --notify-bg:#fff1f2; --notify-border:#ffb4bb; --notify-title:#b4232f; }
.app-notification-icon {
    width: 36px; height: 36px; border-radius: 50%; display:flex; align-items:center; justify-content:center;
    background: color-mix(in srgb, var(--notify-accent) 13%, white);
    color: var(--notify-accent); font-size: 17px;
}
.app-notification-title { color: var(--notify-title); font-weight: 800; font-size: .92rem; line-height:1.2; margin-bottom: 2px; }
.app-notification-message { font-size: .82rem; line-height: 1.32; color:#263247; overflow-wrap:anywhere; }
.app-notification-tip { display:none; }
.app-notification-close { border:0; background:transparent; color:var(--notify-title); font-size:.9rem; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; padding:0; }
.app-notification-close:hover { background: rgba(0,0,0,.06); }
.app-notification.is-closing { animation: appNotifyOut .14s ease-in forwards; }
@keyframes appNotifyIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:none; } }
@keyframes appNotifyOut { to { opacity:0; transform:translateY(-3px); } }
@media (max-width: 760px) {
    .app-notifications { width:calc(100% - 12px); top:8px; max-height:calc(100dvh - 16px); gap:6px; }
    .app-notification { grid-template-columns:34px minmax(0,1fr) 26px; gap:8px; padding:9px 10px; border-radius:10px; }
    .app-notification-icon { width:32px; height:32px; font-size:15px; }
    .app-notification-title { font-size:.88rem; }
    .app-notification-message { font-size:.8rem; }
    .app-notification-close { width:26px; height:26px; }
}
@media (prefers-reduced-motion: reduce) {
    .app-notification { animation:none; }
}
</style>
<script>
window.AppNotify = window.AppNotify || (function () {
    const meta = {
        success: { cls:'app-notification-success', icon:'bi-check-circle-fill', title:'Success', tip:'Your changes have been saved successfully.' },
        warning: { cls:'app-notification-warning', icon:'bi-exclamation-triangle-fill', title:'Warning', tip:'Please review this before continuing.' },
        info: { cls:'app-notification-info', icon:'bi-info-circle-fill', title:'Information', tip:'Review the information above before continuing.' },
        danger: { cls:'app-notification-error', icon:'bi-exclamation-triangle-fill', title:'Action could not be completed', tip:'Please check the information entered and try again.' },
        error: { cls:'app-notification-error', icon:'bi-exclamation-triangle-fill', title:'Action could not be completed', tip:'Please check the information entered and try again.' }
    };
    const maxStack = 3;
    const close = (el) => { if (!el) return; el.classList.add('is-closing'); setTimeout(() => el.remove(), 150); };
    const enforceStackLimit = (wrap) => {
        if (!wrap) return;
        const items = Array.from(wrap.querySelectorAll('.app-notification'));
        while (items.length > maxStack) {
            const oldest = items.shift();
            if (oldest) oldest.remove();
        }
    };
    const add = (type, message, title, tip, duration = null) => {
        const m = meta[type] || meta.info;
        const wrap = document.getElementById('appNotifications');
        if (!wrap) return;
        const el = document.createElement('div');
        el.className = `app-notification ${m.cls}${type !== 'danger' && type !== 'error' ? ' app-notification-auto' : ''}`;
        el.setAttribute('role','alert');
        el.innerHTML = `<div class="app-notification-icon" aria-hidden="true"><i class="bi ${m.icon}"></i></div>
            <div class="app-notification-content"><div class="app-notification-title"></div><div class="app-notification-message"></div></div>
            <div class="app-notification-tip"><div class="app-notification-tip-label"></div><div class="app-notification-tip-text"></div></div>
            <button type="button" class="app-notification-close" data-notification-close aria-label="Dismiss notification"><i class="bi bi-x-lg"></i></button>`;
        if (!title && message) {
            const parts = message.match(/^(.+?[.!?])(?:\s+|$)(.*)$/s);
            if (parts && parts[1].length <= 96) {
                title = parts[1];
                message = parts[2] || (type === 'success' ? 'The requested action was completed successfully.' : (type === 'error' || type === 'danger' ? 'Please check the information entered and try again.' : 'Please review the information above.'));
            }
        }
        el.querySelector('.app-notification-title').textContent = title || m.title;
        el.querySelector('.app-notification-message').textContent = message || '';
        el.querySelector('.app-notification-tip-label').textContent = type === 'success' ? 'Done' : (type === 'warning' || type === 'info' ? 'Action' : 'Tip');
        el.querySelector('.app-notification-tip-text').textContent = tip || m.tip;
        wrap.appendChild(el);
        enforceStackLimit(wrap);
        const defaultTimeout = type === 'success' ? 2500 : (type === 'info' ? 4500 : (type === 'warning' ? 5000 : 0));
        const timeout = Number.isFinite(Number(duration)) && Number(duration) >= 0 ? Number(duration) : defaultTimeout;
        if (timeout > 0) setTimeout(() => close(el), timeout);
        return el;
    };
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-notification-close]');
        if (btn) close(btn.closest('.app-notification'));
    });
    document.addEventListener('DOMContentLoaded', () => {
        const wrap = document.getElementById('appNotifications');
        enforceStackLimit(wrap);
        document.querySelectorAll('.app-notification-auto').forEach(el => {
            const timeout = el.classList.contains('app-notification-success') ? 2500 : (el.classList.contains('app-notification-info') ? 4500 : 5000);
            setTimeout(() => close(el), timeout);
        });
    });
    return {
        show:add,
        success:(m,t,tip,d)=>add('success',m,t,tip,d),
        error:(m,t,tip,d)=>add('error',m,t,tip,d),
        warning:(m,t,tip,d)=>add('warning',m,t,tip,d),
        info:(m,t,tip,d)=>add('info',m,t,tip,d)
    };
})();
</script>

<!-- Platform-wide confirmation system -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/confirmations.css'); ?>">
<script defer src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/confirmations.js'); ?>"></script>

<!-- Lightweight JS debug helper (shows runtime errors when ?debug=1 or localStorage app-debug=1) -->
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/debug.js'); ?>" defer></script>

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">

