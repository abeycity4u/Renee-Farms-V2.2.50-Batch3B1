<?php
$root=dirname(__DIR__); $h=file_get_contents($root.'/lib/poultry_health.php'); $d=file_get_contents($root.'/lib/poultry_diagnostics.php'); $p=file_get_contents($root.'/management/investigation.php');
$checks=[
 'diagnostic context helper exists'=>strpos($h,'function poultry_health_diagnostic_context')!==false,
 'exact cycle remains canonical evidence'=>strpos($h,'poultry_health_recent_for_cycle')!==false,
 'other-cycle events are queried separately'=>strpos($h,'phe.cycle_id IS NULL OR phe.cycle_id<>?')!==false,
 'diagnostic uses scoped context'=>strpos($d,'poultry_health_diagnostic_context')!==false,
 'other-cycle events become attribution gap'=>strpos($d,'Review cycle attribution')!==false,
 'absence wording is cycle-specific'=>strpos($d,'for this production cycle in the context window')!==false,
 'health review link is cycle filtered'=>strpos($p,"poultry/health.php?production_type=")!==false && strpos($p,"&cycle_id=")!==false,
];
$fail=0; foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." - $name\n"; if(!$ok)$fail++;} echo (count($checks)-$fail).'/'.count($checks)." checks passed\n"; exit($fail?1:0);
