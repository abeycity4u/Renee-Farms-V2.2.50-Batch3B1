<?php
/**
 * V2.3 compact notification / scroll-stability verifier.
 *
 * Static/read-only: no application bootstrap and no database access.
 */
$root = dirname(__DIR__);
$files = [
    'navbar_head.php' => $root . '/navbar_head.php',
    'assets/js/main.js' => $root . '/assets/js/main.js',
    'dashboard.php' => $root . '/dashboard.php',
];

$contents = [];
$failures = 0;
$checks = 0;

$check = static function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

foreach ($files as $label => $path) {
    $data = is_file($path) ? file_get_contents($path) : false;
    $check($data !== false, $label . ' is readable');
    $contents[$label] = $data === false ? '' : $data;
}

$head = $contents['navbar_head.php'];
$main = $contents['assets/js/main.js'];
$dashboard = $contents['dashboard.php'];

$check(str_contains($head, 'position: fixed;'), 'platform notification container remains a fixed overlay');
$check(str_contains($head, 'width: min(620px, calc(100% - 24px));'), 'desktop notification width is capped at 620px');
$check(str_contains($head, 'grid-template-columns: 40px minmax(0, 1fr) 28px;'), 'desktop notification uses compact three-column layout');
$check(str_contains($head, 'width: 36px; height: 36px;'), 'desktop notification icon is compact');
$check(str_contains($head, '.app-notification-tip { display:none; }'), 'redundant Tip/Action side panel is hidden');
$check(str_contains($head, 'contain: layout paint;'), 'notification overlay is layout/paint contained');
$check(str_contains($head, 'overscroll-behavior: contain;'), 'notification overlay cannot chain scrolling into the page');
$check(str_contains($head, 'const maxStack = 3;'), 'dynamic notification stack is capped at three');
$check(str_contains($head, 'const add = (type, message, title, tip, duration = null) =>'), 'shared notification API accepts explicit durations');

$check(str_contains($main, "window.AppNotify.show(mappedType, message, null, null, duration)"), 'legacy dynamic showAlert delegates to shared notification overlay');
$check(str_contains($main, 'position:fixed;top:72px;right:12px;'), 'legacy fallback is also fixed and compact');

$check(str_contains($dashboard, 'let dashboardLowStockCount = <?php echo (int)$lowStockCount; ?>;'), 'dashboard tracks low-stock count in client state');
$check(str_contains($dashboard, 'AppNotify.show(mapped, message, null, null, duration)'), 'dashboard forwards requested notification duration');

$pollStart = strpos($dashboard, '// Refresh low-stock status in the background without changing scroll position.');
$pollEnd = $pollStart === false ? false : strpos($dashboard, '// Check for notifications', $pollStart);
$pollBlock = ($pollStart !== false && $pollEnd !== false) ? substr($dashboard, $pollStart, $pollEnd - $pollStart) : '';
$check($pollBlock !== '', 'dashboard passive low-stock polling block is identifiable');
$check($pollBlock !== '' && !str_contains($pollBlock, 'location.reload'), 'passive low-stock polling never reloads or moves the page');
$check($pollBlock !== '' && str_contains($pollBlock, 'dashboardLowStockCount = lowStockCount;'), 'passive polling updates its comparison baseline without reload');

$check(!str_contains($head, 'width: min(1120px'), 'legacy 1120px notification banner width is gone');
$check(!str_contains($head, 'grid-template-columns: 64px'), 'legacy oversized four-column notification layout is gone');

printf("\n%d checks, %d failure(s).\n", $checks, $failures);
if ($failures === 0) {
    echo "PASS: V2.3 platform notifications are compact, unified, fixed-overlay, and passive dashboard polling is scroll-stable.\n";
    exit(0);
}
exit(1);
