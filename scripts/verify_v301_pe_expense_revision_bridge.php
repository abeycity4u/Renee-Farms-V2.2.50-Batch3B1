<?php

/*
 * V3.0.1 — Production-Entry Expense Revision bridge verifier.
 *
 * Pure/static only:
 * - no database connection;
 * - no database writes;
 * - no snapshot approval.
 */

$root = dirname(__DIR__);

require_once
    $root
    . '/lib/poultry_rearing_economics.php';

$failures = [];

$assert = static function (
    bool $condition,
    string $message
) use (&$failures): void {
    if (!$condition) {
        $failures[] =
            $message;
    }
};


$legacyDirect = [
    'id' => 59,
    'expense_date' => '2026-09-12',
    'cycle_id' => 55,
    'category' => 'misc',
    'amount' => '2000.00',
    'unit' => '1.00',
    'expense_revision_no' => null,
    'expense_causal_fingerprint' => null,
];

$legacyDirectExpected =
    poultry_production_entry_provenance_revision([
        'expense_date' => '2026-09-12',
        'cycle_id' => 55,
        'category' => 'misc',
        'amount' => '2000.00',
        'unit' => '1.00',
    ]);

$legacyDirectSource =
    poultry_production_entry_direct_expense_provenance_source(
        $legacyDirect
    );

$assert(
    $legacyDirectSource['source_revision']
        ===
        $legacyDirectExpected,
    'Legacy direct-expense provenance hash changed.'
);


$canonicalFingerprint =
    str_repeat(
        'a',
        64
    );

$canonicalDirectV2 =
    $legacyDirect;

$canonicalDirectV2[
    'expense_revision_no'
] = 2;

$canonicalDirectV2[
    'expense_causal_fingerprint'
] =
    $canonicalFingerprint;

$canonicalDirectSourceV2 =
    poultry_production_entry_direct_expense_provenance_source(
        $canonicalDirectV2
    );

$assert(
    $canonicalDirectSourceV2[
        'source_revision'
    ] === $canonicalFingerprint,
    'Canonical direct expense does not use causal fingerprint.'
);

$assert(
    $canonicalDirectSourceV2[
        'source_version'
    ] === null,
    'Expense revision number leaked into PE source_version.'
);


$canonicalDirectV3 =
    $canonicalDirectV2;

$canonicalDirectV3[
    'expense_revision_no'
] = 3;

$canonicalDirectSourceV3 =
    poultry_production_entry_direct_expense_provenance_source(
        $canonicalDirectV3
    );

$assert(
    $canonicalDirectSourceV3[
        'source_revision'
    ]
        ===
        $canonicalDirectSourceV2[
            'source_revision'
        ],
    'Revision-number-only change altered canonical PE expense identity.'
);


$legacyShared = [
    'id' => 70,
    'expense_date' => '2026-09-12',
    'farm_type' => 'poultry',
    'production_type' => 'Layer',
    'cycle_id' => null,
    'category' => 'misc',
    'amount' => '100.00',
    'unit' => '1.00',
    'expense_revision_no' => null,
    'expense_causal_fingerprint' => null,
];

$legacySharedExpected =
    poultry_production_entry_provenance_revision([
        'expense_date' => '2026-09-12',
        'farm_type' => 'poultry',
        'production_type' => 'layer',
        'cycle_id' => null,
        'category' => 'misc',
        'amount' => '100.00',
        'unit' => '1.00',
    ]);

$legacySharedSource =
    poultry_production_entry_shared_pool_expense_provenance_source(
        $legacyShared
    );

$assert(
    $legacySharedSource[
        'source_revision'
    ] === $legacySharedExpected,
    'Legacy shared-pool expense provenance hash changed.'
);


$sharedFingerprint =
    str_repeat(
        'b',
        64
    );

$canonicalShared =
    $legacyShared;

$canonicalShared[
    'expense_revision_no'
] = 4;

$canonicalShared[
    'expense_causal_fingerprint'
] =
    $sharedFingerprint;

$canonicalSharedSource =
    poultry_production_entry_shared_pool_expense_provenance_source(
        $canonicalShared
    );

$assert(
    $canonicalSharedSource[
        'source_revision'
    ] === $sharedFingerprint,
    'Canonical shared-pool expense does not use causal fingerprint.'
);


$legacyExplicit = [
    'id' => 91,
    'expense_id' => 70,
    'cycle_id' => 55,
    'allocated_amount' => '30.00',
    'expense_date' => '2026-09-12',
    'category' => 'misc',
    'expense_revision_no' => null,
    'expense_causal_fingerprint' => null,
];

$legacyExplicitExpected =
    poultry_production_entry_provenance_revision([
        'expense_id' => 70,
        'cycle_id' => 55,
        'allocated_amount' => '30.00',
        'expense_date' => '2026-09-12',
        'expense_category' => 'misc',
    ]);

$legacyExplicitSource =
    poultry_production_entry_explicit_allocation_provenance_source(
        $legacyExplicit
    );

$assert(
    $legacyExplicitSource[
        'source_revision'
    ] === $legacyExplicitExpected,
    'Legacy explicit-allocation provenance hash changed.'
);


$canonicalExplicit =
    $legacyExplicit;

$canonicalExplicit[
    'expense_revision_no'
] = 4;

$canonicalExplicit[
    'expense_causal_fingerprint'
] =
    $sharedFingerprint;

