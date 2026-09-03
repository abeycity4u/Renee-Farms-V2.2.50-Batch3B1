<?php
$root = dirname(__DIR__);
$checks = [
    'migration 026 category financial type' => [$root.'/migrations/026_inventory_category_financial_type.sql', 'ADD COLUMN financial_type'],
    'category add captures financial type' => [$root.'/inventory.php', 'name="category_financial_type"'],
    'category is add-item source of truth' => [$root.'/inventory.php', 'SELECT id, financial_type FROM inventory_categories'],
    'feed/category consistency validation' => [$root.'/inventory.php', 'Financial Type is Feed'],
    'inventory financial controlled list' => [$root.'/lib/inventory_financial.php', "'medication_vaccine' => 'Medication / Vaccine'"],
    'single release notes' => [$root.'/RELEASE_NOTES.txt', 'CONSOLIDATED RELEASE NOTES'],
    'single batch notes' => [$root.'/BATCH_NOTES.txt', 'CONSOLIDATED IMPLEMENTATION / QA NOTES'],
];
$failed = false;
foreach ($checks as $label => [$file,$needle]) {
    $ok = is_file($file) && strpos((string)file_get_contents($file), $needle) !== false;
    echo ($ok ? '[PASS] ' : '[FAIL] ').$label.PHP_EOL;
    if (!$ok) $failed = true;
}
foreach (['poultry/layer_expenses.php','poultry/broiler_expenses.php','ruminant/ruminant_expenses.php'] as $rel) {
    $text=(string)file_get_contents($root.'/'.$rel);
    $pos=strpos($text,'<!-- Add Expense Modal -->');
    $add=$pos===false?'':substr($text,$pos);
    $ok=strpos($add,'option value="medication"')===false && strpos($add,'option value="feeds"')===false;
    echo ($ok?'[PASS] ':'[FAIL] ').$rel.' new manual Feed/Medication hidden'.PHP_EOL;
    if(!$ok)$failed=true;
}
$rootNotes = glob($root.'/*NOTES*.txt') ?: [];
$rootRelease = glob($root.'/RELEASE*.txt') ?: [];
$allowed = [$root.'/BATCH_NOTES.txt',$root.'/RELEASE_NOTES.txt'];
$extras=array_values(array_filter(array_merge($rootNotes,$rootRelease), fn($f)=>!in_array($f,$allowed,true)));
$ok=count($extras)===0;
echo ($ok?'[PASS] ':'[FAIL] ').'no version-specific root note files'.PHP_EOL;
if(!$ok){foreach($extras as $f)echo '  extra: '.basename($f).PHP_EOL;$failed=true;}
exit($failed?1:0);
