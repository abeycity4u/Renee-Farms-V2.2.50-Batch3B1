<?php

$pagePath =
    __DIR__
    . '/../management/production_cycles.php';

$jsPath =
    __DIR__
    . '/../assets/js/production-cycles.js';

$permissionJsPath =
    __DIR__
    . '/../assets/js/production-cycle-view-permissions.js';

$managePath =
    __DIR__
    . '/../management/poultry_cycle.php';

$checks = 0;
$failures = 0;

$check = function (
    bool $passed,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    if ($passed) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }

    $failures++;
    echo 'FAIL: ' . $label . PHP_EOL;
};

$check(
    is_readable($pagePath),
    'Production Cycles page is readable'
);

$check(
    is_readable($jsPath),
    'Production Cycles JavaScript is readable'
);

$check(
    is_readable($managePath),
    'Manage Cycle page is readable'
);

$check(
    is_readable($permissionJsPath),
    'Production Cycle delegated-permission JavaScript is readable'
);

$page = is_readable($pagePath)
    ? (string)file_get_contents($pagePath)
    : '';

$js = is_readable($jsPath)
    ? (string)file_get_contents($jsPath)
    : '';

$manage = is_readable($managePath)
    ? (string)file_get_contents($managePath)
    : '';

$permissionJs = is_readable($permissionJsPath)
    ? (string)file_get_contents($permissionJsPath)
    : '';

$check(
    substr_count(
        $page,
        'poultry_cycle_onboarding_record_initial('
    ) === 1,
    'Create Cycle still owns poultry onboarding once'
);

$check(
    substr_count(
        $page,
        'name="poultry_acquisition_type"'
    ) === 1,
    'Create Cycle still contains acquisition type'
);

$check(
    substr_count(
        $page,
        'name="poultry_initial_phase"'
    ) === 1,
    'Create Cycle still contains starting biological stage'
);

$check(
    substr_count(
        $page,
        'You do not need to record the same flock again afterward.'
    ) === 1,
    'Create Cycle still states that flock entry is one-time'
);

$check(
    strpos(
        $page,
        "if (\$action === 'record_poultry_acquisition')"
    ) === false,
    'standalone Record Flock Entry route is retired'
);

$check(
    strpos(
        $page,
        "if (\$action === 'set_initial_poultry_phase')"
    ) === false,
    'standalone Set Initial Phase route is retired'
);

$check(
    strpos(
        $page,
        'value="record_poultry_acquisition"'
    ) === false,
    'standalone Record Flock Entry POST form is retired'
);

$check(
    strpos(
        $page,
        'value="set_initial_poultry_phase"'
    ) === false,
    'standalone Set Initial Phase POST form is retired'
);

$check(
    strpos(
        $page,
        '<h6>Record Flock Entry</h6>'
    ) === false,
    'Record Flock Entry heading is removed'
);

$check(
    strpos(
        $page,
        '<h6>Set Initial Phase</h6>'
    ) === false,
    'Set Initial Phase heading is removed'
);

$check(
    substr_count(
        $page,
        '<strong>Poultry Acquisition History</strong>'
    ) === 1
    && strpos(
        $page,
        'Recorded Acquisition History'
    ) === false
    && strpos(
        $page,
        'Cost / Bird</th>'
    ) !== false,
    'acquisition summary and audit rows are consolidated into one history table'
);

$check(
    substr_count(
        $page,
        '<strong>Poultry Lifecycle History</strong>'
    ) === 1
    && strpos(
        $page,
        'Recorded Phase History'
    ) === false
    && strpos(
        $page,
        '<th>Phase Status</th>'
    ) !== false,
    'lifecycle summary and phase rows are consolidated into one history table'
);

$check(
    strpos(
        $page,
        "if (\$action === 'void_poultry_acquisition')"
    ) === false
    && strpos(
        $page,
        'value="void_poultry_acquisition"'
    ) === false
    && strpos(
        $manage,
        "if (\$action === 'void_poultry_acquisition')"
    ) === false
    && strpos(
        $manage,
        'value="void_poultry_acquisition"'
    ) === false
    && strpos(
        $page,
        '/management/production_cycle_edit.php?id='
    ) !== false,
    'routine acquisition correction moves from Manage Cycle to Edit Cycle'
);

$check(
    strpos(
        $page,
        "if (\$action === 'transition_poultry_phase')"
    ) === false
    && strpos(
        $page,
        'value="transition_poultry_phase"'
    ) === false
    && substr_count(
        $manage,
        "if (\$action === 'transition_poultry_phase')"
    ) === 1
    && substr_count(
        $manage,
        'value="transition_poultry_phase"'
    ) === 1,
    'lifecycle transition is owned only by Manage Cycle'
);

$check(
    strpos(
        $page,
        "if (\$action === 'end_poultry_phase')"
    ) === false
    && strpos(
        $page,
        'value="end_poultry_phase"'
    ) === false
    && strpos(
        $manage,
        "if (\$action === 'end_poultry_phase')"
    ) === false
    && strpos(
        $manage,
        'value="end_poultry_phase"'
    ) === false
    && substr_count(
        $manage,
        'value="end_production"'
    ) === 1,
    'standalone terminal phase-end mutation is retired in favor of Manage Cycle End Production'
);

