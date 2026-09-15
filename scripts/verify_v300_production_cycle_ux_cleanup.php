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
    ) === 1,
    'surviving acquisition section is explicitly history'
);

$check(
    substr_count(
        $page,
        '<h6>Recorded Acquisition History</h6>'
    ) === 1,
    'recorded acquisition history remains visible'
);

$check(
    substr_count(
        $page,
        "if (\$action === 'void_poultry_acquisition')"
    ) === 1,
    'controlled acquisition correction route remains'
);

$check(
    substr_count(
        $page,
        'value="void_poultry_acquisition"'
    ) === 1,
    'controlled acquisition correction form remains'
);

$check(
    substr_count(
        $page,
        "if (\$action === 'transition_poultry_phase')"
    ) === 1,
    'phase transition route remains'
);

$check(
    substr_count(
        $page,
        'value="transition_poultry_phase"'
    ) === 1,
    'phase transition form remains'
);

$check(
    substr_count(
        $page,
        "if (\$action === 'end_poultry_phase')"
    ) === 1,
    'End Current Phase route remains'
);

$check(
    substr_count(
        $page,
        'value="end_poultry_phase"'
    ) === 1,
    'End Current Phase form remains'
);

$check(
    substr_count(
        $page,
        "if (\$action === 'confirm_population_cutover')"
    ) === 1,
    'Population Cutover route remains'
);

$check(
    substr_count(
        $page,
        'value="confirm_population_cutover"'
    ) === 1,
    'Population Cutover form remains'
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
    substr_count(
        $page,
        'New V3 poultry cycles record the starting flock during Create Cycle.'
    ) === 1,
    'legacy missing-acquisition copy no longer implies a second entry form'
);

$check(
    substr_count(
        $page,
        'New V3 poultry cycles record the starting biological stage during Create Cycle.'
    ) === 1,
    'legacy missing-lifecycle copy no longer implies a second initial-phase form'
);

$check(
    substr_count(
        $page,
        '<h6>Record Phase Transition</h6>'
    ) === 1,
    'phase transition workspace remains visible'
);

$check(
    substr_count(
        $page,
        '<h6>End Current Phase</h6>'
    ) === 1,
    'End Current Phase workspace remains visible'
);

$check(
    substr_count(
        $page,
        "require_once(__DIR__ . '/../lib/poultry_cycle_acquisition.php');"
    ) === 1,
    'acquisition service remains available for history and correction'
);

$check(
    substr_count(
        $page,
        "require_once(__DIR__ . '/../lib/poultry_cycle_lifecycle.php');"
    ) === 1,
    'lifecycle service remains available'
);

$check(
    substr_count(
        $page,
        'class="col-lg-6"'
    ) >= 2,
    'remaining lifecycle actions use the simplified two-column layout'
);

$check(
    strpos(
        $page,
        'This section keeps the resulting acquisition history visible for audit and controlled correction'
    ) !== false,
    'acquisition section explains the new one-entry workflow'
);

$check(
    substr_count(
        $page,
        'poultry_acquisition_void('
    ) === 1,
    'auditable acquisition void behavior remains'
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
