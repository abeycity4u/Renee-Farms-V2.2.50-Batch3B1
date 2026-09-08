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

$behaviors = file_get_contents($root . '/assets/js/app-behaviors.js');
$navbar = file_get_contents($root . '/navbar_head.php');

$check(
    str_contains($navbar, "versioned_asset('/assets/js/app-behaviors.js')"),
    'Navbar centrally loads shared CSP behavior layer'
);

$check(
    !preg_match('/<script\\b[^>]*\\bdefer\\b[^>]*app-behaviors\\.js/i', $navbar),
    'Shared CSP behavior layer installs before parser-time resource failures'
);

$targets = [
    'dashboard.php',
    'api/stock_history.php',
    'management/reports.php',
];

$check(
    str_contains($behaviors, "document.addEventListener('error'"),
    'Shared behavior layer listens for resource load errors'
);

$check(
    str_contains($behaviors, 'target instanceof HTMLScriptElement'),
    'Shared behavior limits fallback handling to script elements'
);

$check(
    str_contains($behaviors, "[data-chart-fallback]"),
    'Shared behavior recognizes data-chart-fallback contract'
);

$check(
    str_contains($behaviors, "typeof window.loadChartFallback !== 'function'"),
    'Shared behavior safely guards missing loadChartFallback function'
);

$check(
    str_contains($behaviors, 'window.loadChartFallback();'),
    'Shared behavior delegates Chart.js failure to page-owned fallback function'
);

foreach ($targets as $relative) {
    $content = file_get_contents($root . '/' . $relative);

    $check(
        !str_contains($content, 'onerror="loadChartFallback()"'),
        $relative . ' no longer uses inline Chart.js onerror handler'
    );

    $check(
        str_contains($content, 'data-chart-fallback'),
        $relative . ' opts into centralized Chart.js fallback behavior'
    );

    $check(
        str_contains($content, 'function loadChartFallback()'),
        $relative . ' retains its page-owned fallback implementation'
    );

    $check(
        str_contains($content, '/assets/js/chart-fallback.js'),
        $relative . ' still targets the local chart fallback asset'
    );
}

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
