<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$delivery =
    file_get_contents(
        $root
        . '/includes/account_credential_delivery.php'
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
        $delivery,
        'text-transform:uppercase;letter-spacing:.4px;">Farm</div>'
    ),
    'Farm field uses stacked mobile label'
);

$check(
    str_contains(
        $delivery,
        'text-transform:uppercase;letter-spacing:.4px;">Farm Workspace ID</div>'
    ),
    'Workspace field uses stacked mobile label'
);

$check(
    str_contains(
        $delivery,
        'text-transform:uppercase;letter-spacing:.4px;">Username</div>'
    ),
    'Username field uses stacked mobile label'
);

$check(
    !str_contains(
        $delivery,
        'width:42%;'
    ),
    'old fixed two-column detail width is retired'
);

$check(
    str_contains(
        $delivery,
        'bgcolor="#d1fae5"'
    ),
    'CTA includes light email-client background fallback'
);

$check(
    str_contains(
        $delivery,
        'color:#064e3b !important;-webkit-text-fill-color:#064e3b;'
    ),
    'CTA uses dark evergreen text for resilient contrast'
);

$check(
    substr_count(
        $delivery,
        '-webkit-text-fill-color:#064e3b'
    ) >= 2,
    'CTA anchor and inner text both protect evergreen color'
);

$check(
    str_contains(
        $delivery,
        'Activate Your Account'
    ),
    'activation CTA wording preserved'
);

$check(
    str_contains(
        $delivery,
        'Reset Your Password'
    ),
    'reset CTA wording preserved'
);

$check(
    str_contains(
        $delivery,
        'open the secure account page'
    ),
    'secure fallback link preserved'
);

$check(
    str_contains(
        $delivery,
        'return 86400;'
    ),
    'activation TTL unchanged'
);

$check(
    str_contains(
        $delivery,
        'return 3600;'
    ),
    'password-reset TTL unchanged'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "CREDENTIAL_EMAIL_MOBILE_POLISH=PASS\n";
    exit(0);
}

echo "CREDENTIAL_EMAIL_MOBILE_POLISH=FAIL\n";
exit(1);
