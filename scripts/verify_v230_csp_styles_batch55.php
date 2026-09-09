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
    $root . '/management/users.php'
);

$css = file_get_contents(
    $root . '/assets/css/users-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Users page contains zero inline style blocks'
);

$check(
    substr_count(
        $page,
        "versioned_asset('/assets/css/users-page.css')"
    ) === 1,
    'Users page loads versioned stylesheet exactly once'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Users stylesheet contains no PHP'
);

$check(
    count(explode("\n", $css)) >= 60,
    'Users stylesheet retains expected content volume'
);

foreach ([
    '.user-management-shell',
    '.hero-card',
    '.metric-card',
    '.metric-card:hover',
    '.users-table-wrap',
    '.table thead th',
    '.table tbody td',
    '.user-avatar',
] as $token) {
    $check(
        str_contains($css, $token),
        'Users stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $css,
        'Externalized from management/users.php for CSP compatibility'
    ),
    'Users stylesheet records CSP extraction purpose'
);

$check(
    preg_match_all(
        '/\sstyle\s*=/i',
        $page
    ) === 2,
    'Existing users style attributes remain for later CSP phase'
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
        '.user-management-shell {'
    ),
    'Users presentation no longer remains inline'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
