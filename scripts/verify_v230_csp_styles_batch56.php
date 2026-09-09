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

$cssPath = $root . '/assets/css/error-page.css';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';

$check(
    $css !== '',
    'Shared error-page stylesheet exists and is readable'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Shared error-page stylesheet contains no PHP'
);

foreach ([
    '* { box-sizing: border-box; }',
    'html, body',
    'body {',
    '.card',
    '.brand',
    '.code',
    'h1',
    'p {',
    'a {',
    'a:focus-visible',
] as $token) {
    $check(
        str_contains($css, $token),
        'Shared error stylesheet retains ' . $token
    );
}

foreach ([
    '403' => [
        'status' => "http_response_code(403);",
        'title' => '<title>Access denied | Renee Farms</title>',
        'heading' => '<h1>Access denied</h1>',
        'code' => '>403</p>',
    ],
    '404' => [
        'status' => "http_response_code(404);",
        'title' => '<title>Page not found | Renee Farms</title>',
        'heading' => '<h1>Page not found</h1>',
        'code' => '>404</p>',
    ],
] as $name => $expected) {
    $path = $root . '/errors/' . $name . '.php';
    $page = is_file($path) ? file_get_contents($path) : '';

    $check(
        $page !== '',
        "Error {$name} page exists and is readable"
    );

    $check(
        preg_match_all(
            '/<style\b[^>]*>.*?<\/style\s*>/is',
            $page
        ) === 0,
        "Error {$name} contains zero inline style blocks"
    );

    $check(
        substr_count(
            $page,
            '/assets/css/error-page.css'
        ) === 1,
        "Error {$name} loads shared error stylesheet exactly once"
    );

    $check(
        preg_match_all(
            '/\sstyle\s*=/i',
            $page
        ) === 0,
        "Error {$name} contains zero inline style attributes"
    );

    $check(
        str_contains($page, $expected['status']),
        "Error {$name} preserves HTTP status contract"
    );

    $check(
        str_contains($page, $expected['title']),
        "Error {$name} preserves page title"
    );

    $check(
        str_contains($page, $expected['heading']),
        "Error {$name} preserves visible heading"
    );

    $check(
        str_contains($page, $expected['code']),
        "Error {$name} preserves visible status code"
    );

    $check(
        str_contains(
            $page,
            '<a href="/">Return to homepage</a>'
        ),
        "Error {$name} preserves homepage recovery action"
    );
}

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
