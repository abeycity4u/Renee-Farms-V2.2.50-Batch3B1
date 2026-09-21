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

$page = is_file($pagePath)
    ? (string)file_get_contents($pagePath)
    : '';

$js = is_file($jsPath)
    ? (string)file_get_contents($jsPath)
    : '';

$checks = 0;
$failures = 0;

$check =
    static function (
        string $label,
        bool $ok
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo
            ($ok ? 'PASS: ' : 'FAIL: ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };

$check(
    'Layer Daily Record sources are readable',
    $page !== ''
    && $js !== ''
);

$check(
    'Sold Stock display is positioned in Layer modal beside Crates row',
    str_contains(
        $page,
        'id="soldStockContainer"'
    )
    && str_contains(
        $page,
        'id="soldStockDisplay"'
    )
    && strpos(
        $page,
        'id="cratesCount"'
    ) < strpos(
        $page,
        'id="soldStockContainer"'
    )
);

$check(
    'Sold Stock display is read-only presentation and not a submitted input',
    !str_contains(
        $page,
        'name="sold_stock"'
    )
    && str_contains(
        $page,
        'role="status"'
    )
);

$check(
    'Sold Stock block is hidden by default',
    str_contains(
        $page,
        'ms-md-auto d-none'
    )
);

$check(
    'Layer JS derives Sold Stock from canonical sale movement totals',
    str_contains(
        $js,
        "payload?.movement_totals?.sale"
    )
    && str_contains(
        $js,
        "saleDelta < 0"
    )
);

$check(
    'Layer JS displays exact Sold Stock label',
    str_contains(
        $js,
        'Sold Stock:'
    )
);

$check(
    'Layer JS hides Sold Stock when no sale exists',
    str_contains(
        $js,
        'soldStock <= 0'
    )
    && str_contains(
        $js,
        "container.classList.add('d-none')"
    )
);

$check(
    'New-record canonical opening request also renders Sold Stock',
    substr_count(
        $js,
        'renderSoldStock(payload);'
    ) >= 2
);

$check(
    'Existing-record flows request Sold Stock for selected date',
    str_contains(
        $js,
        'fetchSoldStock(date);'
    )
    && str_contains(
        $js,
        'fetchSoldStock(recordDate);'
    )
);

$check(
    'Form reset clears stale Sold Stock state',
    str_contains(
        $js,
        'clearSoldStockDisplay();'
    )
);

$check(
    'Layer browser still uses shared canonical previous-stock API',
    str_contains(
        $js,
        '../api/get_previous_stock.php?type=layer'
    )
);

$check(
    'No population-ledger SQL is duplicated into Layer browser code',
    !str_contains(
        $js,
        'production_population_movements'
    )
    && !str_contains(
        $js,
        'production_population_baselines'
    )
);

echo PHP_EOL
    . 'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo 'FAILED_COUNT='
    . $failures
    . PHP_EOL;

echo 'DATABASE_CONNECTION_USED=NO'
    . PHP_EOL;

echo 'DATABASE_WRITE_PERFORMED=NO'
    . PHP_EOL;

echo 'RESULT='
    . ($failures === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
