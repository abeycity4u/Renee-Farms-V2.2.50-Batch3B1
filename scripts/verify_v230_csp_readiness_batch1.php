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
$ruminant = file_get_contents($root . '/ruminant/ruminant_daily_record.php');

$check(
    !str_contains($stock, 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css'),
    'Stock history no longer loads duplicate CDN Bootstrap CSS'
);

$check(
    !str_contains($stock, 'https://code.jquery.com/jquery-3.6.0.min.js'),
    'Stock history no longer loads CDN jQuery'
);

$check(
    !str_contains($stock, 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'),
    'Stock history no longer loads CDN Bootstrap JS'
);

$check(
    str_contains($stock, "versioned_asset('/assets/vendor/jquery/jquery.min.js')"),
    'Stock history uses local jQuery vendor asset'
);

$check(
    str_contains($stock, "versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js')"),
    'Stock history uses local Bootstrap vendor asset'
);

$check(
    str_contains(
        $ruminant,
        "versioned_asset('/assets/vendor/jquery/jquery.min.js'); ?>\"></script>"
    ),
    'Ruminant daily closes external jQuery script correctly'
);

$check(
    !str_contains(
        $ruminant,
        "versioned_asset('/assets/vendor/jquery/jquery.min.js'); ?>\">\n    const feedItemSelector"
    ),
    'Ruminant daily does not place application code inside external script tag'
);

$check(
    str_contains($ruminant, "<script>\n    const feedItemSelector"),
    'Ruminant feed selector logic uses its own inline script block'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
