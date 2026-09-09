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
    $root . '/billing/account.php'
);

$css = file_get_contents(
    $root . '/assets/css/billing-account-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Billing account contains zero inline style blocks'
);

$check(
    substr_count(
        $page,
        "versioned_asset('/assets/css/billing-account-page.css')"
    ) === 1,
    'Billing account loads versioned stylesheet exactly once'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Billing account stylesheet contains no PHP'
);

foreach ([
    '.billing-shell',
    '.billing-hero',
    '.billing-card',
    '.metric-label',
    '.metric-value',
    '.provider-option',
    '.provider-primary',
    '.seat-meter',
    '.billing-note',
    '.empty-state',
    '.billing-email-box',
] as $token) {
    $check(
        str_contains($css, $token),
        'Billing account stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $css,
        'Externalized from billing/account.php for CSP compatibility'
    ),
    'Billing account stylesheet records CSP extraction purpose'
);

$check(
    preg_match_all(
        '/\sstyle\s*=/i',
        $page
    ) === 0,
    'Billing account contains no inline style attributes'
);

$check(
    !str_contains(
        $page,
        '.billing-shell{'
    ),
    'Billing account presentation no longer remains inline'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
