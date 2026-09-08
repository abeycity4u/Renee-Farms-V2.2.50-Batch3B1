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

$page = file_get_contents($root . '/management/production_cycles.php');
$js = file_get_contents($root . '/assets/js/production-cycles.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/production-cycles.js')"
    ),
    'Production Cycles loads external versioned behavior asset'
);

$check(
    substr_count($page, 'production-cycles.js') === 1,
    'Production Cycles loads external behavior asset exactly once'
);

$check(
    str_contains($js, "document.addEventListener('DOMContentLoaded'"),
    'External asset retains DOMContentLoaded behavior'
);

$check(
    str_contains($js, "document.getElementById('acquisitionCycle')"),
    'External asset retains acquisition cycle behavior'
);

$check(
    str_contains($js, "document.getElementById('acquisitionType')"),
    'External asset retains acquisition type behavior'
);

$check(
    str_contains($js, 'input[name="acquisition_quantity"]'),
    'External asset retains acquisition quantity behavior'
);

$check(
    str_contains($js, "document.getElementById('acquisitionUnitPrice')"),
    'External asset retains acquisition unit-price behavior'
);

$check(
    str_contains($js, "document.getElementById('acquisitionTotalCost')"),
    'External asset retains acquisition total-cost behavior'
);

$check(
    !str_contains($js, '<?php') &&
    !str_contains($js, '<?='),
    'External Production Cycles asset contains no PHP'
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
    'Production Cycles contains zero active inline script blocks'
);

$check(
    substr_count($js, '(function () {') >= 2,
    'External asset retains both acquisition IIFEs'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
