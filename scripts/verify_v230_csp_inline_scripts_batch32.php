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

$php = file_get_contents(
    $root . '/includes/dashboard_action_permissions.php'
);

$readonly = file_get_contents(
    $root . '/assets/js/dashboard-stock-readonly.js'
);

$quick = file_get_contents(
    $root . '/assets/js/dashboard-quick-stock.js'
);

$check(
    str_contains(
        $php,
        "BASE_URL . '/assets/js/dashboard-stock-readonly.js'"
    ),
    'Dashboard permissions retains readonly stock asset fallback'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/dashboard-stock-readonly.js')"
    ),
    'Dashboard permissions uses versioned readonly stock asset'
);

$check(
    str_contains(
        $php,
        "BASE_URL . '/assets/js/dashboard-quick-stock.js'"
    ),
    'Dashboard permissions retains quick-stock asset fallback'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/dashboard-quick-stock.js')"
    ),
    'Dashboard permissions uses versioned quick-stock asset'
);

$check(
    !str_contains($php, 'DOMContentLoaded'),
    'Dashboard permission PHP contains no embedded browser behavior'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Dashboard permission include has zero literal inline script blocks'
);

$check(
    substr_count($php, '<style') === 2,
    'Existing Dashboard permission style blocks remain untouched'
);

$check(
    str_contains(
        $readonly,
        "document.addEventListener('DOMContentLoaded'"
    ),
    'Readonly dashboard asset waits for DOM readiness'
);

$check(
    str_contains($readonly, 'stockTable')
    && str_contains($readonly, 'quickStockUpdate'),
    'Readonly dashboard asset retains stock action targeting'
);

$check(
    str_contains($readonly, '.remove()'),
    'Readonly dashboard asset retains restricted-control removal'
);

$check(
    str_contains(
        $quick,
        "document.addEventListener('DOMContentLoaded'"
    ),
    'Quick-stock dashboard asset waits for DOM readiness'
);

$check(
    str_contains($quick, 'quickStockForm'),
    'Quick-stock asset retains form binding'
);

$check(
    str_contains($quick, 'quickStockUpdate'),
    'Quick-stock asset retains quick stock action binding'
);

$check(
    str_contains($quick, 'bootstrap'),
    'Quick-stock asset retains Bootstrap interaction'
);

$check(
    str_contains($quick, 'submit'),
    'Quick-stock asset retains submission behavior'
);

$check(
    !str_contains($readonly, '<?php')
    && !str_contains($readonly, '<?='),
    'Readonly dashboard asset contains no PHP'
);

$check(
    !str_contains($quick, '<?php')
    && !str_contains($quick, '<?='),
    'Quick-stock dashboard asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
