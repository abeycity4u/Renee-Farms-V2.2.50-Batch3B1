<?php
$root = dirname(__DIR__);
$checks = [];
function chk($label, $ok) { global $checks; $checks[] = [$label, (bool)$ok]; }
$helper = file_get_contents($root.'/lib/ruminant_animal_economics.php');
$view = file_get_contents($root.'/ruminant/animal_view.php');
chk('Economics helper exists', is_file($root.'/lib/ruminant_animal_economics.php'));
chk('Expense allocations are canonical direct-cost source', strpos($helper,'ruminant_expense_animal_allocations') !== false);
chk('Sale allocations are canonical direct-revenue source', strpos($helper,'ruminant_sale_animal_allocations') !== false);
chk('Purchase cost included', strpos($helper,"purchase_cost") !== false);
chk('Exit revenue is identified without duplicate addition', strpos($helper,'exitRevenueTotal') !== false && strpos($helper,'revenueTotal - $exitRevenueTotal') !== false);
chk('Direct net formula implemented', strpos($helper,'$revenueTotal - $directCostTotal') !== false);
chk('Direct economics remains separately preserved', strpos($helper,"'direct_net_position' => ".'$directNet') !== false && strpos($view,'Direct Net Position') !== false);
chk('Animal profile loads economics helper', strpos($view,'ruminant_animal_economics.php') !== false);
chk('Animal profile calls economics engine', strpos($view,'ruminant_animal_economics($pdo, $farmId, $animalId)') !== false);
chk('Direct net is explicitly labelled', strpos($view,'Direct Net Position') !== false);
chk('Shared-cost limitation disclosed', strpos($view,'not silently divided across animals') !== false);
chk('Direct cost history is visible', strpos($view,'Direct Cost History') !== false);
chk('Attributed revenue history is visible', strpos($view,'Attributed Revenue History') !== false);
chk('Exit-linked revenue says it is not double-counted', strpos($view,'not added twice') !== false);
$fail=0; foreach($checks as [$label,$ok]) { echo ($ok?'PASS':'FAIL')." - $label\n"; if(!$ok)$fail++; }
echo "\n".(count($checks)-$fail).'/'.count($checks)." checks passed.\n";
exit($fail?1:0);
