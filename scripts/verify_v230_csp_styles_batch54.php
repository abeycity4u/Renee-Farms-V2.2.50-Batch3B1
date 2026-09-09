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
    $root . '/billing/sandbox_checkout.php'
);

$css = file_get_contents(
    $root . '/assets/css/sandbox-checkout-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Sandbox checkout contains zero inline style blocks'
);

$check(
    substr_count(
        $page,
        "versioned_asset('/assets/css/sandbox-checkout-page.css')"
    ) === 1,
    'Sandbox checkout loads versioned stylesheet exactly once'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Sandbox checkout stylesheet contains no PHP'
);

foreach ([
    'body {',
    '.card',
    '.badge',
    '.grid',
    '.item',
    '.label',
    '.warning',
    'button',
    'a {',
    '@media (max-width:600px)',
] as $token) {
    $check(
        str_contains($css, $token),
        'Sandbox checkout stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $css,
        'Externalized from billing/sandbox_checkout.php for CSP compatibility'
    ),
    'Sandbox checkout stylesheet records CSP extraction purpose'
);

$check(
    preg_match_all(
        '/\sstyle\s*=/i',
        $page
    ) === 1,
    'Existing sandbox style attribute remains for later CSP phase'
);

$check(
    substr_count(
        $page,
        'style="margin-top:18px;font-size:13px;color:#6c7786;"'
    ) === 1,
    'Final sandbox warning style remains untouched'
);

$check(
    !str_contains(
        $page,
        '@media (max-width:600px)'
    ),
    'Sandbox checkout presentation no longer remains inline'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
