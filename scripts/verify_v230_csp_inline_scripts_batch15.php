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

$sign = file_get_contents($root . '/sign.php');
$signJs = file_get_contents($root . '/assets/js/sign.js');

$check(
    str_contains(
        $sign,
        "versioned_asset('/assets/js/sign.js')"
    ),
    'Sign page loads external versioned sign behavior asset'
);

$check(
    !str_contains($sign, 'function syncLoginType()'),
    'Sign page no longer contains inline syncLoginType function'
);

$check(
    str_contains($signJs, 'function syncLoginType()'),
    'External sign asset contains login-type synchronization'
);

$check(
    str_contains($signJs, "[data-login-type]:checked"),
    'External sign asset reads selected login type'
);

$check(
    str_contains($signJs, "document.getElementById('farmWorkspaceField')"),
    'External sign asset resolves workspace field'
);

$check(
    str_contains($signJs, "document.getElementById('farm_slug')"),
    'External sign asset resolves farm slug input'
);

$check(
    str_contains($signJs, "workspace.style.display = isPlatform ? 'none' : '';"),
    'External sign asset toggles workspace visibility'
);

$check(
    str_contains($signJs, 'input.required = !isPlatform;'),
    'External sign asset toggles workspace requirement'
);

$check(
    str_contains($signJs, "field.addEventListener('change', syncLoginType);"),
    'External sign asset binds login-type change behavior'
);

$check(
    str_contains($signJs, 'syncLoginType();'),
    'External sign asset initializes login-type state'
);

/*
 * Count active inline script blocks in sign.php.
 */
$active = preg_replace('/<!--.*?-->/s', '', $sign);
$inlineCount = 0;

if (preg_match_all('/<script\b([^>]*)>(.*?)<\/script\s*>/is', $active, $matches, PREG_SET_ORDER)) {
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
    'Sign page contains zero active inline script blocks'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
