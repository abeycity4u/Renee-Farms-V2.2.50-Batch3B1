<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$account = (string)file_get_contents(
    $root . '/billing/account.php'
);

$notifications = (string)file_get_contents(
    $root . '/includes/notifications.php'
);

$failures = [];

$contracts = [
    'Account explicitly loads shared notification helper'
        => str_contains(
            $account,
            "require_once dirname(__DIR__) . '/includes/notifications.php';"
        ),

    'Farm Profile error uses shared notification renderer'
        => str_contains(
            $account,
            "renderNotification("
        )
        && str_contains(
            $account,
            "'Farm Profile could not be updated.'"
        ),

    'Farm Profile raw Bootstrap error alert retired'
        => !str_contains(
            $account,
            '<div class="alert alert-danger"><?= htmlspecialchars($profileFormError'
        ),

    'Farm Profile error variable remains intact'
        => str_contains(
            $account,
            '$profileFormError ='
        ),

    'Shared renderer signature remains authoritative'
        => str_contains(
            $notifications,
            'function renderNotification(string $type, string $message, ?string $title = null, ?string $tip = null): void'
        ),

    'Shared renderer still owns error notification class'
        => str_contains(
            $notifications,
            'app-notification-error'
        ),

    'Farm Profile form value preservation remains'
        => str_contains(
            $account,
            '$profileFormValue'
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
