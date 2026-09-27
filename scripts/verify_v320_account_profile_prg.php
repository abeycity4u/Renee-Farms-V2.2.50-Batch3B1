<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$account = (string)file_get_contents(
    $root . '/billing/account.php'
);

$notifications = (string)file_get_contents(
    $root . '/includes/notifications.php'
);

$navbar = (string)file_get_contents(
    $root . '/navbar.php'
);

$notificationCss = (string)file_get_contents(
    $root . '/assets/css/notifications.css'
);

$failures = [];

$checks = [
    'shared notification helper loaded'
        => str_contains(
            $account,
            "/includes/notifications.php"
        ),

    'failed profile values stored in session'
        => str_contains(
            $account,
            "\$_SESSION['farm_profile_form_value']"
        ),

    'failed profile values consumed once after redirect'
        => str_contains(
            $account,
            "unset(\n        \$_SESSION['farm_profile_form_value']"
        ),

    'Farm Profile errors use shared PRG helper'
        => str_contains(
            $account,
            'redirectWithNotification('
        )
        && str_contains(
            $account,
            "'/billing/account.php'"
        ),

    'shared redirect uses HTTP 303'
        => str_contains(
            $notifications,
            "true,\n            303"
        ),

    'global notification container exists'
        => str_contains(
            $navbar,
            'id="appNotifications"'
        ),

    'session notifications render globally'
        => str_contains(
            $navbar,
            'renderSessionNotifications'
        ),

    'global notifications are fixed'
        => str_contains(
            $notificationCss,
            'position: fixed;'
        ),

    'global notifications are horizontally centered'
        => str_contains(
            $notificationCss,
            'left: 50%;'
        )
        && str_contains(
            $notificationCss,
            'transform: translateX(-50%);'
        ),

    'global notification desktop top offset exists'
        => str_contains(
            $notificationCss,
            'top: 72px;'
        ),

    'profile form still preserves rejected values'
        => str_contains(
            $account,
            '$profileDisplay'
        )
        && str_contains(
            $account,
            '$profileFormValue'
        ),

    'page header uses persisted profile identity'
        => str_contains(
            $account,
            "\$profile['name']"
        ),

    'old profile-specific raw Bootstrap alert is retired'
        => !str_contains(
            $account,
            '<div class="alert alert-danger"><?= htmlspecialchars($profileFormError'
        ),

    'old local Farm Profile notification renderer is retired'
        => !str_contains(
            $account,
            "'Farm Profile could not be updated.'"
        ),

    'shared profile update authority preserved'
        => str_contains(
            $account,
            'farm_profile_update_identity('
        ),

    'workspace assertion preserved'
        => str_contains(
            $account,
            'farm_profile_assert_workspace_id_available('
        ),

    'transaction remains'
        => str_contains(
            $account,
            '$pdo->beginTransaction();'
        )
        && str_contains(
            $account,
            '$pdo->commit();'
        )
        && str_contains(
            $account,
            '$pdo->rollBack();'
        ),
];

foreach ($checks as $label => $pass) {
    if ($pass) {
        echo "PASS: $label\n";
    } else {
        $failures[] = $label;
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(
            STDERR,
            "FAIL: $failure\n"
        );
    }

    exit(1);
}

echo "PASS: V3.2 Farm Profile POST-Redirect-GET contract\n";
