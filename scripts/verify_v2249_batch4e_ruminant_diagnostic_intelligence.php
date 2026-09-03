<?php
$root=dirname(__DIR__); $checks=[];
function c4e($name,$ok){global $checks;$checks[]=[$name,(bool)$ok];}
$lib=file_get_contents($root.'/lib/ruminant_diagnostics.php');
$fi=file_get_contents($root.'/lib/farm_intelligence.php');
$page=file_get_contents($root.'/management/ruminant_investigation.php');
c4e('Ruminant diagnostics library exists',is_file($root.'/lib/ruminant_diagnostics.php'));
c4e('Ruminant investigation page exists',is_file($root.'/management/ruminant_investigation.php'));
c4e('Ruminant investigation enforces ruminant access',strpos($page,"ensureAllowed('ruminant_daily')")!==false);
c4e('Ruminant investigation loads shared permission helpers before ensureAllowed',strpos($page,"require_once __DIR__.'/../includes/functions.php';")!==false && strpos($page,"require_once __DIR__.'/../includes/functions.php';") < strpos($page,"ensureAllowed('ruminant_daily')"));
c4e('Weight intelligence links to investigation',strpos($fi,'management/ruminant_investigation.php?animal_id=')!==false);
c4e('Uses individual weight history',strpos($lib,'ruminant_animal_weights')!==false && strpos($lib,'immediately')===false);
c4e('Uses structured health events',strpos($lib,'ruminant_health_events')!==false);
c4e('Uses dated cycle membership',strpos($lib,'ruminant_animal_cycle_memberships')!==false);
c4e('Uses effective stock movements',strpos($lib,'stock_effective_sql_predicate')!==false);
c4e('Does not infer individual herd feed',strpos($lib,'not individual-animal feed intake')!==false || strpos($lib,'individual consumption is not inferred')!==false);
c4e('Timeline states non-causation',strpos($page,'recorded sequence, not causation')!==false);
c4e('No diagnosis/prescription wording',strpos($page,'not a veterinary diagnosis')!==false && strpos($page,'not medication or disease prescriptions')!==false);
c4e('Diagnostic engine did not require a diagnostic-schema migration',!is_file($root.'/migrations/034_ruminant_diagnostic_intelligence.sql'));
$fail=0;foreach($checks as [$n,$ok]){echo ($ok?'PASS':'FAIL')." - $n\n";if(!$ok)$fail++;}echo count($checks)." checks, $fail failures\n";exit($fail?1:0);
