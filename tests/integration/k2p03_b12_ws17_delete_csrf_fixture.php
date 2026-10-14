<?php
declare(strict_types=1);
/**
 * WS17 focused emitted-response fixture (run each case as separate CLI).
 * Disconnected /tmp test copies of the REAL shared responder + JSON
 * transport; a test-only hard-OFF/ON gate and unneeded save-gate dependency
 * stub. Never loads the application, changes checked-in gate or opens a DB.
 */
$root = dirname(__DIR__, 2);
$case = (string)($argv[1] ?? '');
if (!in_array($case, ['off_invalid_csrf', 'on_invalid_csrf'], true)) {
    fwrite(STDERR, "WS17_UNKNOWN_CASE\n");
    exit(2);
}
$on = $case === 'on_invalid_csrf';
$dir = sys_get_temp_dir() . '/b12-ws17-' . bin2hex(random_bytes(10));
if (!mkdir($dir, 0700) || is_link($dir)) {
    fwrite(STDERR, "WS17_TEMP_SETUP_FAILED\n");
    exit(2);
}
$names = [
    'daily_record_revision_delete_http.php',
    'daily_record_revision_http_transport.php',
];
register_shutdown_function(static function () use ($root, $dir, $names): void {
    $status = http_response_code();
    echo "\nHTTP_STATUS=" . ($status === false ? 'unset' : $status) . "\n";
    echo 'ORIGINAL_GATE_SHA256=' .
        hash_file('sha256', $root . '/lib/daily_record_revision_cutover_gate.php') . "\n";
    foreach (array_merge($names, [
        'daily_record_revision_cutover_gate.php',
        'daily_record_revision_save_gate.php'
    ]) as $name) {
        if (is_file($dir . '/' . $name)) unlink($dir . '/' . $name);
    }
    if (is_dir($dir)) rmdir($dir);
    echo 'WS17_TEMP_REMOVED=' . (!is_dir($dir) ? 'YES' : 'NO') . "\n";
});
foreach ($names as $name) {
    if (!copy($root . '/lib/' . $name, $dir . '/' . $name)) {
        fwrite(STDERR, "WS17_COPY_FAILED\n");
        exit(2);
    }
}
// The CSRF response path does not use the SAVE gate's classes or SQL.
$unused = "<?php\ndeclare(strict_types=1);\n// Isolated unused dependency stub.\n";
$gate = "<?php\ndeclare(strict_types=1);\n" .
    "function daily_record_revision_cutover_enabled():bool{return " .
    ($on ? 'true' : 'false') . ";}\n";
if (file_put_contents($dir . '/daily_record_revision_cutover_gate.php', $gate, LOCK_EX) !== strlen($gate)
    || file_put_contents($dir . '/daily_record_revision_save_gate.php', $unused, LOCK_EX) !== strlen($unused)) {
    fwrite(STDERR, "WS17_STUB_SETUP_FAILED\n");
    exit(2);
}
require $dir . '/daily_record_revision_cutover_gate.php';
require $dir . '/daily_record_revision_delete_http.php';
http_response_code(200);
daily_record_revision_delete_http_csrf_failure();
fwrite(STDERR, "WS17_CSRF_HELPER_UNEXPECTED_RETURN\n");
exit(3);
