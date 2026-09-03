<?php
$root = dirname(__DIR__);
$nav = file_get_contents($root . '/navbar.php');
$head = file_get_contents($root . '/navbar_head.php');
$js = file_get_contents($root . '/assets/js/navigation.js');

$checks = [
    'navbar uses platform-owned mobile toggle hook' => strpos($nav, 'data-app-navbar-toggle') !== false,
    'navbar toggle exposes aria controls' => strpos($nav, 'aria-controls="navbarMenu"') !== false,
    'navbar no longer depends on Bootstrap collapse data-api' => strpos($nav, 'data-bs-toggle="collapse"') === false,
    'shared head loads central navigation controller' => strpos($head, '/assets/js/navigation.js') !== false,
    'navigation controller targets shared navbar' => strpos($js, "getElementById('appNavbar')") !== false,
    'navigation controller toggles navbar show state' => strpos($js, "menu.classList.toggle('show'") !== false,
    'navigation controller maintains aria-expanded' => strpos($js, "aria-expanded") !== false,
    'mobile navigation closes after link selection' => strpos($js, 'window.innerWidth < 992') !== false,
    'navigation controller is idempotent' => strpos($js, 'appNavigationReady') !== false,
];

$navbarPages = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $path = $file->getPathname();
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    if (preg_match('#^(vendor|scripts|migrations|includes|api)/#', str_replace('\\','/',$rel))) continue;
    $content = file_get_contents($path);
    if (strpos($content, 'navbar.php') === false) continue;
    if ($rel === 'navbar.php') continue;
    $navbarPages[] = $rel;
    if (strpos($content, 'navbar_head.php') === false) {
        $checks['navbar page uses shared head: ' . $rel] = false;
    }
}
$checks['all rendered navbar pages inherit central navigation asset'] = !array_filter($checks, fn($v,$k) => str_starts_with($k,'navbar page uses shared head:') && !$v, ARRAY_FILTER_USE_BOTH);

$failed = 0;
foreach ($checks as $name => $pass) {
    echo ($pass ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$pass) $failed++;
}
echo 'Navbar pages audited: ' . count($navbarPages) . PHP_EOL;
echo 'Checks: ' . count($checks) . ', failed: ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
