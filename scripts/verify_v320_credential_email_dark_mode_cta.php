<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$delivery =
    file_get_contents(
        $root . '/includes/account_credential_delivery.php'
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
        'bgcolor="#d1fae5"'
    ),
    'CTA uses light Renee green fallback'
);

$check(
    str_contains(
        $delivery,
        'border:1px solid #10b981'
    ),
    'CTA has visible green boundary'
);

$check(
    str_contains(
        $delivery,
        'color:#064e3b !important;-webkit-text-fill-color:#064e3b'
    ),
    'CTA uses dark evergreen text'
);

$check(
    substr_count(
        $delivery,
        '-webkit-text-fill-color:#064e3b'
    ) >= 2,
    'CTA anchor and text share resilient dark color'
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
        'return 86400;'
    ),
    'activation TTL remains 24 hours'
);

$check(
    str_contains(
        $delivery,
        'return 3600;'
    ),
    'password reset TTL remains 1 hour'
);

$check(
    str_contains(
        $delivery,
        'open the secure account page'
    ),
    'secure fallback link preserved'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "CREDENTIAL_EMAIL_DARK_MODE_CTA=PASS\n";
    exit(0);
}

echo "CREDENTIAL_EMAIL_DARK_MODE_CTA=FAIL\n";
exit(1);
