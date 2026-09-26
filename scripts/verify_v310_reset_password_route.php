<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$route =
    $root . '/account/reset_password.php';

$failures = 0;

function verify_v310_reset_route(
    bool $condition,
    string $message
): void {
    global $failures;

    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    echo 'FAIL: ' . $message . PHP_EOL;
    $failures++;
}

verify_v310_reset_route(
    is_file($route),
    'reset-password route exists'
);

if (!is_file($route)) {
    echo "V310_RESET_PASSWORD_ROUTE=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents(
        $route
    );

verify_v310_reset_route(
    str_contains(
        $source,
        "require_once dirname(__DIR__) . '/init.php';"
    ),
    'route uses application bootstrap'
);

verify_v310_reset_route(
    str_contains(
        $source,
        "'/includes/account_credential_lifecycle.php'"
    ),
    'route delegates reset consumption to shared lifecycle'
);

verify_v310_reset_route(
    str_contains(
        $source,
        "header('Cache-Control: no-store, max-age=0');"
    ),
    'reset route explicitly disables browser caching'
);

verify_v310_reset_route(
    str_contains(
        $source,
        "header('Referrer-Policy: no-referrer');"
    ),
    'token landing overrides referrer policy to no-referrer'
);

verify_v310_reset_route(
    str_contains(
        $source,
        "array_key_exists('token', \$_GET)"
    ),
    'tokenized GET landing is explicitly recognized'
);

verify_v310_reset_route(
    str_contains(
        $source,
        'session_regenerate_id(true);'
    ),
    'token landing and successful reset rotate session identity'
);

verify_v310_reset_route(
    str_contains(
        $source,
        'ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY'
    ),
    'raw reset capability uses server-side session handoff'
);

verify_v310_reset_route(
    substr_count(
        $source,
        'account_reset_redirect_clean();'
    ) >= 6,
    'token landing and POST outcomes use clean PRG redirects'
);

verify_v310_reset_route(
    str_contains(
        $source,
        'csrf_request_is_valid()'
    )
    && str_contains(
        $source,
        'csrf_field()'
    ),
    'reset POST uses central CSRF authority'
);

verify_v310_reset_route(
    str_contains(
        $source,
        'rate_limit_attempt('
    )
    && str_contains(
        $source,
        "'account_credential_password_reset_submit'"
    ),
    'reset POST uses shared browser-compatible limiter'
);

verify_v310_reset_route(
    str_contains(
        $source,
        'password_security_validate('
    )
    && str_contains(
        $source,
        'password_security_min_length()'
    ),
    'route reuses central password policy'
);

verify_v310_reset_route(
    str_contains(
        $source,
        'account_credential_consume_password_reset('
    ),
    'route delegates mutation and token consumption centrally'
);

verify_v310_reset_route(
    !str_contains(
        $source,
        'password_hash('
    ),
    'route performs no local password hashing'
);

verify_v310_reset_route(
    !preg_match(
        '/\b(?:SELECT|INSERT|UPDATE|DELETE)\b[\s\S]{0,160}\b(?:users|account_credential_tokens)\b/i',
        $source
    ),
    'route performs no direct credential SQL'
);

verify_v310_reset_route(
    !str_contains(
        $source,
        'platform_mail_send('
    )
    && !str_contains(
        $source,
        'account_credential_send'
    ),
    'reset route performs no email delivery'
);

verify_v310_reset_route(
    !str_contains(
        $source,
        'account_credential_issue_token('
    ),
    'reset route never issues a credential token'
);

verify_v310_reset_route(
    !preg_match(
        '/type\s*=\s*["\']hidden["\'][^>]*(?:token)|name\s*=\s*["\']token["\']/i',
        $source
    ),
    'raw credential token is never emitted as a hidden field'
);

verify_v310_reset_route(
    !preg_match(
        '/htmlspecialchars\s*\(\s*\$rawToken|echo\s+\$rawToken/',
        $source
    ),
    'raw reset token is never rendered into HTML'
);

verify_v310_reset_route(
    !str_contains(
        $source,
        '$e->getMessage()'
    ),
    'lifecycle exception messages are not exposed publicly'
);

verify_v310_reset_route(
    preg_match(
        '/error_log\s*\([\s\S]{0,200}get_class\s*\(\s*\$e\s*\)/',
        $source
    ) === 1,
    'unexpected reset failures log only exception class'
);

verify_v310_reset_route(
    str_contains(
        $source,
        'hash_equals($newPassword, $confirmPassword)'
    ),
    'password confirmation comparison is explicit'
);

verify_v310_reset_route(
    str_contains(
        $source,
        "versioned_asset('/assets/css/sign-page.css')"
    ),
    'reset page reuses canonical public authentication styling'
);

verify_v310_reset_route(
    !str_contains(
        $source,
        'subscription_recovery'
    ),
    'credential reset remains separate from subscription recovery'
);

if ($failures > 0) {
    echo "V310_RESET_PASSWORD_ROUTE=FAIL\n";
    exit(1);
}

echo "V310_RESET_PASSWORD_ROUTE=PASS\n";
