<?php
$root=dirname(__DIR__);
$checks=[];
function chk(&$checks,$ok,$label){$checks[]=[$ok,$label]; echo ($ok?'PASS':'FAIL')." - {$label}\n";}
$lib=file_get_contents($root.'/lib/poultry_rearing_economics.php');
$page=file_get_contents($root.'/management/poultry_cycle.php');
$cycles=file_get_contents($root.'/management/production_cycles.php');
chk($checks,$lib!==false,'rearing economics helper exists');
chk($checks,strpos($lib,"require_once __DIR__ . '/stock_costing.php';")!==false,'rearing economics explicitly loads canonical stock-costing dependency');
chk($checks,strpos($lib,"require_once __DIR__ . '/production_population.php';")!==false,'rearing economics explicitly loads canonical population dependency');
chk($checks,strpos($lib,"phase'] === 'rearing'")!==false,'explicit Rearing phase is required; age/output inference is not used');
chk($checks,strpos($lib,"transaction_type='used'")!==false,'economics reads effective USED inventory transactions');
chk($checks,strpos($lib,'stock_feed_item_sql_predicate')!==false,'feed cost uses canonical feed classification predicate');
chk($checks,strpos($lib,'inventory_operating_consumption_classifications')!==false,'eligible non-feed stock consumption reuses canonical operating classifications');
chk($checks,strpos($lib,"category<>'feeds'")!==false,'manual feed expense is excluded from rearing investment to prevent feed double count');
chk($checks,strpos($lib,'financial_allocations')!==false,'explicit shared-expense allocations are included');
chk($checks,strpos($lib,'unallocated_shared_expense_pool')!==false,'unallocated Layer shared costs are disclosed separately');
chk($checks,strpos($lib,'purchased_point_of_lay')!==false,'POL entry is handled separately from on-farm rearing');
chk($checks,strpos($lib,'poultry_production_entry_population_boundary')!==false,'production-entry flock boundary is centralized in one helper');
chk($checks,strpos($lib,'production_population_state')!==false,'V3 Production-Entry flock uses canonical historical population state');
chk($checks,strpos($lib,'Canonical population ledger at Rearing close')!==false,'canonical population ledger is the V3 Production-Entry headcount authority');
chk($checks,strpos($lib,'Legacy fallback only')!==false,'legacy cycles retain exact Daily Record boundary fallback');
chk($checks,strpos($lib,'does not reconcile to the rearing-end Daily Record closing flock')!==false && strpos($lib,'does not reconcile to the production-start Daily Record opening flock')!==false,'Daily Record boundary remains visible reconciliation evidence without replacing canonical population authority');
chk($checks,strpos($page,'Cycle Workspace')!==false,'dedicated poultry cycle workspace exists');
chk(
    $checks,
    strpos($page,'Record Transition')!==false
    && strpos($page,'Record Flock Entry')===false
    && strpos($page,'Set Initial Biological Stage')===false,
    'V3.0.1 workspace owns authorized lifecycle transition without restoring duplicate poultry onboarding'
);
chk($checks,strpos($page,'Layer Rearing & Production-Entry Economics')!==false,'workspace exposes Layer rearing economics');
chk($checks,strpos($page,'does not alter monthly profitability')!==false,'workspace declares canonical period profitability remains untouched');
chk($checks,strpos($lib,'known_attributable_rearing_cost')!==false,'known attributable rearing cost remains visible even when complete investment is unavailable');
chk($checks,strpos($page,'Known Attributable Rearing Cost')!==false && strpos($page,'Attributed Rearing Investment')!==false,'workspace distinguishes known cost from attributed rearing investment');
chk($checks,strpos($lib,'Renee will')===false && strpos($page,'Renee does')===false && strpos($page,'before Renee')===false,'new workspace/economics operational copy does not speak as the platform brand');
chk($checks,strpos($cycles,'Manage Cycle')!==false && strpos($cycles,'poultry_cycle.php?id=')!==false,'Production Cycles links poultry rows to dedicated workspace');
chk($checks,strpos($cycles,'id="poultry-entry-acquisition"')!==false && strpos($cycles,'id="poultry-lifecycle-history"')!==false,'legacy management forms remain reachable during progressive extraction');
$failed=array_filter($checks,fn($c)=>!$c[0]);
echo "\n".(count($failed)?'FAILED':'ALL CHECKS PASSED').': '.(count($checks)-count($failed)).'/'.count($checks)."\n";
exit(count($failed)?1:0);
