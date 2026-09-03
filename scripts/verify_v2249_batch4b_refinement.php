<?php
$root=dirname(__DIR__);$tests=[];
$read=fn($f)=>file_get_contents($root.'/'.$f);
foreach(['poultry/layers_daily_record.php','poultry/broiler_daily_record.php','ruminant/ruminant_daily_record.php'] as $f){$s=$read($f);$tests[$f.' Feed Item column']=str_contains($s,'<th>Feed Item</th>');$tests[$f.' Not assigned']=str_contains($s,"'Not assigned'");$tests[$f.' historical resolver']=str_contains($s,'historicalFeedItemNames');}
$fi=$read('lib/farm_intelligence.php');$pd=$read('lib/poultry_diagnostics.php');
$tests['Layer flock deterioration trigger']=str_contains($fi,'poultry-layer-flock-deterioration-');
$tests['Absolute egg output evidence']=str_contains($pd,'Absolute egg output');
$tests['Live flock decline evidence']=str_contains($pd,'Live flock declined');
$tests['Dated feed sequence']=str_contains($pd,'Recorded feed sequence:');
$tests['Bird age continuity check']=str_contains($pd,'Bird age did not advance');
$n=0;foreach($tests as $name=>$ok){echo ($ok?'PASS':'FAIL')." - $name\n";if($ok)$n++;}echo "$n/".count($tests)." PASS\n";exit($n===count($tests)?0:1);
