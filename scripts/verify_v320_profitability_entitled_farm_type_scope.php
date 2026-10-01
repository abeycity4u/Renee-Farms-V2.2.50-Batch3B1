<?php

$root = dirname(__DIR__);

$entitlements =
    file_get_contents(
        $root . '/includes/farm_entitlements.php'
    );

$profitability =
    file_get_contents(
        $root . '/management/profitability.php'
    );

$checks = [
    'shared reporting scope helper exists' =>
        str_contains(
            $entitlements,
            'function farm_entitlement_reporting_farm_types'
        ),

    'reporting helper derives poultry entitlement' =>
        str_contains(
            $entitlements,
            'in_array(\'poultry\', $enabled, true)'
        ),

    'reporting helper derives ruminant entitlement' =>
        str_contains(
            $entitlements,
            'in_array(\'ruminant\', $enabled, true)'
        ),

    'General is Sales-only fallback' =>
        str_contains(
            $entitlements,
            'farm_entitlement_sales_available('
        )
        &&
        str_contains(
            $entitlements,
            '$reportingTypes[] = \'general\';'
        ),

    'Profitability consumes shared reporting scope' =>
        str_contains(
            $profitability,
            'farm_entitlement_reporting_farm_types('
        ),

    'Profitability validates requested scope against entitled scopes' =>
        str_contains(
            $profitability,
            '$validFarmTypes'
        )
        &&
        str_contains(
            $profitability,
            '$reportingFarmTypes'
        ),

    'Farm type selector renders from entitled scopes' =>
        str_contains(
            $profitability,
            'foreach ($reportingFarmTypes as $reportFarmType)'
        ),

    'All option is conditional on multiple reporting scopes' =>
        str_contains(
            $profitability,
            'count($reportingFarmTypes) > 1'
        ),

    'Legacy hardcoded four-option selector removed' =>
        !str_contains(
            $profitability,
            '<option value="poultry"'
        )
        &&
        !str_contains(
            $profitability,
            '<option value="ruminant"'
        )
        &&
        !str_contains(
            $profitability,
            '<option value="general"'
        ),
];

$failed = false;

foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$passed) {
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

echo "PASS: Profitability farm-type scope follows tenant entitlement."
    . PHP_EOL;
