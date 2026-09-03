<?php
$root=dirname(__DIR__);
$service=file_get_contents($root.'/lib/poultry_production_entry_snapshots.php');
$page=file_get_contents($root.'/management/poultry_cycle.php');
$migration=file_get_contents($root.'/migrations/031_poultry_production_entry_snapshots.sql');
$checks=[];
function ck(&$c,$ok,$m){$c[]=[$ok,$m]; echo ($ok?'PASS':'FAIL')." - $m\n";}
ck($checks,strpos($migration,'UNIQUE KEY uniq_poultry_entry_snapshot_version')!==false,'snapshot versions are unique per farm/cycle');
ck($checks,strpos($migration,"ENUM('original','revised')")!==false,'original and revised states are explicit');
ck($checks,strpos($migration,'source_fingerprint CHAR(64)')!==false,'source fingerprint is persisted');
ck($checks,strpos($service,"hash('sha256'")!==false,'candidate fingerprints source-derived economic facts');
ck($checks,strpos($service,'FOR UPDATE')!==false,'approval serializes version creation');
ck($checks,strpos($service,'source-derived economic basis has not changed')!==false,'unchanged basis cannot create duplicate version');
ck($checks,strpos($service,"version_no']+1")!==false,'revision appends a new version');
ck($checks,strpos($service,'UPDATE poultry_production_entry_snapshots')===false,'approved snapshots are not overwritten');
ck($checks,strpos($service,'production_entry_confirmation')!==false && strpos($service,'source_transaction_correction')!==false,'structured correction categories are enforced');
ck($checks,strpos($service,'Bird Cost Basis')===false && strpos($service,'bird_unit_cost')===false,'snapshot service does not mutate mortality valuation basis');
ck($checks,strpos($page,'Historical economics changed after the latest approval.')!==false,'workspace detects changed source-derived economics');
ck($checks,strpos($page,'Confirm Entry Basis')!==false && strpos($page,'Approve Revised Basis')!==false,'workspace separates first approval from revisions');
ck($checks,strpos($page,'Attributed Rearing Investment')!==false && strpos($page,'Complete Rearing Investment')===false,'financial terminology uses attributed rather than falsely complete');
ck($checks,strpos($page,'do not replace source accounting here')!==false,'revision UI directs correction to source records');
ck($checks,strpos($page,'Bird Cost Basis is not changed')!==false,'workspace preserves Bird Cost Basis boundary');
$fail=count(array_filter($checks,fn($x)=>!$x[0])); echo "\n".(count($checks)-$fail)."/".count($checks)." checks passed.\n"; exit($fail?1:0);
