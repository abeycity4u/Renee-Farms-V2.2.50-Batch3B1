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

$contracts = [
    'Account explicitly loads shared notification helper'
        => str_contains(
            $account,
            "require_once dirname(__DIR__) . '/includes/notifications.php';"
        ),

    'Farm Profile errors use shared notification redirect'
        => str_contains(
            $account,
            'redirectWithNotification('
        ),

    'Farm Profile shared redirect returns to Account'
        => str_contains(
            $account,
            "'/billing/account.php'"
        ),

    'Farm Profile raw Bootstrap error alert retired'
        => !str_contains(
            $account,
            '<div class="alert alert-danger"><?= htmlspecialchars($profileFormError'
        ),

    'Shared notification redirect signature remains authoritative'
        => str_contains(
            $notifications,
            'function redirectWithNotification('
        ),

    'Shared notification renderer remains authoritative'
        => str_contains(
            $notifications,
            'function renderNotification(string $type, string $message, ?string $title = null, ?string $tip = null): void'
        ),

    'Session notifications render in navbar notification container'
        => str_contains(
            $navbar,
            'id="appNotifications"'
        )
        && str_contains(
            $navbar,
            'renderSessionNotifications'
        ),

    'Shared notification container remains fixed top-center'
        => str_contains(
            $notificationCss,
            'position: fixed;'
        )
        && str_contains(
            $notificationCss,
            'top: 72px;'
        )
        && str_contains(
            $notificationCss,
            'left: 50%;'
        )
        && str_contains(
            $notificationCss,
            'transform: translateX(-50%);'
        ),

    'Farm Profile form value preservation remains'
        => str_contains(
            $account,
            "\$_SESSION['farm_profile_form_value']"
        ),

    'Farm Profile shared service remains loaded'
        => str_contains(
            $account,
            "require_once dirname(__DIR__) . '/includes/farm_profile.php';"
        ),
];

foreach ($contracts as $label => $pass) {
    if ($pass) {
        echo 'PASS: ' . $label . PHP_EOL;
    } else {
        $failures[] = $label;
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(
            STDERR,
            'FAIL: '
            . $failure
            . PHP_EOL
        );
    }

    exit(1);
}

echo "PASS: V3.2 Account Farm Profile shared notification contract\n";
