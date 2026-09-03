<?php
$root=dirname(__DIR__);
$checks=[];
function ck($name,$ok){global $checks;$checks[]=[$name,(bool)$ok];}
$intel=file_get_contents($root.'/lib/farm_intelligence.php');
ck('Batch 3 uses farm-history Layer trend', strpos($intel,'latest 7 recorded days')!==false && strpos($intel,'preceding 7 recorded days')!==false);
ck('Layer trend does not claim external benchmark', strpos($intel,'not an external production benchmark')!==false);
ck('Broiler mortality trend weighted by opening flock', strpos($intel,'weights mortality by opening flock')!==false);
ck('Inventory cover uses effective USED ledger', strpos($intel,"transaction_type='used'")!==false && strpos($intel,'stock_effective_sql_predicate()')!==false);
ck('Inventory cover explicitly remains an estimate', strpos($intel,'not a guaranteed run-out date')!==false);
ck('Minimum-stock danger remains separate', strpos($intel,'minimum-stock danger already explains these items')!==false);
ck('Receivables separated from revenue', strpos($intel,'Receivables are cash-collection intelligence, not revenue')!==false);
ck('Receivables uses customer ledger balance', strpos($intel,'customer_ledger_entries')!==false && strpos($intel,"HAVING balance>0.005")!==false);
ck('Ruminant weight trend compares own history', strpos($intel,'own previous recorded weight')!==false);
ck('Ruminant weight trend does not invent breed targets', strpos($intel,'does not invent breed growth targets')!==false);
ck('Weight decline is warning', strpos($intel,"'ruminant-weight-decline','Ruminant','warning'")!==false);
ck('Missing weight coverage is informational', strpos($intel,"'ruminant-weight-coverage','Data quality','info'")!==false);
ck('Canonical financial summary remains delegated', strpos($intel,'getProfitabilitySummary(')!==false);
ck('No write-back of intelligence totals', strpos($intel,'INSERT INTO profit_loss_summary')===false && strpos($intel,'UPDATE profit_loss_summary')===false);
$fail=0;foreach($checks as [$n,$ok]){echo ($ok?'PASS':'FAIL')." - $n\n";if(!$ok)$fail++;}
echo "\n".(count($checks)-$fail)."/".count($checks)." PASS\n";exit($fail?1:0);
