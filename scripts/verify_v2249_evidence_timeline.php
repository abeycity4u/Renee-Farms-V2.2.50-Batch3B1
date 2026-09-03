<?php
$root=dirname(__DIR__);$checks=[];
function v($name,$ok){global $checks;$checks[]=[$name,(bool)$ok];}
$d=file_get_contents($root.'/lib/poultry_diagnostics.php');$p=file_get_contents($root.'/management/investigation.php');
v('diagnostic engine returns timeline',strpos($d,"'timeline'=>\$timeline")!==false);
v('timeline is date sorted',strpos($d,'usort($timeline')!==false);
v('feed changes enter timeline',strpos($d,"'feed','Feed item changed'")!==false);
v('mortality enters timeline',strpos($d,"'mortality','Mortality recorded'")!==false);
v('water per bird movement enters timeline',strpos($d,"'water','Water intake/bird moved'")!==false);
v('layer egg decline enters timeline',strpos($d,"'production','Egg output declined'")!==false);
v('health events enter timeline',strpos($d,"'health','Health & Treatment event'")!==false);
v('timeline explicitly avoids causation claim',strpos($p,'recorded sequence, not causation')!==false);
v('investigation renders Evidence timeline',strpos($p,'Evidence timeline')!==false);
v('timeline safely escapes title and detail',strpos($p,"htmlspecialchars(\$t['title'])")!==false && strpos($p,"htmlspecialchars(\$t['detail'])")!==false);
$pass=0;foreach($checks as [$n,$ok]){echo ($ok?'PASS':'FAIL')." - $n\n";$pass+=$ok?1:0;}echo "$pass/".count($checks)." PASS\n";exit($pass===count($checks)?0:1);
