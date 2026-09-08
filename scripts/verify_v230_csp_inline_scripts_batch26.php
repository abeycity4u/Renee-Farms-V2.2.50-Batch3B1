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

$page = file_get_contents($root . '/poultry/layer_feeds.php');
$js = file_get_contents($root . '/assets/js/layer-feeds.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/layer-feeds.js')"
    ),
    'Layer Feeds page loads external versioned behavior asset'
);

$check(
    substr_count($page, 'layer-feeds.js') === 1,
    'Layer Feeds page loads external behavior asset exactly once'
);

$check(
    str_contains($js, "$('#feedsTable').DataTable"),
    'External Layer Feeds asset retains DataTable setup'
);

$check(
    str_contains($js, "$('#monthSelector').change"),
    'External Layer Feeds asset retains month selector behavior'
);

$check(
    str_contains($js, "window.location.href = 'layer_feeds.php?month='"),
    'External Layer Feeds asset retains month navigation contract'
);

$check(
    str_contains($js, "$('.edit-transaction').on('click'"),
    'External Layer Feeds asset retains edit transaction handler'
);

$check(
    str_contains($js, "document.getElementById('editTransactionModal')"),
    'External Layer Feeds asset retains edit modal target'
);

$check(
    str_contains($js, 'bootstrap.Modal.getOrCreateInstance'),
    'External Layer Feeds asset retains Bootstrap modal behavior'
);

$check(
    !str_contains($js, '<?php') &&
    !str_contains($js, '<?='),
    'External Layer Feeds asset contains no PHP'
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
    'Layer Feeds page contains zero active inline script blocks'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
