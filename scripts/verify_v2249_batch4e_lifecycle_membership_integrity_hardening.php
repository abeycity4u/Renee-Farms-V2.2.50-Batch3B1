<?php
$root=dirname(__DIR__);$checks=[];
function c4eh($name,$ok){global $checks;$checks[]=[$name,(bool)$ok];}
$service=file_get_contents($root.'/lib/ruminant_lifecycle_service.php');
$mem=file_get_contents($root.'/lib/ruminant_cycle_membership.php');
$sale=file_get_contents($root.'/lib/ruminant_animal_exit.php');
$manual=file_get_contents($root.'/lib/ruminant_lifecycle_integrity.php');
$fi=file_get_contents($root.'/lib/farm_intelligence.php');
$page=file_get_contents($root.'/management/ruminant_membership_integrity.php');
$migration=file_get_contents($root.'/migrations/034_ruminant_membership_exit_boundary_integrity.sql');
c4eh('Central lifecycle boundary service exists',is_file($root.'/lib/ruminant_lifecycle_service.php') && strpos($service,'ruminant_lifecycle_apply_exit_boundary')!==false);
c4eh('Exit closure remembers reversible prior membership end date',strpos($migration,'closed_by_exit_event_id')!==false && strpos($migration,'pre_exit_end_date')!==false && strpos($service,'pre_exit_end_date=end_date')!==false);
c4eh('Manual exits use central membership boundary',strpos($manual,'ruminant_lifecycle_apply_exit_boundary')!==false);
c4eh('Sale exits use central membership boundary',strpos($sale,'ruminant_lifecycle_apply_exit_boundary')!==false);
c4eh('Sale exit reversals restore auto-closed membership boundary',substr_count($sale,'ruminant_lifecycle_reverse_exit_boundary')>=2);
c4eh('Exited animals cannot receive open membership beyond exit',strpos($mem,"Membership for an exited animal must end on or before its recorded exit date")!==false);
c4eh('Membership close cannot extend beyond recorded exit',strpos($mem,"Membership cannot extend beyond the animal's recorded exit date")!==false);
c4eh('Integrity review page exists and is CSRF protected',is_file($root.'/management/ruminant_membership_integrity.php') && strpos($page,'verify_csrf_token')!==false);
c4eh('Repair is explicit and audit logged',strpos($page,'Close at Exit Date')!==false && strpos($page,'audit_log_event')!==false);
c4eh('Farm Intelligence links directly to membership review',strpos($fi,'management/ruminant_membership_integrity.php')!==false && strpos($fi,'Review memberships')!==false);
c4eh('Repair does not silently create lifecycle exits',strpos($service,'INSERT INTO ruminant_animal_exit_events')===false);
c4eh('Migration 034 is present',is_file($root.'/migrations/034_ruminant_membership_exit_boundary_integrity.sql'));
$fail=0;foreach($checks as [$n,$ok]){echo ($ok?'PASS':'FAIL')." - $n\n";if(!$ok)$fail++;}echo count($checks)." checks, $fail failures\n";exit($fail?1:0);
