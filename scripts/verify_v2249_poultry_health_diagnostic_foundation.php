<?php
$root = dirname(__DIR__);
$checks = [];
function checkit(&$checks,$ok,$label){ $checks[] = [$ok,$label]; echo ($ok?'PASS':'FAIL')." - {$label}\n"; }
$migration = file_get_contents($root.'/migrations/033_poultry_health_events.sql');
$page = file_get_contents($root.'/poultry/health.php');
$lib = file_get_contents($root.'/lib/poultry_health.php');
$nav = file_get_contents($root.'/navbar.php');
$layer = file_get_contents($root.'/poultry/layers_daily_record.php');
$broiler = file_get_contents($root.'/poultry/broiler_daily_record.php');
checkit($checks, strpos($migration,'CREATE TABLE IF NOT EXISTS poultry_health_events')!==false, 'migration creates poultry_health_events');
checkit($checks, strpos($migration,"production_type ENUM('layer','broiler')")!==false, 'health events are Layer/Broiler scoped');
checkit($checks, strpos($migration,'stock_item_id INT NULL')!==false, 'optional inventory reference exists');
checkit($checks, strpos($page,"ensureAllowed('poultry_health')")!==false, 'page enforces poultry_health permission');
checkit($checks, strpos($page,'verify_csrf_token')!==false, 'health writes verify CSRF');
checkit($checks, strpos($page,'poultry_health_validate_cycle')!==false, 'cycle/species/date validation is used');
checkit($checks, strpos($page,'Reference only; no stock is deducted here.')!==false, 'inventory reference avoids hidden stock mutation');
checkit($checks, strpos($lib,'poultry_health_recent_for_cycle')!==false, 'diagnostic context helper exists');
checkit($checks, strpos($lib,"['medication_vaccine','supplement']")!==false, 'linked inventory classification is constrained');
checkit($checks, strpos($nav,'/poultry/health.php')!==false, 'navbar exposes Health & Treatment');
checkit($checks, strpos($layer,'Quick daily note only.')!==false, 'Layer legacy medication field is clarified');
checkit($checks, strpos($broiler,'Quick daily note only.')!==false, 'Broiler legacy medication field is clarified');
checkit($checks, strpos($page,'Reason / Symptoms')!==false, 'structured symptom/reason evidence is captured');
checkit($checks, strpos($page,'Recorded By')!==false, 'health history exposes recorder');
checkit($checks, strpos($page,'audit_log_event')!==false, 'health mutations are audit logged');
checkit($checks, strpos($page,"bootstrap5/js/bootstrap.bundle.min.js")!==false, 'Poultry Health page loads Bootstrap modal runtime');
$fails = count(array_filter($checks, fn($x)=>!$x[0]));
echo "\n".(count($checks)-$fails)."/".count($checks)." PASS\n";
exit($fails?1:0);

