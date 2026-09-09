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

$cssPath = $root . '/assets/css/management-workspaces.css';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';

$check(
    $css !== '',
    'Shared management workspace stylesheet exists and is readable'
);

$check(
    !str_contains($css, '<?php')
    && !str_contains($css, '<?='),
    'Management workspace stylesheet contains no PHP'
);

foreach ([
    '.intel-shell',
    '.tenant-view-shell',
    '.inv-shell',
    '.rinv-shell',
    '.workspace-stat',
    '.basis-history-table',
    '.inv-timeline',
    '.rinv-timeline',
    '.intel-category',
    '.tenant-view-readonly',
] as $token) {
    $check(
        str_contains($css, $token),
        'Management workspace stylesheet retains ' . $token
    );
}

$pages = [
    'management/intelligence.php' => 1,
    'management/platform_tenant_view.php' => 0,
    'management/investigation.php' => 0,
    'management/ruminant_investigation.php' => 0,
    'management/poultry_cycle.php' => 0,
];

foreach ($pages as $relative => $expectedStyleAttrs) {
    $page = file_get_contents($root . '/' . $relative);

    $check(
        preg_match_all(
            '/<style\b[^>]*>.*?<\/style\s*>/is',
            $page
        ) === 0,
        "{$relative} contains zero inline style blocks"
    );

    $check(
        substr_count(
            $page,
            "versioned_asset('/assets/css/management-workspaces.css')"
        ) === 1,
        "{$relative} loads shared management stylesheet exactly once"
    );

    $check(
        preg_match_all(
            '/\sstyle\s*=/i',
            $page
        ) === $expectedStyleAttrs,
        "{$relative} preserves expected inline style-attribute count"
    );
}

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
