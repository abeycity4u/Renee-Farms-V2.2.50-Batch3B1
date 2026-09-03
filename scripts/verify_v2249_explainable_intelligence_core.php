<?php
$root=dirname(__DIR__); $checks=[];
function chk2(&$checks,$label,$ok){$checks[]=[$label,(bool)$ok];}
$intel=file_get_contents($root.'/lib/farm_intelligence.php');
$dash=file_get_contents($root.'/dashboard.php');
chk2($checks,'Explainable signal engine exists',strpos($intel,'farm_intelligence_explainable_signals')!==false);
chk2($checks,'Signals reuse canonical financial summary',substr_count($intel,'farm_intelligence_summary(')>=3);
chk2($checks,'Prior comparison uses same elapsed calendar days',strpos($intel,'same elapsed calendar days')!==false);
chk2($checks,'Signals expose measured values',strpos($intel,"'measured_value'")!==false);
chk2($checks,'Signals expose reasons',strpos($intel,"'reason'")!==false);
chk2($checks,'Signals expose source periods',strpos($intel,"'period_label'")!==false);
chk2($checks,'Inventory minimum signal exists',strpos($intel,"'inventory-low-stock'")!==false);
chk2($checks,'Uncosted USED stock warning exists',strpos($intel,"'data-uncosted-stock-use'")!==false && strpos($intel,'t.total_cost IS NULL')!==false);
chk2($checks,'Legacy sales UOM remains an explicit data-quality signal',strpos($intel,"'data-legacy-sales-uom'")!==false);
chk2($checks,'Poultry mortality basis signal reuses unit economics',strpos($intel,'getPoultryUnitEconomics(')!==false && strpos($intel,"'uncosted_mortality'")!==false);
chk2($checks,'Active poultry daily-record freshness is checked',strpos($intel,'daily record is not current')!==false);
chk2($checks,'Ruminant membership coverage signal exists',strpos($intel,"'ruminant-membership-coverage'")!==false);
chk2($checks,'Ruminant withdrawal signal uses recorded withdrawal dates',strpos($intel,"'ruminant-withdrawal'")!==false && strpos($intel,'withdrawal_until')!==false);
chk2($checks,'Ruminant stale open membership signal exists',strpos($intel,"'ruminant-open-exit-membership'")!==false);
chk2($checks,'Intelligence method uses current commercial wording',strpos($intel,'Farm Intelligence highlights recorded conditions')!==false && stripos($intel,'percentage')===false);
chk2($checks,'Dashboard uses explainable signal engine',strpos($dash,'farm_intelligence_explainable_signals(')!==false);
chk2($checks,'Dashboard removed old health score calculation',strpos($dash,'$smartHealthScore')===false);
chk2($checks,'Dashboard uses compact current-state intelligence wording',strpos($dash,'Management intelligence')!==false && stripos($dash,'hidden health percentage')===false);
chk2($checks,'Dashboard shows measured value and reason',strpos($dash,"['measured_value']")!==false && strpos($dash,"['reason']")!==false);
chk2($checks,'Dashboard label uses canonical Total Operating Cost',strpos($dash,'Total Operating Cost')!==false && strpos($dash,'Monthly Expenses')===false);

$full=file_get_contents($root.'/management/intelligence.php');
$nav=file_get_contents($root.'/navbar.php');
$reports=file_get_contents($root.'/management/reports.php');
chk2($checks,'Full Farm Intelligence page exists',is_file($root.'/management/intelligence.php') && strpos($full,'farm_intelligence_explainable_signals(')!==false);
chk2($checks,'Full intelligence page requires business-report access',strpos($full,'requireBusinessReportAccess();')!==false);
chk2($checks,'All intelligence signals are grouped rather than discarded',strpos($full,'foreach ($signals as $signal)')!==false && strpos($full,'foreach ($grouped as $category=>$categorySignals)')!==false);
chk2($checks,'Management navigation exposes Farm Intelligence',strpos($nav,'/management/intelligence.php')!==false);
chk2($checks,'Analytics CSV headers match canonical financial columns',strpos($reports,"'Feed Consumed'")!==false && strpos($reports,"'Other Operating Cost'")!==false && strpos($reports,"'Total Operating Cost'")!==false && strpos($reports,"'Feeds Expenses'")===false);

$pass=0; foreach($checks as [$label,$ok]){echo ($ok?'PASS':'FAIL')." - $label\n"; if($ok)$pass++;}
echo "\n{$pass}/".count($checks)." PASS\n"; exit($pass===count($checks)?0:1);
