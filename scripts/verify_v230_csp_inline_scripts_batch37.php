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

$php = file_get_contents(
    $root . '/management/sales_records.php'
);

$js = file_get_contents(
    $root . '/assets/js/management-sales-records.js'
);

$check(
    str_contains($php, 'id="managementSalesRecordsConfig"'),
    'Sales Records emits centralized page config'
);

$check(
    str_contains($php, 'data-sale-unit-presets="'),
    'Sales Records config exposes sale unit presets'
);

$check(
    str_contains($php, 'data-sales-cycles="'),
    'Sales Records config exposes production cycles'
);

$check(
    str_contains($php, 'data-ruminant-sale-animals="'),
    'Sales Records config exposes ruminant animals'
);

$check(
    str_contains($php, 'data-ruminant-sale-allocation-map="'),
    'Sales Records config exposes animal allocation map'
);

$check(
    str_contains($php, 'data-ruminant-sale-exit-map="'),
    'Sales Records config exposes animal exit map'
);

$check(
    str_contains($php, 'data-csrf-token="'),
    'Sales Records config exposes CSRF token'
);

$check(
    str_contains($php, 'data-delete-sale-url="'),
    'Sales Records config exposes delete endpoint'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/management-sales-records.js')"
    ),
    'Sales Records loads versioned external behavior asset'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Sales Records has zero literal inline script blocks'
);

$check(
    substr_count($php, 'app_json_script(') >= 5,
    'Sales Records retains safe JSON serialization for dynamic datasets'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Sales Records safely escapes config attributes'
);

$check(
    str_contains(
        $js,
        "document.getElementById('managementSalesRecordsConfig')"
    ),
    'External asset reads centralized config'
);

$check(
    str_contains($js, 'dataset.saleUnitPresets')
    && str_contains($js, 'dataset.salesCycles'),
    'External asset reads unit and cycle config'
);

$check(
    str_contains($js, 'dataset.ruminantSaleAnimals')
    && str_contains($js, 'dataset.ruminantSaleAllocationMap')
    && str_contains($js, 'dataset.ruminantSaleExitMap'),
    'External asset reads ruminant sale config'
);

$check(
    str_contains($js, 'dataset.csrfToken')
    && str_contains($js, 'dataset.deleteSaleUrl'),
    'External asset reads mutation security config'
);

$check(
    str_contains($js, 'function toggleCustomSaleUnit')
    && str_contains($js, 'function setEditSaleUnit'),
    'External asset retains sale unit behavior'
);

$check(
    str_contains($js, 'function updateTotalField')
    && str_contains($js, 'function updateOutstandingField')
    && str_contains($js, 'function setupTotalCalculator'),
    'External asset retains amount calculation behavior'
);

$check(
    str_contains($js, 'function refreshSaleAttribution'),
    'External asset retains sale attribution behavior'
);

$check(
    str_contains($js, 'function refreshRuminantSaleAnimalChoices')
    && str_contains($js, 'function loadEditSaleAnimalAllocation'),
    'External asset retains ruminant animal sale behavior'
);

$check(
    str_contains($js, 'function refreshEditReceivablePosition'),
    'External asset retains receivable edit positioning'
);

$check(
    str_contains($js, 'function applyFilters')
    && str_contains($js, 'function refreshReportProductionTypes'),
    'External asset retains report filtering behavior'
);

$check(
    str_contains($js, 'function deleteSale'),
    'External asset retains sale deletion behavior'
);

$check(
    str_contains($js, 'AppConfirm.ask'),
    'External asset retains centralized delete confirmation'
);

$check(
    str_contains($js, 'fetch(')
    && str_contains($js, 'salesRecordsConfig.csrfToken')
    && str_contains($js, 'salesRecordsConfig.deleteSaleUrl'),
    'External asset retains CSRF-protected delete request'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'Sales Records external asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
