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

$cssPath = $root . '/assets/css/feeds-ledger.css';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';

$check(
    $css !== '',
    'Shared feed-ledger stylesheet exists and is readable'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Shared feed-ledger stylesheet contains no PHP'
);

foreach ([
    '.feeds-ledger-page #feedsTable',
    '.feeds-ledger-page #feedsTable thead th',
    '.feeds-ledger-page #feedsTable tbody td',
    '.feeds-ledger-page #feedsTable_wrapper',
    '.feeds-ledger-light-filter #feedsTable_wrapper .dataTables_filter label',
    '.feeds-ledger-stock-hover .stock-card',
    '.feeds-ledger-stock-hover .stock-card:hover',
    'min-width: 1320px;',
    'background-color: #198754;',
    'color: #ffffff;',
    'transition: transform 0.2s;',
    'transform: translateY(-5px);',
] as $token) {
    $check(
        str_contains($css, $token),
        'Feed-ledger stylesheet retains ' . $token
    );
}

$pages = [
    'poultry/layer_feeds.php' => [
        'body' => '<body class="poultry-page feeds-ledger-page feeds-ledger-light-filter">',
        'light_filter' => true,
        'stock_hover' => false,
    ],
    'poultry/broiler_feeds.php' => [
        'body' => '<body class="poultry-page feeds-ledger-page feeds-ledger-stock-hover">',
        'light_filter' => false,
        'stock_hover' => true,
    ],
    'ruminant/ruminant_feeds_record.php' => [
        'body' => '<body class="ruminant-page feeds-ledger-page feeds-ledger-light-filter feeds-ledger-stock-hover">',
        'light_filter' => true,
        'stock_hover' => true,
    ],
];

foreach ($pages as $relative => $expected) {
    $page = file_get_contents($root . '/' . $relative);

    $check(
        preg_match_all(
            '/<style\b[^>]*>.*?<\/style\s*>/is',
            $page
        ) === 0,
        "{$relative} contains zero inline style blocks"
    );

    $check(
        substr_count(
            $page,
            "versioned_asset('/assets/css/feeds-ledger.css')"
        ) === 1,
        "{$relative} loads shared feed-ledger stylesheet exactly once"
    );

    $check(
        str_contains($page, $expected['body']),
        "{$relative} retains expected scoped body classes"
    );

    $check(
        str_contains($expected['body'], 'feeds-ledger-light-filter')
            === $expected['light_filter'],
        "{$relative} light-filter scope matches previous behavior"
    );

    $check(
        str_contains($expected['body'], 'feeds-ledger-stock-hover')
            === $expected['stock_hover'],
        "{$relative} stock-hover scope matches previous behavior"
    );
}

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
