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
    $root . '/inventory.php'
);

$css = file_get_contents(
    $root . '/assets/css/inventory-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Inventory contains zero inline style blocks'
);

$check(
    substr_count(
        $page,
        "versioned_asset('/assets/css/inventory-page.css')"
    ) === 1,
    'Inventory loads versioned page stylesheet exactly once'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Inventory stylesheet contains no PHP'
);

$check(
    count(explode("\n", $css)) >= 170,
    'Inventory stylesheet retains expected content volume'
);

foreach ([
    '.inventory-shell',
    '.inventory-command-card',
    '.inventory-score-ring',
    '.inventory-priority-list',
    '.inventory-table-card',
    '.inventory-table',
    '.inventory-action-group',
    '@media (min-width: 768px)',
    '@media (max-width: 767.98px)',
] as $token) {
    $check(
        str_contains($css, $token),
        'Inventory stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $css,
        'Externalized from inventory.php for CSP compatibility'
    ),
    'Inventory stylesheet records CSP extraction purpose'
);

$check(
    preg_match_all(
        '/\sstyle\s*=/i',
        $page
    ) === 6,
    'Existing inventory style attributes remain for later CSP phase'
);

$check(
    substr_count(
        $page,
        'style="--score:'
    ) === 1,
    'Dynamic inventory health score style remains untouched'
);

$check(
    str_contains(
        $css,
        'conic-gradient(var(--score-color) calc(var(--score) * 1%)'
    ),
    'Inventory score ring still consumes dynamic score variable'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
