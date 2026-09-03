<?php
$root = dirname(__DIR__);
$cssPath = $root . '/assets/css/responsive.css';
$headPath = $root . '/navbar_head.php';
$css = is_file($cssPath) ? file_get_contents($cssPath) : '';
$head = file_get_contents($headPath);
$dashboard = file_get_contents($root . '/dashboard.php');
$health = file_get_contents($root . '/poultry/health.php');
$investigation = file_get_contents($root . '/management/investigation.php');
$category = file_get_contents($root . '/inventory/category_list.php');

$checks = [
 'responsive.css is globally linked' => strpos($head, "/assets/css/responsive.css") !== false,
 'responsive.css loads after theme.css' => strpos($head, "/assets/css/responsive.css") > strpos($head, "/assets/css/theme.css"),
 'phone breakpoint is centralized' => strpos($css, '@media (max-width: 767.98px)') !== false,
 'collapsed-navbar tablet breakpoint is centralized' => strpos($css, '@media (max-width: 991.98px)') !== false,
 'shared responsive toolbar primitive exists' => strpos($css, '.app-responsive-toolbar') !== false,
 'shared responsive form primitive exists' => strpos($css, '.app-responsive-form') !== false,
 'tables use shared horizontal scrolling rule' => strpos($css, '.table-responsive') !== false && strpos($css, 'overflow-x: auto') !== false,
 'fixed-width form controls are viewport constrained' => strpos($css, '.form-control') !== false && strpos($css, 'max-width: 100%') !== false,
 'dashboard Management Intelligence mobile layout is centralized' => strpos($css, '.smart-command-card .smart-insight > .d-flex') !== false,
 'dashboard intelligence titles can wrap' => strpos($css, '.smart-command-card .smart-insight h6.text-truncate') !== false,
 'dashboard intelligence action links become full-width' => strpos($css, '.smart-command-card .smart-insight a') !== false,
 'Farm Intelligence action layout is centralized' => strpos($css, '.intel-row .intel-action') !== false,
 'Poultry Health page uses shared toolbar primitive' => strpos($health, 'app-responsive-toolbar') !== false,
 'Investigation page uses shared toolbar primitive' => strpos($investigation, 'app-responsive-toolbar') !== false,
 'Inventory category table has responsive wrapper' => strpos($category, '<div class="table-responsive">') !== false,
];

$pass = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . " - $name\n";
    if ($ok) $pass++;
}
echo "$pass/" . count($checks) . " PASS\n";
exit($pass === count($checks) ? 0 : 1);
