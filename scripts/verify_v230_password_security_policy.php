<?php
$root = dirname(__DIR__);
$policy = @file_get_contents($root . '/includes/password_security.php') ?: '';
$guard = @file_get_contents($root . '/includes/user_management_tenant_guard.php') ?: '';
$farms = @file_get_contents($root . '/management/farms.php') ?: '';
$users = @file_get_contents($root . '/management/users.php') ?: '';
$sign = @file_get_contents($root . '/sign.php') ?: '';
$recovery = @file_get_contents($root . '/includes/subscription_recovery.php') ?: '';

$checks = [];
$add = static function (string $label, bool $ok) use (&$checks): void {
    $checks[] = [$label, $ok];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
};

$add('Central password policy helper exists', str_contains($policy, 'function password_security_min_length'));
$add('Central minimum password length is 8', str_contains($policy, 'return 8;'));
$add('Central password validator exists', str_contains($policy, 'function password_security_validate'));
$add('Central password hasher uses PASSWORD_DEFAULT', str_contains($policy, 'password_hash($password, PASSWORD_DEFAULT)'));
$add('Central verifier rejects non-hash stored values', str_contains($policy, "password_get_info(\$storedHash)['algo'] === 0"));
$add('Team User guard loads central password policy', str_contains($guard, "require_once __DIR__ . '/password_security.php'"));
$add('Team User add password is server-side validated', str_contains($guard, "\$isAdd = isset(\$_POST['add_user'])") && str_contains($guard, 'password_security_validate($password)'));
$add('Team User replacement password is server-side validated', str_contains($guard, "\$isEdit = isset(\$_POST['edit_user'])") && str_contains($guard, "if (\$isEdit && \$password === '') return;"));
$add('Team User page still hashes new passwords', str_contains($users, "password_hash(\$_POST['password'], PASSWORD_DEFAULT)"));
$add('Farm Admin still enforces minimum length 8', str_contains($farms, 'const FARM_OWNER_MIN_PASSWORD_LENGTH = 8;'));
$add('Farm Admin still hashes stored passwords', str_contains($farms, 'password_hash($password, PASSWORD_DEFAULT)'));
$add('Normal login delegates to central hash-only verifier', str_contains($sign, "password_security_verify(\$password, (string)(\$user['password'] ?? ''))"));
$add('Normal login no longer accepts plaintext compatibility credentials', !str_contains($sign, 'password_get_info(') && !str_contains($sign, "hash_equals((string) \$user['password']"));
$add('Recovery login delegates to central hash-only verifier', str_contains($recovery, "password_security_verify(\$password, (string)(\$account['password'] ?? ''))"));
$add('Recovery login no longer accepts plaintext compatibility credentials', !str_contains($recovery, 'password_get_info($stored)') && !str_contains($recovery, 'hash_equals($stored, $password)'));

$failures = count(array_filter($checks, static fn(array $check): bool => !$check[1]));
echo PHP_EOL . count($checks) . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
