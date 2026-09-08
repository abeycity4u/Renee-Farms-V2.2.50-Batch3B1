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

$page = file_get_contents($root . '/ruminant/ruminant_feeds_record.php');
$js = file_get_contents($root . '/assets/js/ruminant-feeds-record.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/ruminant-feeds-record.js')"
    ),
    'Ruminant Feeds loads external versioned behavior asset'
);

$check(
    !str_contains($page, "$('#feedsTable').DataTable"),
    'Ruminant Feeds no longer contains inline DataTable initialization'
);

$check(
    !str_contains($page, "$('#monthSelector').on"),
    'Ruminant Feeds no longer contains inline month selector behavior'
);

$check(
    !str_contains($page, "$('.edit-transaction').on"),
    'Ruminant Feeds no longer contains inline edit transaction behavior'
);

$check(
    str_contains($js, "$('#feedsTable').DataTable"),
    'External Ruminant Feeds asset retains DataTable initialization'
);

$check(
    str_contains($js, "$('#monthSelector').on"),
    'External Ruminant Feeds asset retains month selector behavior'
);

$check(
    str_contains($js, "$('.edit-transaction').on"),
    'External Ruminant Feeds asset retains edit transaction behavior'
);

$check(
    str_contains(
        $js,
        "bootstrap.Modal.getOrCreateInstance(document.getElementById('editTransactionModal')).show();"
    ),
    'External Ruminant Feeds asset retains edit modal behavior'
);

$check(
    !str_contains($js, '<?php') &&
    !str_contains($js, '<?='),
    'External Ruminant Feeds asset contains no PHP'
);

$check(
    str_contains(
        $page,
        'https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css'
    ),
    'Responsive CSS dependency remains intentionally unchanged'
);

$check(
    str_contains(
        $page,
        'https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js'
    ),
    'Responsive JS adapter dependency remains intentionally unchanged'
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
    'Ruminant Feeds contains zero active inline script blocks'
);

$check(
    substr_count($page, 'ruminant-feeds-record.js') === 1,
    'Ruminant Feeds loads external behavior asset exactly once'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
