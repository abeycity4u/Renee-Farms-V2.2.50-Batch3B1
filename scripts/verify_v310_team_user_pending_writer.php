<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$users =
    (string)file_get_contents(
        $root
        . '/management/users.php'
    );

$guard =
    (string)file_get_contents(
        $root
        . '/includes/user_management_tenant_guard.php'
    );

$js =
    (string)file_get_contents(
        $root
        . '/assets/js/users.js'
    );

$failures = 0;

function verify_team(
    bool $condition,
    string $message
): void {
    global $failures;

    if ($condition) {
        echo 'PASS: '
            . $message
            . PHP_EOL;
        return;
    }

    echo 'FAIL: '
        . $message
        . PHP_EOL;

    $failures++;
}

verify_team(
    str_contains(
        $users,
        '/includes/account_identity_policy.php'
    )
    && str_contains(
        $users,
        '/includes/account_credential_lifecycle.php'
    )
    && str_contains(
        $users,
        '/includes/account_pending_user.php'
    ),
    'Team User writer loads shared account authorities'
);

$addStart =
    strpos(
        $users,
        "if (isset(\$_POST['add_user']))"
    );

$deleteStart =
    strpos(
        $users,
        "if (isset(\$_POST['delete_user']))",
        $addStart === false
            ? 0
            : $addStart
    );

$add =
    (
        $addStart !== false
        && $deleteStart !== false
        && $addStart < $deleteStart
    )
        ? substr(
            $users,
            $addStart,
            $deleteStart - $addStart
        )
        : '';

verify_team(
    $add !== ''
    && str_contains(
        $add,
        'account_identity_normalize_username('
    )
    && str_contains(
        $add,
        'account_identity_normalize_full_name('
    )
    && str_contains(
        $add,
        'account_credential_normalize_email('
    ),
    'new Team User uses shared identity and credential-email policies'
);

verify_team(
    str_contains(
        $add,
        'account_pending_user_create('
    )
    && !preg_match(
        '/INSERT\s+INTO\s+users\b/i',
        $add
    )
    && !str_contains(
        $add,
        'password_security_hash('
    ),
    'new Team User uses pending writer without direct user insert or initial password'
);

verify_team(
    str_contains(
        $add,
        '$pdo->beginTransaction();'
    )
    && str_contains(
        $add,
        'INSERT INTO user_roles'
    )
    && str_contains(
        $add,
        '$pdo->commit();'
    )
    && str_contains(
        $add,
        '$pdo->rollBack();'
    ),
    'new user, roles, and activation intent are caller-transaction atomic'
);

$editStart =
    strpos(
        $users,
        "if (isset(\$_POST['edit_user']))"
    );

$getUsers =
    strpos(
        $users,
        '// Get all users',
        $editStart === false
            ? 0
            : $editStart
    );

$edit =
    (
        $editStart !== false
        && $getUsers !== false
        && $editStart < $getUsers
    )
        ? substr(
            $users,
            $editStart,
            $getUsers - $editStart
        )
        : '';

verify_team(
    str_contains(
        $edit,
        'FOR UPDATE'
    )
    && str_contains(
        $edit,
        'credential_state'
    )
    && str_contains(
        $edit,
        "'pending_activation'"
    )
    && str_contains(
        $edit,
        "'active'"
    ),
    'edit locks target and branches on credential state'
);

verify_team(
    str_contains(
        $edit,
        'account_pending_user_update_email('
    )
    && str_contains(
        $edit,
        'Pending users choose their password from the activation link.'
    )
    && !str_contains(
        $edit,
        'account_pending_user_resend_activation('
    ),
    'pending edit corrects email but never implicitly resends activation'
);

verify_team(
    str_contains(
        $edit,
        '$rawEmail === \'\''
    )
    && str_contains(
        $edit,
        '? null'
    )
    && str_contains(
        $edit,
        'account_credential_normalize_email('
    ),
    'active legacy account may remain without credential email'
);

verify_team(
    str_contains(
        $edit,
        'password_security_validate('
    )
    && str_contains(
        $edit,
        'password_security_hash('
    ),
    'active password replacement remains centrally validated and hashed'
);

verify_team(
    str_contains(
        $edit,
        '$pdo->beginTransaction();'
    )
    && str_contains(
        $edit,
        'DELETE ur'
    )
    && str_contains(
        $edit,
        'INSERT INTO user_roles'
    )
    && str_contains(
        $edit,
        '$pdo->commit();'
    )
    && str_contains(
        $edit,
        '$pdo->rollBack();'
    ),
    'edit identity, email, roles, and pending activation job are atomic'
);

$addModalStart =
    strpos(
        $users,
        '<!-- Add User Modal -->'
    );

$editModalStart =
    strpos(
        $users,
        '<!-- Edit User Modal -->',
        $addModalStart === false
            ? 0
            : $addModalStart
    );

$addModal =
    (
        $addModalStart !== false
        && $editModalStart !== false
        && $addModalStart < $editModalStart
    )
        ? substr(
            $users,
            $addModalStart,
            $editModalStart - $addModalStart
        )
        : '';

