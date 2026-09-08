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

$behaviors = file_get_contents($root . '/assets/js/app-behaviors.js');
$health = file_get_contents($root . '/poultry/health.php');

$check(
    str_contains($behaviors, "[data-health-new-event]"),
    'Shared behavior recognizes new health event contract'
);

$check(
    str_contains($behaviors, 'window.newHealthEvent();'),
    'Shared behavior delegates new health event to page-owned function'
);

$check(
    str_contains($behaviors, "[data-health-edit-event]"),
    'Shared behavior recognizes edit health event contract'
);

$check(
    str_contains($behaviors, 'dataset.healthEvent'),
    'Shared behavior reads structured health event payload'
);

$check(
    str_contains($behaviors, 'JSON.parse(encoded)'),
    'Shared behavior parses structured health event payload'
);

$check(
    str_contains($behaviors, 'window.editHealthEvent(eventData);'),
    'Shared behavior delegates edit health event to page-owned function'
);

$check(
    str_contains($behaviors, "[data-health-delete-id]"),
    'Shared behavior recognizes health delete contract'
);

$check(
    str_contains($behaviors, 'dataset.healthDeleteId'),
    'Shared behavior reads health event delete id'
);

$check(
    str_contains($behaviors, 'window.confirmDeleteEvent(eventId);'),
    'Shared behavior delegates delete confirmation to page-owned function'
);

$check(
    str_contains($behaviors, "[data-health-filter-cycles]"),
    'Shared behavior recognizes health cycle-filter contract'
);

$check(
    str_contains($behaviors, 'window.filterCycleOptions();'),
    'Shared behavior delegates cycle filtering to page-owned function'
);

$check(
    !preg_match('/onclick=.*(?:newHealthEvent|editHealthEvent|confirmDeleteEvent)/', $health),
    'Poultry Health no longer uses inline click handlers'
);

$check(
    !str_contains($health, 'onchange="filterCycleOptions()'),
    'Poultry Health no longer uses inline cycle-filter change handler'
);

$check(
    substr_count($health, 'data-health-new-event') === 1,
    'Poultry Health has exactly one new-event data contract'
);

$check(
    substr_count($health, 'data-health-edit-event') === 1,
    'Poultry Health has exactly one edit-event data contract'
);

$check(
    substr_count($health, 'data-health-event=') === 1,
    'Poultry Health has exactly one structured event payload contract'
);

$check(
    substr_count($health, 'data-health-delete-id=') === 1,
    'Poultry Health has exactly one delete-event data contract'
);

$check(
    substr_count($health, 'data-health-filter-cycles') === 1,
    'Poultry Health has exactly one cycle-filter data contract'
);

$check(
    str_contains(
        $health,
        'app_attr(json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE))'
    ),
    'Poultry Health safely HTML-escapes structured edit payload'
);

$check(
    str_contains($health, 'function newHealthEvent()'),
    'Poultry Health retains page-owned newHealthEvent implementation'
);

$check(
    str_contains($health, 'function editHealthEvent(e)'),
    'Poultry Health retains page-owned editHealthEvent implementation'
);

$check(
    str_contains($health, 'function confirmDeleteEvent(id)'),
    'Poultry Health retains page-owned confirmDeleteEvent implementation'
);

$check(
    str_contains($health, 'function filterCycleOptions()'),
    'Poultry Health retains page-owned filterCycleOptions implementation'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
