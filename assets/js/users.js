/**
 * User management page behavior.
 * Externalized for CSP compatibility.
 */
attachEditModal({
        buttonSelector: '.edit-user-btn',
        modalSelector: '#editUserModal',
        fieldMap: {
            userId: 'input[name="user_id"]',
            username: 'input[name="username"]',
            fullName: 'input[name="full_name"]'
        },
        onShow: ({ modalElement, data }) => {
            const passwordField = modalElement.querySelector('input[name="password"]');
            if (passwordField) passwordField.value = '';
            const roles = (data.roles || '').split(',');
            modalElement.querySelectorAll('input[name="roles[]"]').forEach((field) => { field.checked = roles.includes(field.value); });
        }
    });

function confirmUserDeletion(form, username) {
    AppConfirm.ask('This removes ' + username + "'s login access immediately.", {title:'Delete user?',confirmText:'Delete user',danger:true}).then(function(confirmed){
        if(!confirmed) return; const action=document.createElement('input'); action.type='hidden'; action.name='delete_user'; action.value='1'; form.appendChild(action); form.submit();
    });
}