verify_team(
    str_contains(
        $addModal,
        'name="email"'
    )
    && str_contains(
        $addModal,
        'type="email"'
    )
    && str_contains(
        $addModal,
        'required'
    )
    && !str_contains(
        $addModal,
        'name="password"'
    ),
    'Add User requires credential email and contains no password field'
);

verify_team(
    substr_count(
        $users,
        'maxlength="<?php echo ACCOUNT_IDENTITY_USERNAME_MAX; ?>"'
    ) === 2
    && substr_count(
        $users,
        'maxlength="<?php echo ACCOUNT_IDENTITY_FULL_NAME_MAX; ?>"'
    ) === 2,
    'Add and Edit forms both reuse shared identity maximums'
);

verify_team(
    str_contains(
        $users,
        'data-email='
    )
    && str_contains(
        $users,
        'data-credential-state='
    ),
    'edit action carries credential email and state'
);

verify_team(
    !str_contains(
        $guard,
        "\$isAdd = isset(\$_POST['add_user']);"
    )
    && str_contains(
        $guard,
        "\$isEdit = isset(\$_POST['edit_user']);"
    )
    && str_contains(
        $guard,
        'password_security_validate($password)'
    ),
    'guard no longer requires Add User password and preserves edit password policy'
);

verify_team(
    str_contains(
        $guard,
        'user_management_tenant_guard'
    )
    && str_contains(
        $guard,
        'WHERE id = ? AND farm_id = ? LIMIT 1'
    ),
    'tenant edit guard remains intact'
);

verify_team(
    str_contains(
        $js,
        "email: 'input[name=\"email\"]'"
    )
    && str_contains(
        $js,
        'data.credentialState'
    )
    && str_contains(
        $js,
        "credentialState ===\n            'pending_activation'"
    )
    && str_contains(
        $js,
        'passwordField.disabled ='
    )
    && str_contains(
        $js,
        'emailField.required ='
    ),
    'edit JavaScript applies credential-state-specific email and password UI'
);

verify_team(
    str_contains(
        $users,
        "isset(\$_POST['resend_activation'])"
    )
    && str_contains(
        $users,
        'account_pending_user_resend_activation('
    ),
    'Team User exposes a separate explicit resend activation POST action'
);

$resendStart =
    strpos(
        $users,
        "if (isset(\$_POST['resend_activation']))"
    );

$editStartForResend =
    strpos(
        $users,
        "if (isset(\$_POST['edit_user']))",
        $resendStart === false
            ? 0
            : $resendStart
    );

$resendBody =
    (
        $resendStart !== false
        && $editStartForResend !== false
        && $resendStart < $editStartForResend
    )
        ? substr(
            $users,
            $resendStart,
            $editStartForResend - $resendStart
        )
        : '';

verify_team(
    $resendBody !== ''
    && str_contains(
        $resendBody,
        '$pdo->beginTransaction();'
    )
    && str_contains(
        $resendBody,
        'WHERE id = ?'
    )
    && str_contains(
        $resendBody,
        'AND farm_id = ?'
    )
    && str_contains(
        $resendBody,
        'FOR UPDATE'
    )
    && str_contains(
        $resendBody,
        "'pending_activation'"
    )
    && str_contains(
        $resendBody,
        "'farm_admin'"
    )
    && str_contains(
        $resendBody,
        'account_pending_user_resend_activation('
    )
    && str_contains(
        $resendBody,
        '$pdo->commit();'
    )
    && str_contains(
        $resendBody,
        '$pdo->rollBack();'
    ),
    'resend action is tenant-scoped pending-only protected and caller-transaction atomic'
);

verify_team(
    !str_contains(
        $resendBody,
        'UPDATE users'
    )
    && !str_contains(
        $resendBody,
        'DELETE ur'
    )
    && !str_contains(
        $resendBody,
        'INSERT INTO user_roles'
    )
    && !str_contains(
        $resendBody,
        'password_security_hash('
    ),
    'resend action mutates no identity roles email or password directly'
);

verify_team(
    str_contains(
        $users,
        "=== 'pending_activation'): ?>"
    )
    && str_contains(
        $users,
        'data-resend-activation-form'
    )
    && str_contains(
        $users,
        'data-resend-activation'
    )
    && str_contains(
        $users,
        'Resend activation'
    ),
    'resend UI is rendered only for pending activation rows'
);

verify_team(
    str_contains(
        $js,
        '[data-resend-activation]'
    )
    && str_contains(
        $js,
        'AppConfirm.ask('
    )
    && str_contains(
        $js,
        "action.name =\n                            'resend_activation';"
    ),
    'resend action requires explicit UI confirmation'
);

verify_team(
    !str_contains(
        $edit,
        'account_pending_user_resend_activation('
    ),
    'ordinary Edit User remains decoupled from activation resend'
);

if ($failures > 0) {
    echo "V310_TEAM_USER_PENDING_WRITER=FAIL\n";
    exit(1);
}

echo "V310_TEAM_USER_PENDING_WRITER=PASS\n";
