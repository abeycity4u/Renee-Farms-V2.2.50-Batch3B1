<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$pages = [
    'ruminant/ruminant_daily_record.php',
    'ruminant/ruminant_expenses.php',
    'poultry/layers_daily_record.php',
    'poultry/broiler_daily_record.php',
    'poultry/broiler_expenses.php',
    'poultry/health.php',
    'management/farms.php',
    'management/users.php',
];

foreach ($pages as $relative) {
    $path = $root . '/' . $relative;
    $source = is_file($path) ? file_get_contents($path) : '';

    $check($source !== '', "{$relative} exists");

    $check(
        preg_match_all(
            '/\bstyle\s*=\s*(["\']).*?\1/is',
            $source
        ) === 0,
        "{$relative} contains zero inline style attributes"
    );
}

$cssPath = $root . '/assets/css/style.css';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';

$check($css !== '', 'assets/css/style.css exists');

foreach ([
    '.app-month-selector{width:200px;}',
    '.app-purple-card{background-color:#7c4dff;}',
    '.app-content-max-1500{max-width:1500px;}',
    '.app-cell-max-300{max-width:300px;}',
    '.app-farm-logo-preview{max-height:72px;}',
    '.app-select-max-420{max-width:420px;}',
] as $rule) {
    $check(
        substr_count($css, $rule) === 1,
        "Shared CSS contains {$rule} once"
    );
}

$contracts = [
    [
        'file' => 'ruminant/ruminant_daily_record.php',
        'token' => 'class="form-control js-calendar-input app-month-selector"',
    ],
    [
        'file' => 'ruminant/ruminant_expenses.php',
        'token' => 'class="form-control js-calendar-input app-month-selector"',
    ],
    [
        'file' => 'poultry/layers_daily_record.php',
        'token' => 'class="form-control js-calendar-input app-month-selector"',
    ],
    [
        'file' => 'poultry/broiler_daily_record.php',
        'token' => 'class="form-control js-calendar-input app-month-selector"',
    ],
    [
        'file' => 'poultry/broiler_expenses.php',
        'token' => 'class="form-control js-calendar-input app-month-selector"',
    ],
    [
        'file' => 'poultry/layers_daily_record.php',
        'token' => 'class="card text-white app-purple-card"',
    ],
    [
        'file' => 'poultry/broiler_daily_record.php',
        'token' => 'class="card text-white app-purple-card"',
    ],
    [
        'file' => 'poultry/health.php',
        'token' => 'app-content-max-1500',
    ],
    [
        'file' => 'poultry/health.php',
        'token' => 'app-cell-max-300',
    ],
    [
        'file' => 'management/farms.php',
        'token' => 'app-farm-logo-preview',
    ],
    [
        'file' => 'management/users.php',
        'token' => 'app-select-max-420',
    ],
    [
        'file' => 'management/users.php',
        'token' => 'class="d-inline" data-confirm=',
    ],
];

foreach ($contracts as $contract) {
    $source = file_get_contents(
        $root . '/' . $contract['file']
    );

    $check(
        str_contains($source, $contract['token']),
        "{$contract['file']} preserves {$contract['token']}"
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
    $filesWithAttrs === 16,
    'Expected browser style-attribute file count is 16'
);

$check(
    $totalAttrs === 69,
    'Expected browser style-attribute count is 69'
);

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
