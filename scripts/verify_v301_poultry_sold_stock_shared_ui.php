<?php

declare(strict_types=1);

/**
 * V3.0.1 — Shared Poultry Daily Record Sold Stock UI verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);
$paths = [
    'shared' => $root . '/assets/js/daily-population-movement-ui.js',
    'layer_js' => $root . '/assets/js/layers-daily-record.js',
    'broiler_js' => $root . '/assets/js/broiler-daily-record.js',
    'layer_page' => $root . '/poultry/layers_daily_record.php',
    'broiler_page' => $root . '/poultry/broiler_daily_record.php',
];

$sources = [];
foreach ($paths as $key => $path) {
    $sources[$key] = is_file($path) ? (string)file_get_contents($path) : '';
}

$checks = 0;
$failures = 0;
$check = static function (string $label, bool $ok) use (&$checks, &$failures): void {
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$check(
    'Shared poultry Sold Stock sources are readable',
    !in_array('', $sources, true)
);

$check(
    'One shared browser helper owns Sold Stock movement rendering',
    substr_count($sources['shared'], 'function createSoldStockDisplay(') === 1
    && str_contains($sources['shared'], 'payload?.movement_totals?.sale')
);

$check(
    'Shared helper uses canonical previous-stock API and cycle/date parameters',
    str_contains($sources['shared'], '../api/get_previous_stock.php')
    && str_contains($sources['shared'], 'cycle_id: String(cycleId)')
    && str_contains($sources['shared'], 'date: selectedDate')
);

$check(
    'Shared helper displays exact Sold Stock label and hides zero-sale dates',
    str_contains($sources['shared'], 'Sold Stock:')
    && str_contains($sources['shared'], 'soldStock <= 0')
    && str_contains($sources['shared'], "container.classList.add('d-none')")
);

foreach (['layer', 'broiler'] as $type) {
    $page = $sources[$type . '_page'];
    $js = $sources[$type . '_js'];

    $check(
        ucfirst($type) . ' modal exposes one read-only hidden Sold Stock surface',
        substr_count($page, 'id="soldStockContainer"') === 1
        && substr_count($page, 'id="soldStockDisplay"') === 1
        && !str_contains($page, 'name="sold_stock"')
        && str_contains($page, 'fw-semibold py-2 px-0')
    );

    $check(
        ucfirst($type) . ' loads shared movement UI before page-specific JS',
        strpos($page, '/assets/js/daily-population-movement-ui.js')
            < strpos(
                $page,
                '/assets/js/' . (
                    $type === 'layer'
                        ? 'layers-daily-record.js'
                        : 'broiler-daily-record.js'
                )
            )
    );

    $check(
        ucfirst($type) . ' creates Sold Stock display from shared helper',
        str_contains($js, 'DailyPopulationMovementUI.createSoldStockDisplay(')
        && str_contains($js, "type: '" . $type . "'")
    );

    $check(
        ucfirst($type) . ' reuses canonical opening payload for new-record Sold Stock',
        str_contains($js, 'soldStockUI.render(payload);')
    );

    $check(
        ucfirst($type) . ' fetches Sold Stock for existing record dates',
        str_contains($js, 'soldStockUI.fetchForDate(date);')
        && str_contains($js, 'soldStockUI.fetchForDate(recordDate);')
    );

    $check(
        ucfirst($type) . ' clears stale Sold Stock during form reset',
        str_contains($js, 'soldStockUI.clear();')
    );

    $check(
        ucfirst($type) . ' has no private duplicate Sold Stock implementation',
        !str_contains($js, 'function renderSoldStock(')
        && !str_contains($js, 'function clearSoldStockDisplay(')
        && !str_contains($js, 'function fetchSoldStock(')
    );
}

$check(
    'Browser code does not duplicate population-ledger SQL',
    !str_contains(implode("
", $sources), 'production_population_movements')
    && !str_contains(implode("
", $sources), 'production_population_baselines')
);

echo PHP_EOL . 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT=' . ($failures === 0 ? 'PASS' : 'FAIL') . PHP_EOL;

exit($failures === 0 ? 0 : 1);
