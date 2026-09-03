<?php
$root=dirname(__DIR__);$tests=[];$ok=function($n,$v)use(&$tests){$tests[]=[$n,(bool)$v];};
$d=file_get_contents($root.'/lib/poultry_diagnostics.php');$f=file_get_contents($root.'/lib/farm_intelligence.php');$p=file_get_contents($root.'/management/investigation.php');
$ok('diagnostic engine exists',strpos($d,'poultry_diagnostic_investigate')!==false);
$ok('uses structured health history',(strpos($d,'poultry_health_recent_for_cycle')!==false || strpos($d,'poultry_health_diagnostic_context')!==false));
$ok('compares feed per bird',strpos($d,"feed_consumption_bags")!==false);
$ok('compares water per bird',strpos($d,'water_consumption_liters')!==false);
$ok('detects feed item changes',strpos($d,'Feed item changed')!==false);
$ok('gates incomplete history',strpos($d,'14 recorded daily records')!==false);
$ok('uses evidence strength labels',strpos($d,"'Strong'")!==false && strpos($d,"'Moderate'")!==false && strpos($d,"'Limited'")!==false);
$ok('does not diagnose disease',strpos($p,'not a veterinary diagnosis')!==false);
$ok('does not prescribe medication',strpos($p,'not medication or disease prescriptions')!==false);
$ok('layer signal opens investigation',strpos($f,"management/investigation.php?type=layer&issue=laying_decline")!==false);
$ok('broiler signal opens investigation',strpos($f,"management/investigation.php?type=broiler&issue=mortality")!==false);
$ok('investigation links daily records',strpos($p,'Review Daily Records')!==false);
$ok('investigation links health history',strpos($p,'Review Health & Treatment')!==false);
$ok('investigation shows evidence gaps',strpos($p,'Evidence gaps')!==false);
foreach($tests as [$n,$v])echo ($v?'PASS':'FAIL')." - $n\n";$pass=count(array_filter($tests,fn($x)=>$x[1]));echo "$pass/".count($tests)." PASS\n";exit($pass===count($tests)?0:1);
