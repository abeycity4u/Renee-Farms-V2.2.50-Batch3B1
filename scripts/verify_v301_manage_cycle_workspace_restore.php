<?php
/**
 * V3.0.1 — Manage Cycle workspace restoration verifier.
 * Static only: no DB connection and no DB writes.
 */

$root = dirname(__DIR__);
$path = $root . '/management/poultry_cycle.php';
$manage = is_file($path)
    ? (string)file_get_contents($path)
    : '';

$overviewPath =
    $root . '/management/production_cycles.php';

$overview = is_file($overviewPath)
    ? (string)file_get_contents($overviewPath)
    : '';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    $manage !== '',
    'Manage Cycle page is readable'
);

foreach ([
    'poultry_cycle_acquisition.php',
    'poultry_rearing_economics.php',
    'poultry_production_entry_snapshots.php',
    'poultry_cycle_completion.php',
] as $service) {
    $check(
        strpos($manage, $service) !== false,
        'Manage Cycle loads ' . $service
    );
}

foreach ([
    'id="entry"',
    'id="lifecycle"',
    'id="economics"',
    'id="economic-basis"',
    'id="end-production"',
] as $marker) {
    $check(
        substr_count($manage, $marker) === 1,
        'workspace contains exactly one ' . $marker
    );
}

$check(
    strpos(
        $manage,
        'value="transition_poultry_phase"'
    ) !== false
    && strpos(
        $manage,
        'poultry_lifecycle_transition_phase('
    ) !== false,
    'Lifecycle transition is restored to selected cycle'
);

$check(
    strpos(
        $manage,
        'value="void_poultry_acquisition"'
    ) === false
    && strpos(
        $manage,
        'poultry_acquisition_void('
    ) === false
    && strpos(
        $manage,
        'Correct an Erroneous Entry'
    ) === false,
    'routine acquisition correction surface stays retired from Manage Cycle'
);

$check(
    strpos(
        $manage,
        'value="approve_production_entry_basis"'
    ) !== false
    && strpos(
        $manage,
        'poultry_production_entry_approve('
    ) !== false,
    'Production-entry economic approval is restored'
);

$check(
    strpos(
        $manage,
        'value="end_production"'
    ) !== false
    && strpos(
        $manage,
        'poultry_cycle_end_production('
    ) !== false,
    'Canonical End Production remains present'
);

$check(
    strpos(
        $manage,
        'value="record_poultry_acquisition"'
    ) === false
    && strpos(
        $manage,
        'Record Flock Entry'
    ) === false,
    'duplicate flock-entry onboarding stays retired'
);

$check(
    strpos(
        $manage,
        'value="set_initial_poultry_phase"'
    ) === false
    && strpos(
        $manage,
        'Set Initial Biological Stage'
    ) === false,
    'duplicate initial-stage onboarding stays retired'
);

$check(
    strpos(
        $manage,
        'value="end_poultry_phase"'
    ) === false
    && strpos(
        $manage,
        'End Current Stage'
    ) === false,
    'terminal stage uses canonical End Production instead of competing phase-end control'
);

$check(
    strpos(
        $manage,
        'is the terminal biological stage for this cycle.'
    ) !== false
    && strpos(
        $manage,
        'That canonical operation closes the cycle'
    ) !== false,
    'terminal lifecycle UX directs farmer to End Production'
);

$check(
    strpos(
        $manage,
        'production_population_state('
    ) !== false
    && strpos(
        $manage,
        '$populationLabel = \'Tracked Population\';'
    ) !== false,
    'canonical V3 population display is preserved'
);

$check(
    strpos(
        $manage,
        'poultry_acquisition_history('
    ) !== false
    && strpos(
        $manage,
        'poultry_acquisition_summary('
    ) !== false,
    'selected-cycle acquisition history is restored'
);

$check(
    strpos(
        $manage,
        'poultry_rearing_economics('
    ) !== false,
    'Layer rearing economics is restored'
);

$check(
    strpos(
        $manage,
        'poultry_production_entry_candidate('
    ) !== false
    && strpos(
        $manage,
        'poultry_production_entry_snapshots('
    ) !== false,
    'Production-entry economic history is restored'
);

