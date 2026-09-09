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
    $root . '/sign.php'
);

$css = file_get_contents(
    $root . '/assets/css/sign-page.css'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $page
    ) === 0,
    'Sign page contains zero inline style blocks'
);

$check(
    substr_count(
        $page,
        'assets/css/sign-page.css'
    ) === 1,
    'Sign page loads stylesheet exactly once'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Sign stylesheet contains no PHP'
);

$check(
    count(explode("\n", $css)) >= 300,
    'Sign stylesheet retains expected content volume'
);

foreach ([
    ':root',
    '--bg-1:',
    '.auth-shell',
    '.auth-hero',
    '.auth-card',
    '.login-type-toggle',
    '.back-link',
    '@media',
] as $token) {
    $check(
        str_contains($css, $token),
        'Sign stylesheet retains ' . $token
    );
}

$check(
    str_contains(
        $css,
        'Externalized from sign.php for CSP compatibility'
    ),
    'Sign stylesheet records CSP extraction purpose'
);

$check(
    !str_contains($page, '--bg-1:'),
    'Sign root variables no longer remain inline'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
