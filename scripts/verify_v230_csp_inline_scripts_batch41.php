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
    $root . '/poultry/broiler_feeds.php'
);

$js = file_get_contents(
    $root . '/assets/js/broiler-feeds.js'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Broiler Feeds has zero literal inline script blocks'
);

$check(
    str_contains($php, 'id="broilerFeedsConfig"'),
    'Broiler Feeds emits centralized page config'
);

$check(
    str_contains($php, 'data-ledger-view="'),
    'Broiler Feeds config exposes ledger view'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/broiler-feeds.js')"
    ),
    'Broiler Feeds loads versioned external behavior asset'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Broiler Feeds safely escapes ledger config'
);

$check(
    str_contains(
        $js,
        "document.getElementById('broilerFeedsConfig')"
    ),
    'External asset reads centralized config'
);

$check(
    str_contains($js, 'dataset.ledgerView'),
    'External asset reads ledger view'
);

$check(
    str_contains($js, "$('#feedsTable').DataTable"),
    'External asset retains DataTables initialization'
);

$check(
    str_contains($js, 'monthSelector'),
    'External asset retains month selector behavior'
);

$check(
    str_contains($js, '.edit-transaction'),
    'External asset retains edit transaction binding'
);

$check(
    str_contains($js, '#editTransactionId')
    && str_contains($js, '#editTransactionDate'),
    'External asset retains edit transaction identity/date population'
);

$check(
    str_contains($js, '#editFeedItem')
    && str_contains($js, '#editTransactionType'),
    'External asset retains edit feed/type population'
);

$check(
    str_contains($js, '#editCycleId'),
    'External asset retains cycle selection'
);

$check(
    str_contains($js, 'bootstrap.Modal'),
    'External asset retains edit modal launch'
);

$check(
    str_contains($js, 'broilerFeedsConfig.ledgerView'),
    'External asset uses centralized ledger view'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'Broiler Feeds external asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
