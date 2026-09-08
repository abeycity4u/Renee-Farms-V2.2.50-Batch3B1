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
    $root . '/poultry/broiler_daily_record.php'
);

$js = file_get_contents(
    $root . '/assets/js/broiler-daily-record.js'
);

$appBehaviors = file_get_contents(
    $root . '/assets/js/app-behaviors.js'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Broiler Daily has zero literal inline script blocks'
);

$check(
    str_contains($php, 'id="broilerDailyConfig"'),
    'Broiler Daily emits centralized page config'
);

$check(
    str_contains($php, 'data-selected-cycle-id="'),
    'Broiler Daily config exposes selected cycle'
);

$check(
    str_contains($php, 'data-year-month="'),
    'Broiler Daily config exposes selected month'
);

$check(
    str_contains($php, 'data-can-edit-opening="'),
    'Broiler Daily config exposes opening-stock edit permission'
);

$check(
    str_contains($php, 'data-can-delete="'),
    'Broiler Daily config exposes delete permission'
);

$check(
    str_contains($php, 'data-csrf-token="'),
    'Broiler Daily config exposes CSRF token'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/broiler-daily-record.js')"
    ),
    'Broiler Daily loads versioned external behavior asset'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Broiler Daily safely escapes dynamic config attributes'
);

$check(
    str_contains(
        $js,
        "document.getElementById('broilerDailyConfig')"
    ),
    'External asset reads centralized config'
);

$check(
    str_contains($js, 'dataset.selectedCycleId')
    && str_contains($js, 'dataset.yearMonth')
    && str_contains($js, 'dataset.canEditOpening')
    && str_contains($js, 'dataset.canDelete')
    && str_contains($js, 'dataset.csrfToken'),
    'External asset reads all dynamic config values'
);

foreach ([
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
        'External asset retains ' . $fn
    );
}

$check(
    str_contains(
        $appBehaviors,
        'window.deleteBroilerDailyRecord'
    )
    && str_contains(
        $js,
        'window.deleteBroilerDailyRecord = async function deleteBroilerDailyRecord'
    ),
    'Broiler Daily preserves app-behaviors delete global contract'
);

$check(
    str_contains(
        $js,
        'if (broilerDailyConfig.canDelete)'
    ),
    'Broiler Daily preserves delete permission guard'
);

$check(
    str_contains(
        $js,
        'csrf_token: broilerDailyConfig.csrfToken'
    ),
    'Broiler Daily delete request uses centralized CSRF token'
);

$check(
    str_contains(
        $js,
        'encodeURIComponent(broilerDailyConfig.selectedCycleId)'
    ),
    'Month navigation safely encodes selected cycle'
);

$check(
    str_contains(
        $js,
        'encodeURIComponent(broilerDailyConfig.yearMonth)'
    ),
    'Cycle navigation safely encodes selected month'
);

$check(
    str_contains($js, 'AppConfirm.ask'),
    'Broiler Daily retains delete confirmation behavior'
);

$check(
    substr_count($js, 'AppNotify.') >= 3,
    'Broiler Daily retains notification behavior'
);

$check(
    str_contains($js, 'attachEditModal({'),
    'Broiler Daily retains shared edit-modal integration'
);

$check(
    str_contains($js, 'window.ReneeCalendar'),
    'Broiler Daily retains shared calendar runtime integration'
);

$check(
    substr_count($js, 'fetch(') === 5,
    'Broiler Daily retains five API request paths'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'Broiler Daily external asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
