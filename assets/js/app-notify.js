/**
 * Platform-wide notification runtime.
 * Preserves the public window.AppNotify contract.
 * Externalized for CSP compatibility.
 */
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
