/**
 * Sign-in page behavior.
 * Kept external for CSP compatibility.
 */
(function () {
    'use strict';

    function syncLoginType() {
        const checked = document.querySelector('[data-login-type]:checked');
        const isPlatform = checked && checked.value === 'platform';
        const workspace = document.getElementById('farmWorkspaceField');
        const input = document.getElementById('farm_slug');

        if (!workspace || !input) {
            return;
        }

        workspace.style.display = isPlatform ? 'none' : '';
        input.required = !isPlatform;
    }

    document.querySelectorAll('[data-login-type]').forEach(function (field) {
        field.addEventListener('change', syncLoginType);
    });

    syncLoginType();
})();
