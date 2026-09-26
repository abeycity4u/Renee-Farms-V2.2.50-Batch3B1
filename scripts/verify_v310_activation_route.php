<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$route =
    $root . '/account/activate.php';

$failures = 0;

function verify_v310_activation_route(
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

verify_v310_activation_route(
    is_file($route),
    'activation route exists'
);

if (!is_file($route)) {
    echo "V310_ACTIVATION_ROUTE=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents(
        $route
    );

verify_v310_activation_route(
    str_contains(
        $source,
        "require_once dirname(__DIR__) . '/init.php';"
    ),
    'activation route uses application bootstrap'
);

verify_v310_activation_route(
    str_contains(
        $source,
        "'/includes/account_credential_lifecycle.php'"
    ),
    'activation route delegates lifecycle mutation centrally'
);

verify_v310_activation_route(
    str_contains(
        $source,
        "header('Cache-Control: no-store, max-age=0');"
    ),
    'activation route explicitly disables caching'
);

verify_v310_activation_route(
    str_contains(
        $source,
        "header('Referrer-Policy: no-referrer');"
    ),
    'token landing uses no-referrer policy'
);

verify_v310_activation_route(
    str_contains(
        $source,
        "array_key_exists('token', \$_GET)"
    ),
    'activation recognizes tokenized GET landing'
);

verify_v310_activation_route(
    str_contains(
        $source,
        'ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY'
    ),
    'raw activation capability uses server-side session handoff'
);

verify_v310_activation_route(
    str_contains(
        $source,
        'session_regenerate_id(true);'
    ),
    'activation token landing and success rotate session identity'
);

verify_v310_activation_route(
    str_contains(
        $source,
        'csrf_request_is_valid()'
    )
    && str_contains(
        $source,
        'csrf_field()'
    ),
    'activation POST uses central CSRF authority'
);

verify_v310_activation_route(
    str_contains(
        $source,
        'rate_limit_attempt('
    )
    && str_contains(
        $source,
        "'account_credential_activation_submit'"
    ),
    'activation POST uses shared browser-compatible rate limiter'
);

verify_v310_activation_route(
    str_contains(
        $source,
        'password_security_validate('
    )
    && str_contains(
        $source,
        'password_security_min_length()'
    ),
    'activation route reuses central password policy'
);

verify_v310_activation_route(
    str_contains(
        $source,
        'account_credential_consume_activation('
    ),
    'activation delegates pending-to-active transition centrally'
);

verify_v310_activation_route(
    !str_contains(
        $source,
        'account_credential_consume_password_reset('
    ),
    'activation never calls password-reset lifecycle mutation'
);

verify_v310_activation_route(
    !str_contains(
        $source,
        'password_hash('
    ),
    'activation route performs no local password hashing'
);

verify_v310_activation_route(
    !preg_match(
        '/\b(?:SELECT|INSERT|UPDATE|DELETE)\b[\s\S]{0,180}\b(?:users|account_credential_tokens)\b/i',
        $source
    ),
    'activation route performs no direct credential SQL'
);

verify_v310_activation_route(
    !str_contains(
        $source,
        'platform_mail_send('
    )
    && !str_contains(
        $source,
        'account_credential_send'
    ),
    'activation route performs no credential delivery'
);

verify_v310_activation_route(
    !str_contains(
        $source,
        'account_credential_issue_token('
    ),
    'activation route never issues a token'
);

verify_v310_activation_route(
    !preg_match(
        '/type\s*=\s*["\']hidden["\'][^>]*(?:token)|name\s*=\s*["\']token["\']/i',
        $source
    ),
    'raw activation token is never emitted as hidden form state'
);

verify_v310_activation_route(
    !preg_match(
        '/htmlspecialchars\s*\(\s*\$rawToken|echo\s+\$rawToken/',
        $source
    ),
    'raw activation token is never rendered into HTML'
);

verify_v310_activation_route(
    !str_contains(
        $source,
        '$e->getMessage()'
    ),
    'lifecycle exception messages are not exposed publicly'
);

verify_v310_activation_route(
    preg_match(
        '/error_log\s*\([\s\S]{0,220}get_class\s*\(\s*\$e\s*\)/',
        $source
    ) === 1,
    'terminal activation failure logs exception class only'
);

verify_v310_activation_route(
    str_contains(
        $source,
        'hash_equals($newPassword, $confirmPassword)'
    ),
    'activation password confirmation is explicit'
);

verify_v310_activation_route(
    str_contains(
        $source,
        "versioned_asset('/assets/css/sign-page.css')"
    ),
    'activation page reuses public authentication styling'
);

verify_v310_activation_route(
    !str_contains(
        $source,
        'subscription_recovery'
    ),
    'activation remains separate from billing recovery'
);

if ($failures > 0) {
    echo "V310_ACTIVATION_ROUTE=FAIL\n";
    exit(1);
}

echo "V310_ACTIVATION_ROUTE=PASS\n";
