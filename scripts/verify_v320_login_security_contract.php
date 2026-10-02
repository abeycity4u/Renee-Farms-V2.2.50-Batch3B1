<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sign = file_get_contents($root . '/sign.php');
$passwordSecurity = file_get_contents($root . '/includes/password_security.php');

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }
    echo "FAIL: {$label}\n";
    $failures++;
};

$check(
    str_contains($sign, "require_once(__DIR__ . '/includes/csrf.php')")
        || str_contains($sign, "require_once __DIR__ . '/includes/csrf.php'"),
    'sign-in route loads shared CSRF adapter'
);

$postPosition = strpos($sign, "if (\$_SERVER['REQUEST_METHOD'] == 'POST')");
$csrfPosition = strpos($sign, 'csrf_validate_request()');
$queryPosition = strpos($sign, '$pdo->prepare(', $postPosition === false ? 0 : $postPosition);

$check(
    $postPosition !== false
        && $csrfPosition !== false
        && $csrfPosition > $postPosition
        && ($queryPosition === false || $csrfPosition < $queryPosition),
    'CSRF validation executes before credential lookup'
);

$check(
    str_contains($sign, 'csrf_field()'),
    'sign-in form emits CSRF token field'
);

$check(
    preg_match("/require_rate_limit\\(\\s*'login_attempt'\\s*,\\s*12\\s*,\\s*300\\s*\\)/", $sign) === 1,
    'login attempt rate limit remains active'
);

$check(
    str_contains($sign, 'password_security_verify('),
    'sign-in delegates password checking to central password security helper'
);

$check(
    str_contains($passwordSecurity, 'password_verify(')
        && str_contains($passwordSecurity, "password_get_info(\$storedHash)['algo'] === 0"),
    'central password verifier rejects plaintext and uses password_verify'
);

$check(
    str_contains($sign, 'session_regenerate_id(true)'),
    'successful login rotates session identifier'
);

$check(
    !str_contains($sign, "'password' => \$password")
        && !str_contains($sign, 'log_app_error($password')
        && !str_contains($sign, 'error_log($password'),
    'login route does not log submitted password'
);

$check(
    str_contains($sign, "header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/sign.php', true, 303)"),
    'failed login uses PRG redirect back to canonical sign-in route'
);

echo "FAILURES={$failures}\n";
if ($failures === 0) {
    echo "LOGIN_SECURITY_CONTRACT=PASS\n";
    exit(0);
}

echo "LOGIN_SECURITY_CONTRACT=FAIL\n";
exit(1);
