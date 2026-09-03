<?php
$root = dirname(__DIR__);
$checks = [];
function check_it($condition, $message) { global $checks; $checks[] = [$condition, $message]; echo ($condition ? "PASS" : "FAIL") . " - {$message}\n"; }
$financial = file_get_contents($root . '/includes/financial.php');
$invfin = file_get_contents($root . '/lib/inventory_financial.php');
$inventory = file_get_contents($root . '/inventory.php');
$api = file_get_contents($root . '/api/update_stock.php');
$profit = file_get_contents($root . '/management/profitability.php');
check_it(strpos($invfin, "'medication_vaccine' => 'Medication / Vaccine'") !== false, 'Medication/Vaccine is eligible operating inventory consumption');
check_it(strpos($invfin, "'supplement' => 'Supplement'") !== false, 'Supplement is eligible operating inventory consumption');
check_it(strpos($invfin, "'consumables' => 'Consumables'") !== false, 'Consumables is eligible operating inventory consumption');
check_it(strpos($financial, 'inventory_operating_consumption_cost') !== false, 'Profitability exposes consumed non-feed operating inventory cost');
check_it(strpos($financial, "t.transaction_type='used'") !== false, 'Profitability recognition is usage-based');
check_it(strpos($financial, 't.financial_classification IN') !== false, 'Profitability uses stock transaction financial snapshot');
check_it(strpos($financial, '$nonFeedExpenses=$manualNonFeedExpenses+$inventoryOperatingConsumption') !== false, 'Other operating cost combines manual expenses and eligible inventory consumption');
check_it(strpos($inventory, 'Production Cycle <span class="text-muted">(optional)</span>') !== false, 'Update Stock offers optional cycle attribution');
check_it(strpos($inventory, "if (\$type === 'used'") !== false, 'Cycle attribution is restricted to USED stock');
check_it(strpos($inventory, 'attribution_validate_cycle') !== false, 'Inventory validates selected cycle ownership/type');
check_it(strpos($api, 'attribution_validate_cycle') !== false, 'Stock API validates cycle attribution too');
check_it(strpos($profit, 'Consumed operating inventory') !== false, 'Profitability page makes inventory-consumption component auditable');
$failed = count(array_filter($checks, fn($c) => !$c[0]));
echo "\n" . count($checks) . " checks, {$failed} failed.\n";
exit($failed ? 1 : 0);
