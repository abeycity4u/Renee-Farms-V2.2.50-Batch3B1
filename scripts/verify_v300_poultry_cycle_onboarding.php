<?php
/**
 * V3 Step 5B1 — Poultry Cycle Onboarding verifier.
 *
 * Static/pure contract verifier:
 * - no database connection;
 * - no database writes;
 * - proves the new-cycle UI delegates to canonical poultry services;
 * - proves Manage Cycle remains a valid workspace target;
 * - keeps the canonical lifecycle service protected.
 */

$root = dirname(__DIR__);

$pagePath = $root . '/management/production_cycles.php';
$managePath = $root . '/management/poultry_cycle.php';
$acqPath = $root . '/lib/poultry_cycle_acquisition.php';
$lifecyclePath = $root . '/lib/poultry_cycle_lifecycle.php';
$helperPath = $root . '/lib/poultry_cycle_onboarding.php';
$jsPath = $root . '/assets/js/production-cycles.js';

$checks = 0;
$failures = 0;

$check = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
};

$page = is_file($pagePath) ? (string)file_get_contents($pagePath) : '';
$manage = is_file($managePath) ? (string)file_get_contents($managePath) : '';
$acq = is_file($acqPath) ? (string)file_get_contents($acqPath) : '';
$lifecycle = is_file($lifecyclePath) ? (string)file_get_contents($lifecyclePath) : '';
$helper = is_file($helperPath) ? (string)file_get_contents($helperPath) : '';
$js = is_file($jsPath) ? (string)file_get_contents($jsPath) : '';

$check($page !== '', 'Production Cycles page is readable');
$check($helper !== '', 'shared poultry onboarding helper exists');
$check($acq !== '', 'canonical poultry acquisition service is readable');
$check($lifecycle !== '', 'canonical poultry lifecycle service is readable');
$check($js !== '', 'Production Cycles JavaScript is readable');

$createStart = strpos($page, "if (\$action === 'create_cycle')");
$createEnd = strpos($page, "if (\$action === 'update_bird_cost_basis')");
$createSection = '';

if (
    $createStart !== false
    && $createEnd !== false
    && $createEnd > $createStart
) {
    $createSection = substr(
        $page,
        $createStart,
        $createEnd - $createStart
    );
}

$check(
    $createSection !== '',
    'Create Cycle POST adapter is discoverable'
);

$check(
    substr_count(
        $createSection,
        'poultry_cycle_onboarding_record_initial('
    ) === 1,
    'Create Cycle delegates poultry initialization exactly once'
);

$check(
    strpos($helper, 'poultry_acquisition_record(') !== false
    && substr_count($helper, 'poultry_acquisition_record(') === 1,
    'onboarding helper delegates flock entry exactly once'
);

$check(
    strpos($helper, 'poultry_lifecycle_record_initial_phase(') !== false
    && substr_count(
        $helper,
        'poultry_lifecycle_record_initial_phase('
    ) === 1,
    'onboarding helper delegates initial lifecycle exactly once'
);

$check(
    strpos($helper, '->prepare(') === false
    && strpos($helper, '->query(') === false
    && stripos($helper, 'INSERT INTO') === false
    && stripos($helper, 'UPDATE ') === false
    && stripos($helper, 'DELETE FROM') === false,
    'onboarding helper owns no direct SQL persistence'
);

$check(
    strpos($helper, '$pdo->inTransaction()') !== false
    && strpos($helper, '$pdo->beginTransaction()') !== false
    && strpos($helper, '$pdo->commit()') !== false
    && strpos($helper, '$pdo->rollBack()') !== false,
    'onboarding helper is atomic while preserving caller transactions'
);

$check(
    strpos($createSection, "'quantity' => (int)\$openingHeadcount") !== false
    && strpos(
        $createSection,
        "'age_days' => max(1, (int)\$startAgeDays)"
    ) !== false,
    'new poultry onboarding reuses opening headcount and start age instead of duplicating them'
);

$check(
    strpos($createSection, "'start_date' => \$startDate") !== false,
    'new-cycle flock entry and initial stage share the explicit cycle start date'
);

$check(
    strpos($page, 'name="poultry_acquisition_type"') !== false
    && strpos($page, 'name="poultry_initial_phase"') !== false,
    'Create Cycle explicitly asks entry type and starting biological stage'
);

$check(
    strpos($page, 'Farm-raised / internal transfer') !== false
    && strpos(
        $page,
        'Do not use it for an external purchase'
    ) !== false,
    'internal-transfer meaning is explicit and cannot be mistaken for an external purchase'
);

