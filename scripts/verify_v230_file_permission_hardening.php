<?php
$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$permissions = file_get_contents($root . '/admin/permissions_save.php');
$api = file_get_contents($root . '/api/api_helpers.php');

$check(
    str_contains($permissions, '@mkdir($logDir, 0775, true);'),
    'Permission error log directory uses non-world-writable mode'
);

$check(
    !str_contains($permissions, '@mkdir($logDir, 0777, true);'),
    'Permission error log directory does not use 0777'
);

$check(
    str_contains($permissions, "FILE_APPEND | LOCK_EX"),
    'Permission error log appends use exclusive locking'
);

$check(
    str_contains($api, "@mkdir(\$logDir,0775,true)")
    || str_contains($api, "@mkdir(\$logDir, 0775, true)"),
    'Application log directory also uses non-world-writable mode'
);

$check(
    !preg_match('/mkdir\([^;]*0777/', $permissions . "\n" . $api),
    'Audited logging helpers contain no world-writable directory creation'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
