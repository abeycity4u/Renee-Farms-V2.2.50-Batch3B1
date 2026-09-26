<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$sign =
    $root . '/sign.php';

$lifecycle =
    $root
    . '/includes/account_credential_lifecycle.php';

$failures = 0;

function verify_v310_login_state(
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

verify_v310_login_state(
    is_file($sign),
    'sign-in route exists'
);

verify_v310_login_state(
    is_file($lifecycle),
    'shared credential lifecycle exists'
);

if (
    !is_file($sign)
    || !is_file($lifecycle)
) {
    echo "V310_LOGIN_CREDENTIAL_STATE=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents(
        $sign
    );

$lifecycleSource =
    (string)file_get_contents(
        $lifecycle
    );

verify_v310_login_state(
    str_contains(
        $source,
        "require_once(__DIR__ . '/includes/account_credential_lifecycle.php');"
    ),
    'sign-in loads shared credential lifecycle'
);

verify_v310_login_state(
    str_contains(
        $source,
        'account_credential_login_allowed($user)'
    ),
    'sign-in delegates credential-state eligibility centrally'
);

verify_v310_login_state(
    str_contains(
        $lifecycleSource,
        'function account_credential_login_allowed('
    ),
    'login eligibility authority remains centrally defined'
);

verify_v310_login_state(
    preg_match(
        '/function\s+account_credential_login_allowed[\s\S]{0,700}hash_equals\s*\(\s*[\'"]active[\'"]/s',
        $lifecycleSource
    ) === 1,
    'central eligibility authority requires active credential state'
);

$passwordPos =
    strpos(
        $source,
        'verifyLoginPassword($pdo, $user, $password)'
    );

$statePos =
    strpos(
        $source,
        'account_credential_login_allowed($user)'
    );

$lastLoginPos =
    strpos(
        $source,
        'UPDATE users SET last_login_at = NOW()'
    );

$sessionPos =
    strpos(
        $source,
        'session_regenerate_id(true);'
    );

verify_v310_login_state(
    $passwordPos !== false
    && $statePos !== false
    && $passwordPos < $statePos,
    'password verification precedes credential-state rejection'
);

verify_v310_login_state(
    $statePos !== false
    && $lastLoginPos !== false
    && $statePos < $lastLoginPos,
    'credential-state eligibility precedes last-login mutation'
);

verify_v310_login_state(
    $statePos !== false
    && $sessionPos !== false
    && $statePos < $sessionPos,
    'credential-state eligibility precedes session establishment'
);

verify_v310_login_state(
    str_contains(
        $source,
        'Invalid workspace, username, or password.'
    ),
    'failed login continues to use generic browser message'
);

verify_v310_login_state(
    !preg_match(
        '/login_error[\s\S]{0,220}(?:pending_activation|pending activation|activate your account)/i',
        $source
    ),
    'login exposes no pending-activation-specific failure'
);

verify_v310_login_state(
    !preg_match(
        '/\$_SESSION\s*\[[\'"](?:credential_state|activation_state)[\'"]\]/i',
        $source
    ),
    'credential state is not persisted into login session'
);

verify_v310_login_state(
    substr_count(
        $source,
        'account_credential_login_allowed($user)'
    ) === 1,
    'login eligibility gate appears exactly once'
);

verify_v310_login_state(
    !preg_match(
        '/credential_state\s*===?\s*[\'"]active[\'"]|credential_state\s*!==?\s*[\'"]pending_activation[\'"]/i',
        $source
    ),
    'sign-in does not duplicate lifecycle credential-state policy'
);

verify_v310_login_state(
    str_contains(
        $source,
        "require_rate_limit('login_attempt', 12, 300);"
    ),
    'existing login throttle remains unchanged'
);

verify_v310_login_state(
    str_contains(
        $source,
        "header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/sign.php', true, 303);"
    ),
    'existing failed-login PRG remains unchanged'
);

if ($failures > 0) {
    echo "V310_LOGIN_CREDENTIAL_STATE=FAIL\n";
    exit(1);
}

echo "V310_LOGIN_CREDENTIAL_STATE=PASS\n";
