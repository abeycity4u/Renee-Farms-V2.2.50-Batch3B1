<?php
/**
 * Focused V2.3 subscription lifecycle / expiry hardening verifier.
 * Static + pure-contract checks only: it never mutates tenant data or payments.
 */

$root = dirname(__DIR__);
$files = [
    'lifecycle' => $root . '/includes/subscription_lifecycle.php',
    'runtime' => $root . '/includes/farm_entitlement_runtime.php',
    'recovery' => $root . '/includes/subscription_recovery.php',
    'recover_page' => $root . '/billing/recover.php',
    'billing_application' => $root . '/includes/billing_subscription_application.php',
];

$checks = [];
$failures = [];
$pass = static function (string $label, bool $ok) use (&$checks, &$failures): void {
    $checks[] = [$label, $ok];
    if (!$ok) $failures[] = $label;
};

$content = [];
foreach ($files as $key => $path) {
    $exists = is_file($path);
    $pass($key . ' file exists', $exists);
    $content[$key] = $exists ? (string)file_get_contents($path) : '';
}

$lifecycle = $content['lifecycle'];
$runtime = $content['runtime'];
$recoverPage = $content['recover_page'];
$billingApplication = $content['billing_application'];

$pass('only trial/active are automatic expiry sources',
    str_contains($lifecycle, "return ['trial', 'active'];"));
$pass('automatic expiry target is past_due',
    str_contains($lifecycle, "SET subscription_status = 'past_due'"));
$pass('automatic transition never writes suspended',
    !str_contains($lifecycle, "SET subscription_status = 'suspended'"));
$pass('owner workspace is excluded from lifecycle writes',
    str_contains($lifecycle, "slug <> 'owner'"));
$pass('expiry requires an actual end timestamp comparison',
    str_contains($lifecycle, 'subscription_lifecycle_is_expired'));
$pass('expiry boundary is inclusive at term end',
    str_contains($lifecycle, 'return $endsAt <= $now;'));
$pass('ordinary non-expired requests avoid row lock transaction',
    strpos($lifecycle, 'subscription_lifecycle_farm($pdo, $farmId, false)') !== false
    && strpos($lifecycle, 'subscription_lifecycle_farm($pdo, $farmId, true)') !== false);
$pass('expired candidate is rechecked under row lock',
    str_contains($lifecycle, 'FOR UPDATE'));
$pass('expiry transition is immutable-history captured',
    str_contains($lifecycle, 'subscription_record_capture($pdo, $farmId, \'subscription_expired\', null)'));
$pass('past_due extends existing recovery target contract',
    str_contains($lifecycle, "return ['suspended', 'cancelled', 'past_due'];"));
$pass('Farm Admin past_due uses restricted recovery session',
    str_contains($lifecycle, 'subscription_recovery_start($account)')
    && str_contains($lifecycle, "subscription_lifecycle_redirect('/billing/recover.php')"));
$pass('non-admin past_due clears normal identity',
    str_contains($lifecycle, 'subscription_recovery_clear_normal_identity()'));
$pass('login request refreshes tenant lifecycle before authentication continues',
    str_contains($lifecycle, 'in_array($script, [\'sign.php\', \'login.php\'], true)')
    && str_contains($lifecycle, 'subscription_lifecycle_refresh_slug'));
$pass('runtime entitlement boundary invokes lifecycle guard',
    str_contains($runtime, "require_once __DIR__ . '/subscription_lifecycle.php';")
    && str_contains($runtime, 'subscription_lifecycle_request_guard($pdo)'));
$pass('recovery page explicitly accepts past_due',
    str_contains($recoverPage, "['suspended', 'cancelled', 'past_due', 'active']"));
$pass('recovery page still promotes only active subscription',
    str_contains($recoverPage, "=== 'active'")
    && str_contains($recoverPage, 'subscription_recovery_promote_to_login($pdo)'));
$pass('verified paid application restores farm to active',
    str_contains($billingApplication, "subscription_status = 'active'"));
$pass('billing application remains paid-attempt gated',
    str_contains($billingApplication, "!== 'paid'"));
$pass('lifecycle does not alter product fields during expiry',
    !preg_match("/SET\\s+subscription_plan\\s*=/i", $lifecycle)
    && !preg_match("/SET\\s+subscription_ends_at\\s*=/i", $lifecycle)
    && !preg_match("/SET\\s+subscription_starts_at\\s*=/i", $lifecycle));

// Pure time/status behavior without requiring config.php or a database.
if (is_file($files['lifecycle'])) {
    require_once $files['lifecycle'];
    $now = new DateTimeImmutable('2026-09-07 12:00:00', new DateTimeZone(date_default_timezone_get()));
    $pass('pure active expired contract', subscription_lifecycle_is_expired('active', '2026-09-07 11:59:59', $now));
    $pass('pure trial expired contract', subscription_lifecycle_is_expired('trial', '2026-09-07 12:00:00', $now));
    $pass('future active remains active', !subscription_lifecycle_is_expired('active', '2026-09-07 12:00:01', $now));
    $pass('past_due is idempotent/non-expirable', !subscription_lifecycle_is_expired('past_due', '2026-09-01 00:00:00', $now));
    $pass('suspended is never auto-transitioned', !subscription_lifecycle_is_expired('suspended', '2026-09-01 00:00:00', $now));
}

foreach ($checks as [$label, $ok]) {
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $label . PHP_EOL;
}

echo PHP_EOL . count($checks) . ' checks, ' . count($failures) . ' failures.' . PHP_EOL;
if ($failures) exit(1);
echo 'V2.3 subscription lifecycle / expiry hardening contract passed.' . PHP_EOL;
