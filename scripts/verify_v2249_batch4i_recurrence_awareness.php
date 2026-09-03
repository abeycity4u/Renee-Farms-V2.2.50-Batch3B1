<?php
$root=dirname(__DIR__);$checks=[];function ck($n,$ok){global $checks;$checks[]=[$n,$ok];echo ($ok?'PASS':'FAIL')." — $n\n";}
$f=file_get_contents($root.'/lib/investigation_followup.php');$i=file_get_contents($root.'/lib/farm_intelligence.php');$ui=file_get_contents($root.'/management/intelligence.php');$d=file_get_contents($root.'/dashboard.php');
ck('follow-through computes latest source evidence dates',str_contains($f,'MAX(record_date) evidence_date')&&str_contains($f,'MAX(weight_date) evidence_date'));
ck('resolved reviews detect later source evidence',str_contains($f,'$hasNewEvidence')&&str_contains($f,"'new_activity'"));
ck('new evidence does not mutate prior follow-up row',!str_contains($f,'UPDATE management_investigation_followups SET status'));
ck('poultry investigate links carry intelligence as-of date',substr_count($i,"&as_of='.urlencode(\$asOfDate)")>=3);
ck('ruminant investigate link remains as-of scoped',str_contains($i,"ruminant_investigation.php?animal_id=")&&str_contains($i,"&as_of='.urlencode(\$asOfDate)"));
ck('full intelligence shows new activity since review',str_contains($ui,'New activity since review'));
ck('full intelligence shows latest new source record date',str_contains($ui,'new source record'));
ck('dashboard shows recurrence awareness',str_contains($d,'New activity since review'));
ck('previously resolved state retained when no new evidence',str_contains($ui,'Previously resolved')&&str_contains($d,'Previously resolved'));
ck('open follow-up state retained',str_contains($ui,'Follow-up open')&&str_contains($d,'Follow-up open'));
$fail=count(array_filter($checks,fn($x)=>!$x[1]));echo "\n".count($checks)." checks, $fail failure(s).\n";exit($fail?1:0);
