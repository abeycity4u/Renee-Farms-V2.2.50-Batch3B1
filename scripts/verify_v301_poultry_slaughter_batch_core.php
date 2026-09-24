<?php

$root = dirname(__DIR__);

$serviceFile =
    $root
    .
    '/lib/poultry_slaughter_service.php';

$populationFile =
    $root
    .
    '/lib/production_population.php';

$migrationFile =
    $root
    .
    '/migrations/079_poultry_slaughter_processing_foundation.sql';

$fail =
    static function (
        string $message
    ): void {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );

        exit(1);
    };

$pass =
    static function (
        string $message
    ): void {
        echo
            "PASS: {$message}\n";
    };


foreach (
    [
        $serviceFile,
        $populationFile,
        $migrationFile,
    ]
    as $file
) {
    if (
        !is_file($file)
        ||
        !is_readable($file)
    ) {
        $fail(
            'Required source file is missing: '
            .
            basename($file)
        );
    }
}


$service =
    file_get_contents(
        $serviceFile
    );

$population =
    file_get_contents(
        $populationFile
    );

$migration =
    file_get_contents(
        $migrationFile
    );

if (
    $service === false
    ||
    $population === false
    ||
    $migration === false
) {
    $fail(
        'Required source could not be read.'
    );
}


$requiredServiceSnippets = [
    'function poultry_slaughter_batch_create(',
    'function poultry_slaughter_cost_basis_locked(',
    'function poultry_slaughter_allocate_pool_cents(',
    'production_population_lock_cycle(',
    'production_population_lock_baseline(',
    'production_population_balance_locked(',
    'production_population_project_delta_locked(',
    "'poultry_slaughter'",
    "'movement_type' =>",
    "'slaughter'",
    'production_population_projection_sync(',
    'poultry_acquisition_history(',
    'getProfitabilitySummary(',
    "status <> 'reversed'",
    'FOR UPDATE',
    "'processing_operating_cost' =>",
    '0.0',
    'cost_basis_provenance_fingerprint',
    'canonical_cycle_cost_pool_proportional_v1',
];

foreach (
    $requiredServiceSnippets
    as $snippet
) {
    if (
        strpos(
            $service,
            $snippet
        ) === false
    ) {
        $fail(
            'Poultry slaughter service is missing contract: '
            .
            $snippet
        );
    }
}

$pass(
    'Poultry slaughter service contains canonical batch/population/costing contracts'
);


if (
    substr_count(
        $population,
        "'poultry_slaughter',"
    ) !== 1
) {
    $fail(
        'Canonical population source catalog must contain poultry_slaughter exactly once.'
    );
}

$pass(
    'Canonical population source catalog owns poultry_slaughter'
);


$batchCreateStart =
    strpos(
        $service,
        'function poultry_slaughter_batch_create('
    );

$batchCreateEnd =
    $batchCreateStart === false
        ? false
        : strpos(
            $service,
            "if (!function_exists('poultry_slaughter_batch_locked')) {",
            $batchCreateStart
        );

if (
    $batchCreateStart === false
    ||
    $batchCreateEnd === false
    ||
    $batchCreateEnd <= $batchCreateStart
) {
    $fail(
        'Batch-core verifier could not isolate poultry_slaughter_batch_create().'
    );
}

$batchCreateSlice =
    substr(
        $service,
        $batchCreateStart,
        $batchCreateEnd
        -
        $batchCreateStart
    );

if (
    strpos(
        $batchCreateSlice,
        'stock_apply_movement('
    ) !== false
) {
    $fail(
        'Batch creation must not mutate Inventory.'
    );
}

if (
    preg_match(
        '/INSERT\s+INTO\s+sales_records/i',
        $batchCreateSlice
    )
) {
    $fail(
        'Poultry slaughter batch creation must not create Sales revenue.'
    );
}

if (
    preg_match(
        '/INSERT\s+INTO\s+poultry_slaughter_outputs/i',
        $batchCreateSlice
    )
) {
    $fail(
        'Poultry slaughter batch creation must not create processed-output Inventory rows.'
    );
}

$pass(
    'Batch creation remains separate from Inventory output and Sales revenue'
);


if (
    strpos(
        $migration,
        'chk_poultry_slaughter_full_cost_conservation'
    ) === false
) {
    $fail(
        'Migration 079 must enforce full-cost conservation.'
    );
}

