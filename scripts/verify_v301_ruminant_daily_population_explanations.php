<?php

declare(strict_types=1);

/**
 * V3.0.1 — Ruminant Daily Record population explanation verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$paths = [
    'shared_ui' =>
        $root . '/assets/js/daily-population-movement-ui.js',
    'ruminant_js' =>
        $root . '/assets/js/ruminant-daily-record.js',
    'ruminant_page' =>
        $root . '/ruminant/ruminant_daily_record.php',
    'sales_page' =>
        $root . '/management/sales_records.php',
    'sales_js' =>
        $root . '/assets/js/management-sales-records.js',
    'sales_population' =>
        $root . '/lib/sale_population_effects.php',
];

$sources = [];

foreach ($paths as $key => $path) {
    $sources[$key] = is_file($path)
        ? (string)file_get_contents($path)
        : '';
}

$checks = 0;
$failures = 0;

$check = static function (
    string $label,
    bool $ok
) use (&$checks, &$failures): void {
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    'Focused population-explanation sources are readable',
    !in_array('', $sources, true)
);

$check(
    'Shared UI keeps existing poultry Sold Stock helper',
    str_contains(
        $sources['shared_ui'],
        'createSoldStockDisplay'
    )
    && str_contains(
        $sources['shared_ui'],
        'Sold'
    )
);

$check(
    'Shared UI derives Ruminant sold stock from canonical sale movement',
    str_contains(
        $sources['shared_ui'],
        "soldStock: removalQuantity('sale')"
    )
);

$check(
    'Shared UI derives Ruminant culled stock from canonical cull movement',
    str_contains(
        $sources['shared_ui'],
        "culledStock: removalQuantity('cull')"
    )
);

$check(
    'Tagged mortality is canonical mortality minus Daily Record group mortality',
    str_contains(
        $sources['shared_ui'],
        "totalMortality - groupMortality"
    )
);

$check(
    'Ruminant modal exposes separate read-only tagged mortality',
    str_contains(
        $sources['ruminant_page'],
        'Tagged Mortality (Animal Registry):'
    )
    && str_contains(
        $sources['ruminant_page'],
        'ruminantTaggedMortalityValue'
    )
);

$check(
    'Ruminant modal exposes Sold Stock only through movement surface',
    str_contains(
        $sources['ruminant_page'],
        'Sold Stock:'
    )
    && str_contains(
        $sources['ruminant_page'],
        'ruminantSoldStockRow'
    )
);

$check(
    'Ruminant modal exposes Culled Stock only through movement surface',
    str_contains(
        $sources['ruminant_page'],
        'Culled'
    )
    && str_contains(
        $sources['ruminant_page'],
        'ruminantCulledStockRow'
    )
);

$check(
    'Ruminant movement surface stays compact',
    str_contains(
        $sources['ruminant_page'],
        'd-flex flex-wrap align-items-center gap-2 small'
    )
    && !str_contains(
        $sources['ruminant_page'],
        'class="alert alert-light border d-none mb-3"'
    )
);

$check(
    'Editable group mortality field remains separate',
    str_contains(
        $sources['ruminant_page'],
        'Mortality (Unregistered / Group)'
    )
    && str_contains(
        $sources['ruminant_page'],
        'name="mortality"'
    )
);

$check(
    'Ruminant page loads shared movement UI before page behavior',
    strpos(
        $sources['ruminant_page'],
        "/assets/js/daily-population-movement-ui.js"
    ) < strpos(
        $sources['ruminant_page'],
        "/assets/js/ruminant-daily-record.js"
    )
);

$check(
    'Ruminant browser reads canonical movement totals through opening API',
    str_contains(
        $sources['ruminant_js'],
        'fetchPopulationBoundary('
    )
    && str_contains(
        $sources['ruminant_js'],
        '../api/get_previous_stock.php?'
    )
    && str_contains(
        $sources['ruminant_js'],
        'populationMovementDisplay?.render('
    )
);

$check(
    'Existing Daily Record mortality is passed only as group context',
    str_contains(
        $sources['ruminant_js'],
        'Number(data.mortality || 0)'
    )
    && !str_contains(
        $sources['ruminant_js'],
        "document.getElementById('mortality').value = payload"
    )
);

$check(
    'Financial-only default copy no longer promises zero lifecycle population change',
    !str_contains(
        $sources['sales_page'],
        'Saving this sale will not reduce or change live population in any production cycle.'
    )
    && !str_contains(
        $sources['sales_js'],
        'Saving this sale will not reduce or change live population in any production cycle.'
    )
);

$check(
    'Ruminant Financial-only copy explains tagged lifecycle independence',
    str_contains(
        $sources['sales_js'],
        'no additional aggregate/group headcount deduction'
    )
    && str_contains(
        $sources['sales_js'],
        'Animal Registry lifecycle'
    )
);

$check(
    'Ruminant Remove-live copy excludes already-tagged lifecycle exits',
    str_contains(
        $sources['sales_js'],
        'Do not include tagged animals already marked Sold live or Culled/slaughtered'
    )
);

$check(
    'Sales backend still treats financial-only as empty generic population rows',
    str_contains(
        $sources['sales_population'],
        "if (\$mode === '' || \$mode === 'financial_only')"
    )
    && str_contains(
        $sources['sales_population'],
        'return [];'
    )
);

$check(
    'No backend population writer was added to Ruminant modal UI files',
    !str_contains(
        $sources['shared_ui'],
        'production_population_movements'
    )
    && !str_contains(
        $sources['ruminant_js'],
        'production_population_movements'
    )
);

echo PHP_EOL . 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . ($failures === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
