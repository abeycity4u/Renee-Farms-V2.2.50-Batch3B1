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
    $root . '/ruminant/ruminant_daily_record.php'
);

$js = file_get_contents(
    $root . '/assets/js/ruminant-daily-record.js'
);

$appBehaviors = file_get_contents(
    $root . '/assets/js/app-behaviors.js'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Ruminant Daily has zero literal inline script blocks'
);

$check(
    str_contains($php, 'id="ruminantDailyConfig"'),
    'Ruminant Daily emits centralized page config'
);

$check(
    str_contains($php, 'data-selected-cycle-id="'),
    'Ruminant Daily config exposes selected cycle'
);

$check(
    str_contains($php, 'data-year-month="'),
    'Ruminant Daily config exposes selected month'
);

$check(
    str_contains($php, 'data-selected-cycle-animal-type="'),
    'Ruminant Daily config exposes selected cycle animal type'
);

$check(
    str_contains($php, 'data-can-edit-opening="'),
    'Ruminant Daily config exposes opening-stock edit permission'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/ruminant-daily-record.js')"
    ),
    'Ruminant Daily loads versioned external behavior asset'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Ruminant Daily safely escapes dynamic config attributes'
);

$check(
    str_contains(
        $js,
        "document.getElementById('ruminantDailyConfig')"
    ),
    'Ruminant Daily asset reads centralized config'
);

$check(
    str_contains($js, 'dataset.selectedCycleId')
    && str_contains($js, 'dataset.yearMonth')
    && str_contains($js, 'dataset.selectedCycleAnimalType')
    && str_contains($js, 'dataset.canEditOpening'),
    'Ruminant Daily asset reads all dynamic config values'
);

foreach ([
    'getSelectedCycleAnimalType',
    'parseNumericInput',
    'openRecordModal',
    'loadExistingRecordOrPreviousStock',
    'lockRetrievedOpeningStock',
    'unlockOpeningStock',
    'fetchRecordData',
    'fetchPreviousStock',
    'checkExistingRecord',
    'resetForm',
] as $fn) {
    $check(
        str_contains($js, 'function ' . $fn),
        'Ruminant Daily retains ' . $fn
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
    'Ruminant Daily preserves openRecordModal shared contract'
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
    'Ruminant Daily preserves checkExistingRecord shared contract'
);

$check(
    str_contains($js, 'const feedItemSelector'),
    'Ruminant Daily retains feed item selector behavior'
);

$check(
    str_contains(
        $js,
        "feedItemSelector.addEventListener('change'"
    ),
    'Ruminant Daily retains feed-unit change event'
);

$check(
    str_contains($js, 'feedConsumptionUnit')
    && str_contains($js, 'feedConsumptionLabel'),
    'Ruminant Daily retains feed-unit field and label synchronization'
);

$check(
    str_contains(
        $js,
        'encodeURIComponent(ruminantDailyConfig.selectedCycleId)'
    ),
    'Month navigation safely encodes selected cycle'
);

$check(
    str_contains(
        $js,
        'encodeURIComponent(ruminantDailyConfig.yearMonth)'
    ),
    'Cycle navigation safely encodes selected month'
);

$check(
    str_contains(
        $js,
        'ruminantDailyConfig.selectedCycleAnimalType'
    ),
    'Ruminant Daily uses configured selected cycle animal type'
);

$check(
    str_contains(
        $js,
        'ruminantDailyConfig.canEditRetrievedOpeningStock'
    ),
    'Ruminant Daily uses configured opening-stock permission'
);

$check(
    str_contains(
        $js,
        "validRuminantTypes = ['cattle', 'goat', 'sheep', 'other']"
    ),
    'Ruminant Daily retains valid species contract'
);

$check(
    substr_count($js, 'fetch(') === 4,
    'Ruminant Daily retains four API request paths'
);

$check(
    str_contains($js, 'attachEditModal({'),
    'Ruminant Daily retains shared edit-modal integration'
);

$check(
    str_contains($js, 'window.ReneeCalendar'),
    'Ruminant Daily retains shared calendar runtime integration'
);

$check(
    str_contains(
        $js,
        "document.getElementById('animalType').addEventListener('change'"
    ),
    'Ruminant Daily retains animal-type change behavior'
);

$check(
    str_contains(
        $js,
        "document.getElementById('recordForm').addEventListener('submit'"
    ),
    'Ruminant Daily retains numeric submit normalization'
);

$check(
    str_contains(
        $js,
        "button.dataset.cycleAnimalType"
    ),
    'Ruminant Daily retains calendar animal-type routing'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'Ruminant Daily external asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
