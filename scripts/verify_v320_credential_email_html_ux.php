<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$mailer =
    file_get_contents(
        $root
        . '/includes/platform_mailer.php'
    );

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
        $mailer,
        'function platform_mail_mime_payload('
    ),
    'central MIME payload builder exists'
);

$check(
    str_contains(
        $mailer,
        'Content-Type: multipart/alternative; boundary="'
    ),
    'HTML mail uses multipart alternative'
);

$check(
    str_contains(
        $mailer,
        "(string)(\$options['html_body'] ?? '')"
    ),
    'SMTP/PHP transport accepts HTML body option'
);

$check(
    substr_count(
        $mailer,
        "(string)(\$options['html_body'] ?? '')"
    ) === 2,
    'both mail transports consume HTML body centrally'
);

$check(
    str_contains(
        $delivery,
        'function account_credential_delivery_html_message('
    ),
    'credential HTML builder exists'
);

$check(
    str_contains(
        $delivery,
        'Activate Your Account'
    ),
    'activation CTA is user friendly'
);

$check(
    str_contains(
        $delivery,
        'Reset Your Password'
    ),
    'password reset CTA is user friendly'
);

$check(
    str_contains(
        $delivery,
        'open the secure account page'
    ),
    'HTML fallback avoids exposing long raw URL'
);

$check(
    str_contains(
        $delivery,
        "'html_body' =>"
    ),
    'credential builder returns HTML body'
);

$check(
    str_contains(
        $delivery,
        "(string)(\$message['html_body'] ?? '')"
    ),
    'credential send passes HTML body to transport'
);

$check(
    str_contains(
        $delivery,
        ". \$link . \"\\n\\n\""
    ),
    'plain-text fallback retains raw credential link'
);

$check(
    str_contains(
        $delivery,
        'account_credential_activation_ttl_seconds'
    )
    && str_contains(
        $delivery,
        'return 86400;'
    ),
    '24-hour activation TTL remains unchanged'
);

$check(
    str_contains(
        $delivery,
        'account_credential_password_reset_ttl_seconds'
    )
    && str_contains(
        $delivery,
        'return 3600;'
    ),
    '1-hour reset TTL remains unchanged'
);

$check(
    !str_contains(
        $mailer,
        'error_log($body'
    )
    && !str_contains(
        $mailer,
        'error_log($htmlBody'
    ),
    'mail bodies are not logged'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "CREDENTIAL_EMAIL_HTML_UX=PASS\n";
    exit(0);
}

echo "CREDENTIAL_EMAIL_HTML_UX=FAIL\n";
exit(1);
