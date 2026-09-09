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
    $root . '/poultry/layers_daily_record.php'
);

$js = file_get_contents(
    $root . '/assets/js/layers-daily-record.js'
);

$appBehaviors = file_get_contents(
    $root . '/assets/js/app-behaviors.js'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Layer Daily has zero literal inline script blocks'
);

$check(
    str_contains($php, 'id="layerDailyConfig"'),
    'Layer Daily emits centralized page config'
);

$check(
    str_contains($php, 'data-selected-cycle-id="'),
    'Layer Daily config exposes selected cycle'
);

$check(
    str_contains($php, 'data-year-month="'),
    'Layer Daily config exposes selected month'
);

$check(
    str_contains($php, 'data-can-edit-opening="'),
    'Layer Daily config exposes opening-stock edit permission'
);

$check(
    str_contains($php, 'data-can-delete="'),
    'Layer Daily config exposes delete permission'
);

$check(
    str_contains($php, 'data-csrf-token="'),
    'Layer Daily config exposes CSRF token'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/layers-daily-record.js')"
    ),
    'Layer Daily loads versioned external behavior asset'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Layer Daily safely escapes dynamic config attributes'
);

$check(
    str_contains(
        $js,
        "document.getElementById('layerDailyConfig')"
    ),
    'Layer Daily asset reads centralized config'
);

$check(
    str_contains($js, 'dataset.selectedCycleId')
    && str_contains($js, 'dataset.yearMonth')
    && str_contains($js, 'dataset.canEditOpening')
    && str_contains($js, 'dataset.canDelete')
    && str_contains($js, 'dataset.csrfToken'),
    'Layer Daily asset reads all dynamic config values'
);

foreach ([
    'parseNumericInput',
    'calculateLayingRate',
    'calculateCratesCount',
    'lockRetrievedOpeningStock',
    'unlockOpeningStock',
    'openRecordModal',
    'loadExistingRecordOrPreviousStock',
    'fetchRecordData',
    'fetchPreviousStock',
    'checkExistingRecord',
    'resetForm',
] as $fn) {
    $check(
        str_contains($js, 'function ' . $fn),
        'Layer Daily retains ' . $fn
    );
}

$check(
    str_contains(
        $appBehaviors,
        'window.openRecordModal'
    )
    && str_contains(
        $js,
        'function openRecordModal'
    ),
    'Layer Daily preserves openRecordModal shared contract'
);

$check(
    str_contains(
        $appBehaviors,
        'window.checkExistingRecord'
    )
    && str_contains(
        $js,
        'function checkExistingRecord'
    ),
    'Layer Daily preserves checkExistingRecord shared contract'
);

$check(
    str_contains(
        $appBehaviors,
        'window.deleteLayerDailyRecord'
    )
    && str_contains(
        $js,
        'window.deleteLayerDailyRecord = function deleteLayerDailyRecord'
    ),
    'Layer Daily preserves delete global contract'
);

$check(
    str_contains(
        $js,
        'if (layerDailyConfig.canDelete)'
    ),
    'Layer Daily preserves delete permission guard'
);

$check(
    str_contains(
        $js,
        'csrf_token: layerDailyConfig.csrfToken'
    ),
    'Layer Daily delete request uses centralized CSRF token'
);

$check(
    str_contains(
        $js,
        'encodeURIComponent(layerDailyConfig.selectedCycleId)'
    ),
    'Month navigation safely encodes selected cycle'
);

$check(
    str_contains(
        $js,
        'encodeURIComponent(layerDailyConfig.yearMonth)'
    ),
    'Cycle navigation safely encodes selected month'
);

$check(
    substr_count($js, 'calculateLayingRate') >= 4,
    'Layer Daily retains laying-rate calculations and event wiring'
);

$check(
    substr_count($js, 'calculateCratesCount') >= 3,
    'Layer Daily retains crates calculations and event wiring'
);

$check(
    str_contains($js, 'attachEditModal({'),
    'Layer Daily retains shared edit-modal integration'
);

$check(
    str_contains($js, 'AppConfirm.ask'),
    'Layer Daily retains delete confirmation behavior'
);

$check(
    substr_count($js, 'AppNotify.') >= 2,
    'Layer Daily retains delete error notification behavior'
);

$check(
    str_contains($js, 'window.ReneeCalendar'),
    'Layer Daily retains shared calendar runtime integration'
);

$check(
    substr_count($js, 'fetch(') === 5,
    'Layer Daily retains five API request paths'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'Layer Daily external asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
