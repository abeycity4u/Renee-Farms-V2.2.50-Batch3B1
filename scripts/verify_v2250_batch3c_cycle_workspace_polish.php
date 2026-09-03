<?php
$root=dirname(__DIR__);
$file=$root.'/management/poultry_cycle.php';
$source=file_exists($file)?file_get_contents($file):'';

$checks=[
  'workspace navigation group is labelled' => 'Workspace sections',
  'source module navigation group is labelled' => 'Source modules',
  'economic basis workspace anchor is exposed' => 'href="#economic-basis">Economic Basis</a>',
  'daily records are marked as source-module navigation' => 'Daily Records ↗',
  'feed records are marked as source-module navigation' => 'Feed Records ↗',
  'health records are marked as source-module navigation' => 'Health & Treatment ↗',
  'expenses are marked as source-module navigation' => 'Expenses ↗',
  'approved basis history heading is present' => 'Approved Basis History',
  'immutable-history note is present' => 'Newest approved version first · previous versions remain immutable',
  'latest approved snapshot is visibly identified as current' => 'badge bg-success ms-1">Current</span>',
  'revision detail presentation separates category styling' => 'history-category',
  'revision reason presentation is readable' => 'history-reason',
  'duplicate shared-expense warning suppression remains scoped' => 'str_starts_with((string)$warning,\'Unallocated shared Layer expenses exist in the Rearing window.\')',
];

$fails=0;
foreach($checks as $label=>$needle){
  $ok=$source!=='' && strpos($source,$needle)!==false;
  echo ($ok?'PASS':'FAIL')." - {$label}\n";
  if(!$ok)$fails++;
}

$forbidden=[
  'V2.2.50 Batch 3C',
  'Batch 3C',
  'micro-change',
];
foreach($forbidden as $label){
  $ok=$source!=='' && strpos($source,$label)===false;
  echo ($ok?'PASS':'FAIL')." - tenant workspace hides internal label {$label}\n";
  if(!$ok)$fails++;
}

$total=count($checks)+count($forbidden);
echo "\n".($total-$fails)."/{$total} checks passed.\n";
exit($fails?1:0);
