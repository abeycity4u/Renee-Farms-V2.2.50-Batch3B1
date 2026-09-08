<?php
$root = dirname(__DIR__);
$sign = @file_get_contents($root . '/sign.php') ?: '';

$checks = [];
$add = static function (string $label, bool $ok) use (&$checks): void {
    $checks[] = [$label, $ok];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
};

$add('Failed login stores generic error in session', str_contains($sign, "\$_SESSION['login_error'] = 'Invalid workspace, username, or password.';"));
$add('Failed login preserves only selected account type', str_contains($sign, "\$_SESSION['login_account_type'] = \$accountType;"));
$add('Failed login responds with 303 redirect', str_contains($sign, "'/sign.php', true, 303"));
$add('Failed login exits after redirect', str_contains($sign, "header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/sign.php', true, 303);\n    exit();"));
$add('GET consumes login error flash', str_contains($sign, "\$flashError = \$_SESSION['login_error'] ?? null;"));
$add('GET clears login error flash', str_contains($sign, "unset(\$_SESSION['login_error']);"));
$add('GET restores selected account type', str_contains($sign, "\$flashAccountType = \$_SESSION['login_account_type'] ?? null;"));
$add('GET clears account type flash', str_contains($sign, "unset(\$_SESSION['login_account_type']);"));
$add('Username is not stored in login flash', !str_contains($sign, "\$_SESSION['login_username']"));
$add('Workspace is not stored in login flash', !str_contains($sign, "\$_SESSION['login_farm_slug']"));
$add('Password is never stored in session flash', !str_contains($sign, "\$_SESSION['login_password']"));
$add('Failure path no longer renders error directly from POST', !str_contains($sign, "\$error = 'Invalid workspace, username, or password.';"));

$failures = count(array_filter($checks, static fn(array $check): bool => !$check[1]));
echo PHP_EOL . count($checks) . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
