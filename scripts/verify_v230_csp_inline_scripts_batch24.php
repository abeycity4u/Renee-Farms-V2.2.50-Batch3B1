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

$page = file_get_contents($root . '/management/farms.php');
$js = file_get_contents($root . '/assets/js/farms.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/farms.js')"
    ),
    'Farms page loads external versioned behavior asset'
);

$check(
    substr_count($page, 'farms.js') === 1,
    'Farms page loads external behavior asset exactly once'
);

$check(
    str_contains($js, "document.getElementById('farmAccountForm')"),
    'External asset retains farm account form behavior'
);

$check(
    str_contains($js, 'data-module-entitlement'),
    'External asset retains module entitlement validation'
);

$check(
    str_contains($js, "input[name=\"logo\"]"),
    'External asset retains logo input handling'
);

$check(
    str_contains(
        $js,
        "const allowedLogoTypes = ['image/jpeg', 'image/png', 'image/webp'];"
    ),
    'External asset retains allowed logo MIME types'
);

$check(
    str_contains($js, 'function confirmFarmDeletion(form, farmName)'),
    'External asset retains farm deletion confirmation'
);

$check(
    substr_count($js, 'AppConfirm.ask') >= 3,
    'External asset retains multi-step confirmation dialogs'
);

$check(
    str_contains($js, "button.name='delete_farm'") ||
    str_contains($js, "button.name = 'delete_farm'"),
    'External asset retains delete-farm submission contract'
);

$check(
    str_contains($js, 'function confirmFarmSuspend(form)'),
    'External asset retains farm suspension confirmation'
);

$check(
    str_contains($js, "button.name = 'suspend_farm'") ||
    str_contains($js, "button.name='suspend_farm'"),
    'External asset retains suspend-farm submission contract'
);

$check(
    !str_contains($js, '<?php') &&
    !str_contains($js, '<?='),
    'External Farms asset contains no PHP'
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
    'Farms page contains zero active inline script blocks'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
