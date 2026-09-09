<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$files = [
    'ruminant/ruminant_feeds_record.php',
    'poultry/layer_feeds.php',
    'poultry/broiler_feeds.php',
    'poultry/layer_expenses.php',
    'management/reports.php',
];

foreach ($files as $relative) {
    $path = $root . '/' . $relative;
    $source = is_file($path) ? file_get_contents($path) : '';

    $check($source !== '', "{$relative} exists");
}

$expectedStyleCounts = [
    'ruminant/ruminant_feeds_record.php' => 1,
    'poultry/layer_feeds.php' => 1,
    'poultry/broiler_feeds.php' => 1,
    'poultry/layer_expenses.php' => 1,
    'management/reports.php' => 0,
];

foreach ($expectedStyleCounts as $relative => $expected) {
    $source = file_get_contents($root . '/' . $relative);

    $count = preg_match_all(
        '/\bstyle\s*=\s*(["\']).*?\1/is',
        $source
    );

    $check(
        $count === $expected,
        "{$relative} expected inline style count is {$expected}"
    );
}

$css = file_get_contents($root . '/assets/css/style.css');

foreach ([
    '.app-month-selector{width:200px;}',
    '.app-progress-h-8{height:8px;}',
    '.app-progress-h-5{height:5px;}',
    '.app-width-150{width:150px;}',
    '.app-width-200{width:200px;}',
] as $rule) {
    $check(
        substr_count($css, $rule) === 1,
        "Shared CSS preserves {$rule}"
    );
}

$contracts = [
    [
        'file' => 'ruminant/ruminant_feeds_record.php',
        'token' => 'app-month-selector',
    ],
    [
        'file' => 'poultry/layer_feeds.php',
        'token' => 'app-month-selector',
    ],
    [
        'file' => 'poultry/broiler_feeds.php',
        'token' => 'app-month-selector',
    ],
    [
        'file' => 'poultry/layer_expenses.php',
        'token' => 'app-month-selector',
    ],
    [
        'file' => 'ruminant/ruminant_feeds_record.php',
        'token' => 'progress app-progress-h-8',
    ],
    [
        'file' => 'poultry/layer_feeds.php',
        'token' => 'progress app-progress-h-8',
    ],
    [
        'file' => 'poultry/broiler_feeds.php',
        'token' => 'progress app-progress-h-8',
    ],
    [
        'file' => 'poultry/layer_expenses.php',
        'token' => 'progress app-progress-h-5',
    ],
    [
        'file' => 'management/reports.php',
        'token' => 'form-select app-width-150',
    ],
    [
        'file' => 'management/reports.php',
        'token' => 'form-select app-width-200',
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

$dynamicContracts = [
    'ruminant/ruminant_feeds_record.php'
        => 'style="width: <?php echo min($stockPercent, 100); ?>%"',
    'poultry/layer_feeds.php'
        => 'style="width: <?php echo min($stockPercent, 100); ?>%"',
    'poultry/broiler_feeds.php'
        => 'style="width: <?php echo min($stockPercent, 100); ?>%"',
    'poultry/layer_expenses.php'
        => 'style="width: <?php echo $percentage; ?>%"',
];

foreach ($dynamicContracts as $relative => $token) {
    $source = file_get_contents($root . '/' . $relative);

    $check(
        substr_count($source, $token) === 1,
        "{$relative} preserves dynamic width contract"
    );
}

$totalAttrs = 0;
$filesWithAttrs = 0;

$excludedPdfPages = [
    'management/debt_history_pdf.php',
    'management/expense_report_pdf.php',
    'management/sales_report_pdf.php',
];

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
    if (in_array($relative, $excludedPdfPages, true)) continue;
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
    $filesWithAttrs === 12,
    'Expected browser style-attribute file count is 12'
);

$check(
    $totalAttrs === 53,
    'Expected browser style-attribute count is 53'
);

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
