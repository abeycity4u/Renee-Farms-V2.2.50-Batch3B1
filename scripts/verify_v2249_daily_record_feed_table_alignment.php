<?php
$root = dirname(__DIR__);
$checks = [];
function check_contains($file, $needle, $label) {
    global $checks;
    $text = file_get_contents($file);
    $ok = strpos($text, $needle) !== false;
    $checks[] = [$ok, $label];
}
function check_order($file, array $needles, $label) {
    global $checks;
    $text = file_get_contents($file);
    $pos = -1; $ok = true;
    foreach ($needles as $needle) {
        $next = strpos($text, $needle, $pos + 1);
        if ($next === false || $next < $pos) { $ok = false; break; }
        $pos = $next;
    }
    $checks[] = [$ok, $label];
}
$layer = $root . '/poultry/layers_daily_record.php';
$broiler = $root . '/poultry/broiler_daily_record.php';
$rum = $root . '/ruminant/ruminant_daily_record.php';
check_order($layer, ['<th>Feed (bags)</th>', '<th>Feed Item</th>', '<th>Water (L)</th>'], 'Layer header order Feed -> Feed Item -> Water');
check_contains($layer, "number_format(\$monthlyTotals['feed_consumption'], 2); ?></td>\n                                                <td>--</td>\n                                                <td class=\"fw-bold\"><?php echo number_format(\$monthlyTotals['water_consumption'])", 'Layer TOTAL reserves Feed Item column');
check_order($broiler, ['<th>Feed (bags)</th>', '<th>Feed Item</th>', '<th>Water (L)</th>'], 'Broiler header order Feed -> Feed Item -> Water');
check_contains($broiler, "number_format(\$monthlyTotals['feed_consumption'], 2); ?></td>\n                                                <td>--</td>\n                                                <td class=\"fw-bold\"><?php echo number_format(\$monthlyTotals['water_consumption'])", 'Broiler TOTAL reserves Feed Item column');
check_contains($broiler, "(\$canEdit || \$canDelete) ? '10' : '9'", 'Broiler empty-state colspan includes Feed Item');
check_order($rum, ['<th>Feed (kg)</th>', '<th>Feed Item</th>', '<th>Water (L)</th>'], 'Ruminant header includes Feed Item in correct position');
check_contains($rum, "number_format(\$typeTotals['feed_consumption'], 2); ?></td>\n                                                <td>--</td>\n                                                <td class=\"fw-bold\"><?php echo number_format(\$typeTotals['water_consumption'])", 'Ruminant TOTAL reserves Feed Item column');
$failed = 0;
foreach ($checks as [$ok,$label]) { echo ($ok ? 'PASS' : 'FAIL') . " - $label\n"; if (!$ok) $failed++; }
echo count($checks) . ' checks, ' . $failed . " failed\n";
exit($failed ? 1 : 0);
