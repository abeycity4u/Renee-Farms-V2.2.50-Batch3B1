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

$stock = file_get_contents($root . '/api/stock_history.php');

$feedFiles = [
    'poultry/layer_feeds.php',
    'poultry/broiler_feeds.php',
    'ruminant/ruminant_feeds_record.php',
];

$check(
    !str_contains(
        $stock,
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css'
    ),
    'Stock history no longer loads duplicate Bootstrap Icons CDN CSS'
);

foreach ($feedFiles as $relative) {
    $content = file_get_contents($root . '/' . $relative);

    $check(
        !str_contains(
            $content,
            'https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css'
        ),
        $relative . ' no longer loads DataTables Bootstrap CSS from CDN'
    );

    $check(
        str_contains(
            $content,
            "versioned_asset('/assets/vendor/datatables/css/dataTables.bootstrap5.min.css')"
        ),
        $relative . ' uses local DataTables Bootstrap CSS'
    );
}

$remainingExpected = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    'https://fonts.googleapis.com',
    'https://fonts.gstatic.com',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
    'https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css',
    'https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js',
];

$all = '';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();

    if (str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $all .= file_get_contents($path) . "\n";
}

foreach ($remainingExpected as $url) {
    $check(
        str_contains($all, $url),
        'Expected deferred external dependency still present: ' . $url
    );
}

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
