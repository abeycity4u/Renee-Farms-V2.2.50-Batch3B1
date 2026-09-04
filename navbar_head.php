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
?>
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
    width: min(1120px, calc(100% - 32px));
    z-index: 20000;
    display: flex;
    flex-direction: column;
    gap: 12px;
    pointer-events: none;
}
.app-notification {
    --notify-accent: #dc3545;
    --notify-bg: #fff1f2;
    --notify-border: #ffb4bb;
    --notify-title: #b4232f;
    display: grid;
    grid-template-columns: 64px minmax(0, 1fr) minmax(230px, 0.42fr) 36px;
    align-items: center;
    gap: 18px;
    padding: 14px 18px;
    border: 1px solid var(--notify-border);
    border-left: 6px solid var(--notify-accent);
    border-radius: 16px;
    background: linear-gradient(105deg, var(--notify-bg), #fff 72%);
    box-shadow: 0 12px 34px rgba(15, 23, 42, .14);
    color: #172033;
    pointer-events: auto;
    animation: appNotifyIn .22s ease-out;
}
.app-notification-success { --notify-accent:#198754; --notify-bg:#effaf3; --notify-border:#a9dfbd; --notify-title:#137443; }
.app-notification-warning { --notify-accent:#f59e0b; --notify-bg:#fff8e8; --notify-border:#ffd58a; --notify-title:#a86600; }
.app-notification-info { --notify-accent:#0d6efd; --notify-bg:#eef6ff; --notify-border:#9fc5ff; --notify-title:#0759bb; }
.app-notification-error { --notify-accent:#dc3545; --notify-bg:#fff1f2; --notify-border:#ffb4bb; --notify-title:#b4232f; }
.app-notification-icon {
    width: 56px; height: 56px; border-radius: 50%; display:flex; align-items:center; justify-content:center;
    background: color-mix(in srgb, var(--notify-accent) 13%, white);
    color: var(--notify-accent); font-size: 25px;
}
.app-notification-title { color: var(--notify-title); font-weight: 800; font-size: 1rem; margin-bottom: 3px; }
.app-notification-message { font-size: .96rem; line-height: 1.45; color:#263247; overflow-wrap:anywhere; }
.app-notification-tip { border-left: 1px solid rgba(0,0,0,.10); padding-left: 18px; font-size: .84rem; line-height:1.35; color:#4b5565; }
.app-notification-tip-label { color: var(--notify-title); font-weight:800; font-size:.9rem; margin-bottom:3px; }
.app-notification-close { border:0; background:transparent; color:var(--notify-title); font-size:1.05rem; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
.app-notification-close:hover { background: rgba(0,0,0,.06); }
.app-notification.is-closing { animation: appNotifyOut .18s ease-in forwards; }
@keyframes appNotifyIn { from { opacity:0; transform:translateY(-10px) scale(.985); } to { opacity:1; transform:none; } }
@keyframes appNotifyOut { to { opacity:0; transform:translateY(-8px) scale(.985); } }
@media (max-width: 760px) {
    .app-notifications { width:calc(100% - 20px); top:10px; }
    .app-notification { grid-template-columns:44px minmax(0,1fr) 34px; gap:11px; padding:12px; }
    .app-notification-icon { width:42px; height:42px; font-size:19px; }
    .app-notification-tip { display:none; }
    .app-notification-title { font-size:.92rem; }
    .app-notification-message { font-size:.86rem; }
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
    const close = (el) => { if (!el) return; el.classList.add('is-closing'); setTimeout(() => el.remove(), 190); };
    const add = (type, message, title, tip) => {
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
        const timeout = type === 'success' ? 2500 : (type === 'info' ? 4500 : (type === 'warning' ? 5000 : 0));
        if (timeout > 0) setTimeout(() => close(el), timeout);
        return el;
    };
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-notification-close]');
        if (btn) close(btn.closest('.app-notification'));
    });
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.app-notification-auto').forEach(el => {
            const timeout = el.classList.contains('app-notification-success') ? 2500 : (el.classList.contains('app-notification-info') ? 4500 : 5000);
            setTimeout(() => close(el), timeout);
        });
    });
    return { show:add, success:(m,t,tip)=>add('success',m,t,tip), error:(m,t,tip)=>add('error',m,t,tip), warning:(m,t,tip)=>add('warning',m,t,tip), info:(m,t,tip)=>add('info',m,t,tip) };
})();
</script>

<!-- Platform-wide confirmation system -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/confirmations.css'); ?>">
<script defer src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/confirmations.js'); ?>"></script>

<!-- Lightweight JS debug helper (shows runtime errors when ?debug=1 or localStorage app-debug=1) -->
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/debug.js'); ?>" defer></script>

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">

