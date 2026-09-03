<?php
$root=dirname(__DIR__); $checks=[];
function chk2($label,$ok){global $checks;$checks[]=[$label,(bool)$ok];}
$migration=file_get_contents($root.'/migrations/032_ruminant_animal_cycle_memberships.sql');
$membership=file_get_contents($root.'/lib/ruminant_cycle_membership.php');
$shared=file_get_contents($root.'/lib/ruminant_shared_cost_economics.php');
$econ=file_get_contents($root.'/lib/ruminant_animal_economics.php');
$view=file_get_contents($root.'/ruminant/animal_view.php');
chk2('Membership migration exists',is_file($root.'/migrations/032_ruminant_animal_cycle_memberships.sql'));
chk2('Membership history table is date-ranged',strpos($migration,'ruminant_animal_cycle_memberships')!==false && strpos($migration,'start_date DATE NOT NULL')!==false && strpos($migration,'end_date DATE NULL')!==false);
chk2('Membership validates species against cycle',strpos($membership,'selected production cycle must match the animal species')!==false);
chk2('Membership overlap is refused',strpos($membership,'overlaps the existing')!==false);
chk2('Eligibility requires explicit membership',strpos($membership,'FROM ruminant_animal_cycle_memberships m')!==false);
chk2('Sale exit date caps later shared allocation',strpos($membership,'xe.exit_date < ?')!==false);
chk2('Shared expense excludes explicit animal allocations',strpos($shared,'NOT EXISTS (SELECT 1 FROM ruminant_expense_animal_allocations')!==false);
chk2('Manual feed purchases excluded from operating allocation',strpos($shared,"e.category<>'feeds'")!==false);
chk2('Inventory allocation uses effective USED movements',strpos($shared,"transaction_type='used'")!==false && strpos($shared,'stock_effective_sql_predicate')!==false);
chk2('Inventory pool limited to feed plus operating classifications',strpos($shared,"array_merge(['feed'],array_keys(inventory_operating_consumption_classifications()))")!==false);
chk2('Cent-exact deterministic allocation implemented',strpos($shared,'intdiv($totalCents,$count)')!==false && strpos($shared,'sort($eligibleAnimalIds,SORT_NUMERIC)')!==false);
chk2('Fully allocated formula is separate from direct formula',strpos($econ,'$fullyAllocatedNet = round($revenueTotal - $fullyAllocatedCost, 2)')!==false);
chk2('No weight interpolation is used',strpos($shared,'weight_kg')===false);
chk2('Animal profile exposes membership history',strpos($view,'Production Cycle Membership')!==false && strpos($view,'Assign Production Cycle')!==false);
chk2('Animal profile exposes fully allocated economics',strpos($view,'Fully Allocated Animal Economics')!==false && strpos($view,'Fully Allocated Net Position')!==false);
chk2('Uncovered costs stay visible',strpos($view,'Incomplete shared-cost coverage')!==false && strpos($econ,'uncovered_species_shared_cost')!==false);
chk2('Cross-species shared pools are explicitly excluded',strpos($view,'cross-species pools remain outside this figure')!==false);
chk2('Shared cost history is auditable',strpos($view,'Allocated Shared Cost History')!==false && strpos($view,'Eligible Animals')!==false);
$fail=0;foreach($checks as[$l,$ok]){echo($ok?'PASS':'FAIL')." - $l\n";if(!$ok)$fail++;}
echo "\n".(count($checks)-$fail).'/'.count($checks)." checks passed.\n";exit($fail?1:0);
