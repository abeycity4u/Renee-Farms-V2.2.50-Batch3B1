<?php
$root=dirname(__DIR__);$checks=[];
function ck($n,$v){global $checks;$checks[]=[$n,(bool)$v];}
$h=file_get_contents($root.'/lib/investigation_followup.php');
$r=file_get_contents($root.'/management/ruminant_investigation.php');
$p=file_get_contents($root.'/management/investigation.php');
ck('history helper exists',str_contains($h,'function investigation_followup_prior_history'));
ck('history excludes current episode',str_contains($h,"f.episode_key<>?"));
ck('history is newest first',str_contains($h,'ORDER BY f.as_of_date DESC,f.id DESC'));
ck('ruminant loads prior history',str_contains($r,'investigation_followup_prior_history'));
ck('poultry loads prior history',str_contains($p,'investigation_followup_prior_history'));
ck('ruminant history remains after current follow-up exists',str_contains($r,'if(!empty($priorHistory))')&&!str_contains($r,'if(!$followup && $priorFollowup)'));
ck('poultry history remains after current follow-up exists',str_contains($p,'if(!empty($priorHistory))')&&!str_contains($p,'if(!$followup && $priorFollowup)'));
ck('history explains current episode isolation',str_contains($r,'They do not resolve or alter the current evidence window.')&&str_contains($p,'They do not resolve or alter the current evidence window.'));
ck('history preserves outcome finding action',str_contains($r,'<strong>Outcome:</strong>')&&str_contains($r,'<strong>Finding:</strong>')&&str_contains($r,'<strong>Action:</strong>'));
$bad=array_filter($checks,fn($x)=>!$x[1]);foreach($checks as [$n,$ok])echo ($ok?'PASS':'FAIL')." - $n\n";exit($bad?1:0);
