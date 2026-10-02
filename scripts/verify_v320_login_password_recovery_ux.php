<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$sign =
    file_get_contents(
        $root . '/sign.php'
    );

$css =
    file_get_contents(
        $root . '/assets/css/sign-page.css'
    );

$forgot =
    file_get_contents(
        $root . '/account/forgot_password.php'
    );

$reset =
    file_get_contents(
        $root . '/account/reset_password.php'
    );

$failures = 0;

$check =
    static function (
        bool $condition,
        string $label
    ) use (&$failures): void {
        if ($condition) {
            echo "PASS: {$label}\n";
            return;
        }

        echo "FAIL: {$label}\n";
        $failures++;
    };

$check(
    str_contains(
        $sign,
        "/account/forgot_password.php"
    ),
    'login exposes public forgot-password route'
);

$check(
    str_contains(
        $sign,
        'Forgot password?'
    ),
    'login shows clear recovery action'
);

$check(
    str_contains(
        $sign,
        'class="auth-recovery-link"'
    ),
    'recovery action has dedicated presentation hook'
);

$check(
    str_contains(
        $sign,
        'autocomplete="current-password"'
    ),
    'login password retains browser password-manager support'
);

$check(
    str_contains(
        $sign,
        'Need a new account? Contact your administrator.'
    ),
    'account-setup guidance is distinct from password recovery'
);

$check(
    !str_contains(
        $sign,
        'Need access? Contact your administrator for account setup.'
    ),
    'ambiguous old helper is retired'
);

$check(
    str_contains(
        $css,
        '.auth-recovery-row'
    ),
    'shared sign stylesheet contains recovery row'
);

$check(
    str_contains(
        $css,
        '.auth-recovery-link'
    ),
    'shared sign stylesheet contains recovery link styling'
);

$check(
    str_contains(
        $css,
        '.auth-recovery-link:focus-visible'
    ),
    'recovery link has keyboard focus treatment'
);

$check(
    str_contains(
        $forgot,
        'account_credential_request_farm_password_reset('
    ),
    'existing farm reset request flow remains present'
);

$check(
    str_contains(
        $forgot,
        'account_credential_request_platform_password_reset('
    ),
    'existing platform reset request flow remains present'
);

$check(
    str_contains(
        $reset,
        'account_credential_consume_password_reset('
    ),
    'existing token consumption flow remains present'
);

$check(
    str_contains(
        $reset,
        'Continue to sign in'
    ),
    'successful reset still returns user to sign in'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "LOGIN_PASSWORD_RECOVERY_UX=PASS\n";
    exit(0);
}

echo "LOGIN_PASSWORD_RECOVERY_UX=FAIL\n";
exit(1);
