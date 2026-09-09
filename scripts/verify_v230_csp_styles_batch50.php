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
    $root . '/admin/permissions.php'
);

$css = file_get_contents(
    $root . '/assets/css/permissions-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Permissions page contains zero inline style blocks'
);

$check(
    substr_count(
        $page,
        "versioned_asset('/assets/css/permissions-page.css')"
    ) === 1,
    'Permissions page loads versioned stylesheet exactly once'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Permissions stylesheet contains no PHP'
);

$check(
    count(explode("\n", $css)) >= 100,
    'Permissions stylesheet retains expected content volume'
);

foreach ([
    '.permissions-shell',
    '.permissions-card',
    '.permission-group-title',
    '.permission-module',
    '.permission-check',
    '.permission-not-applicable',
    '.sticky-actions',
    '.security-note',
    'html[data-theme="dark"]',
    '@media (max-width: 768px)',
] as $token) {
    $check(
        str_contains($css, $token),
        'Permissions stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $css,
        'Externalized from admin/permissions.php for CSP compatibility'
    ),
    'Permissions stylesheet records CSP extraction purpose'
);

$check(
    preg_match_all(
        '/\sstyle\s*=/i',
        $page
    ) === 1,
    'Existing permissions style attribute remains for later CSP phase'
);

$check(
    substr_count(
        $page,
        'style="max-width:420px"'
    ) === 1,
    'Farm selector width style remains untouched'
);

$check(
    !str_contains(
        $page,
        '.permissions-shell {'
    ),
    'Permissions presentation no longer remains inline'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
