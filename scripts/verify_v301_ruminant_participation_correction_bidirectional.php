<?php

$root =
    dirname(__DIR__);

$serviceFile =
    $root
    . '/lib/ruminant_participation_correction.php';

$migrationFile =
    $root
    . '/migrations/078_ruminant_participation_correction_bidirectional.sql';

$service =
    is_file($serviceFile)
        ? (string)file_get_contents(
            $serviceFile
        )
        : '';

$migration =
    is_file($migrationFile)
        ? (string)file_get_contents(
            $migrationFile
        )
        : '';

$checks = [];

$check =
    static function (
        bool $condition,
        string $label
    ) use (&$checks): void {
        $checks[] = [
            'ok' => $condition,
            'label' => $label,
        ];
    };


$check(
    $service !== '',
    'central correction service exists'
);

$check(
    $migration !== '',
    'migration 078 exists'
);

$check(
    strpos(
        $service,
        'ruminant_participation_correction_direction'
    ) !== false,
    'one central correction service owns direction policy'
);

$check(
    strpos(
        $service,
        'ruminant_participation_correction_later_dependency_blockers_locked'
    ) !== false,
    'later corrections have central locked dependency validation'
);

foreach (
    [
        'ruminant_animal_weights',
        'ruminant_health_events',
        'ruminant_animal_exit_events',
        'ruminant_sale_animal_allocations',
        'ruminant_expense_animal_allocations',
        'ruminant_slaughter_batches',
        'ruminant_animal_cycle_transfers',
        'production_population_transfers',
        'ruminant_animal_cycle_memberships',
    ]
    as $requiredDependency
) {
    $check(
        strpos(
            $service,
            $requiredDependency
        ) !== false,
        'later dependency guard includes '
        . $requiredDependency
    );
}

$check(
    strpos(
        $service,
        'opened_by_transfer_id'
    ) !== false
    &&
    strpos(
        $service,
        'Reverse or correct the transfer instead.'
    ) !== false,
    'transfer-owned starts remain protected'
);

$check(
    strpos(
        $service,
        'ruminant_economic_participation_exact_cycle_population'
    ) !== false,
    'physical-population evidence remains mandatory'
);

$check(
    strpos(
        $service,
        'registered_after_count'
    ) !== false,
    'named participation remains bounded by physical population'
);

$check(
    strpos(
        $service,
        'frozen_cycle_slaughter_batch_count'
    ) !== false,
    'cycle-level frozen slaughter exposure is disclosed'
);

$check(
    strpos(
        $migration,
        'DROP CONSTRAINT chk_rpc_farm_entry_extension'
    ) !== false
    &&
    strpos(
        $migration,
        'DROP CONSTRAINT chk_rpc_membership_extension'
    ) !== false,
    'migration retires earlier-only database checks'
);

$check(
    strpos(
        $migration,
        'chk_rpc_direction_coherent'
    ) !== false,
    'migration enforces coherent correction direction'
);

$check(
    strpos(
        $migration,
        'chk_rpc_has_change'
    ) !== false,
    'migration rejects no-op audit rows'
);

$check(
    strpos(
        $migration,
        'DROP CONSTRAINT chk_rpc_entry_before_participation'
    ) === false,
    'farm-entry-before-participation constraint is preserved'
);

$check(
    strpos(
        $migration,
        'UPDATE ruminant_participation_corrections'
    ) === false,
    'migration never rewrites immutable correction history'
);

$check(
    strpos(
        $migration,
        '078_ruminant_participation_correction_bidirectional.sql'
    ) !== false,
    'migration records schema version'
);


require_once $serviceFile;


$check(
    ruminant_participation_correction_direction(
        '2026-09-14',
        '2026-09-06',
        '2026-09-14',
        '2026-09-06'
    ) === 'earlier',
    'existing Goat-style earlier correction remains supported'
);

$check(
    ruminant_participation_correction_direction(
        '2026-09-01',
        '2026-09-02',
        '2026-09-01',
        '2026-09-02'
    ) === 'later',
    'later correction direction is supported'
);

$check(
    ruminant_participation_correction_direction(
        '2026-09-01',
        '2026-09-01',
        '2026-09-01',
        '2026-09-02'
    ) === 'later',
    'membership-only later correction is coherent'
);

$check(
    ruminant_participation_correction_direction(
        '2026-09-02',
        '2026-09-01',
        '2026-09-02',
        '2026-09-02'
    ) === 'earlier',
    'farm-entry-only earlier correction is coherent'
);

$check(
    ruminant_participation_correction_direction(
        '2026-09-01',
        '2026-09-01',
        '2026-09-01',
        '2026-09-01'
    ) === 'unchanged',
    'unchanged direction is identifiable for apply-level rejection'
);

$mixedRejected = false;

try {
    ruminant_participation_correction_direction(
        '2026-09-01',
        '2026-09-02',
        '2026-09-02',
        '2026-09-01'
    );
} catch (
    RuminantParticipationCorrectionException $e
) {
    $mixedRejected = true;
}

$check(
    $mixedRejected,
    'mixed historical direction is rejected'
);


$failed = 0;

foreach ($checks as $row) {
    if (!$row['ok']) {
        $failed++;

        echo
            'FAIL: '
            . $row['label']
            . PHP_EOL;
    }
}

if ($failed === 0) {
    echo
        'RUMINANT_PARTICIPATION_CORRECTION_BIDIRECTIONAL=PASS CHECKS='
        . count($checks)
        . ' FAILED=0'
        . PHP_EOL;

    exit(0);
}

echo
    'RUMINANT_PARTICIPATION_CORRECTION_BIDIRECTIONAL=FAIL CHECKS='
    . count($checks)
    . ' FAILED='
    . $failed
    . PHP_EOL;

exit(1);
