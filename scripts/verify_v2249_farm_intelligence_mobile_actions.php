<?php
$root = dirname(__DIR__);
$page = file_get_contents($root . '/management/intelligence.php');
$css = file_get_contents($root . '/assets/css/responsive.css');
$checks = [
 'Central responsive stylesheet exists' => is_file($root . '/assets/css/responsive.css'),
 'Mobile breakpoint exists centrally' => strpos($css, '@media (max-width: 767.98px)') !== false,
 'Signal rows use mobile grid centrally' => strpos($css, '.intel-row > .d-flex') !== false && strpos($css, 'grid-template-columns: 34px minmax(0, 1fr)') !== false,
 'Action gets full mobile row centrally' => strpos($css, '.intel-row .intel-action') !== false && strpos($css, 'grid-column: 1 / -1') !== false,
 'Action button fills available width centrally' => strpos($css, '.intel-row .intel-action .btn') !== false && strpos($css, 'width: 100%') !== false,
 'Measured value may wrap centrally' => strpos($css, '.intel-row .intel-measure') !== false && strpos($css, 'white-space: normal !important') !== false,
 'Farm Intelligence filter uses shared responsive form primitive' => strpos($page, 'app-responsive-form') !== false,
];
$pass=0;
foreach($checks as $name=>$ok){ echo ($ok?'PASS':'FAIL')." - $name\n"; if($ok)$pass++; }
echo "$pass/".count($checks)." PASS\n";
exit($pass===count($checks)?0:1);
