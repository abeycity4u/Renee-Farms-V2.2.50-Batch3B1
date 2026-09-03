<?php
$root=dirname(__DIR__);$checks=[];
function ck($ok,$m){global $checks;$checks[]=[$ok,$m];echo ($ok?'PASS':'FAIL')." - $m\n";}
$m=file_get_contents($root.'/migrations/035_management_investigation_followups.sql');
$l=file_get_contents($root.'/lib/investigation_followup.php');
$p=file_get_contents($root.'/management/investigation.php');
$r=file_get_contents($root.'/management/ruminant_investigation.php');
ck(strpos($m,'management_investigation_followups')!==false,'follow-up migration exists');
ck(strpos($m,'UNIQUE KEY uniq_management_investigation')!==false,'investigation identity is historically scoped');
ck(strpos($l,'Measurement / data error')!==false && strpos($l,'Veterinary review required')!==false,'controlled outcomes exist');
ck(strpos($l,'finding_notes')!==false && strpos($l,'action_taken')!==false,'finding and action are persisted');
ck(strpos($p,'Management follow-through')!==false && strpos($r,'Management follow-through')!==false,'both investigation pages expose follow-through');
ck(strpos($p,"audit_log_event")!==false && strpos($r,"audit_log_event")!==false,'writes are audit logged');
ck(strpos($p,"data-confirm")!==false && strpos($r,"data-confirm")!==false,'resolution uses central confirmation architecture');
ck(strpos($p,'does not edit Daily Records')!==false && strpos($r,'does not edit Daily Records')!==false,'UI states source history is not silently edited');
ck(strpos($p,"verify_csrf_token(\$_POST['csrf_token'] ?? '')")!==false && strpos($r,"verify_csrf_token(\$_POST['csrf_token'] ?? '')")!==false,'follow-up writes use the platform CSRF verifier');
ck(strpos($p,'csrf_verify()')===false && strpos($r,'csrf_verify()')===false,'no undefined csrf_verify helper remains');
ck(strpos($p,"includes/functions.php")!==false && strpos($p,"ensureAllowed(")>strpos($p,"includes/functions.php"),'Poultry Investigation loads permission helpers before ensureAllowed');
$fails=count(array_filter($checks,fn($x)=>!$x[0])); echo "\n".(count($checks)-$fails).'/'.count($checks)." checks passed.\n"; exit($fails?1:0);
