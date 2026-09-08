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
    $root . '/management/reports.php'
);

$fallback = file_get_contents(
    $root . '/assets/js/reports-chart-fallback.js'
);

$reports = file_get_contents(
    $root . '/assets/js/management-reports.js'
);

$check(
    str_contains($php, 'id="managementReportsConfig"'),
    'Reports emits centralized chart config'
);

$check(
    str_contains($php, 'data-profit-labels="')
    && str_contains($php, 'data-profit-values="'),
    'Reports config exposes profitability chart datasets'
);

$check(
    str_contains($php, 'data-product-labels="')
    && str_contains($php, 'data-product-values="'),
    'Reports config exposes product chart datasets'
);

$check(
    str_contains($php, 'data-expense-labels="')
    && str_contains($php, 'data-expense-values="'),
    'Reports config exposes expense chart datasets'
);

$check(
    substr_count($php, 'app_json_script(') >= 6,
    'Reports retains safe JSON serialization for all chart datasets'
);

$check(
    str_contains($php, 'data-fallback-src="'),
    'Reports exposes versioned Chart fallback source'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/reports-chart-fallback.js')"
    ),
    'Reports loads versioned head fallback asset'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/management-reports.js')"
    ),
    'Reports loads versioned report behavior asset'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Reports has zero literal inline script blocks'
);

$check(
    str_contains(
        $fallback,
        'window.loadChartFallback = function loadChartFallback()'
    ),
    'Fallback asset preserves shared global loader contract'
);

$check(
    str_contains($fallback, 'window.fmChartFallbackLoaded'),
    'Fallback asset preserves duplicate-load guard'
);

$check(
    str_contains($fallback, 'document.currentScript'),
    'Fallback asset reads its own fallback source config'
);

$check(
    str_contains($fallback, 'dataset.fallbackSrc'),
    'Fallback asset reads configured fallback URL'
);

$check(
    str_contains($fallback, "document.createElement('script')"),
    'Fallback asset retains dynamic fallback script loading'
);

$check(
    !str_contains($fallback, '<?php')
    && !str_contains($fallback, '<?='),
    'Fallback asset contains no PHP'
);

$check(
    str_contains(
        $reports,
        "document.getElementById('managementReportsConfig')"
    ),
    'Reports asset reads centralized config'
);

$check(
    str_contains($reports, 'dataset.profitLabels')
    && str_contains($reports, 'dataset.profitValues'),
    'Reports asset reads profitability chart config'
);

$check(
    str_contains($reports, 'dataset.productLabels')
    && str_contains($reports, 'dataset.productValues'),
    'Reports asset reads product chart config'
);

$check(
    str_contains($reports, 'dataset.expenseLabels')
    && str_contains($reports, 'dataset.expenseValues'),
    'Reports asset reads expense chart config'
);

$check(
    str_contains($reports, 'updateReport'),
    'Reports asset retains filter navigation'
);

$check(
    str_contains(
        $reports,
        'window.exportToExcel = function exportToExcel()'
    ),
    'Reports asset preserves shared Excel export contract'
);

$check(
    substr_count($reports, 'new Chart') === 3,
    'Reports asset retains all three Chart.js visualizations'
);

$check(
    str_contains($reports, 'profitChart')
    && str_contains($reports, 'productsChart')
    && str_contains($reports, 'expensesChart'),
    'Reports asset retains all chart bindings'
);

$check(
    str_contains($reports, 'window.location'),
    'Reports asset retains navigation/export redirects'
);

$check(
    !str_contains($reports, '<?php')
    && !str_contains($reports, '<?='),
    'Reports behavior asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
