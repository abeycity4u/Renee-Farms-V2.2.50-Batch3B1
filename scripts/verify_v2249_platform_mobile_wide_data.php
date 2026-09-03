<?php
$root = dirname(__DIR__);
$css = file_get_contents($root.'/assets/css/responsive.css');
$checks = [];
function v($name,$ok){ global $checks; $checks[$name]=(bool)$ok; }
v('responsive table overflow is forced', strpos($css,'overflow-x: auto !important')!==false);
v('table cells prevent character breaking', strpos($css,'word-break: normal !important')!==false && strpos($css,'overflow-wrap: normal !important')!==false);
v('wide tables gain column-aware minimum widths', strpos($css,':has(thead th:nth-child(11))')!==false);
v('calendar scroll primitive exists', strpos($css,'.app-calendar-scroll')!==false && strpos($css,'43.75rem')!==false);
v('user table clipping override exists', strpos($css,'.users-table-wrap.table-responsive')!==false);
foreach(['poultry/layers_daily_record.php','poultry/broiler_daily_record.php','ruminant/ruminant_daily_record.php'] as $rel){
  $x=file_get_contents($root.'/'.$rel);
  v($rel.' uses centralized calendar scroll', strpos($x,'app-calendar-scroll')!==false);
}
// Every rendered application page with an HTML table should use a responsive wrapper.
$skip=['sales_report_pdf.php','expense_report_pdf.php','debt_history_pdf.php'];
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
$unwrapped=[];
foreach($it as $f){
  if($f->getExtension()!=='php') continue;
  $path=$f->getPathname();
  if(strpos($path,DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)!==false) continue;
  if(strpos($path,DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR)!==false) continue;
  if(strpos($path,DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR)!==false) continue;
  if(in_array(basename($path),$skip,true)) continue;
  $x=file_get_contents($path);
  if(strpos($x,'<table')!==false && strpos($x,'table-responsive')===false) $unwrapped[]=str_replace($root.'/','',$path);
}
v('all application HTML-table pages expose a responsive wrapper', count($unwrapped)===0);
$fail=0; foreach($checks as $name=>$ok){ echo ($ok?'PASS':'FAIL')." - $name\n"; if(!$ok)$fail++; }
if($unwrapped) echo 'Unwrapped: '.implode(', ',$unwrapped)."\n";
echo count($checks)." checks, $fail failures\n";
exit($fail?1:0);
