<?php
$root=dirname(__DIR__);
$checks=[];
function ck($ok,$label){global $checks;$checks[]=[$ok,$label];echo ($ok?'PASS':'FAIL')." - $label\n";}
$reg=file_get_contents($root.'/ruminant/animal_registry.php');
$view=file_get_contents($root.'/ruminant/animal_view.php');
$life=file_get_contents($root.'/lib/ruminant_lifecycle_integrity.php');
$mem=file_get_contents($root.'/lib/ruminant_cycle_membership.php');
ck(str_contains($reg,'manual_exit'),'Registry records non-sale exits explicitly');
ck(str_contains($reg,'Effective Date'),'Manual exit requires an effective date');
ck(str_contains($reg,'Dead')&&str_contains($reg,'Transferred')&&str_contains($reg,'Culled'),'Non-sale outcomes cover dead/transferred/culled');
ck(!str_contains($reg,'name="status" id="status"'),'Animal edit cannot directly overwrite lifecycle status');
ck(str_contains($life,'ruminant_animal_exit_events'),'Manual exits use canonical exit-event history');
ck(str_contains($life,'ruminant_lifecycle_apply_exit_boundary') || str_contains($life,'ruminant_cycle_close_open_membership_at_exit'),'Manual exit closes open membership history');
ck(str_contains($mem,'exit_date < ?'),'Exit-date eligibility remains inclusive on exit date');
ck(str_contains($reg,'Species cannot be changed after operational/economic history exists'),'Species changes are protected after history exists');
ck(str_contains($reg,'has_history'),'Species-lock UX is exposed to edit form');
ck(str_contains($mem,'ruminant_cycle_membership_has_financial_activity'),'Membership deletion checks financial activity');
ck(str_contains($mem,'cannot be deleted because that would rewrite historical shared-cost economics'),'Historical membership deletion is blocked');
ck(str_contains($mem,'ruminant_cycle_membership_close'),'Membership can be closed with an effective date');
ck(str_contains($view,'close_cycle_membership'),'Animal profile exposes membership closing workflow');
ck(str_contains($view,'ruminant_exit_outcome_display'),'Exit history supports sale and non-sale outcomes');
$fail=array_filter($checks,fn($x)=>!$x[0]); echo "\n".count($checks)." checks, ".count($fail)." failed.\n"; exit($fail?1:0);
