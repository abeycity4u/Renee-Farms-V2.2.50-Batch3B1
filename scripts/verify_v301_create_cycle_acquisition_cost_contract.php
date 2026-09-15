<?php

$root = dirname(__DIR__);
$failures = 0;
$passes = 0;

function check_contract(bool $condition, string $label): void
{
    global $failures, $passes;

    if ($condition) {
        $passes++;
        echo "[PASS] {$label}\n";
    } else {
        $failures++;
        echo "[FAIL] {$label}\n";
    }
}

$page = file_get_contents($root . '/management/production_cycles.php');
$js = file_get_contents($root . '/assets/js/production-cycles.js');
$acq = file_get_contents($root . '/lib/poultry_cycle_acquisition.php');

$action = '<input type="hidden" name="action" value="create_cycle">';
$start = strpos((string)$page, $action);
$end = $start === false
    ? false
    : strpos((string)$page, '</form>', $start);

$form = (
    $start !== false
    && $end !== false
)
    ? substr((string)$page, $start, $end - $start)
    : '';

check_contract($form !== '', 'Create Cycle form found.');

check_contract(
    strpos($form, 'name="bird_unit_cost"') === false,
    'Create Cycle no longer asks for Bird Cost Basis.'
);

check_contract(
    strpos($form, 'name="poultry_unit_price"') === false,
    'Create Cycle no longer asks for Unit Purchase Price.'
);

check_contract(
    strpos($form, 'name="poultry_total_cost"') !== false,
    'Total Acquisition Cost remains the authoritative economic input.'
);

check_contract(
    strpos((string)$page, '$derivedBirdUnitCost') !== false
    && strpos(
        (string)$page,
        'poultry_acquisition_cost_per_bird('
    ) !== false,
    'Bird Cost Basis is derived server-side.'
);

check_contract(
    strpos((string)$page, "'bird_unit_cost' =>") !== false,
    'Internal Bird Cost Basis storage remains intact.'
);

check_contract(
    strpos((string)$js, 'createPoultryUnitPrice') === false
    && strpos((string)$js, 'unitPrice') === false,
    'Old Unit Purchase Price calculator is removed.'
);

check_contract(
    strpos(
        (string)$js,
        "totalCost.required = acquisitionType.value !== 'internal_transfer';"
    ) !== false,
    'Purchased entries still require total acquisition cost.'
);

check_contract(
    strpos($form, 'Farm-raised / internal transfer') !== false,
    'Farm-raised/internal-transfer entry remains supported.'
);

check_contract(
    strpos(
        (string)$acq,
        'function poultry_acquisition_cost_per_bird('
    ) !== false,
    'Shared acquisition cost-per-bird helper exists.'
);

require_once $root . '/lib/poultry_cycle_acquisition.php';

check_contract(
    poultry_acquisition_cost_per_bird(750000.0, 500) === 1500.0,
    '₦750,000 / 500 birds = ₦1,500 per bird.'
);

check_contract(
    poultry_acquisition_cost_per_bird(null, 500) === null,
    'Uncosted acquisition produces no artificial cost basis.'
);

check_contract(
    poultry_acquisition_cost_per_bird(750000.0, 0) === null,
    'Zero quantity produces no artificial cost basis.'
);

echo "RESULT={$passes}_PASS_{$failures}_FAIL\n";

exit($failures > 0 ? 1 : 0);