$canonicalExplicitExpected =
    poultry_production_entry_provenance_revision([
        'expense_id' => 70,
        'cycle_id' => 55,
        'allocated_amount' => '30.00',
        'expense_date' => '2026-09-12',
        'expense_category' => 'misc',
        'expense_causal_fingerprint' =>
            $sharedFingerprint,
    ]);

$canonicalExplicitSource =
    poultry_production_entry_explicit_allocation_provenance_source(
        $canonicalExplicit
    );

$assert(
    $canonicalExplicitSource[
        'source_revision'
    ] === $canonicalExplicitExpected,
    'Canonical explicit allocation does not include parent causal identity.'
);


$legacySharedAllocation = [
    'id' => 92,
    'expense_id' => 70,
    'cycle_id' => 55,
    'allocated_amount' => '30.00',
    'expense_revision_no' => null,
    'expense_causal_fingerprint' => null,
];

$legacySharedAllocationExpected =
    poultry_production_entry_provenance_revision([
        'expense_id' => 70,
        'cycle_id' => 55,
        'allocated_amount' => '30.00',
    ]);

$legacySharedAllocationSource =
    poultry_production_entry_shared_pool_allocation_provenance_source(
        $legacySharedAllocation,
        '2026-09-12'
    );

$assert(
    $legacySharedAllocationSource[
        'source_revision'
    ] === $legacySharedAllocationExpected,
    'Legacy shared-pool allocation provenance hash changed.'
);


$canonicalSharedAllocation =
    $legacySharedAllocation;

$canonicalSharedAllocation[
    'expense_revision_no'
] = 4;

$canonicalSharedAllocation[
    'expense_causal_fingerprint'
] =
    $sharedFingerprint;

$canonicalSharedAllocationExpected =
    poultry_production_entry_provenance_revision([
        'expense_id' => 70,
        'cycle_id' => 55,
        'allocated_amount' => '30.00',
        'expense_causal_fingerprint' =>
            $sharedFingerprint,
    ]);

$canonicalSharedAllocationSource =
    poultry_production_entry_shared_pool_allocation_provenance_source(
        $canonicalSharedAllocation,
        '2026-09-12'
    );

$assert(
    $canonicalSharedAllocationSource[
        'source_revision'
    ] === $canonicalSharedAllocationExpected,
    'Canonical shared-pool allocation does not include parent causal identity.'
);


$partialRejected = false;

try {
    $partial =
        $legacyDirect;

    $partial[
        'expense_revision_no'
    ] = 1;

    poultry_production_entry_direct_expense_provenance_source(
        $partial
    );

} catch (RuntimeException $e) {
    $partialRejected =
        $e->getMessage()
        ===
        'Expense revision provenance metadata is incomplete.';
}

$assert(
    $partialRejected,
    'Partial expense provenance metadata did not fail closed.'
);


$invalidRejected = false;

try {
    $invalid =
        $legacyDirect;

    $invalid[
        'expense_revision_no'
    ] = 1;

    $invalid[
        'expense_causal_fingerprint'
    ] = 'not-a-sha256';

    poultry_production_entry_direct_expense_provenance_source(
        $invalid
    );

} catch (RuntimeException $e) {
    $invalidRejected =
        $e->getMessage()
        ===
        'Expense revision provenance metadata is invalid.';
}

$assert(
    $invalidRejected,
    'Invalid canonical expense fingerprint did not fail closed.'
);


$economics =
    file_get_contents(
        $root
        . '/lib/poultry_rearing_economics.php'
    );

$assert(
    strpos(
        $economics,
        "expense_revision_no,\n             expense_causal_fingerprint\n         FROM farm_expenses"
    ) !== false,
    'Direct expense query does not fetch canonical metadata.'
);

$assert(
    substr_count(
        $economics,
        'e.expense_revision_no'
    ) >= 2
    &&
    substr_count(
        $economics,
        'e.expense_causal_fingerprint'
    ) >= 2,
    'Joined expense queries do not fetch canonical parent metadata.'
);

$assert(
    substr_count(
        $economics,
        "'expense_revision_no' =>"
    ) >= 2
    &&
    substr_count(
        $economics,
        "'expense_causal_fingerprint' =>"
    ) >= 2,
    'Shared-pool provenance rows do not propagate canonical parent metadata.'
);


if ($failures) {
    echo "RESULT=FAIL\n";

    foreach ($failures as $failure) {
        echo "FAIL={$failure}\n";
    }

    exit(1);
}

echo "RESULT=PASS\n";
echo "LEGACY_DIRECT_FALLBACK=PASS\n";
echo "LEGACY_SHARED_POOL_FALLBACK=PASS\n";
echo "LEGACY_EXPLICIT_ALLOCATION_FALLBACK=PASS\n";
echo "LEGACY_SHARED_ALLOCATION_FALLBACK=PASS\n";
echo "CANONICAL_DIRECT_CAUSAL_IDENTITY=PASS\n";
echo "CANONICAL_SHARED_POOL_CAUSAL_IDENTITY=PASS\n";
echo "CANONICAL_EXPLICIT_ALLOCATION_PARENT_IDENTITY=PASS\n";
echo "CANONICAL_SHARED_ALLOCATION_PARENT_IDENTITY=PASS\n";
echo "REVISION_NUMBER_NON_CAUSAL=PASS\n";
echo "PARTIAL_METADATA_FAIL_CLOSED=PASS\n";
echo "INVALID_FINGERPRINT_FAIL_CLOSED=PASS\n";
echo "QUERY_METADATA_COVERAGE=PASS\n";
echo "DATABASE_CONNECTION_USED=NO\n";
echo "DATABASE_WRITE_PERFORMED=NO\n";
