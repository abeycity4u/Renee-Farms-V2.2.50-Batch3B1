<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
};

$page = file_get_contents($root . '/navbar.php');
$js = file_get_contents($root . '/assets/js/navbar.js');

$check(
    str_contains($page, "versioned_asset('/assets/js/navbar.js')"),
    'Navbar loads external versioned behavior asset'
);

$check(
    substr_count($page, 'navbar.js') === 1,
    'Navbar loads external behavior asset exactly once'
);

$check(
    substr_count($js, "document.addEventListener('DOMContentLoaded'") >= 2,
    'External navbar asset preserves both DOMContentLoaded initializers'
);

$check(
    str_contains($js, 'data-nav-dropdown-toggle'),
    'External navbar asset retains dropdown toggle behavior'
);

$check(
    str_contains($js, 'bootstrap.Dropdown'),
    'External navbar asset retains Bootstrap dropdown integration'
);

$check(
    str_contains($js, 'setCompactState'),
    'External navbar asset retains compact navigation behavior'
);

$check(
    str_contains($js, 'navDebugEnabled'),
    'External navbar asset retains navigation debug behavior'
);

$check(
    str_contains($js, 'themeToggle'),
    'External navbar asset retains account-menu theme toggle'
);

$check(
    str_contains($js, 'themeQuickToggle'),
    'External navbar asset retains quick theme toggle'
);

$check(
    str_contains($js, 'farm-theme'),
    'External navbar asset retains theme persistence key'
);

$check(
    str_contains($js, 'localStorage'),
    'External navbar asset retains localStorage behavior'
);

$check(
    !str_contains($js, '<?php') &&
    !str_contains($js, '<?='),
    'External navbar asset contains no PHP'
);

$active = preg_replace('/<!--.*?-->/s', '', $page);
$inlineCount = 0;

if (preg_match_all(
    '/<script\b([^>]*)>(.*?)<\/script\s*>/is',
    $active,
    $matches,
    PREG_SET_ORDER
)) {
    foreach ($matches as $match) {
        $attrs = $match[1] ?? '';
        $body = trim($match[2] ?? '');

        if (preg_match('/\bsrc\s*=/i', $attrs)) {
            continue;
        }

        if ($body !== '') {
            $inlineCount++;
        }
    }
}

$check(
    $inlineCount === 0,
    'Navbar contains zero active inline script blocks'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
