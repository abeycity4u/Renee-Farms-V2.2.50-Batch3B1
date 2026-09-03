<?php
$root = dirname(__DIR__);
$checks = [];
function chk($label, $condition) {
    global $checks;
    $checks[] = [$label, (bool)$condition];
}
function txt($path) { return file_get_contents($path); }
$layer = txt($root . '/poultry/layer_expenses.php');
$broiler = txt($root . '/poultry/broiler_expenses.php');
$rum = txt($root . '/ruminant/ruminant_expenses.php');
$attr = txt($root . '/lib/attribution.php');

chk('Attribution production display helper exists', strpos($attr, 'function attribution_production_label') !== false);
chk('Attribution cycle display helper exists', strpos($attr, 'function attribution_cycle_label') !== false);
chk('Layer query resolves expense cycle code', strpos($layer, 'pc.cycle_code AS expense_cycle_code') !== false);
chk('Broiler query resolves expense cycle code', strpos($broiler, 'pc.cycle_code AS expense_cycle_code') !== false);
chk('Ruminant query resolves expense cycle code', strpos($rum, 'pc.cycle_code AS expense_cycle_code') !== false);
chk('Layer Detailed Expenses shows Production / Cycle', strpos($layer, '<th>Production / Cycle</th>') !== false);
chk('Broiler Detailed Expenses shows Production / Cycle', strpos($broiler, '<th>Production / Cycle</th>') !== false);
chk('Ruminant Detailed Expenses shows Production / Cycle', strpos($rum, '<th>Production / Cycle</th>') !== false);
chk('Layer uses transaction production/cycle values', strpos($layer, "attribution_cycle_label('poultry'") !== false && strpos($layer, "expense_cycle_code") !== false);
chk('Broiler uses transaction production/cycle values', strpos($broiler, "attribution_cycle_label('poultry'") !== false && strpos($broiler, "expense_cycle_code") !== false);
chk('Ruminant uses transaction production/cycle values', strpos($rum, "attribution_cycle_label('ruminant'") !== false && strpos($rum, "expense_cycle_code") !== false);
chk('Shared Ruminant label is explicit', strpos($attr, "'Shared Ruminant'") !== false);
chk('Shared Poultry label is explicit', strpos($attr, "'Shared Poultry'") !== false);

$passed = 0;
foreach ($checks as [$label, $ok]) {
    echo ($ok ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
    if ($ok) $passed++;
}
echo PHP_EOL . $passed . '/' . count($checks) . ' checks passed.' . PHP_EOL;
exit($passed === count($checks) ? 0 : 1);
