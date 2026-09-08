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

$page = file_get_contents($root . '/poultry/health.php');
$js = file_get_contents($root . '/assets/js/poultry-health.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/poultry-health.js')"
    ),
    'Poultry Health loads external versioned behavior asset'
);

$check(
    str_contains($page, 'id="poultryHealthConfig"'),
    'Poultry Health exposes page configuration element'
);

$check(
    str_contains($page, 'data-default-date='),
    'Poultry Health exposes default date contract'
);

$check(
    str_contains($page, 'data-can-delete='),
    'Poultry Health exposes delete-capability contract'
);

$check(
    str_contains(
        $page,
        "app_attr(function_exists('app_today') ? app_today() : date('Y-m-d'))"
    ),
    'Poultry Health safely escapes default date attribute'
);

$check(
    !str_contains($page, 'function filterCycleOptions()'),
    'Poultry Health no longer contains inline filterCycleOptions implementation'
);

$check(
    !str_contains($page, 'function newHealthEvent()'),
    'Poultry Health no longer contains inline newHealthEvent implementation'
);

$check(
    !str_contains($page, 'function editHealthEvent(e)'),
    'Poultry Health no longer contains inline editHealthEvent implementation'
);

$check(
    !str_contains($page, 'function confirmDeleteEvent(id)'),
    'Poultry Health no longer contains inline confirmDeleteEvent implementation'
);

$check(
    str_contains($js, 'function filterCycleOptions()'),
    'External Health asset retains cycle-filter behavior'
);

$check(
    str_contains($js, 'function newHealthEvent()'),
    'External Health asset retains new-event behavior'
);

$check(
    str_contains($js, 'function editHealthEvent(e)'),
    'External Health asset retains edit-event behavior'
);

$check(
    str_contains($js, 'async function confirmDeleteEvent(id)'),
    'External Health asset retains delete confirmation behavior'
);

$check(
    str_contains($js, "document.getElementById('poultryHealthConfig')"),
    'External Health asset reads page configuration'
);

$check(
    str_contains($js, 'config.dataset.defaultDate'),
    'External Health asset reads default date'
);

$check(
    str_contains($js, "config.dataset.canDelete === '1'"),
    'External Health asset reads delete capability'
);

$check(
    str_contains(
        $js,
        "document.getElementById('event_date').value = window.PoultryHealthConfig.defaultDate || '';"
    ),
    'External Health asset applies configured default date'
);

$check(
    str_contains($js, 'if (!window.PoultryHealthConfig.canDelete)'),
    'External Health asset guards delete behavior by capability'
);

$check(
    !str_contains($js, '<?php') && !str_contains($js, '<?='),
    'External Health asset contains no PHP'
);

$check(
    str_contains($page, 'data-health-filter-cycles'),
    'Poultry Health retains shared cycle-filter contract'
);

$check(
    str_contains($page, 'data-health-new-event'),
    'Poultry Health retains shared new-event contract'
);

$check(
    str_contains($page, 'data-health-edit-event'),
    'Poultry Health retains shared edit-event contract'
);

$check(
    str_contains($page, 'data-health-delete-id'),
    'Poultry Health retains shared delete-event contract'
);

/*
 * Count active inline scripts.
 */
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
    'Poultry Health contains zero active inline script blocks'
);

$check(
    substr_count($page, 'poultry-health.js') === 1,
    'Poultry Health loads external behavior asset exactly once'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