$check(
    strpos(
        $page,
        "if (\$action === 'confirm_population_cutover')"
    ) === false
    && strpos(
        $page,
        '/management/legacy_cycle_setup.php'
    ) !== false,
    'Population Cutover route is retired from Production Cycles and relocated'
);

$check(
    strpos(
        $page,
        'value="confirm_population_cutover"'
    ) === false
    && strpos(
        $manage,
        '/management/legacy_cycle_setup.php#population-cutover'
    ) !== false,
    'Population Cutover form is removed from normal cycle UI and Manage Cycle links to Legacy Cycle Setup'
);

$check(
    strpos(
        $manage,
        'value="record_poultry_acquisition"'
    ) === false
    && strpos(
        $manage,
        'value="set_initial_poultry_phase"'
    ) === false
    && strpos(
        $manage,
        'value="end_poultry_phase"'
    ) === false
    && substr_count(
        $manage,
        'value="transition_poultry_phase"'
    ) === 1
    && substr_count(
        $manage,
        'value="end_production"'
    ) === 1,
    'Manage Cycle restores contextual lifecycle transition without re-owning initial onboarding or competing phase-end controls'
);

$check(
    substr_count(
        $manage,
        'value="end_production"'
    ) === 1,
    'Manage Cycle End Production form remains'
);

$check(
    substr_count(
        $manage,
        'Population cutover is required'
    ) === 1,
    'Manage Cycle population-cutover protection remains'
);

$check(
    strpos(
        $js,
        "document.getElementById('acquisitionCycle')"
    ) === false,
    'old standalone acquisition-cycle JavaScript is removed'
);

$check(
    strpos(
        $js,
        "document.getElementById('acquisitionType')"
    ) === false,
    'old standalone acquisition-type JavaScript is removed'
);

$check(
    strpos(
        $js,
        'input[name="acquisition_quantity"]'
    ) === false,
    'old standalone acquisition quantity JavaScript is removed'
);

$check(
    strpos(
        $permissionJs,
        "action === 'record_poultry_acquisition'"
    ) === false,
    'delegated permission JavaScript no longer targets the retired flock-entry form'
);

$check(
    substr_count(
        $js,
        "document.getElementById('poultryCycleOnboardingWrap')"
    ) === 1,
    'Create Cycle onboarding JavaScript remains'
);

$check(
    strpos(
        $page,
        'Legacy cycle with no recorded acquisition history.'
    ) !== false
    && strpos(
        $page,
        '<h6>Record Flock Entry</h6>'
    ) === false
    && strpos(
        $page,
        'value="record_poultry_acquisition"'
    ) === false,
    'legacy missing-acquisition state is informational and does not imply a second entry form'
);

$check(
    strpos(
        $page,
        'No lifecycle history is recorded for this legacy cycle.'
    ) !== false
    && strpos(
        $page,
        '<h6>Set Initial Phase</h6>'
    ) === false
    && strpos(
        $page,
        'value="set_initial_poultry_phase"'
    ) === false,
    'legacy missing-lifecycle state is informational and does not imply a second initial-phase form'
);

$check(
    strpos(
        $page,
        '<h6>Record Phase Transition</h6>'
    ) === false
    && strpos(
        $page,
        '<h6>End Current Phase</h6>'
    ) === false
    && strpos(
        $page,
        'Lifecycle changes are managed inside the selected cycle.'
    ) !== false,
    'Production Cycles keeps lifecycle history read-only and delegates writes to Manage Cycle'
);

$check(
    substr_count(
        $page,
        "require_once(__DIR__ . '/../lib/poultry_cycle_acquisition.php');"
    ) === 1,
    'acquisition service remains available for aggregate history'
);

$check(
    substr_count(
        $page,
        "require_once(__DIR__ . '/../lib/poultry_cycle_lifecycle.php');"
    ) === 1,
    'lifecycle service remains available for aggregate history'
);

$check(
    strpos(
        $page,
        'Use Edit for cycle details and corrections to Opening Headcount or Total Acquisition Cost.'
    ) !== false,
    'acquisition overview explains Edit Cycle correction ownership'
);

$check(
    substr_count(
        $page,
        'poultry_acquisition_void('
    ) === 0
    && substr_count(
        $manage,
        'poultry_acquisition_void('
    ) === 0,
    'routine acquisition void mutation is retired from farmer-facing cycle pages'
);

$check(
    strpos(
        $page,
        '>Manage Cycle</a>'
    ) !== false,
    'Production Cycles retains the route into selected-cycle management'
);

echo PHP_EOL;
echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;

if ($failures === 0) {
    echo 'PRODUCTION CYCLE UX CLEANUP PATCH 1: PASSED'
        . PHP_EOL;
}

echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
