<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$expectedCounts = [
    'dashboard.php' => 2,
    'management/sales_records.php' => 14,
    'management/poultry_ruminant_report.php' => 2,
    'management/expenses.php' => 8,
];

foreach ($expectedCounts as $relative => $expected) {
    $path = $root . '/' . $relative;
    $source = is_file($path) ? file_get_contents($path) : '';

    $check($source !== '', "{$relative} exists");

    $count = preg_match_all(
        '/\bstyle\s*=\s*(["\']).*?\1/is',
        $source
    );

    $check(
        $count === $expected,
        "{$relative} expected remaining style count is {$expected}"
    );
}

$cssPath = $root . '/assets/css/style.css';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';

$check($css !== '', 'assets/css/style.css exists');

foreach ([
    '.app-z-1{z-index:1;}',
    '.app-scroll-max-340{max-height:340px;overflow-y:auto;}',
    '.app-progress-h-8{height:8px;}',
    '.app-width-150{width:150px;}',
    '.app-width-190{width:190px;}',
    '.app-width-140{width:140px;}',
] as $rule) {
    $check(
        substr_count($css, $rule) === 1,
        "Shared CSS preserves {$rule}"
    );
}

$contracts = [
    ['dashboard.php', 'app-z-1'],
    ['dashboard.php', 'app-scroll-max-340'],
    ['dashboard.php', 'progress app-progress-h-8'],
    ['management/sales_records.php', 'app-width-150'],
    ['management/sales_records.php', 'app-width-190'],
    ['management/sales_records.php', 'app-width-140'],
    ['management/poultry_ruminant_report.php', 'app-width-150'],
    ['management/poultry_ruminant_report.php', 'app-width-140'],
    ['management/expenses.php', 'app-width-150'],
    ['management/expenses.php', 'app-width-190'],
    ['management/expenses.php', 'app-width-140'],
];

foreach ($contracts as [$relative, $token]) {
    $source = file_get_contents($root . '/' . $relative);

    $check(
        str_contains($source, $token),
        "{$relative} preserves {$token}"
    );
}

$dynamicContracts = [
    'dashboard.php' => [
        'style="width: <?php echo (int) min(100, round($stockPercent)); ?>%"',
        'style="width: <?php echo (int) round($progress); ?>%"',
    ],
    'management/sales_records.php' => [
        "style=\"width: 170px; <?php echo \$reportMode === 'yearly' ? 'display:none;' : ''; ?>\"",
        "style=\"width: 130px; <?php echo \$reportMode === 'monthly' ? 'display:none;' : ''; ?>\"",
    ],
    'management/poultry_ruminant_report.php' => [
        "style=\"width:170px;<?php echo \$reportMode === 'yearly' ? 'display:none;' : ''; ?>\"",
        "style=\"width:130px;<?php echo \$reportMode === 'monthly' ? 'display:none;' : ''; ?>\"",
    ],
    'management/expenses.php' => [
        "width: 170px;",
        "style=\"width: 130px; <?php echo \$reportMode === 'monthly' ? 'display:none;' : ''; ?>\"",
        'style="width: <?php echo $percentage; ?>%"',
    ],
];

foreach ($dynamicContracts as $relative => $tokens) {
    $source = file_get_contents($root . '/' . $relative);

    foreach ($tokens as $token) {
        $check(
            str_contains($source, $token),
            "{$relative} preserves dynamic style contract"
        );
    }
}

$excluded = [
    'includes/pdf/PdfReportService.php',
    'management/debt_history_pdf.php',
    'management/expense_report_pdf.php',
    'management/sales_report_pdf.php',
    'includes/production_cycle_view_permissions.php',
    'includes/dashboard_action_permissions.php',
    'includes/legacy_authorization_closure.php',
];

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
    if (in_array($relative, $excluded, true)) continue;
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
    $filesWithAttrs === 9,
    'Expected true browser style-attribute file count is 9'
);

$check(
    $totalAttrs === 36,
    'Expected true browser style-attribute count is 36'
);

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
