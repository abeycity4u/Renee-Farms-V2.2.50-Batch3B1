<?php

declare(strict_types=1);

/**
 * V3.0.1 — Layer Daily Record Sold Stock display verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);
$pagePath = $root . '/poultry/layers_daily_record.php';
$jsPath = $root . '/assets/js/layers-daily-record.js';
$sharedPath = $root . '/assets/js/daily-population-movement-ui.js';

$page = is_file($pagePath) ? (string)file_get_contents($pagePath) : '';
$js = is_file($jsPath) ? (string)file_get_contents($jsPath) : '';
$shared = is_file($sharedPath) ? (string)file_get_contents($sharedPath) : '';

$checks = 0;
$failures = 0;

$check = static function (string $label, bool $ok) use (&$checks, &$failures): void {
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$check(
    'Layer Daily Record Sold Stock sources are readable',
    $page !== '' && $js !== '' && $shared !== ''
);

$check(
    'Sold Stock display is positioned in Layer modal beside Crates row',
    str_contains($page, 'id="soldStockContainer"')
    && str_contains($page, 'id="soldStockDisplay"')
    && strpos($page, 'id="cratesCount"') < strpos($page, 'id="soldStockContainer"')
);

$check(
    'Sold Stock display is read-only presentation and not a submitted input',
    !str_contains($page, 'name="sold_stock"')
    && str_contains($page, 'role="status"')
);

$check(
    'Sold Stock block is hidden by default',
    str_contains($page, 'ms-md-auto d-none')
);

$check(
    'Shared UI derives Sold Stock from canonical sale movement totals',
    str_contains($shared, 'payload?.movement_totals?.sale')
    && str_contains($shared, 'saleDelta < 0')
);

$check(
    'Shared UI displays exact Sold Stock label',
    str_contains($shared, 'Sold Stock:')
);

$check(
    'Shared UI hides Sold Stock when no sale exists',
    str_contains($shared, 'soldStock <= 0')
    && str_contains($shared, "container.classList.add('d-none')")
);

$check(
    'Layer consumes shared Sold Stock helper',
    str_contains($js, 'DailyPopulationMovementUI.createSoldStockDisplay(')
    && str_contains($js, "type: 'layer'")
);

$check(
    'New and existing Layer record flows use shared Sold Stock helper',
    str_contains($js, 'soldStockUI.render(payload);')
    && substr_count($js, 'soldStockUI.fetchForDate(') >= 2
);

$check(
    'Form reset clears stale Sold Stock state through shared helper',
    str_contains($js, 'soldStockUI.clear();')
);

$check(
    'Layer loads shared helper before page-specific browser code',
    strpos($page, '/assets/js/daily-population-movement-ui.js')
        < strpos($page, '/assets/js/layers-daily-record.js')
);

$check(
    'No population-ledger SQL is duplicated into Layer browser code',
    !str_contains($js, 'production_population_movements')
    && !str_contains($js, 'production_population_baselines')
    && !str_contains($shared, 'production_population_movements')
    && !str_contains($shared, 'production_population_baselines')
);

echo PHP_EOL . 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT=' . ($failures === 0 ? 'PASS' : 'FAIL') . PHP_EOL;

exit($failures === 0 ? 0 : 1);
