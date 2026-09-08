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

$page = file_get_contents($root . '/management/poultry_ruminant_report.php');
$js = file_get_contents($root . '/assets/js/poultry-ruminant-report.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/poultry-ruminant-report.js')"
    ),
    'Poultry/Ruminant report loads external versioned behavior asset'
);

$check(
    substr_count($page, 'poultry-ruminant-report.js') === 1,
    'Report page loads external behavior asset exactly once'
);

$check(
    str_contains($js, 'function applyFilters()'),
    'External report asset retains applyFilters function'
);

$check(
    str_contains($js, "$('#farmTypeFilter').val()"),
    'External report asset retains farm type filter'
);

$check(
    str_contains($js, "$('#reportMode').val()"),
    'External report asset retains report mode filter'
);

$check(
    str_contains($js, "$('#monthFilter').val()"),
    'External report asset retains month filter'
);

$check(
    str_contains($js, "$('#yearFilter').val()"),
    'External report asset retains year filter'
);

$check(
    str_contains($js, 'window.location.href'),
    'External report asset retains filter navigation'
);

$check(
    str_contains(
        $js,
        "$('#farmTypeFilter, #reportMode, #monthFilter, #yearFilter').on('change'"
    ),
    'External report asset retains change handler'
);

$check(
    str_contains($js, "$('#monthFilter').toggle(mode === 'monthly')") &&
    str_contains($js, "$('#yearFilter').toggle(mode === 'yearly')"),
    'External report asset retains monthly/yearly control toggles'
);

$check(
    !str_contains($js, '<?php') &&
    !str_contains($js, '<?='),
    'External report asset contains no PHP'
);

$active = preg_replace('/<!--.*?-->/s', '', $page);
$inlineCount = 0;

if (preg_match_all(
    '/<script\b([^>]*)>(.*?)<\/script\s*>/is',
    $active,
    $matches,
    PREG_SET_ORDER
)) {
    foreach ($matches as $match) {
        $attrs = $match[1] ?? '';
        $body = trim($match[2] ?? '');

        if (preg_match('/\bsrc\s*=/i', $attrs)) {
            continue;
        }

        if ($body !== '') {
            $inlineCount++;
        }
    }
}

$check(
    $inlineCount === 0,
    'Poultry/Ruminant report contains zero active inline script blocks'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
