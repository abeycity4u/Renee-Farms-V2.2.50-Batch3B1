<?php

$root = dirname(__DIR__);

$files = [
    'layer' =>
        $root . '/poultry/layers_daily_record.php',

    'broiler' =>
        $root . '/poultry/broiler_daily_record.php',

    'ruminant' =>
        $root . '/ruminant/ruminant_daily_record.php',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            "Missing Daily Record source: {$name}\n"
        );
        exit(1);
    }
}

$layer =
    file_get_contents($files['layer']);

$broiler =
    file_get_contents($files['broiler']);

$ruminant =
    file_get_contents($files['ruminant']);

$checks = [];

$checks['LAYER_FEED_PRECISION'] =
    strpos(
        $layer,
        'id="feedConsumption" step="0.01"'
    ) !== false;

$checks['BROILER_FEED_PRECISION'] =
    strpos(
        $broiler,
        'id="feedConsumption" step="0.01"'
    ) !== false;

$checks['RUMINANT_FEED_PRECISION'] =
    strpos(
        $ruminant,
        'id="feedConsumption" step="0.01"'
    ) !== false;

$checks['LAYER_LAYING_RATE_PRECISION'] =
    strpos(
        $layer,
        'id="layingRate" step="0.01"'
    ) !== false;

$checks['LAYER_LAYING_RATE_READONLY'] =
    preg_match(
        '/id="layingRate"[^>]*\breadonly\b[^>]*>/',
        $layer
    ) === 1;

$checks['LAYER_LAYING_RATE_SERVER_DERIVED'] =
    strpos(
        $layer,
        'round(($eggProduction / $openingStock) * 100, 2)'
    ) !== false;

$checks['LAYER_CRATES_SERVER_DERIVED'] =
    strpos(
        $layer,
        'round($eggProduction / 30, 2)'
    ) !== false;

$checks['LAYER_POSTED_RATE_NOT_AUTHORITY'] =
    strpos(
        $layer,
        "\$_POST['laying_rate']"
    ) === false;

$checks['RUMINANT_BACKEND_ACCEPTS_DECIMAL'] =
    strpos(
        $ruminant,
        "\$feedConsumption = \$nonNegative("
    ) !== false;

$layerJsPath =
    $root . '/assets/js/layers-daily-record.js';

$layerJs =
    is_file($layerJsPath)
        ? file_get_contents($layerJsPath)
        : '';

$checks['LAYER_BROWSER_RATE_TWO_DECIMAL'] =
    strpos(
        $layerJs,
        "layingRate.toFixed(2)"
    ) !== false;

$checks['RUMINANT_CALENDAR_FEED_TWO_DECIMAL'] =
    strpos(
        $ruminant,
        'number_format($dayFeedConsumption, 2)'
    ) !== false;

$checks['LAYER_CALENDAR_FEED_TWO_DECIMAL'] =
    strpos(
        $layer,
        'number_format($dayFeedConsumption, 2)'
    ) !== false;

$checks['BROILER_CALENDAR_FEED_TWO_DECIMAL'] =
    strpos(
        $broiler,
        'number_format($dayFeedConsumption, 2)'
    ) !== false;

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

echo 'RESULT=' .
    ($failed ? 'FAIL' : 'PASS') .
    PHP_EOL;

echo 'CHECK_COUNT=' .
    count($checks) .
    PHP_EOL;

foreach ($checks as $name => $passed) {
    echo $name .
        '=' .
        ($passed ? 'PASS' : 'FAIL') .
        PHP_EOL;
}

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITES=NONE\n";

if ($failed) {
    echo 'FAILED=' .
        implode(',', $failed) .
        PHP_EOL;

    exit(1);
}

exit(0);
