<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$route =
    $root . '/account/forgot_password.php';

$failures = 0;

function verify_v310_forgot_route(
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

verify_v310_forgot_route(
    is_file($route),
    'forgot-password public route exists'
);

if (!is_file($route)) {
    echo "V310_FORGOT_PASSWORD_ROUTE=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents(
        $route
    );

verify_v310_forgot_route(
    str_contains(
        $source,
        "require_once dirname(__DIR__) . '/init.php';"
    ),
    'route uses application bootstrap'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        "require_once dirname(__DIR__) . '/api/api_helpers.php';"
    ),
    'route loads shared rate-limit authority'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        "'/includes/account_credential_request.php'"
    ),
    'route delegates credential lookup and enqueue to shared request service'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        "header('Cache-Control: no-store, max-age=0');"
    ),
    'public credential route disables browser caching'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        'csrf_request_is_valid()'
    )
    && str_contains(
        $source,
        'csrf_field()'
    ),
    'route uses central CSRF validation and form token helpers'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        'rate_limit_attempt('
    )
    && str_contains(
        $source,
        "'account_credential_reset_request'"
    ),
    'route uses shared non-terminating limiter decision'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        'ACCOUNT_CREDENTIAL_RESET_REQUEST_LIMIT = 8'
    )
    && str_contains(
        $source,
        'ACCOUNT_CREDENTIAL_RESET_REQUEST_WINDOW_SECONDS = 300'
    ),
    'password reset request limit is explicitly bounded'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        'account_credential_request_farm_password_reset('
    )
    && str_contains(
        $source,
        'account_credential_request_platform_password_reset('
    ),
    'route delegates both farm and platform recovery identities'
);

verify_v310_forgot_route(
    !str_contains(
        $source,
        'account_credential_outbox_enqueue('
    ),
    'route contains no direct outbox persistence'
);

verify_v310_forgot_route(
    !str_contains(
        $source,
        'account_credential_send'
    )
    && !str_contains(
        $source,
        'platform_mail_send('
    ),
    'route contains no synchronous credential mail transport'
);

verify_v310_forgot_route(
    !str_contains(
        $source,
        'account_credential_issue_token('
    ),
    'route contains no token issuance'
);

verify_v310_forgot_route(
    !preg_match(
        '/UPDATE\s+users|INSERT\s+INTO\s+users|DELETE\s+FROM\s+users/i',
        $source
    ),
    'route contains no direct account mutation SQL'
);

verify_v310_forgot_route(
    substr_count(
        $source,
        "true,\n            303"
    ) >= 2,
    'POST outcomes use 303 redirect semantics'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        "\$_SESSION['credential_request_message']"
    )
    && str_contains(
        $source,
        "\$_SESSION['credential_request_type']"
    ),
    'route uses bounded session flash for PRG'
);

verify_v310_forgot_route(
    !str_contains(
        $source,
        "\$_SESSION['credential_request_username']"
    )
    && !str_contains(
        $source,
        "\$_SESSION['credential_request_workspace']"
    )
    && !str_contains(
        $source,
        "\$_SESSION['credential_request_workspace_id']"
    ),
    'username and workspace are never persisted in session flash'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        "\$_SESSION['credential_request_account_type']"
    ),
    'only non-sensitive account-type UI state is preserved across PRG'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        "Too many requests. Please try again shortly."
    ),
    'browser rate-limit rejection has an HTML-safe message'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        "If the account is eligible"
    )
    && str_contains(
        $source,
        "For privacy"
    ),
    'page copy avoids claiming account existence'
);

verify_v310_forgot_route(
    !str_contains(
        $source,
        'subscription_recovery'
    ),
    'credential recovery remains separate from subscription recovery'
);

verify_v310_forgot_route(
    str_contains(
        $source,
        "versioned_asset('/assets/css/sign-page.css')"
    ),
    'route reuses canonical public sign-in styling'
);

if ($failures > 0) {
    echo "V310_FORGOT_PASSWORD_ROUTE=FAIL\n";
    exit(1);
}

echo "V310_FORGOT_PASSWORD_ROUTE=PASS\n";
