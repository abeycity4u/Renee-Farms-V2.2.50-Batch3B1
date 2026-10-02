<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$page =
    file_get_contents(
        $root . '/account/forgot_password.php'
    );

$css =
    file_get_contents(
        $root . '/assets/css/sign-page.css'
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
        $page,
        "\$_SESSION['credential_request_complete']"
    ),
    'recovery flow has one-request completion state'
);

$check(
    str_contains(
        $page,
        '$_SESSION[\'credential_request_complete\'] ='
    ),
    'successful POST records completion state'
);

$check(
    str_contains(
        $page,
        'unset('
    )
    && str_contains(
        $page,
        "\$_SESSION['credential_request_complete']"
    ),
    'completion state is consumed after redirect'
);

$check(
    str_contains(
        $page,
        '<?php if ($requestComplete): ?>'
    ),
    'confirmation state replaces normal form'
);

$check(
    str_contains(
        $page,
        'Check your email'
    ),
    'confirmation has clear heading'
);

$check(
    str_contains(
        $page,
        'If the account details are valid, a password reset'
    ),
    'confirmation preserves account-safe wording'
);

$check(
    str_contains(
        $page,
        '<strong>1 hour</strong>'
    ),
    'confirmation explains reset expiry'
);

$check(
    str_contains(
        $page,
        'check your spam or junk'
    ),
    'confirmation includes delivery troubleshooting'
);

$check(
    str_contains(
        $page,
        'Back to Sign In'
    ),
    'confirmation provides sign-in return'
);

$check(
    str_contains(
        $page,
        'Return to Home Page'
    ),
    'confirmation provides home-page return'
);

$check(
    str_contains(
        $page,
        "BASE_URL . '/'"
    ),
    'home action uses application base URL'
);

$check(
    str_contains(
        $page,
        'Send Password Reset Link'
    ),
    'request button wording is explicit'
);

$check(
    str_contains(
        $page,
        '<?php else: ?>'
    )
    && str_contains(
        $page,
        '<form'
    ),
    'form remains available outside successful completion state'
);

$check(
    str_contains(
        $page,
        'Too many requests. Please try again shortly.'
    ),
    'rate-limit error behavior remains present'
);

$check(
    str_contains(
        $page,
        'Your request could not be verified. Please try again.'
    ),
    'CSRF error behavior remains present'
);

$check(
    str_contains(
        $css,
        '.recovery-confirmation'
    ),
    'shared auth stylesheet contains confirmation presentation'
);

$check(
    str_contains(
        $css,
        '.recovery-confirmation-actions'
    ),
    'confirmation actions have dedicated responsive layout'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "PASSWORD_RECOVERY_CONFIRMATION_UX=PASS\n";
    exit(0);
}

echo "PASSWORD_RECOVERY_CONFIRMATION_UX=FAIL\n";
exit(1);
