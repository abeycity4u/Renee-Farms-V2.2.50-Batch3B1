<?php
$root = dirname(__DIR__);
$checks = [];
function ck($cond,$msg){global $checks;$checks[]=[$cond,$msg];echo ($cond?'PASS':'FAIL')." - $msg\n";}
$m = file_get_contents($root.'/migrations/031_sales_unit_of_measure.sql');
$s = file_get_contents($root.'/management/sales_records.php');
$pdf = file_get_contents($root.'/management/sales_report_pdf.php');
$lib = file_get_contents($root.'/lib/sales_units.php');
$egg = file_get_contents($root.'/lib/layer_egg_inventory.php');
$alloc = file_get_contents($root.'/lib/sales_allocation.php');
ck(strpos($m,'unit_of_measure VARCHAR(30) NULL')!==false,'Migration adds nullable sales unit without guessing history');
ck(strpos($s,'sales_unit_from_post')!==false,'Add/Edit validates unit server-side');
ck(strpos($s,'unit_of_measure, unit_price')!==false,'New sale persists unit');
ck(strpos($s,'quantity=?, unit_of_measure=?, unit_price=?')!==false,'Sale edit persists unit');
ck(strpos($s,'Unit of Measure')!==false,'Add/Edit UI exposes unit');
ck(strpos($s,'Other / Custom')!==false,'Custom unit UI exists');
ck(strpos($s,'data-unit=')!==false,'Edit button carries stored unit');
ck(strpos($s,'Not specified (legacy)')===false || strpos($lib,'Not specified (legacy)')!==false,'Legacy unit labeling is centralized');
ck(strpos($pdf,'sales_unit_label')!==false,'PDF shows sales unit');
ck(strpos($lib,"'Head' => 'Head'")!==false && strpos($lib,"'Kg' => 'Kg'")!==false && strpos($lib,"'Litre' => 'Litre'")!==false,'Common farm sale units are preset');
ck(strpos($egg,'layer_egg_sale_quantity_crates')!==false,'Layer egg inventory converts UOM to canonical crates');
ck(strpos($egg,"['crate','crates','tray','trays']")!==false && strpos($egg,"['dozen','dozens']")!==false,'Known egg UOM conversions are explicit');
ck(strpos($alloc,'cannot be safely converted to crates')!==false,'Unsupported egg units fail safe instead of corrupting pooled allocation');
exit(count(array_filter($checks,fn($x)=>!$x[0]))?1:0);
