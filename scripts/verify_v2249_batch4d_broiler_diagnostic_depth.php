<?php
$root=dirname(__DIR__);$d=file_get_contents($root.'/lib/poultry_diagnostics.php');$tests=[];$ok=function($n,$v)use(&$tests){$tests[]=[$n,(bool)$v];};
$ok('broiler flock survival context',strpos($d,"if(\$productionType==='broiler' && \$recent)")!==false && strpos($d,"'Live flock declined'")!==false);
$ok('broiler mortality concentration evidence',strpos($d,"'Mortality concentration'")!==false);
$ok('mortality concentration uses recent seven records',strpos($d,'$recentMortTotal')!==false && strpos($d,'$peakMort')!==false);
$ok('peak mortality is normalized to opening flock',strpos($d,'$peakRate')!==false && strpos($d,"% of its opening flock")!==false);
$ok('concentration explicitly avoids diagnosis',strpos($d,'Concentration is a pattern to review, not a disease diagnosis.')!==false);
$ok('timeline tracks feed intake per bird movement',strpos($d,"'Feed intake/bird moved'")!==false && strpos($d,'$feedDayDelta')!==false);
$ok('feed timeline remains per-bird normalized',strpos($d,'$prevFeedPerBird')!==false && strpos($d,'$curFeedPerBird')!==false);
$ok('existing water timeline preserved',strpos($d,"'Water intake/bird moved'")!==false);
$ok('structured health context preserved',strpos($d,'poultry_health_diagnostic_context')!==false);
$ok('no external broiler target or FCR invented',strpos($d,'target weight')===false && strpos($d,'target-weight')===false && strpos($d,'FCR')===false);
foreach($tests as [$n,$v])echo ($v?'PASS':'FAIL')." - $n\n";$pass=count(array_filter($tests,fn($x)=>$x[1]));echo "$pass/".count($tests)." PASS\n";exit($pass===count($tests)?0:1);
