<?php

declare(strict_types=1);

/**
 * V3.2 sender-role verifier.
 *
 * Pure/static contract checks only.
 * Sends no email and mutates no database state.
 */

require_once
    dirname(__DIR__)
    . '/includes/platform_mailer.php';

$failures = 0;

function sender_role_check(
    bool $condition,
    string $label
): void {
    global $failures;

    if ($condition) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }

    $failures++;

    echo 'FAIL: ' . $label . PHP_EOL;
}

putenv(
    'PLATFORM_MAIL_FROM=default@example.test'
);

putenv(
    'PLATFORM_MAIL_FROM_BILLING=billing@example.test'
);

putenv(
    'PLATFORM_MAIL_FROM_ONBOARDING=onboarding@example.test'
);

putenv(
    'PLATFORM_MAIL_FROM_SECURITY=security@example.test'
);

putenv(
    'PLATFORM_MAIL_FROM_NOTIFICATIONS=notifications@example.test'
);

sender_role_check(
    platform_mail_from_address()
        === 'default@example.test',
    'default sender remains backward compatible'
);

sender_role_check(
    platform_mail_from_address('billing')
        === 'billing@example.test',
    'billing sender resolves centrally'
);

sender_role_check(
    platform_mail_from_address('onboarding')
        === 'onboarding@example.test',
    'onboarding sender resolves centrally'
);

sender_role_check(
    platform_mail_from_address('security')
        === 'security@example.test',
    'security sender resolves centrally'
);

sender_role_check(
    platform_mail_from_address('notifications')
        === 'notifications@example.test',
    'notifications sender resolves centrally'
);

$invalidRejected = false;

try {
    platform_mail_sender_role(
        'unsupported-role'
    );
} catch (InvalidArgumentException $e) {
    $invalidRejected =
        $e->getMessage()
        ===
        'Unsupported platform mail sender role.';
}

sender_role_check(
    $invalidRejected,
    'unsupported sender role is rejected'
);

$root =
    dirname(__DIR__);

$mailer =
    file_get_contents(
        $root
        . '/includes/platform_mailer.php'
    );

$onboarding =
    file_get_contents(
        $root
        . '/includes/farm_onboarding_mail.php'
    );

$credentials =
    file_get_contents(
        $root
        . '/includes/account_credential_delivery.php'
    );

$renewal =
    file_get_contents(
        $root
        . '/scripts/run_v230_subscription_renewal_reminders.php'
    );

$bridge =
    file_get_contents(
        $root
        . '/scripts/v310_private_cli_bridge.php'
    );

sender_role_check(
    is_string($mailer)
    && str_contains(
        $mailer,
        "'PLATFORM_MAIL_FROM_BILLING'"
    )
    && str_contains(
        $mailer,
        "'PLATFORM_MAIL_FROM_ONBOARDING'"
    )
    && str_contains(
        $mailer,
        "'PLATFORM_MAIL_FROM_SECURITY'"
    )
    && str_contains(
        $mailer,
        "'PLATFORM_MAIL_FROM_NOTIFICATIONS'"
    ),
    'central mailer owns all sender-role configuration keys'
);

sender_role_check(
    is_string($mailer)
    && str_contains(
        $mailer,
        "platform_mail_sender_role("
    )
    && str_contains(
        $mailer,
        "\$options['sender']"
    ),
    'shared mail send validates sender role centrally'
);

sender_role_check(
    is_string($onboarding)
    && str_contains(
        $onboarding,
        "'sender' => 'onboarding'"
    ),
    'farm onboarding uses onboarding sender role'
);

sender_role_check(
    is_string($credentials)
    && str_contains(
        $credentials,
        "\$purpose === 'password_reset'"
    )
    && str_contains(
        $credentials,
        "? 'security'"
    )
    && str_contains(
        $credentials,
        ": 'onboarding'"
    ),
    'credential delivery separates activation and security sender roles'
);

sender_role_check(
    is_string($renewal)
    && str_contains(
        $renewal,
        "'sender' => 'billing'"
    ),
    'renewal reminder uses billing sender role'
);

sender_role_check(
    is_string($bridge)
    && str_contains(
        $bridge,
        "'PLATFORM_MAIL_FROM_BILLING'"
    )
    && str_contains(
        $bridge,
        "'PLATFORM_MAIL_FROM_ONBOARDING'"
    )
    && str_contains(
        $bridge,
        "'PLATFORM_MAIL_FROM_SECURITY'"
    )
    && str_contains(
        $bridge,
        "'PLATFORM_MAIL_FROM_NOTIFICATIONS'"
    ),
    'private CLI bridge imports optional sender-role authorities'
);

if ($failures > 0) {
    echo 'FAILURES='
        . $failures
        . PHP_EOL;

    exit(1);
}

echo 'PASS: platform mail sender-role contract verified.'
    . PHP_EOL;

exit(0);
