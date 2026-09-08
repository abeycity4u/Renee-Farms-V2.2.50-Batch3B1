<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$php = file_get_contents(
    $root . '/dashboard.php'
);

$js = file_get_contents(
    $root . '/assets/js/dashboard.js'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Dashboard has zero literal inline script blocks'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/reports-chart-fallback.js')"
    ),
    'Dashboard reuses shared Chart fallback loader'
);

$check(
    str_contains($php, 'data-fallback-src="'),
    'Dashboard exposes versioned Chart fallback source'
);

$check(
    str_contains($php, 'id="dashboardConfig"'),
    'Dashboard emits centralized page config'
);

$check(
    str_contains($php, 'data-low-stock-count="'),
    'Dashboard config exposes low-stock count'
);

$check(
    str_contains($php, 'data-farm-access="'),
    'Dashboard config exposes farm access'
);

$check(
    str_contains($php, 'data-low-stock-items="'),
    'Dashboard config exposes low-stock items'
);

$check(
    str_contains($php, 'data-today="'),
    'Dashboard config exposes application date'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/dashboard.js')"
    ),
    'Dashboard loads versioned external behavior asset'
);

$check(
    str_contains($php, 'app_json_script($lowStockItems)'),
    'Dashboard retains safe JSON serialization for low-stock items'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Dashboard safely escapes dynamic config attributes'
);

$check(
    str_contains(
        $js,
        "document.getElementById('dashboardConfig')"
    ),
    'Dashboard asset reads centralized config'
);

$check(
    str_contains($js, 'dataset.lowStockCount')
    && str_contains($js, 'dataset.farmAccess')
    && str_contains($js, 'dataset.lowStockItems')
    && str_contains($js, 'dataset.today'),
    'Dashboard asset reads all dynamic config values'
);

$check(
    str_contains($js, 'function filterStock'),
    'Dashboard asset retains stock filtering'
);

$check(
    str_contains($js, 'function quickStockUpdate'),
    'Dashboard asset retains quick stock update contract'
);

$check(
    str_contains($js, 'function refreshStockData'),
    'Dashboard asset retains stock refresh behavior'
);

$check(
    str_contains($js, 'function checkNotifications'),
    'Dashboard asset retains notification checks'
);

$check(
    str_contains($js, 'function updateNotificationBadge'),
    'Dashboard asset retains notification badge behavior'
);

$check(
    str_contains($js, 'function showAlert'),
    'Dashboard asset retains shared alert contract'
);

$check(
    str_contains($js, 'function updateCurrentTime'),
    'Dashboard asset retains live clock behavior'
);

$check(
    !str_contains($js, '(function () {')
    && !str_contains($js, '(function(){'),
    'Dashboard runtime is not hidden inside an IIFE'
);

$check(
    str_contains(
        file_get_contents($root . '/assets/js/app-behaviors.js'),
        'window.quickStockUpdate'
    )
    && str_contains($js, 'function quickStockUpdate'),
    'Dashboard preserves app-behaviors quickStockUpdate global contract'
);

$check(
    str_contains(
        file_get_contents($root . '/assets/js/dashboard-quick-stock.js'),
        "showAlert('success'"
    )
    && str_contains($js, 'function showAlert'),
    'Dashboard preserves quick-stock helper showAlert contract'
);

$check(
    substr_count(
        $js,
        'encodeURIComponent(dashboardConfig.farmAccess)'
    ) === 2,
    'Dashboard safely encodes farm access in both background requests'
);

$check(
    str_contains(
        $js,
        'encodeURIComponent(today)'
    ),
    'Dashboard safely encodes date in pending-task request'
);

$check(
    str_contains($js, 'dashboardConfig.lowStockItems'),
    'Dashboard notification checks use centralized low-stock data'
);

$check(
    str_contains($js, 'window.AppNotify'),
    'Dashboard retains platform-wide AppNotify delegation'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'Dashboard external asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
