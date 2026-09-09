<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$headPath = $root . '/navbar_head.php';
$endpointPath = $root . '/tenant_theme.css.php';

$head = is_file($headPath) ? file_get_contents($headPath) : '';
$endpoint = is_file($endpointPath) ? file_get_contents($endpointPath) : '';

$check($head !== '', 'navbar_head.php exists');
$check($endpoint !== '', 'tenant theme endpoint exists');

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $head
    ) === 0,
    'navbar_head.php contains zero inline style blocks'
);

$check(
    substr_count($head, '/tenant_theme.css.php') === 1,
    'navbar_head.php loads tenant theme stylesheet endpoint once'
);

$check(
    !str_contains($head, '$tenantPrimaryColor'),
    'navbar_head.php no longer renders tenant color inline'
);

$check(
    str_contains(
        $endpoint,
        "require_once __DIR__ . '/init.php';"
    ),
    'Tenant theme endpoint uses application initialization'
);

$check(
    str_contains(
        $endpoint,
        "header('Content-Type: text/css; charset=UTF-8');"
    ),
    'Tenant theme endpoint serves CSS content type'
);

$check(
    str_contains(
        $endpoint,
        "header('Cache-Control: private, no-store, max-age=0');"
    ),
    'Tenant theme endpoint prevents cross-tenant caching'
);

$check(
    str_contains(
        $endpoint,
        "header('X-Content-Type-Options: nosniff');"
    ),
    'Tenant theme endpoint sends nosniff'
);

$check(
    str_contains(
        $endpoint,
        "currentFarm()['primary_color'] ?? '#198754'"
    ),
    'Tenant theme endpoint reads current farm primary color'
);

$check(
    str_contains(
        $endpoint,
        "preg_match('/^#[0-9a-fA-F]{6}$/', \$tenantPrimaryColor)"
    ),
    'Tenant theme endpoint validates six-digit hex colors'
);

$check(
    substr_count($endpoint, "'#198754'") >= 2,
    'Tenant theme endpoint preserves safe default color'
);

foreach ([
    '--farm-primary:',
    '--bs-primary:var(--farm-primary);',
    '--bs-link-color:var(--farm-primary);',
    '--bs-link-hover-color:var(--farm-primary);',
    '--bs-btn-bg:var(--farm-primary);',
    '--bs-btn-border-color:var(--farm-primary);',
    '--bs-btn-hover-bg:var(--farm-primary);',
    '--bs-btn-hover-border-color:var(--farm-primary);',
    '--bs-btn-active-bg:var(--farm-primary);',
    '--bs-btn-active-border-color:var(--farm-primary);',
    '.text-primary{color:var(--farm-primary)!important;}',
] as $token) {
    $check(
        str_contains($endpoint, $token),
        "Tenant theme preserves {$token}"
    );
}

$browserInlineStyles = [];

foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $root,
        FilesystemIterator::SKIP_DOTS
    )
) as $file) {
    if (!$file->isFile()) continue;

    $path = $file->getPathname();
    $relative = str_replace(
        $root . DIRECTORY_SEPARATOR,
        '',
        $path
    );

    if (!str_ends_with($relative, '.php')) continue;
    if (str_contains($relative, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) continue;
    if (str_contains($relative, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
    if ($relative === 'includes/pdf/PdfReportService.php') continue;

    $source = file_get_contents($path);

    $count = preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $source
    );

    if ($count > 0) {
        $browserInlineStyles[$relative] = $count;
    }
}

$check(
    $browserInlineStyles === [],
    'Application contains zero browser inline style blocks'
);

if ($browserInlineStyles !== []) {
    foreach ($browserInlineStyles as $relative => $count) {
        echo "[INFO] {$relative}: {$count} inline style block(s)" . PHP_EOL;
    }
}

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
