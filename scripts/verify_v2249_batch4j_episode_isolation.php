<?php
$root=dirname(__DIR__);$ok=0;$n=0;function ck($m,$v){global $ok,$n;$n++;echo ($v?'PASS':'FAIL')." - $m\n";if($v)$ok++;}
$m=file_get_contents($root.'/migrations/036_management_investigation_episode_identity.sql');
$f=file_get_contents($root.'/lib/investigation_followup.php');
$r=file_get_contents($root.'/management/ruminant_investigation.php');
$p=file_get_contents($root.'/management/investigation.php');
$d=file_get_contents($root.'/lib/poultry_diagnostics.php');
ck('migration adds episode key',str_contains($m,'ADD COLUMN episode_key')&&str_contains($m,'uniq_management_investigation_episode'));
ck('legacy reviews preserved without guessed evidence mapping',str_contains($m,"CONCAT('legacy:', id)"));
ck('deterministic episode helper exists',str_contains($f,'investigation_followup_episode_key'));
ck('exact follow-up lookup uses episode key',str_contains($f,'f.episode_key=?'));
ck('save persists episode key',str_contains($f,'as_of_date,episode_key,status'));
ck('prior review helper remains read-only history',str_contains($f,'investigation_followup_latest_prior'));
ck('ruminant uses diagnostic from/to dates',str_contains($r,"(string)\$d['from_date']")&&str_contains($r,"(string)\$d['to_date']"));
ck('poultry uses diagnostic from/to dates',str_contains($p,"(string)\$d['from_date']")&&str_contains($p,"(string)\$d['to_date']"));
ck('poultry diagnostic exposes evidence window',str_contains($d,"'from_date'=>")&&str_contains($d,"'to_date'=>"));
ck('UI separates previous investigation from new episode',str_contains($r,'Previous investigation')&&str_contains($r,'New investigation episode')&&str_contains($p,'Previous investigation'));
exit($ok===$n?0:1);
