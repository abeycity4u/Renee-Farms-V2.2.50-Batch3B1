<?php

/**
 * V3.0.1 G3 — Production Cycles dashboard consolidation.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$page =
    (string)file_get_contents(
        $root
        . '/management/production_cycles.php'
    );

$js =
    (string)file_get_contents(
        $root
        . '/assets/js/production-cycles.js'
    );

$checks = [];
$failures = [];

$check = static function (
    string $name,
    bool $ok
) use (
    &$checks,
    &$failures
): void {
    $checks[$name] = $ok;

    if (!$ok) {
        $failures[] = $name;
    }
};

$createMarker =
    strpos(
        $page,
        'id="create-cycle"'
    );

$maintenanceMarker =
    strpos(
        $page,
        'id="cycle-maintenance-tools"'
    );

$recentMarker =
    strpos(
        $page,
        'id="recent-cycles"'
    );

$check(
    'CHOOSE_CYCLE_BUTTON_RETIRED',
    strpos(
        $page,
        'Choose a Cycle'
    ) === false
    && strpos(
        $page,
        'href="#recent-cycles"'
    ) === false
);

$check(
    'NEW_CYCLE_BUTTON_RETIRED',
    strpos(
        $page,
        'data-open-cycle-tools'
    ) === false
    && strpos(
        $page,
        'href="#create-cycle"'
    ) === false
);

$check(
    'OUTER_CREATE_COLLAPSE_RETIRED',
    strpos(
        $page,
        'id="cycle-tools"'
    ) === false
);

$check(
    'CREATE_FORM_DIRECT',
    $createMarker !== false
    && substr_count(
        $page,
        'value="create_cycle"'
    ) === 1
    && $maintenanceMarker !== false
    && $createMarker < $maintenanceMarker
);

$adminWindow =
    $createMarker !== false
        ? substr(
            $page,
            max(
                0,
                $createMarker - 600
            ),
            700
        )
        : '';

$check(
    'CREATE_FORM_PRIVILEGED',
    strpos(
        $adminWindow,
        "isPlatformOwner() || hasRole('farm_admin')"
    ) !== false
);

$check(
    'ADVANCED_MAINTENANCE_REMAINS_COLLAPSIBLE',
    $maintenanceMarker !== false
    && strpos(
        $page,
        '<details',
        max(
            0,
            $maintenanceMarker - 200
        )
    ) !== false
);

$check(
    'CYCLE_LIST_REMAINS_AFTER_MAINTENANCE',
    $recentMarker !== false
    && $maintenanceMarker !== false
    && $recentMarker > $maintenanceMarker
);

$check(
    'ACQUISITION_SINGLE_TABLE',
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
    ) !== false
    && strpos(
        $page,
        'poultry_acquisition_cost_per_bird('
    ) !== false
);

$check(
    'ACQUISITION_AUDIT_STATES_PRESERVED',
    strpos(
        $page,
        '<span class="badge bg-success">Active</span>'
    ) !== false
    && strpos(
        $page,
        '<span class="badge bg-secondary">Voided</span>'
    ) !== false
    && strpos(
        $page,
        'void_reason'
    ) !== false
);

$check(
    'ACQUISITION_EMPTY_LEGACY_STATE_PRESERVED',
    strpos(
        $page,
        'Legacy cycle with no recorded acquisition history.'
    ) !== false
);

$check(
    'ACQUISITION_DUPLICATE_SUMMARY_MAP_RETIRED',
    strpos(
        $page,
        '$poultryAcquisitionSummaryByCycle'
    ) === false
);

$check(
    'LIFECYCLE_SINGLE_TABLE',
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
    ) !== false
);

$check(
    'LIFECYCLE_CURRENT_COMPLETED_STATES_PRESERVED',
    strpos(
        $page,
        '<span class="badge bg-primary">Current</span>'
    ) !== false
    && strpos(
        $page,
        '<span class="badge bg-secondary">Completed</span>'
    ) !== false
    && strpos(
        $page,
        "phaseRow['end_date'] === null"
    ) !== false
);

$check(
    'LIFECYCLE_UNDEFINED_LEGACY_STATE_PRESERVED',
    strpos(
        $page,
        'No lifecycle history is recorded for this legacy cycle.'
    ) !== false
);

$check(
    'LIFECYCLE_DUPLICATE_CURRENT_MAP_RETIRED',
    strpos(
        $page,
        '$poultryLifecycleByCycle'
    ) === false
);

$check(
    'LEGACY_SETUP_OWNERSHIP_UNCHANGED',
    strpos(
        $page,
        '/management/legacy_cycle_setup.php'
    ) !== false
    && strpos(
        $page,
        'value="confirm_population_cutover"'
    ) === false
);

$check(
    'MANUAL_BIRD_COST_REMAINS_RETIRED',
    strpos(
        $page,
        'value="update_bird_cost_basis"'
    ) === false
    && strpos(
        $page,
        'name="bird_unit_cost"'
    ) === false
);

$check(
    'MANAGE_CYCLE_ROUTE_PRESERVED',
    strpos(
        $page,
        '/management/poultry_cycle.php?id='
    ) !== false
);

$check(
    'REMOVED_CTA_JS_RETIRED',
    strpos(
        $js,
        "document.getElementById('cycle-tools')"
    ) === false
    && strpos(
        $js,
        'data-open-cycle-tools'
    ) === false
    && strpos(
        $js,
        'openTargetedCycleTools'
    ) === false
);

$check(
    'MAINTENANCE_DEEP_LINK_JS_PRESERVED',
    strpos(
        $js,
        'openTargetedMaintenance'
    ) !== false
    && strpos(
        $js,
        'maintenanceTools.contains(target)'
    ) !== false
    && strpos(
        $js,
        'maintenanceTools.open = true'
    ) !== false
);

$check(
    'NO_G2_RUMINANT_CLOSE_IMPLEMENTATION',
    strpos(
        $page,
        'close_ruminant'
    ) === false
    && strpos(
        $page,
        'end_ruminant'
    ) === false
);

foreach (
    $checks
    as $name => $ok
) {
    echo $name
        . '='
        . (
            $ok
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;
}

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo 'DATABASE_CONNECTION=NONE'
    . PHP_EOL;

echo 'DATABASE_WRITE=NONE'
    . PHP_EOL;

if ($failures) {
    echo 'FAILED='
        . implode(
            ',',
            $failures
        )
        . PHP_EOL;

    echo 'RESULT=FAIL'
        . PHP_EOL;

    exit(1);
}

echo 'RESULT=PASS'
    . PHP_EOL;

exit(0);
