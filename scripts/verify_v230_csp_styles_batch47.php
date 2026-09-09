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
    $root . '/index.php'
);

$css = file_get_contents(
    $root . '/assets/css/home-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Index contains zero inline style blocks'
);

$check(
    substr_count(
        $page,
        'assets/css/home-page.css'
    ) === 1,
    'Index loads home-page stylesheet exactly once'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Home-page stylesheet contains no PHP'
);

$check(
    count(explode("\n", $css)) >= 340,
    'Home-page stylesheet retains expected content volume'
);

foreach ([
    ':root',
    '--brand:',
    '.hero',
    '.slideshow-frame',
    '.floating-card',
    '.section-grid',
    '@media (max-width: 980px)',
    '@media (max-width: 560px)',
] as $token) {
    $check(
        str_contains($css, $token),
        'Home-page stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $css,
        'Externalized from index.php for CSP compatibility'
    ),
    'Home-page stylesheet records CSP extraction purpose'
);

$check(
    preg_match_all(
        '/\sstyle\s*=/i',
        $page
    ) === 1,
    'Existing single inline style attribute remains for later CSP phase'
);

$check(
    str_contains(
        $page,
        '<p style="margin-top:1.25rem;color:#365446;font-weight:500;">'
    ),
    'Portal guidance style attribute remains unchanged'
);

$check(
    !str_contains($page, '--brand:'),
    'Index brand CSS no longer remains inline'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
