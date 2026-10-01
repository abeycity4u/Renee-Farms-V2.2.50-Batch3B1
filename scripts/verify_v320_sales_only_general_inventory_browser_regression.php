<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;

function check_regression(
    bool $ok,
    string $label
): void {
    global $failures;

    echo
        ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
}

$dispatcherPath =
    $root
    . '/lib/slaughter_output_sale_dispatch.php';

$dashboardPath =
    $root
    . '/dashboard.php';

if (
    !is_file($dispatcherPath)
    ||
    !is_file($dashboardPath)
) {
    fwrite(
        STDERR,
        "FAIL: required regression source file missing\n"
    );
    exit(1);
}

require_once $dispatcherPath;

check_regression(
    slaughter_output_sale_domain_from_post(
        [
            'sale_stock_source' =>
                'general_inventory',
        ]
    ) === null,
    'General Inventory is non-slaughter at shared dispatcher boundary'
);

$unknownRejected = false;

try {
    slaughter_output_sale_domain_from_post(
        [
            'sale_stock_source' =>
                'not_a_real_source',
        ]
    );
} catch (SlaughterOutputSaleException $e) {
    $unknownRejected =
        $e->getMessage()
        === 'Choose a valid Sales stock source.';
}

check_regression(
    $unknownRejected,
    'unknown Sales stock sources remain rejected'
);

$dispatcher =
    (string)file_get_contents(
        $dispatcherPath
    );

$generalGuardPosition =
    strpos(
        $dispatcher,
        "\$sourceMode === 'general_inventory'"
    );

$rowsParsePosition =
    strpos(
        $dispatcher,
        'slaughter_output_sale_common_rows_from_post('
    );

check_regression(
    $generalGuardPosition !== false
    &&
    $rowsParsePosition !== false
    &&
    $generalGuardPosition < $rowsParsePosition,
    'General Inventory bypasses slaughter-row parsing before hidden/stale lot fields'
);

$dashboard =
    (string)file_get_contents(
        $dashboardPath
    );

check_regression(
    str_contains(
        $dashboard,
        'allowedInventoryFarmTypes('
    )
    &&
    str_contains(
        $dashboard,
        '$dashboardInventoryFarmTypes'
    ),
    'Dashboard consumes shared Inventory entitlement scope'
);

check_regression(
    !str_contains(
        $dashboard,
        "si.farm_type IN ('poultry','ruminant','both')"
    ),
    'Smart Stock Control no longer hard-codes livestock-only Inventory scope'
);

check_regression(
    str_contains(
        $dashboard,
        's.farm_type IN ($transactionPlaceholders)'
    ),
    'Today transactions use the same entitled Inventory scope'
);

if ($failures > 0) {
    echo
        'SALES_ONLY_GENERAL_INVENTORY_BROWSER_REGRESSION=FAIL'
        . PHP_EOL;

    exit(1);
}

echo
    'SALES_ONLY_GENERAL_INVENTORY_BROWSER_REGRESSION=PASS'
    . PHP_EOL;
