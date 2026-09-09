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
    $root . '/dashboard.php'
);

$css = file_get_contents(
    $root . '/assets/css/dashboard-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Dashboard contains zero inline style blocks'
);

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/css/dashboard-page.css')"
    ),
    'Dashboard loads versioned page stylesheet'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Dashboard stylesheet contains no PHP'
);

$check(
    count(explode("\n", $css)) >= 560,
    'Dashboard stylesheet retains expected content volume'
);

foreach ([
    ':root',
    '--brand-primary:',
    '--brand-primary-soft:',
    '--brand-success:',
    '--brand-danger:',
    '--brand-warning:',
    '--brand-surface:',
    '--brand-muted:',
    '--brand-bg:',
] as $token) {
    $check(
        str_contains($css, $token),
        'Dashboard stylesheet retains ' . $token
    );
}

$check(
    preg_match('/(^|\})\s*body\s*\{/m', $css) === 1,
    'Dashboard stylesheet retains page body styling'
);

$check(
    str_contains($css, 'radial-gradient'),
    'Dashboard stylesheet retains background presentation'
);

$check(
    str_contains($css, '@media'),
    'Dashboard stylesheet retains responsive rules'
);

$check(
    str_contains(
        $css,
        'Externalized from dashboard.php for CSP compatibility'
    ),
    'Dashboard stylesheet records CSP extraction purpose'
);

$check(
    substr_count(
        $page,
        '/assets/css/dashboard-page.css'
    ) === 1,
    'Dashboard stylesheet is referenced exactly once'
);

$check(
    !str_contains($page, '--brand-primary:'),
    'Dashboard brand CSS no longer remains inline'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