$check(
    strpos(
        $acq,
        "'purchased' => 'Purchased birds (DOC or older birds)'"
    ) !== false
    && strpos(
        $acq,
        "'purchased' => 'Purchased pullets / young birds'"
    ) !== false,
    'canonical purchase labels preserve actual-age semantics for Broiler and Layer'
);

$check(
    strpos(
        $acq,
        "\$acquisitionType !== 'internal_transfer' && \$totalCost === null"
    ) !== false
    && strpos(
        $acq,
        'Enter the actual total amount paid for purchased birds.'
    ) !== false,
    'external purchases still require a real acquisition amount'
);

$check(
    strpos(
        $helper,
        "\$acquisitionType === 'purchased_point_of_lay'"
    ) !== false
    && strpos(
        $helper,
        "\$initialPhase !== 'production'"
    ) !== false,
    'purchased Point-of-Lay cannot fabricate a Rearing phase'
);

$check(
    strpos(
        $page,
        'Harvest / Sale is a biological stage; it does not record a sales transaction.'
    ) !== false,
    'Broiler Harvest / Sale is explained as lifecycle state rather than a sale write'
);

$check(
    strpos(
        $page,
        '/management/poultry_cycle.php?id='
    ) !== false
    && strpos(
        $createSection,
        '/management/poultry_cycle.php?id='
    ) !== false,
    'successful poultry creation redirects into the existing Manage Cycle workspace'
);

$check(
    strpos(
        $createSection,
        "\$_SESSION['success']"
    ) !== false
    && strpos(
        $createSection,
        'header('
    ) !== false
    && strpos(
        $createSection,
        'exit();'
    ) !== false,
    'successful poultry creation uses POST-Redirect-GET'
);

$check(
    strpos(
        $page,
        'value="record_poultry_acquisition"'
    ) !== false
    && strpos(
        $page,
        'value="void_poultry_acquisition"'
    ) !== false,
    'legacy acquisition setup/correction remains available for existing cycles'
);

$check(
    strpos(
        $page,
        'value="set_initial_poultry_phase"'
    ) !== false
    && strpos(
        $page,
        'value="transition_poultry_phase"'
    ) !== false
    && strpos(
        $page,
        'value="end_poultry_phase"'
    ) !== false,
    'legacy lifecycle completion/transition tools remain available'
);

$check(
    $manage !== ''
    && strpos(
        $manage,
        'Poultry Cycle Workspace'
    ) !== false
    && strpos(
        $manage,
        '/management/production_cycles.php'
    ) !== false
    && strpos(
        $manage,
        'poultry_acquisition_history('
    ) !== false
    && strpos(
        $manage,
        'poultry_lifecycle_history('
    ) !== false,
    'Manage Cycle remains the valid contextual workspace target for poultry onboarding'
);

$check(
    hash('sha256', $lifecycle)
        === 'd6b94f1e630db0dccb80fd72662dd8e25fb728233f3796162effb80ef951b28b',
    'canonical lifecycle service is byte-for-byte unchanged from the Step 5B1 gate'
);

$check(
    strpos(
        $js,
        'New-cycle poultry onboarding.'
    ) !== false
    && strpos(
        $js,
        "acquisitionType.value === 'purchased_point_of_lay'"
    ) !== false
    && strpos(
        $js,
        "initialPhase.value = 'production'"
    ) !== false,
    'UI guides POL creation to Production without changing service rules'
);

$check(
    strpos(
        $js,
        'Internal carry-in may remain uncosted until a defensible cost basis exists.'
    ) !== false,
    'UI explains why internal carry-in may be uncosted'
);

$check(
    strpos(
        $createSection,
        'INSERT INTO poultry_cycle_acquisitions'
    ) === false
    && strpos(
        $createSection,
        'INSERT INTO production_cycle_phases'
    ) === false,
    'Create Cycle route does not duplicate acquisition or lifecycle SQL'
);

echo PHP_EOL;
echo "Checks: {$checks}" . PHP_EOL;
echo "Failures: {$failures}" . PHP_EOL;

if ($failures > 0) {
    echo "V3.0 POULTRY CYCLE ONBOARDING: FAILED" . PHP_EOL;
    echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
    echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
    exit(1);
}

echo "V3.0 POULTRY CYCLE ONBOARDING: PASSED" . PHP_EOL;
echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
