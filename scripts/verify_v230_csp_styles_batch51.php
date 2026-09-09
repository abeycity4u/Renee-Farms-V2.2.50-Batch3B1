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

$page = file_get_contents(
    $root . '/api/stock_history.php'
);

$css = file_get_contents(
    $root . '/assets/css/stock-history-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Stock History contains zero inline style blocks'
);

$check(
    substr_count(
        $page,
        "versioned_asset('/assets/css/stock-history-page.css')"
    ) === 1,
    'Stock History loads versioned stylesheet exactly once'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Stock History stylesheet contains no PHP'
);

foreach ([
    '.summary-card',
    '.summary-value',
    '.history-table td',
    '.history-table th',
] as $token) {
    $check(
        str_contains($css, $token),
        'Stock History stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $css,
        'Externalized from api/stock_history.php for CSP compatibility'
    ),
    'Stock History stylesheet records CSP extraction purpose'
);

$check(
    preg_match_all(
        '/\sstyle\s*=/i',
        $page
    ) === 1,
    'Existing Stock History style attribute remains for later CSP phase'
);

$check(
    substr_count(
        $page,
        'style="width:auto;"'
    ) === 1,
    'Days filter width style remains untouched'
);

$check(
    !str_contains(
        $page,
        '.summary-card {'
    ),
    'Stock History presentation no longer remains inline'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
