<?php
/**
 * V3.0 Step 5B2A — Poultry Manage Cycle operational ownership.
 *
 * Static/pure verifier. No database connection or write.
 */

$root = dirname(__DIR__);
$manage = (string)file_get_contents(
    $root . '/management/poultry_cycle.php'
);
$legacy = (string)file_get_contents(
    $root . '/management/production_cycles.php'
);

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
    strpos($manage, "includes/audit_helpers.php") !== false,
    'Manage Cycle preserves audit helper availability'
);

$check(
    strpos(
        $manage,
        "\$canManageCycleOperations=isPlatformOwner() || hasRole('farm_admin');"
    ) !== false
    && strpos(
        $manage,
        "&& !\$canManageCycleOperations"
    ) !== false
    && substr_count(
        $manage,
        "if(\$canManageCycleOperations)"
    ) >= 3,
    'Manage Cycle mutation UI and POST guard share the Owner/Farm Admin capability'
);

$actions = [
    'record_poultry_acquisition',
    'void_poultry_acquisition',
    'set_initial_poultry_phase',
    'transition_poultry_phase',
    'end_poultry_phase',
];

foreach ($actions as $action) {
    $check(
        strpos(
            $manage,
            "\$action==='" . $action . "'"
        ) !== false,
        'Manage Cycle owns ' . $action
    );
}

$delegates = [
    'poultry_acquisition_record(',
    'poultry_acquisition_void(',
    'poultry_lifecycle_record_initial_phase(',
    'poultry_lifecycle_transition_phase(',
    'poultry_lifecycle_end_current_phase(',
];

foreach ($delegates as $delegate) {
    $check(
        substr_count($manage, $delegate) === 1,
        'Manage Cycle delegates exactly once to '
            . rtrim($delegate, '(')
    );
}

$voidStart = strpos(
    $manage,
    "if (\$action==='void_poultry_acquisition')"
);

$voidEnd = strpos(
    $manage,
    "if (\$action==='set_initial_poultry_phase')",
    $voidStart === false ? 0 : $voidStart
);

$voidSection = '';

if (
    $voidStart !== false
    && $voidEnd !== false
    && $voidEnd > $voidStart
) {
    $voidSection = substr(
        $manage,
        $voidStart,
        $voidEnd - $voidStart
    );
}

$check(
    $voidSection !== ''
    && strpos(
        $voidSection,
        'poultry_acquisition_history('
    ) !== false
    && strpos(
        $voidSection,
        '$currentCycleAcquisitionIds'
    ) !== false
    && strpos(
        $voidSection,
        'in_array('
    ) !== false,
    'acquisition correction is constrained to the current Manage Cycle context'
);

$check(
    strpos(
        $manage,
        "INSERT INTO poultry_cycle_acquisitions"
    ) === false
    && strpos(
        $manage,
        "UPDATE poultry_cycle_acquisitions"
    ) === false
    && strpos(
        $manage,
        "INSERT INTO production_cycle_phases"
    ) === false
    && strpos(
        $manage,
        "UPDATE production_cycle_phases"
    ) === false,
    'Manage Cycle duplicates no acquisition/lifecycle SQL'
);

$check(
    substr_count($manage, 'Manage here') >= 2
    && strpos($manage, 'Read only here') === false,
    'Entry and Lifecycle are contextual Manage Cycle operations'
);

$check(
    strpos(
        $manage,
        "poultry_lifecycle_allowed_phases(\$type)"
    ) !== false
    && strpos(
        $manage,
        "poultry_lifecycle_next_phases(\$type,\$current['phase'])"
    ) !== false,
    'Lifecycle forms are generated from canonical lifecycle policy'
);

$check(
    strpos(
        $manage,
        "/management/production_cycles.php#poultry-entry-acquisition"
    ) === false
    && strpos(
        $manage,
        "/management/production_cycles.php#poultry-lifecycle-history"
    ) === false,
    'Manage Cycle no longer sends operations back to Production Cycles'
);

$check(
    strpos(
        $manage,
        "approve_production_entry_basis"
    ) !== false
    && strpos(
        $manage,
        "poultry_rearing_economics("
    ) !== false
    && strpos(
        $manage,
        "poultry_production_entry_candidate("
    ) !== false
    && strpos(
        $manage,
        "poultry_production_entry_snapshots("
    ) !== false,
    'existing economics and immutable-basis workflow remains present'
);

$check(
    strpos(
        $legacy,
        "record_poultry_acquisition"
    ) !== false
    && strpos(
        $legacy,
        "transition_poultry_phase"
    ) !== false,
    'legacy Production Cycles tools remain temporarily available for safe cutover'
);

$check(
    substr_count(
        $manage,
        "/management/poultry_cycle.php?id="
    ) >= 6,
    'successful contextual mutations use Manage Cycle PRG redirects'
);

echo PHP_EOL;
echo "Checks: {$checks}" . PHP_EOL;
echo "Failures: {$failures}" . PHP_EOL;

if ($failures > 0) {
    echo "V3.0 POULTRY MANAGE CYCLE OWNERSHIP: FAILED" . PHP_EOL;
    echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
    echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
    exit(1);
}

echo "V3.0 POULTRY MANAGE CYCLE OWNERSHIP: PASSED" . PHP_EOL;
echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
