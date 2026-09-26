<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$helpers =
    $root . '/api/api_helpers.php';

$sign =
    $root . '/sign.php';

$login =
    $root . '/login.php';

$failures = 0;

function verify_v310_browser_rate_limit(
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

verify_v310_browser_rate_limit(
    is_file($helpers),
    'shared API/auth helper source exists'
);

if (!is_file($helpers)) {
    echo "V310_BROWSER_RATE_LIMIT_BRIDGE=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents(
        $helpers
    );

$signSource =
    is_file($sign)
        ? (string)file_get_contents($sign)
        : '';

$loginSource =
    is_file($login)
        ? (string)file_get_contents($login)
        : '';

verify_v310_browser_rate_limit(
    str_contains(
        $source,
        'function rate_limit_attempt('
    ),
    'shared non-terminating rate-limit decision exists'
);

verify_v310_browser_rate_limit(
    str_contains(
        $source,
        'function rate_limit_guest_bucket_path('
    )
    && str_contains(
        $source,
        'function rate_limit_increment_guest_bucket('
    ),
    'existing guest persistence primitives remain authoritative'
);

verify_v310_browser_rate_limit(
    str_contains(
        $source,
        "sys_get_temp_dir()"
    )
    && str_contains(
        $source,
        "'renee-rate-limit'"
    ),
    'guest limiter remains server-persistent outside PHP session storage'
);

verify_v310_browser_rate_limit(
    str_contains(
        $source,
        "flock(\$handle, LOCK_EX)"
    ),
    'guest limiter retains exclusive file locking'
);

verify_v310_browser_rate_limit(
    str_contains(
        $source,
        "'guest_server'"
    )
    && str_contains(
        $source,
        "'guest_session_fallback'"
    ),
    'shared decision exposes non-secret limiter scope for diagnostics'
);

verify_v310_browser_rate_limit(
    str_contains(
        $source,
        "'allowed' =>"
    )
    && str_contains(
        $source,
        '$count <= $maxRequests'
    ),
    'shared decision returns allowance without terminating the request'
);

$attemptStart =
    strpos(
        $source,
        'function rate_limit_attempt('
    );

$requireStart =
    strpos(
        $source,
        'function require_rate_limit('
    );

$attemptSource =
    $attemptStart === false
        ? ''
        : (
            $requireStart === false
                ? substr($source, $attemptStart)
                : substr(
                    $source,
                    $attemptStart,
                    $requireStart - $attemptStart
                )
        );

verify_v310_browser_rate_limit(
    !str_contains(
        $attemptSource,
        'send_json('
    )
    && !preg_match(
        '/\bexit\s*\(/',
        $attemptSource
    )
    && !preg_match(
        '/\bexit\s*;/',
        $attemptSource
    ),
    'shared decision contains no JSON response or exit policy'
);

$requireSource =
    $requireStart === false
        ? ''
        : substr(
            $source,
            $requireStart
        );

verify_v310_browser_rate_limit(
    str_contains(
        $requireSource,
        'rate_limit_attempt('
    ),
    'legacy API enforcement delegates to shared decision'
);

verify_v310_browser_rate_limit(
    str_contains(
        $requireSource,
        'send_json('
    )
    && str_contains(
        $requireSource,
        '429'
    ),
    'legacy API enforcement retains JSON HTTP 429 behavior'
);

verify_v310_browser_rate_limit(
    str_contains(
        $signSource,
        "require_rate_limit('login_attempt', 12, 300)"
    ),
    'normal login retains existing 12 per 300 second policy'
);

verify_v310_browser_rate_limit(
    str_contains(
        $loginSource,
        "require_rate_limit('subscription_recovery_attempt', 8, 300)"
    ),
    'subscription recovery retains its independent 8 per 300 second policy'
);

verify_v310_browser_rate_limit(
    !str_contains(
        $source,
        'account_credential'
    ),
    'shared limiter remains credential-agnostic'
);

if ($failures > 0) {
    echo "V310_BROWSER_RATE_LIMIT_BRIDGE=FAIL\n";
    exit(1);
}

echo "V310_BROWSER_RATE_LIMIT_BRIDGE=PASS\n";
