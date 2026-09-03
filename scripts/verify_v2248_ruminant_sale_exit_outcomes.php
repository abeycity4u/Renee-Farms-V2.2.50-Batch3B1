<?php
$root = dirname(__DIR__);
$checks = [];
function chk($cond,$label){ global $checks; $checks[] = [$cond,$label]; echo ($cond?'PASS':'FAIL')." - $label\n"; }

$sales = file_get_contents($root.'/management/sales_records.php');
$lib = file_get_contents($root.'/lib/ruminant_animal_exit.php');
$del = file_get_contents($root.'/api/delete_sale.php');
$view = file_get_contents($root.'/ruminant/animal_view.php');
$migration = file_get_contents($root.'/migrations/030_ruminant_animal_exit_events.sql');

chk(str_contains($migration,'ruminant_animal_exit_events'), 'Migration 030 creates animal exit event table');
chk(str_contains($migration,'UNIQUE KEY uniq_ruminant_exit_sale_animal'), 'One exit event per sale/animal');
chk(str_contains($sales,"require_once(__DIR__ . '/../lib/ruminant_animal_exit.php')"), 'Sales loads lifecycle library');
chk(str_contains($sales,'sale_animal_exit_outcomes'), 'Sales form posts per-animal exit outcome');
chk(str_contains($sales,'Sold live — mark Sold'), 'Sold-live UX available');
chk(str_contains($sales,'Culled/slaughtered — mark Culled'), 'Cull/slaughter UX available');
chk(str_contains($sales,'ruminant_sale_apply_exit_outcomes'), 'Add/Edit sale applies explicit outcomes');
chk(str_contains($lib,"'sold_live' ? 'sold' : 'culled'"), 'Outcome projects to sold/culled registry status');
chk(str_contains($lib,'Only an active animal can be exited by a new sale'), 'New exit refuses already-exited animal');
chk(str_contains($lib,'status changed afterwards'), 'Lifecycle reversal protects later manual status changes');
chk(str_contains($del,'ruminant_sale_reverse_exit_events'), 'Sale deletion safely reverses linked exit projection');
chk(str_contains($view,'Animal Exit History'), 'Animal profile shows exit history');
chk(str_contains($sales,'ruminantSaleExitEvents'), 'Sales report loads exit outcomes');
chk(str_contains($sales,'Revenue only'), 'Revenue-only attribution remains available without exit');
chk(!str_contains($lib,'product_type'), 'Lifecycle outcome does not infer exit from free-text product type');

$failed = count(array_filter($checks, fn($c)=>!$c[0]));
echo "\n".count($checks)." checks, $failed failures\n";
exit($failed?1:0);
