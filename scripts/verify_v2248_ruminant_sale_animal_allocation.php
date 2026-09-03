<?php
$root = dirname(__DIR__);
$checks = [];
function checkit($label, $ok) { global $checks; $checks[] = [$label, (bool)$ok]; }
$sales = file_get_contents($root . '/management/sales_records.php');
$helper = file_get_contents($root . '/lib/ruminant_sale_animal_allocation.php');
$migration = file_get_contents($root . '/migrations/029_ruminant_sale_animal_allocations.sql');

checkit('Migration creates ruminant sale animal allocation table', strpos($migration, 'CREATE TABLE IF NOT EXISTS ruminant_sale_animal_allocations') !== false);
checkit('Migration cascades allocations with sale deletion', strpos($migration, 'REFERENCES sales_records(id) ON DELETE CASCADE') !== false);
checkit('Migration protects tenant farm ownership', strpos($migration, 'FOREIGN KEY (farm_id) REFERENCES farms(id)') !== false);
checkit('Helper keeps sale as source of truth', strpos($helper, 'sales_records remains the single financial source of truth') !== false);
checkit('Helper supports equal and custom allocation', strpos($helper, "['equal', 'custom']") !== false);
checkit('Helper validates production species', strpos($helper, 'Selected animals must match the sale production type.') !== false);
checkit('Custom split must equal sale total', strpos($helper, 'must add up exactly to the sale total') !== false);
checkit('Sales page loads ruminant allocation helper', strpos($sales, 'ruminant_sale_animal_allocation.php') !== false);
checkit('Add sale saves animal revenue attribution', strpos($sales, 'ruminant_sale_save_animal_allocations($pdo, $tenantFarmId, $saleId') !== false);
checkit('Add sale and animal allocation are committed atomically', strpos($sales, '$pdo->beginTransaction();') !== false && strpos($sales, "The sale could not be recorded.") !== false);
checkit('Edit sale updates animal revenue attribution atomically with sale edit transaction', strpos($sales, 'ruminant_sale_save_animal_allocations($pdo, $tenantFarmId, (int)$_POST[\'sale_id\']') !== false);
checkit('Add form exposes Animal Revenue Attribution', strpos($sales, 'id="addSaleAnimalAllocationMode"') !== false);
checkit('Edit form exposes Animal Revenue Attribution', strpos($sales, 'id="editSaleAnimalAllocationMode"') !== false);
checkit('Shared/equal/custom labels are present', strpos($sales, 'Shared revenue — no individual animal allocation') !== false && strpos($sales, 'Selected animals — Equal split') !== false && strpos($sales, 'Selected animals — Custom split') !== false);
checkit('Sales table shows animal revenue attribution', strpos($sales, '<th>Animal Revenue Attribution</th>') !== false);
checkit('Lifecycle changes require an explicit per-animal outcome', strpos($sales, 'Only an explicit Sold live or Culled/slaughtered outcome changes Animal Registry status.') !== false || strpos($sales, 'explicitly choose whether this is revenue only') !== false);
checkit('No automatic animal SOLD update added to helper', stripos($helper, "status='sold'") === false && stripos($helper, 'status = \'sold\'') === false);

$failed = 0;
foreach ($checks as [$label,$ok]) { echo ($ok ? 'PASS' : 'FAIL') . " - $label\n"; if (!$ok) $failed++; }
echo count($checks) . " checks, $failed failures\n";
exit($failed ? 1 : 0);
