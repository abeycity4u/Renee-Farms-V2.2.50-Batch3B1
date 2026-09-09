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
    $root . '/billing/recover.php'
);

$css = file_get_contents(
    $root . '/assets/css/subscription-recovery-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Recovery page contains zero inline style blocks'
);

$check(
    substr_count(
        $page,
        "versioned_asset('/assets/css/subscription-recovery-page.css')"
    ) === 1,
    'Recovery page loads versioned stylesheet exactly once'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Recovery stylesheet contains no PHP'
);

$check(
    count(explode("\n", $css)) >= 80,
    'Recovery stylesheet retains expected content volume'
);

foreach ([
    ':root',
    '.shell',
    '.card',
    '.status-pill',
    '.notice',
    '.summary',
    '.providers',
    '.provider:has(input:checked)',
    '.secure-note',
    '.foot',
    '@media (max-width:720px)',
    '@media (max-width:500px)',
] as $token) {
    $check(
        str_contains($css, $token),
        'Recovery stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $css,
        'Externalized from billing/recover.php for CSP compatibility'
    ),
    'Recovery stylesheet records CSP extraction purpose'
);

$check(
    preg_match_all(
        '/\sstyle\s*=/i',
        $page
    ) === 0,
    'Recovery page contains no inline style attributes'
);

$check(
    !str_contains(
        $page,
        '.provider:has(input:checked)'
    ),
    'Recovery presentation no longer remains inline'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
