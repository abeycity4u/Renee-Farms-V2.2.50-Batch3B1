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
    $root . '/api/stock_history.php'
);

$js = file_get_contents(
    $root . '/assets/js/stock-history.js'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Stock History has zero literal inline script blocks'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/reports-chart-fallback.js')"
    ),
    'Stock History reuses shared Chart fallback loader'
);

$check(
    str_contains($php, 'data-fallback-src="'),
    'Stock History exposes versioned fallback source'
);

$check(
    str_contains($php, 'id="stockHistoryConfig"'),
    'Stock History emits centralized page config'
);

$check(
    str_contains($php, 'data-item-id="'),
    'Stock History config exposes item ID'
);

$check(
    str_contains($php, 'data-history-url="'),
    'Stock History config exposes history API URL'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/stock-history.js')"
    ),
    'Stock History loads versioned external behavior asset'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Stock History safely escapes dynamic config values'
);

$check(
    str_contains(
        $js,
        "document.getElementById('stockHistoryConfig')"
    ),
    'External asset reads centralized config'
);

$check(
    str_contains($js, 'dataset.itemId')
    && str_contains($js, 'dataset.historyUrl'),
    'External asset reads item and API config'
);

$check(
    str_contains($js, 'function formatDate'),
    'External asset retains date formatting'
);

$check(
    str_contains($js, 'function renderChart'),
    'External asset retains chart rendering'
);

$check(
    str_contains($js, 'function escapeHtml'),
    'External asset retains output escaping helper'
);

$check(
    str_contains($js, 'function attributionText'),
    'External asset retains attribution labeling'
);

$check(
    str_contains($js, 'function renderTable'),
    'External asset retains transaction table rendering'
);

$check(
    str_contains($js, 'function updateSummary'),
    'External asset retains summary rendering'
);

$check(
    str_contains($js, 'async function loadHistory'),
    'External asset retains history loading'
);

$check(
    substr_count($js, 'new Chart') === 1,
    'External asset retains stock trend Chart.js visualization'
);

$check(
    substr_count($js, 'fetch(') === 1,
    'External asset retains history API request'
);

$check(
    str_contains($js, 'encodeURIComponent(itemId)')
    && str_contains($js, 'encodeURIComponent(days)'),
    'History request safely encodes dynamic query parameters'
);

$check(
    str_contains($js, "document.getElementById('daysFilter')")
    && str_contains($js, "addEventListener('change'"),
    'External asset retains day-range filter behavior'
);

$check(
    str_contains($js, 'loadHistory();'),
    'External asset retains initial history load'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'Stock History external asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