if (
    strpos(
        $migration,
        'UNIQUE KEY uniq_poultry_slaughter_request'
    ) === false
) {
    $fail(
        'Migration 079 must enforce request-token idempotency.'
    );
}

$pass(
    'Migration 079 preserves full-cost and request-token invariants'
);


require_once $serviceFile;


$tests = [
    [
        100000,
        25,
        100,
        25000,
        'proportional allocation',
    ],
    [
        75000,
        75,
        75,
        75000,
        'final flock absorbs rounding residue',
    ],
    [
        0,
        10,
        100,
        0,
        'zero pool remains zero',
    ],
];

foreach (
    $tests
    as [
        $pool,
        $birds,
        $populationBefore,
        $expected,
        $label,
    ]
) {
    $actual =
        poultry_slaughter_allocate_pool_cents(
            $pool,
            $birds,
            $populationBefore
        );

    if ($actual !== $expected) {
        $fail(
            "Cost-pool test failed for {$label}: expected {$expected}, got {$actual}."
        );
    }
}

$pass(
    'Cost-pool allocation conserves proportional and final-batch value'
);


$threw = false;

try {
    poultry_slaughter_allocate_pool_cents(
        10000,
        101,
        100
    );

} catch (InvalidArgumentException $e) {
    $threw = true;
}

if (!$threw) {
    $fail(
        'Over-slaughter cost allocation must be rejected.'
    );
}

$pass(
    'Over-slaughter allocation is rejected before persistence'
);


/*
 * Stage 14G deployment-readiness invariant:
 *
 * Acquisition completeness is type-neutral. An internal transfer, purchase,
 * hatch or any future acquisition source must not silently become a zero-cost
 * slaughter basis merely because its acquisition type differs.
 */
$serviceSource =
    file_get_contents(
        $serviceFile
    );

if (!is_string($serviceSource)) {
    $fail(
        'Poultry slaughter service source could not be read for acquisition-cost verification.'
    );
}

$acquisitionStart =
    strpos(
        $serviceSource,
        'function poultry_slaughter_acquisition_basis_as_of('
    );

$nextAcquisitionFunction =
    $acquisitionStart === false
        ? false
        : strpos(
            $serviceSource,
            "if (!function_exists('poultry_slaughter_prior_batches_locked'))",
            $acquisitionStart
        );

if (
    $acquisitionStart === false
    ||
    $nextAcquisitionFunction === false
    ||
    $nextAcquisitionFunction <= $acquisitionStart
) {
    $fail(
        'Batch-core verifier could not isolate poultry_slaughter_acquisition_basis_as_of().'
    );
}

$acquisitionContract =
    substr(
        $serviceSource,
        $acquisitionStart,
        $nextAcquisitionFunction - $acquisitionStart
    );

$requiredAcquisitionGuards = [
    "\$row['total_cost'] === null" =>
        'NULL acquisition cost is rejected',

    "\$row['total_cost'] === ''" =>
        'empty acquisition cost is rejected',

    'an active flock acquisition has no defensible cost basis' =>
        'active uncosted acquisition fails closed',

    'requires at least one active, costed flock acquisition for this cycle' =>
        'cycle requires an active costed acquisition',

    'poultry_acquisition_history(' =>
        'slaughter acquisition basis uses canonical acquisition history',
];

foreach (
    $requiredAcquisitionGuards
    as $needle => $label
) {
    if (
        !str_contains(
            $acquisitionContract,
            $needle
        )
    ) {
        $fail(
            "Poultry slaughter acquisition contract regression: {$label}."
        );
    }
}

$forbiddenZeroFallbackPatterns = [
    '/COALESCE\s*\([^)]*total_cost[^)]*,\s*0\s*\)/i',
    '/total_cost\s*=\s*0(?:\.0+)?\b/i',
    '/acquisition_basis\s*=\s*0(?:\.0+)?\b/i',
];

foreach (
    $forbiddenZeroFallbackPatterns
    as $pattern
) {
    if (
        preg_match(
            $pattern,
            $acquisitionContract
        ) === 1
    ) {
        $fail(
            'Poultry slaughter acquisition contract must not silently replace missing acquisition cost with zero.'
        );
    }
}

$pass(
    'Poultry slaughter acquisition costing remains fail-closed for every acquisition type'
);


echo
    "PASS: Stage 14E-2 poultry slaughter batch core source contract\n";
