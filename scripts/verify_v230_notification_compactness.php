<?php
/**
 * V2.3 compact notification / scroll-stability verifier.
 *
 * Static/read-only: no application bootstrap and no database access.
 */
$root = dirname(__DIR__);
$files = [
    'navbar_head.php' => $root . '/navbar_head.php',
    'assets/css/notifications.css' => $root . '/assets/css/notifications.css',
    'assets/js/app-notify.js' => $root . '/assets/js/app-notify.js',
    'assets/js/main.js' => $root . '/assets/js/main.js',
    'assets/js/dashboard.js' => $root . '/assets/js/dashboard.js',
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
$notificationsCss = $contents['assets/css/notifications.css'];
$appNotify = $contents['assets/js/app-notify.js'];
$main = $contents['assets/js/main.js'];
$dashboard = $contents['assets/js/dashboard.js'];

$check(
    str_contains(
        $head,
        "versioned_asset('/assets/css/notifications.css')"
    ),
    'navbar loads the externalized notification presentation'
);

$check(
    str_contains(
        $head,
        "versioned_asset('/assets/js/app-notify.js')"
    ),
    'navbar loads the externalized shared notification runtime'
);

$check(
    (bool)preg_match(
        '/\.app-notifications\s*\{[^}]*position:\s*fixed\s*;/s',
        $notificationsCss
    ),
    'platform notification container remains a fixed overlay'
);

$check(
    (bool)preg_match(
        '/width:\s*min\(620px,\s*calc\(100%\s*-\s*24px\)\)\s*;/',
        $notificationsCss
    ),
    'desktop notification width is capped at 620px'
);

$check(
    (bool)preg_match(
        '/grid-template-columns:\s*40px\s+minmax\(0,\s*1fr\)\s+28px\s*;/',
        $notificationsCss
    ),
    'desktop notification uses compact three-column layout'
);

$check(
    (bool)preg_match(
        '/\.app-notification-icon\s*\{[^}]*width:\s*36px\s*;[^}]*height:\s*36px\s*;/s',
        $notificationsCss
    ),
    'desktop notification icon is compact'
);

$check(
    (bool)preg_match(
        '/\.app-notification-tip\s*\{\s*display:\s*none\s*;\s*\}/',
        $notificationsCss
    ),
    'redundant Tip/Action side panel is hidden'
);

$check(
    (bool)preg_match(
        '/contain:\s*layout\s+paint\s*;/',
        $notificationsCss
    ),
    'notification overlay is layout/paint contained'
);

$check(
    (bool)preg_match(
        '/overscroll-behavior:\s*contain\s*;/',
        $notificationsCss
    ),
    'notification overlay cannot chain scrolling into the page'
);

$check(
    str_contains(
        $appNotify,
        'const maxStack = 3;'
    ),
    'dynamic notification stack is capped at three'
);

$check(
    (bool)preg_match(
        '/const\s+add\s*=\s*\(\s*type\s*,\s*message\s*,\s*title\s*,\s*tip\s*,\s*duration\s*=\s*null\s*\)\s*=>/',
        $appNotify
    ),
    'shared notification API accepts explicit durations'
);

$check(
    str_contains(
        $main,
        'window.AppNotify.show(mappedType, message, null, null, duration)'
    ),
    'legacy dynamic showAlert delegates to shared notification overlay'
);

$check(
    str_contains(
        $main,
        'position:fixed;top:72px;right:12px;'
    ),
    'legacy fallback is also fixed and compact'
);

$check(
    str_contains(
        $dashboard,
        'let dashboardLowStockCount = dashboardConfig.lowStockCount;'
    ),
    'dashboard tracks low-stock count in client state'
);

$check(
    str_contains(
        $dashboard,
        'AppNotify.show(mapped, message, null, null, duration)'
    ),
    'dashboard forwards requested notification duration'
);

$pollStart = strpos(
    $dashboard,
    '// Refresh low-stock status in the background without changing scroll position.'
);

$pollEnd =
    $pollStart === false
        ? false
        : strpos(
            $dashboard,
            '// Check for notifications',
            $pollStart
        );

$pollBlock =
    ($pollStart !== false && $pollEnd !== false)
        ? substr(
            $dashboard,
            $pollStart,
            $pollEnd - $pollStart
        )
        : '';

$check(
    $pollBlock !== '',
    'dashboard passive low-stock polling block is identifiable'
);

$check(
    $pollBlock !== ''
        && !str_contains(
            $pollBlock,
            'location.reload'
        ),
    'passive low-stock polling never reloads or moves the page'
);

$check(
    $pollBlock !== ''
        && str_contains(
            $pollBlock,
            'dashboardLowStockCount = lowStockCount;'
        ),
    'passive polling updates its comparison baseline without reload'
);

$check(
    !str_contains(
        $notificationsCss,
        'width: min(1120px'
    ),
    'legacy 1120px notification banner width is gone'
);

$check(
    !str_contains(
        $notificationsCss,
        'grid-template-columns: 64px'
    ),
    'legacy oversized four-column notification layout is gone'
);

printf("\n%d checks, %d failure(s).\n", $checks, $failures);
if ($failures === 0) {
    echo "PASS: V2.3 platform notifications are compact, unified, fixed-overlay, and passive dashboard polling is scroll-stable.\n";
    exit(0);
}
exit(1);
