<?php
/**
 * Read-only verifier for the V2.3 tooltip/Popper compatibility guard.
 * No database bootstrap or application mutation is performed.
 */

$root = dirname(__DIR__);
$files = [
    'navigation' => $root . '/assets/js/navigation.js',
    'main' => $root . '/assets/js/main.js',
    'dashboard' => $root . '/dashboard.php',
    'head' => $root . '/navbar_head.php',
];

$checks = 0;
$failures = 0;

$check = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$label}\n";
};

$content = [];
foreach ($files as $key => $path) {
    $content[$key] = is_file($path) ? (string)file_get_contents($path) : '';
    $check($content[$key] !== '', basename($path) . ' is available');
}

$nav = $content['navigation'];
$main = $content['main'];
$dashboard = $content['dashboard'];
$head = $content['head'];

$check(str_contains($nav, 'function installTooltipFallback()'), 'global navigation installs tooltip compatibility guard');
$check(str_contains($nav, "window.jQuery.fn.tooltip = function () { return this; };"), 'jQuery tooltip constructor is neutralized');
$check(str_contains($nav, 'window.bootstrap.Tooltip = NativeTitleTooltip;'), 'Bootstrap Tooltip alone is replaced by native-title fallback');
$check(str_contains($nav, 'static getOrCreateInstance(element)'), 'fallback exposes Bootstrap-compatible getOrCreateInstance');
$check(str_contains($nav, 'installTooltipFallback();') && strpos($nav, 'installTooltipFallback();') < strpos($nav, "const navbar = document.getElementById('appNavbar')"), 'guard runs before navbar early return');
$check(str_contains($head, "versioned_asset('/assets/js/navigation.js')") && str_contains($head, '<script defer'), 'tooltip guard is loaded globally through deferred navigation asset');
$check(str_contains($main, 'new bootstrap.Tooltip(tooltipTriggerEl)'), 'legacy main tooltip initializer remains intercepted rather than broadly rewritten');
$check(str_contains($dashboard, "$('[title]').tooltip();"), 'dashboard legacy tooltip initializer remains intercepted');
$check(!str_contains($nav, 'window.bootstrap.Modal =') && !str_contains($nav, 'window.bootstrap.Dropdown =') && !str_contains($nav, 'window.bootstrap.Collapse ='), 'guard does not replace Bootstrap modal/dropdown/collapse components');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures === 0) {
    echo "PASS: V2.3 Tooltip/Popper regression is statically guarded without replacing other Bootstrap components.\n";
    exit(0);
}
exit(1);
