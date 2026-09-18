<?php

/**
 * Production Cycles dashboard consolidation verifier.
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

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (
    &$checks,
    &$failures
): void {
    $checks++;

    echo (
        $ok
            ? 'PASS: '
            : 'FAIL: '
    )
    . $message
    . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$compactPage =
    preg_replace(
        '/\s+/',
        ' ',
        $page
    )
    ?? $page;

$compactJs =
    preg_replace(
        '/\s+/',
        ' ',
        $js
    )
    ?? $js;

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
    $page !== '',
    'Production Cycles page exists and is readable'
);

$check(
    $js !== '',
    'Production Cycles JavaScript exists and is readable'
);

$check(
    strpos(
        $compactPage,
        'Create a new production cycle here or manage an existing cycle below.'
    ) !== false,
    'overview copy matches the direct dashboard workflow'
);

$check(
    strpos(
        $page,
        'Choose a Cycle'
    ) === false
    && strpos(
        $page,
        'href="#recent-cycles"'
    ) === false,
    'Choose a Cycle shortcut is retired'
);

$check(
    strpos(
        $page,
        'data-open-cycle-tools'
    ) === false
    && strpos(
        $page,
        'href="#create-cycle"'
    ) === false
    && strpos(
        $page,
        'id="cycle-tools"'
    ) === false,
    'New Cycle trigger and outer Create Cycle collapse are retired'
);

$check(
    $createMarker !== false
    && substr_count(
        $page,
        'value="create_cycle"'
    ) === 1,
    'Create Cycle form remains exactly once'
);

$check(
    $createMarker !== false
    && $maintenanceMarker !== false
    && $recentMarker !== false
    && $createMarker < $maintenanceMarker
    && $maintenanceMarker < $recentMarker,
    'Create Cycle is direct before Advanced Maintenance and cycle list'
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
    strpos(
        $adminWindow,
        "isPlatformOwner() || hasRole('farm_admin')"
    ) !== false,
    'direct Create Cycle form remains privileged'
);

$check(
    $maintenanceMarker !== false
    && strpos(
        $page,
        '<details',
        max(
            0,
            $maintenanceMarker - 200
        )
    ) !== false,
    'Advanced Maintenance remains independently collapsible'
);

$maintenanceSection =
    (
        $maintenanceMarker !== false
        && $recentMarker !== false
        && $recentMarker > $maintenanceMarker
    )
        ? substr(
            $page,
            $maintenanceMarker,
            $recentMarker
                - $maintenanceMarker
        )
        : '';

$check(
    strpos(
        $maintenanceSection,
        '/management/legacy_cycle_setup.php'
    ) !== false
    && strpos(
        $maintenanceSection,
        'Legacy Cycle Setup'
    ) !== false,
    'Legacy Cycle Setup remains available in Advanced Maintenance'
);

$check(
    strpos(
        $maintenanceSection,
        'value="confirm_population_cutover"'
    ) === false
    && strpos(
        $maintenanceSection,
        'value="update_bird_cost_basis"'
    ) === false
    && strpos(
        $maintenanceSection,
        'name="bird_unit_cost"'
    ) === false,
    'retired cutover and manual Bird Cost Basis writes stay absent'
);

$check(
    substr_count(
        $maintenanceSection,
        '<strong>Poultry Acquisition History</strong>'
    ) === 1
    && strpos(
        $maintenanceSection,
        'Recorded Acquisition History'
    ) === false
    && strpos(
        $maintenanceSection,
        'Cost / Bird</th>'
    ) !== false,
    'acquisition summary and audit history are consolidated into one table'
);

$check(
    substr_count(
        $maintenanceSection,
        '<strong>Poultry Lifecycle History</strong>'
    ) === 1
    && strpos(
        $maintenanceSection,
        'Recorded Phase History'
    ) === false
    && strpos(
        $maintenanceSection,
        '<th>Phase Status</th>'
    ) !== false,
    'lifecycle summary and phase history are consolidated into one table'
);

$check(
    strpos(
        $page,
        '$poultryAcquisitionSummaryByCycle'
    ) === false
    && strpos(
        $page,
        '$poultryLifecycleByCycle'
    ) === false,
    'duplicate presentation-only summary maps are retired'
);

$check(
    strpos(
        $page,
        '/management/poultry_cycle.php?id='
    ) !== false
    && strpos(
        $page,
        'Manage Cycle'
    ) !== false,
    'selected-cycle Manage Cycle route remains intact'
);

$check(
    strpos(
        $compactJs,
        "document.getElementById('cycle-tools')"
    ) === false
    && strpos(
        $compactJs,
        'data-open-cycle-tools'
    ) === false
    && strpos(
        $compactJs,
        'openTargetedCycleTools'
    ) === false,
    'JavaScript no longer owns removed Create Cycle triggers'
);

$check(
    strpos(
        $compactJs,
        'openTargetedMaintenance'
    ) !== false
    && strpos(
        $compactJs,
        'maintenanceTools.contains(target)'
    ) !== false
    && strpos(
        $compactJs,
        'maintenanceTools.open = true'
    ) !== false,
    'Advanced Maintenance deep links still open the maintenance workspace'
);

$check(
    strpos(
        $page,
        "if (\$action === 'record_poultry_acquisition')"
    ) === false
    && strpos(
        $page,
        "if (\$action === 'set_initial_poultry_phase')"
    ) === false
    && strpos(
        $page,
        "if (\$action === 'transition_poultry_phase')"
    ) === false,
    'dashboard consolidation does not restore retired poultry mutations'
);

echo PHP_EOL
    . 'Checks: '
    . $checks
    . PHP_EOL;

echo 'Failures: '
    . $failures
    . PHP_EOL;

echo 'DATABASE_CONNECTION_USED=NO'
    . PHP_EOL;

echo 'DATABASE_WRITE_PERFORMED=NO'
    . PHP_EOL;

if ($failures > 0) {
    echo 'V3.0 PRODUCTION CYCLES OVERVIEW UX: FAILED'
        . PHP_EOL;

    exit(1);
}

echo 'V3.0 PRODUCTION CYCLES OVERVIEW UX: PASSED'
    . PHP_EOL;

exit(0);
