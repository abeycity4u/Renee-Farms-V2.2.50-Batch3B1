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
        fullName: 'input[name="full_name"]',
        email: 'input[name="email"]'
    },
    onShow: ({ modalElement, data }) => {
        const passwordField =
            modalElement.querySelector(
                'input[name="password"]'
            );

        const passwordRow =
            modalElement.querySelector(
                '[data-active-password-row]'
            );

        const emailField =
            modalElement.querySelector(
                'input[name="email"]'
            );

        const pendingNote =
            modalElement.querySelector(
                '[data-pending-activation-note]'
            );

        const credentialState =
            data.credentialState || 'active';

        const isPending =
            credentialState ===
            'pending_activation';

        if (passwordField) {
            passwordField.value = '';
            passwordField.disabled =
                isPending;
        }

        if (passwordRow) {
            passwordRow.classList.toggle(
                'd-none',
                isPending
            );
        }

        if (emailField) {
            emailField.required =
                isPending;
        }

        if (pendingNote) {
            pendingNote.classList.toggle(
                'd-none',
                !isPending
            );
        }

        const roles =
            (data.roles || '')
                .split(',')
                .filter(Boolean);

        modalElement
            .querySelectorAll(
                'input[name="roles[]"]'
            )
            .forEach((field) => {
                field.checked =
                    roles.includes(
                        field.value
                    );
            });
    }
});

function confirmUserDeletion(
    form,
    username
) {
    AppConfirm.ask(
        'This removes '
            + username
            + "'s login access immediately.",
        {
            title: 'Delete user?',
            confirmText: 'Delete user',
            danger: true
        }
    ).then(function(confirmed) {
        if (!confirmed) {
            return;
        }

        const action =
            document.createElement(
                'input'
            );

        action.type = 'hidden';
        action.name = 'delete_user';
        action.value = '1';

        form.appendChild(action);
        form.submit();
    });
}

document
    .querySelectorAll(
        '[data-resend-activation]'
    )
    .forEach((button) => {
        button.addEventListener(
            'click',
            () => {
                const form =
                    button.closest(
                        '[data-resend-activation-form]'
                    );

                if (!form) {
                    return;
                }

                const username =
                    form.dataset.username
                    || 'this user';

                AppConfirm.ask(
                    'Queue new activation instructions for '
                        + username
                        + '?',
                    {
                        title: 'Resend activation?',
                        confirmText: 'Resend activation'
                    }
                ).then(
                    function(confirmed) {
                        if (!confirmed) {
                            return;
                        }

                        const action =
                            document.createElement(
                                'input'
                            );

                        action.type = 'hidden';
                        action.name =
                            'resend_activation';
                        action.value = '1';

                        form.appendChild(
                            action
                        );

                        form.submit();
                    }
                );
            }
        );
    });
