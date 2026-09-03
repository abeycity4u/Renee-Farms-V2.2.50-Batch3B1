<?php
$root = dirname(__DIR__);
$checks = [];
function verify_check($label, $ok) {
    global $checks;
    $checks[] = [$label, (bool)$ok];
    echo ($ok ? "PASS" : "FAIL") . " - {$label}\n";
}
$api = file_get_contents($root . '/api/get_stock_history.php');
$page = file_get_contents($root . '/api/stock_history.php');
verify_check('Stock history API joins production cycles', strpos($api, 'LEFT JOIN production_cycles pc') !== false);
verify_check('Stock history API exposes cycle code', strpos($api, 'pc.cycle_code') !== false);
verify_check('History table has Attributed To column', strpos($page, '<th>Attributed To</th>') !== false);
verify_check('History table has Production Cycle column', strpos($page, '<th>Production Cycle</th>') !== false);
verify_check('Poultry Layer attribution is rendered', strpos($page, "Poultry · Layer") !== false);
verify_check('Poultry shared attribution is rendered', strpos($page, "Shared Poultry") !== false);
verify_check('Ruminant attribution is rendered', strpos($page, "Ruminant · ") !== false);
verify_check('Shared/no-cycle state is explicit', strpos($page, 'Shared / No specific cycle') !== false);
verify_check('History colspans updated to eleven columns', substr_count($page, 'colspan="11"') >= 2);
verify_check('No migration is introduced by this display change', !file_exists($root . '/migrations/028_stock_history_attribution.sql'));
$failed = array_filter($checks, fn($x) => !$x[1]);
echo "\n" . count($checks) . " checks, " . count($failed) . " failures.\n";
exit($failed ? 1 : 0);
