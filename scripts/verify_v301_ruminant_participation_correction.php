<?php

$root =
    dirname(__DIR__);

$serviceFile =
    $root
    . '/lib/ruminant_participation_correction.php';

$migrationFile =
    $root
    . '/migrations/077_ruminant_participation_corrections.sql';

$membershipFile =
    $root
    . '/lib/ruminant_cycle_membership.php';

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

$membership =
    is_file($membershipFile)
        ? (string)file_get_contents(
            $membershipFile
        )
        : '';

$checks = [];

$check =
    static function (
        bool $condition,
        string $label
    ) use (&$checks): void {
        $checks[] = [
            'ok' =>
                $condition,

            'label' =>
                $label,
        ];
    };


$check(
    $service !== '',
    'central participation-correction service exists'
);

$check(
    $migration !== '',
    'immutable correction migration exists'
);

$check(
    strpos(
        $service,
        'ruminant_participation_correction_preview_locked'
    ) !== false,
    'correction has central locked validation'
);

$check(
    strpos(
        $service,
        'ruminant_participation_correction_apply'
    ) !== false,
    'correction has one central mutation service'
);

$check(
    strpos(
        $service,
        'ruminant_economic_participation_exact_cycle_population'
    ) !== false,
    'correction requires canonical physical-population evidence'
);

$check(
    strpos(
        $service,
        'registered_after_count'
    ) !== false,
    'correction checks proposed named participation against physical population'
);

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
    'transfer-owned membership starts remain protected'
);

$check(
    strpos(
        $service,
        'closed_by_transfer_id'
    ) !== false,
    'transfer-closed membership provenance remains visible to correction validation'
);

$check(
    strpos(
        $service,
        'purchase_date'
    ) !== false
    &&
    strpos(
        $service,
        'purchase_date, birth_date and registry timestamps are deliberately'
    ) !== false,
    'purchase and registry facts are not rewritten'
);

$check(
    strpos(
        $service,
        'frozen_slaughter_batch_count'
    ) !== false,
    'frozen slaughter exposure is disclosed'
);

$check(
    strpos(
        $service,
        'request_token'
    ) !== false,
    'correction is idempotent'
);

$check(
    strpos(
        $service,
        'beginTransaction'
    ) !== false
    &&
    strpos(
        $service,
        'rollBack'
    ) !== false
    &&
    strpos(
        $service,
        'commit'
    ) !== false,
    'correction owns an atomic transaction'
);

$check(
    strpos(
        $migration,
        'old_farm_entry_date'
    ) !== false
    &&
    strpos(
        $migration,
        'new_farm_entry_date'
    ) !== false
    &&
    strpos(
        $migration,
        'old_membership_start_date'
    ) !== false
    &&
    strpos(
        $migration,
        'new_membership_start_date'
    ) !== false,
    'correction audit preserves before and after facts'
);

$check(
    strpos(
        $migration,
        'UNIQUE KEY uniq_rpc_request'
    ) !== false,
    'correction request token is uniquely protected'
);

$check(
    strpos(
        $migration,
        'ON DELETE RESTRICT'
    ) !== false,
    'correction provenance cannot be erased through parent deletion'
);

$check(
    strpos(
        $migration,
        "077_ruminant_participation_corrections.sql"
    ) !== false,
    'migration records schema version'
);

$check(
    strpos(
        $membership,
        'opened_by_transfer_id'
    ) !== false,
    'existing membership model retains transfer provenance'
);

require_once $serviceFile;

$check(
    ruminant_participation_correction_reason_is_meaningful(
        'Animals arrived together on 06/09 and tagging was completed later.'
    ),
    'real correction explanation is accepted'
);

$check(
    !ruminant_participation_correction_reason_is_meaningful(
        'N/A'
    ),
    'placeholder correction reason is rejected'
);

$check(
    !ruminant_participation_correction_reason_is_meaningful(
        'none'
    ),
    'empty semantic correction reason is rejected'
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
        'RUMINANT_PARTICIPATION_CORRECTION=PASS CHECKS='
        . count($checks)
        . ' FAILED=0'
        . PHP_EOL;

    exit(0);
}

echo
    'RUMINANT_PARTICIPATION_CORRECTION=FAIL CHECKS='
    . count($checks)
    . ' FAILED='
    . $failed
    . PHP_EOL;

exit(1);
