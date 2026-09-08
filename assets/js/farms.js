/**
 * Farms management page behavior.
 * Externalized for CSP compatibility.
 */
(function () {
    const form = document.getElementById('farmAccountForm');
    if (!form) return;

    const moduleCheckboxes = Array.from(form.querySelectorAll('[data-module-entitlement="1"]'));
    const moduleMessage = 'Select at least one subscribed module so this farm has an active service entitlement.';
    const logoInput = form.querySelector('input[name="logo"]');
    const allowedLogoTypes = ['image/jpeg', 'image/png', 'image/webp'];

    function validateModules() {
        const hasModule = moduleCheckboxes.some((checkbox) => checkbox.checked);
        moduleCheckboxes.forEach((checkbox) => checkbox.setCustomValidity(hasModule ? '' : moduleMessage));
        return hasModule;
    }

    function validateLogo() {
        if (!logoInput || !logoInput.files.length) return true;
        const file = logoInput.files[0];
        const validType = allowedLogoTypes.includes(file.type);
        const validName = /\.(jpe?g|png|webp)$/i.test(file.name || '');
        const validSize = file.size <= 2 * 1024 * 1024;
        const message = !validSize ? 'Logo upload must be smaller than 2 MB.' : 'Logo must be a JPG, PNG, or WebP image.';
        logoInput.setCustomValidity((validType || validName) && validSize ? '' : message);
        return logoInput.checkValidity();
    }

    moduleCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', validateModules));
    if (logoInput) logoInput.addEventListener('change', validateLogo);

    form.addEventListener('submit', function (event) {
        validateModules();
        validateLogo();
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
})();

function confirmFarmDeletion(form, farmName) {
    AppConfirm.ask('This permanently deletes the farm profile and ALL tenant records. This cannot be undone.', {
        title: 'Delete ' + farmName + '?', confirmText: 'Continue', danger: true
    }).then(function(firstConfirmed) {
        if (!firstConfirmed) return;
        AppConfirm.ask('Final confirmation: permanently delete ' + farmName + ' and all of its data?', {
            title: 'Permanent tenant deletion', confirmText: 'Permanently delete', danger: true
        }).then(function(finalConfirmed) {
            if (!finalConfirmed) return;
            const button = document.createElement('input');
            button.type='hidden'; button.name='delete_farm'; button.value='1'; form.appendChild(button);
            form.submit();
        });
    });
}
function confirmFarmSuspend(form) {
    AppConfirm.ask('This will suspend the farm and prevent its users from signing in until it is reactivated.', {
        title: 'Suspend Farm profile?', confirmText: 'Suspend', danger: true
    }).then(function(confirmed) {
        if (!confirmed) return;
        const button = document.createElement('input');
        button.type = 'hidden'; button.name = 'suspend_farm'; button.value = '1';
        form.appendChild(button);
        form.submit();
    });
}
