<?php
/**
 * Focused V2.3 verifier for read-only session lock release.
 * Static/pure checks only; no tenant, payment, subscription, or audit mutation.
 */

$root = dirname(__DIR__);
$helper = $root . '/includes/readonly_session.php';
$pending = $root . '/api/check_pending_tasks.php';
$stock = $root . '/api/get_stock_summary.php';

$checks = [];
$failures = [];
$pass = static function (string $label, bool $ok) use (&$checks, &$failures): void {
    $checks[] = [$label, $ok];
    if (!$ok) $failures[] = $label;
};

$pass('readonly session helper exists', is_file($helper));
$pass('pending-task API exists', is_file($pending));
$pass('stock-summary API exists', is_file($stock));

$helperSource = is_file($helper) ? (string)file_get_contents($helper) : '';
$pendingSource = is_file($pending) ? (string)file_get_contents($pending) : '';
$stockSource = is_file($stock) ? (string)file_get_contents($stock) : '';

$pass('helper exposes canonical release function',
    str_contains($helperSource, 'function release_readonly_session_lock()'));
$pass('helper only closes an active session',
    str_contains($helperSource, 'session_status() !== PHP_SESSION_ACTIVE'));
$pass('helper uses session_write_close',
    str_contains($helperSource, 'session_write_close();'));

foreach ([
    'pending-task API' => $pendingSource,
    'stock-summary API' => $stockSource,
] as $label => $source) {
    $pass($label . ' loads shared helper',
        str_contains($source, "includes/readonly_session.php"));
    $pass($label . ' remains authentication guarded',
        str_contains($source, 'requireLogin();'));
    $pass($label . ' resolves current farm before releasing lock',
        ($farmPos = strpos($source, 'requireCurrentFarmId()')) !== false
        && ($releasePos = strpos($source, 'release_readonly_session_lock();')) !== false
        && $farmPos < $releasePos);
    $pass($label . ' releases session before database read workload',
        ($releasePos = strpos($source, 'release_readonly_session_lock();')) !== false
        && ($queryPos = strpos($source, '$pdo->prepare')) !== false
        && $releasePos < $queryPos);
}

foreach ($checks as [$label, $ok]) {
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $label . PHP_EOL;
}

echo PHP_EOL . count($checks) . ' checks, ' . count($failures) . ' failures.' . PHP_EOL;
if ($failures) exit(1);
echo 'V2.3 read-only session lock release contract passed.' . PHP_EOL;