$check(
    strpos(
        $manage,
        'Daily Records ↗'
    ) !== false
    && strpos(
        $manage,
        'Feed Records ↗'
    ) !== false
    && strpos(
        $manage,
        'Health &amp; Treatment ↗'
    ) !== false
    && strpos(
        $manage,
        'Expenses ↗'
    ) !== false,
    'source-module shortcuts are restored'
);

$manageOwnsSql =
    strpos($manage, '->prepare(') !== false
    || strpos($manage, '->query(') !== false
    || stripos($manage, 'INSERT INTO') !== false
    || stripos($manage, 'UPDATE ') !== false
    || stripos($manage, 'DELETE FROM') !== false;

$check(
    !$manageOwnsSql,
    'Manage Cycle remains service-driven with no direct SQL'
);

$check(
    strpos(
        $manage,
        'Historical source economics changed after the latest approval.'
    ) !== false,
    'economic basis warns when approved source economics later change'
);

$check(
    strpos(
        $manage,
        'production_entry_headcount_source'
    ) !== false
    && strpos(
        $manage,
        "['warnings']"
    ) !== false,
    'rearing economics preserves headcount-source and warning intelligence'
);

$check(
    strpos(
        $manage,
        'Basis pending:'
    ) !== false,
    'economic basis explains why approval is not yet ready'
);

$check(
    strpos(
        $manage,
        'revision_reason'
    ) !== false
    && strpos(
        $manage,
        'approved_at'
    ) !== false
    && strpos(
        $manage,
        'approved_by_name'
    ) !== false,
    'approved-basis history preserves revision reason and approval audit details'
);

$adminMutationBlock = '';

if (
    preg_match(
        '/\$adminMutationActions\s*=\s*\[(.*?)\];/s',
        $manage,
        $adminMutationMatch
    ) === 1
) {
    $adminMutationBlock =
        (string)$adminMutationMatch[1];
}

$check(
    $adminMutationBlock !== ''
    && strpos(
        $adminMutationBlock,
        "'end_production'"
    ) !== false
    && strpos(
        $adminMutationBlock,
        "'void_poultry_acquisition'"
    ) === false
    && strpos(
        $adminMutationBlock,
        "'transition_poultry_phase'"
    ) !== false
    && strpos(
        $adminMutationBlock,
        "'approve_production_entry_basis'"
    ) !== false,
    'remaining selected-cycle mutation actions share the Owner/Farm Admin write boundary'
);

$check(
    strpos(
        $manage,
        'if($needsApproval && $canManageCycleOperations):'
    ) !== false
    && strpos(
        $manage,
        'Approval or revision is available to the Platform Owner or Farm Admin.'
    ) !== false,
    'permitted non-admin users retain read-only economic-basis visibility'
);

$check(
    $overview !== '',
    'Production Cycles overview is readable'
);

$check(
    strpos(
        $overview,
        "if (\$action === 'void_poultry_acquisition')"
    ) === false
    && strpos(
        $overview,
        "if (\$action === 'transition_poultry_phase')"
    ) === false
    && strpos(
        $overview,
        "if (\$action === 'end_poultry_phase')"
    ) === false,
    'Production Cycles no longer duplicates selected-cycle poultry mutation routes'
);

$check(
    strpos(
        $overview,
        'value="void_poultry_acquisition"'
    ) === false
    && strpos(
        $overview,
        'value="transition_poultry_phase"'
    ) === false
    && strpos(
        $overview,
        'value="end_poultry_phase"'
    ) === false,
    'Production Cycles no longer duplicates selected-cycle poultry mutation forms'
);

$check(
    strpos(
        $overview,
        '<h6>Recorded Acquisition History</h6>'
    ) !== false
    && strpos(
        $overview,
        '<h6>Recorded Phase History</h6>'
    ) !== false
    && strpos(
        $overview,
        '>Manage Cycle</a>'
    ) !== false,
    'Production Cycles retains aggregate history and navigation into Manage Cycle'
);

echo PHP_EOL;
echo "Checks: {$checks}" . PHP_EOL;
echo "Failures: {$failures}" . PHP_EOL;

if ($failures > 0) {
    echo "V3.0.1 MANAGE CYCLE WORKSPACE: FAILED" . PHP_EOL;
    echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
    echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
    exit(1);
}

echo "V3.0.1 MANAGE CYCLE WORKSPACE: PASSED" . PHP_EOL;
echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
