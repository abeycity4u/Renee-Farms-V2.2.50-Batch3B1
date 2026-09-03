<?php
$root=dirname(__DIR__);
$checks=[];
function chk(&$checks,$label,$ok){$checks[]=[$label,(bool)$ok];}
$intel=file_get_contents($root.'/lib/farm_intelligence.php');
$dash=file_get_contents($root.'/dashboard.php');
$reports=file_get_contents($root.'/management/reports.php');
$api=file_get_contents($root.'/api/get_chart_data.php');
chk($checks,'Canonical intelligence service exists',is_file($root.'/lib/farm_intelligence.php'));
chk($checks,'Intelligence delegates profitability to canonical engine',strpos($intel,'getProfitabilitySummary(')!==false);
chk($checks,'Monthly series uses canonical intelligence',strpos($intel,'farm_intelligence_monthly_series')!==false);
chk($checks,'Expense intelligence separates Feed Consumed',strpos($intel,"'Feed Consumed'")!==false);
chk($checks,'Top products group by UOM',strpos($intel,'GROUP BY product_type, unit_of_measure')!==false);
chk($checks,'Legacy UOM remains explicit',strpos($intel,'sales_unit_label(null)')!==false);
chk($checks,'Dashboard uses canonical intelligence',strpos($dash,'farm_intelligence_summary(')!==false);
chk($checks,'Dashboard no longer queries legacy summary',strpos($dash,'FROM profit_loss_summary')===false);
chk($checks,'Analytics uses canonical monthly series',strpos($reports,'farm_intelligence_monthly_series(')!==false);
chk($checks,'Analytics removed hardcoded feed expense formula',strpos($reports,"feeds_expenses")===false);
chk($checks,'Analytics top product label is UOM-aware',strpos($reports,"display_product")!==false);
chk($checks,'Chart API requires report access',strpos($api,'requireBusinessReportAccess();')!==false);
chk($checks,'Chart profit uses canonical rolling series',strpos($api,'farm_intelligence_rolling_months(')!==false);
chk($checks,'Year chart uses 12 rolling months',strpos($api,'farm_intelligence_rolling_months($pdo, $farmId, $months, $scope)')!==false);
chk($checks,'Sales API keeps General separate',strpos($api,"'general'=>[]")!==false && strpos($api,"foreach (['poultry','ruminant','general']")!==false);
$pass=0; foreach($checks as [$label,$ok]){echo ($ok?'PASS':'FAIL')." - $label\n"; if($ok)$pass++;}
echo "\n{$pass}/".count($checks)." PASS\n"; exit($pass===count($checks)?0:1);
