<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$farmsPath =
    $root . '/management/farms.php';

$jsPath =
    $root . '/assets/js/farms.js';

$farms =
    is_file($farmsPath)
        ? (string)file_get_contents($farmsPath)
        : '';

$js =
    is_file($jsPath)
        ? (string)file_get_contents($jsPath)
        : '';

$checks = [];

$check = static function (
    string $label,
    bool $ok
) use (&$checks): void {
    $checks[] = $ok;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;
};

$check(
    'Farm Admin uses shared pending-email writer',
    str_contains(
        $farms,
        'account_pending_user_update_email('
    )
);

$check(
    'Farm Admin uses shared activation resend writer',
    str_contains(
        $farms,
        'account_pending_user_resend_activation('
    )
);

$check(
    'Farm Admin has explicit resend POST action',
    str_contains(
        $farms,
        <<<'NEEDLE'
isset($_POST['resend_activation']
NEEDLE
    )
);

$check(
    'Farm Admin edit references credential state',
    str_contains(
        $farms,
        'ownerCredentialState'
    )
    && str_contains(
        $farms,
        "'pending_activation'"
    )
);

$check(
    'Pending Farm Admin direct password is rejected',
    str_contains(
        $farms,
        'Pending Farm Admin accounts choose their password from the activation link.'
    )
);

$check(
    'Pending Farm Admin email result checks changed state',
    str_contains(
        $farms,
        'pendingEmailResult'
    )
    && str_contains(
        $farms,
        "'email_changed'"
    )
);

$check(
    'Farm page does not directly enqueue credential outbox',
    !str_contains(
        $farms,
        'account_credential_outbox_enqueue('
    )
);

$check(
    'Pending Farm Admin password field is active-state only',
    str_contains(
        $farms,
        <<<'NEEDLE'
(($owner['credential_state'] ?? 'active') === 'active')
NEEDLE
    )
);

$check(
    'Pending Farm Admin credential email is required in UI',
    str_contains(
        $farms,
        <<<'NEEDLE'
(($owner['credential_state'] ?? '') === 'pending_activation')
NEEDLE
    )
);

$check(
    'Pending Farm Admin UI exposes explicit resend',
    str_contains(
        $farms,
        'data-farm-admin-resend="1"'
    )
);

$check(
    'Farm Admin resend JS uses AppConfirm',
    str_contains(
        $js,
        'AppConfirm.ask('
    )
    && str_contains(
        $js,
        'Resend Farm Admin activation?'
    )
);

$check(
    'Farm Admin resend JS submits explicit action',
    str_contains(
        $js,
        "action.name = 'resend_activation';"
    )
    && str_contains(
        $js,
        "action.value = '1';"
    )
);

$check(
    'Create and repair path still use shared pending-account creation',
    str_contains(
        $farms,
        'account_pending_user_create('
    )
);

$check(
    'Active Farm Admin optional password update remains available',
    str_contains(
        $farms,
        'allowExistingPasswordUpdate'
    )
    && str_contains(
        $farms,
        'password_security_hash('
    )
);

$check(
    'Pending edit is transaction-bound',
    str_contains(
        $farms,
        '$pdo->beginTransaction();'
    )
    && str_contains(
        $farms,
        '$pdo->commit();'
    )
);

$check(
    'Pending resend is transaction-bound',
    str_contains(
        $farms,
        'Farm Admin activation resend failed for farm'
    )
    && str_contains(
        $farms,
        'account_pending_user_resend_activation('
    )
);

$check(
    'Pending email helper remains shared-only',
    !str_contains(
        $farms,
        "UPDATE account_credential_delivery_outbox"
    )
);

$pass =
    !in_array(
        false,
        $checks,
        true
    );

echo $pass
    ? "FARM_ADMIN_PENDING_LIFECYCLE_CONTRACT=PASS\n"
    : "FARM_ADMIN_PENDING_LIFECYCLE_CONTRACT=FAIL\n";

exit($pass ? 0 : 1);
