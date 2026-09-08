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

$sign = file_get_contents($root . '/sign.php');
$index = file_get_contents($root . '/index.php');

foreach ([
    'sign.php' => $sign,
    'index.php' => $index,
] as $name => $content) {
    $check(
        !str_contains($content, 'fonts.googleapis.com'),
        $name . ' no longer references Google Fonts CSS/preconnect'
    );

    $check(
        !str_contains($content, 'fonts.gstatic.com'),
        $name . ' no longer references Google Fonts asset host'
    );

    $check(
        str_contains(
            $content,
            "font-family: 'Inter', system-ui, -apple-system, Segoe UI, sans-serif;"
        ),
        $name . ' retains local system font fallback stack'
    );
}

$check(
    str_contains(
        file_get_contents($root . '/dashboard.php'),
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'
    ),
    'Deferred Chart.js dependency remains on dashboard'
);

$check(
    str_contains(
        file_get_contents($root . '/management/reports.php'),
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'
    ),
    'Deferred Chart.js dependency remains on reports'
);

$check(
    str_contains(
        file_get_contents($root . '/api/stock_history.php'),
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'
    ),
    'Deferred Chart.js dependency remains on stock history'
);

$check(
    str_contains(
        file_get_contents($root . '/navbar_head.php'),
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css'
    ),
    'Deferred Bootstrap Icons dependency remains centralized'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
