<?php
$root=dirname(__DIR__); $checks=[];
function ck($n,$ok){global $checks;$checks[]=[$n,$ok];echo ($ok?'PASS':'FAIL')." — $n\n";}
$f=file_get_contents($root.'/lib/investigation_followup.php');
$i=file_get_contents($root.'/lib/farm_intelligence.php');
$ui=file_get_contents($root.'/management/intelligence.php');
$d=file_get_contents($root.'/dashboard.php');
ck('central batch follow-up annotation helper exists',str_contains($f,'function investigation_followup_annotate_signals'));
ck('annotation is one batched follow-up query',str_contains($f,'management_investigation_followups') && str_contains($f,'ORDER BY f.as_of_date DESC'));
ck('latest prior follow-up can provide continuing-condition context',str_contains($f,"f.as_of_date<=?"));
ck('farm intelligence delegates follow-up awareness centrally',str_contains($i,'investigation_followup_annotate_signals($pdo,$farmId,$signals,$asOfDate)'));
ck('signal severity is not overwritten by follow-up state',!str_contains($f,"['severity']='success'"));
ck('farm intelligence page shows follow-up open state',str_contains($ui,'Follow-up open'));
ck('farm intelligence page shows previously resolved state',str_contains($ui,'Previously resolved'));
ck('full intelligence page labels prior review context',str_contains($ui,'previous review context'));
ck('dashboard shows compact follow-up awareness',str_contains($d,'Previously resolved') && str_contains($d,'Follow-up open'));
ck('investigate action remains available',str_contains($ui,"signal['action_label']"));
$fail=count(array_filter($checks,fn($x)=>!$x[1]));echo "\n".count($checks)." checks, $fail failure(s).\n";exit($fail?1:0);
