<?php
$root=dirname(__DIR__);
$files=[
  $root.'/management/poultry_cycle.php',
  $root.'/management/production_cycles.php'
];
$bad=['V2.2.50 Batch 3B','V2.2.50 Batch 3A','V2.2.50 Batch 2','V2.2.50 Batch 1'];
$checks=0; $fails=0;
foreach($files as $f){
  $s=file_exists($f)?file_get_contents($f):'';
  foreach($bad as $label){
    $checks++;
    $ok=strpos($s,$label)===false;
    echo ($ok?'PASS':'FAIL')." - tenant UI hides development label {$label} in ".basename($f)."\n";
    if(!$ok)$fails++;
  }
}
echo "\n".($checks-$fails)."/{$checks} checks passed.\n";
exit($fails?1:0);
