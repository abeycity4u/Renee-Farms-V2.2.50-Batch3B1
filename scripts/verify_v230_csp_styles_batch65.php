<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$contracts = [
    [
        'page' => 'index.php',
        'selector' => '.portal-launch-note',
        'css' => 'assets/css/home-page.css',
        'markup' => 'class="portal-launch-note"',
        'rule' => '.portal-launch-note{margin-top:1.25rem;color:#365446;font-weight:500;}',
    ],
    [
        'page' => 'admin/permissions.php',
        'selector' => '.permission-farm-select',
        'css' => 'assets/css/permissions-page.css',
        'markup' => 'class="form-select permission-farm-select"',
        'rule' => '.permission-farm-select{max-width:420px;}',
    ],
    [
        'page' => 'api/stock_history.php',
        'selector' => '.stock-history-days-filter',
        'css' => 'assets/css/stock-history-page.css',
        'markup' => 'class="form-select form-select-sm stock-history-days-filter"',
        'rule' => '.stock-history-days-filter{width:auto;}',
    ],
    [
        'page' => 'billing/sandbox_checkout.php',
        'selector' => '.sandbox-checkout-warning',
        'css' => 'assets/css/sandbox-checkout-page.css',
        'markup' => 'class="sandbox-checkout-warning"',
        'rule' => '.sandbox-checkout-warning{margin-top:18px;font-size:13px;color:#6c7786;}',
    ],
    [
        'page' => 'management/intelligence.php',
        'selector' => '.intelligence-farm-scope',
        'css' => 'assets/css/management-workspaces.css',
        'markup' => 'class="intelligence-farm-scope"',
        'rule' => '.intelligence-farm-scope{min-width:280px;max-width:420px;flex:1;}',
    ],
];

foreach ($contracts as $contract) {
    $pagePath = $root . '/' . $contract['page'];
    $cssPath = $root . '/' . $contract['css'];

    $page = is_file($pagePath) ? file_get_contents($pagePath) : '';
    $css = is_file($cssPath) ? file_get_contents($cssPath) : '';

    $check($page !== '', "{$contract['page']} exists");
    $check($css !== '', "{$contract['css']} exists");

    $check(
        preg_match_all('/\bstyle\s*=\s*(["\']).*?\1/is', $page) === 0,
        "{$contract['page']} contains zero inline style attributes"
    );

    $check(
        str_contains($page, $contract['markup']),
        "{$contract['page']} uses {$contract['selector']}"
    );

    $check(
        substr_count($css, $contract['selector']) === 1,
        "{$contract['css']} contains {$contract['selector']} once"
    );

    $check(
        str_contains($css, $contract['rule']),
        "{$contract['css']} preserves extracted style contract"
    );
}

$totalAttrs = 0;
$filesWithAttrs = 0;

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
    if (str_starts_with($relative, 'scripts/')) continue;
    if ($relative === 'includes/pdf/PdfReportService.php') continue;
    if (str_contains($relative, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;

    $source = file_get_contents($path);

    $count = preg_match_all(
        '/\bstyle\s*=\s*(["\']).*?\1/is',
        $source
    );

    if ($count > 0) {
        $filesWithAttrs++;
        $totalAttrs += $count;
    }
}

$check(
    $filesWithAttrs === 24,
    'Expected browser style-attribute file count is 24'
);

$check(
    $totalAttrs === 81,
    'Expected browser style-attribute count is 81'
);

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
