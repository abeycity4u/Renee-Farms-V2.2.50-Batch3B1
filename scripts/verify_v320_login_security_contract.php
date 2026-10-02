<?php
/** Non-destructive source contract for the public login boundary. */

declare(strict_types=1);

$root = dirname(__DIR__);
$source = file_get_contents($root . '/sign.php');
$failures = 0;

function login_contract_check(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$label}\n";
}

login_contract_check($source !== false, 'sign.php is readable');
if ($source === false) {
    echo "FAILURES={$failures}\n";
    exit(1);
}

$contracts = [
    "require_valid_csrf_post();" => 'login POST requires shared CSRF validation',
    "csrf_field()" => 'login form emits shared CSRF field',
    "require_rate_limit('login_attempt', 12, 300)" => 'login attempts use shared guest rate limiting',
    "password_security_verify(" => 'login uses canonical password verification',
    "account_credential_login_allowed(\$user)" => 'credential-state gate is enforced',
    "session_regenerate_id(true)" => 'successful login rotates the session id',
    "password_security_bind_authenticated_session(\$user);" => 'successful login binds session to current credential state',
    "Invalid workspace, username, or password." => 'login failure remains enumeration-safe',
];

foreach ($contracts as $needle => $label) {
    login_contract_check(str_contains($source, $needle), $label);
}

login_contract_check(
    str_contains($source, 'WHERE u.username = ? AND f.slug = ? LIMIT 1'),
    'farm login lookup uses parameterized username/workspace binding'
);

login_contract_check(
    !str_contains($source, "Invalid password for")
        && !str_contains($source, "User not found")
        && !str_contains($source, "Workspace not found"),
    'login does not expose account-existence-specific failure text'
);

echo "FAILURES={$failures}\n";
echo 'LOGIN_SECURITY_CONTRACT=' . ($failures === 0 ? 'PASS' : 'FAIL') . "\n";
exit($failures === 0 ? 0 : 1);
