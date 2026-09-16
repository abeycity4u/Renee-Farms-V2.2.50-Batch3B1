<?php

/**
 * V3.0.1 — Production-Entry true provenance transition verifier.
 *
 * Static/pure only:
 * - no database connection;
 * - no database writes;
 * - no snapshot approval.
 */

$root = dirname(__DIR__);

require_once
    $root
    . '/lib/poultry_production_entry_provenance.php';

require_once
    $root
    . '/lib/poultry_production_entry_snapshots.php';

$failures = [];

$assert = static function (
    bool $condition,
    string $message
) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$service =
    file_get_contents(
        $root
        . '/lib/poultry_production_entry_snapshots.php'
    );

$page =
    file_get_contents(
        $root
        . '/management/poultry_cycle.php'
    );

$migration =
    file_get_contents(
        $root
        . '/migrations/062_poultry_production_entry_true_provenance.sql'
    );

$foundation =
    file_get_contents(
        $root
        . '/lib/poultry_production_entry_provenance.php'
    );

$assert(
    strpos(
        $migration,
        'provenance_fingerprint CHAR(64) NULL'
    ) !== false,
    'Migration does not add nullable provenance fingerprint.'
);

$assert(
    strpos(
        $migration,
        'provenance_manifest_json LONGTEXT NULL'
    ) !== false,
    'Migration does not add nullable provenance manifest.'
);

$assert(
    strpos(
        $migration,
        'provenance_source_count INT UNSIGNED NULL'
    ) !== false,
    'Migration does not add nullable provenance source count.'
);

$assert(
    stripos(
        $migration,
        'UPDATE poultry_production_entry_snapshots'
    ) === false,
    'Migration attempts to backfill historical snapshots.'
);

$assert(
    strpos(
        $migration,
        '062_poultry_production_entry_true_provenance.sql'
    ) !== false,
    'Migration registry marker is missing.'
);

$assert(
    strpos(
        $service,
        'poultry_production_entry_provenance_build('
    ) !== false,
    'Candidate does not build true provenance.'
);

$assert(
    strpos(
        $service,
        "'provenance_manifest_json'"
    ) !== false
    && strpos(
        $service,
        "'provenance_fingerprint'"
    ) !== false
    && strpos(
        $service,
        "'provenance_source_count'"
    ) !== false,
    'Candidate does not expose complete provenance payload.'
);

$assert(
    strpos(
        $service,
        "\$candidate['source_fingerprint']=hash('sha256', json_encode(\$fingerprintFacts, JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));"
    ) !== false,
    'Legacy value fingerprint contract was not preserved.'
);

$assert(
    strpos(
        $service,
        'poultry_production_entry_snapshot_comparison('
    ) !== false,
    'Shared snapshot comparison helper is missing.'
);

$assert(
    strpos(
        $service,
        "'comparison_basis' => 'provenance'"
    ) !== false
    && strpos(
        $service,
        "'comparison_basis' => 'legacy_value'"
    ) !== false,
    'Transition comparison does not distinguish provenance from legacy value identity.'
);

$assert(
    strpos(
        $service,
        'UPDATE poultry_production_entry_snapshots'
    ) === false,
    'Approved snapshot mutation was introduced.'
);

$assert(
    strpos(
        $service,
        'provenance_fingerprint,provenance_manifest_json,provenance_source_count'
    ) !== false,
    'Approval INSERT does not persist true provenance.'
);

$assert(
    strpos(
        $page,
        'poultry_production_entry_snapshot_comparison('
    ) !== false,
    'Manage Cycle does not use shared transition comparison.'
);

$assert(
    strpos(
        $page,
        "hash_equals((string)\$latestProductionEntrySnapshot['source_fingerprint']"
    ) === false,
    'Manage Cycle still duplicates legacy fingerprint policy.'
);

$assert(
    strpos(
        $page,
        'Approved provenance not recorded for this legacy version.'
    ) !== false,
    'Legacy provenance state is not exposed.'
);

$assert(
    strpos(
        $page,
        'Historical source provenance changed after the latest approval.'
    ) !== false,
    'True provenance change warning is not exposed.'
);

$assert(
    strpos(
        $page,
        '>Provenance</th>'
    ) !== false
    && strpos(
        $page,
        '>Not recorded</span>'
    ) !== false
    && strpos(
        $page,
        '>Recorded</span>'
    ) !== false,
    'Approved history does not expose provenance recording state.'
);

$assert(
    strpos(
        $foundation,
        'poultry_production_entry_provenance_manifest_json'
    ) !== false,
    'Canonical manifest JSON helper is missing.'
);


/*
 * Pure deterministic manifest/serialization contract.
 */
$context = [
    'farm_id' => 4,
    'cycle_id' => 55,
    'mode' => 'reared',
    'production_entry_date' => '2026-09-15',
    'rearing_start_date' => '2026-09-10',
    'rearing_end_date' => '2026-09-14',
];

$sources = [
    [
        'role' => 'direct_expense',
        'source_type' => 'farm_expense',
        'source_id' => '59',
        'source_revision' =>
            poultry_production_entry_provenance_revision([
                'expense_date' => '2026-09-12',
                'amount' => '2000.00',
            ]),
        'effective_date' => '2026-09-12',
    ],
    [
        'role' => 'feed_use',
        'source_type' => 'stock_transaction',
        'source_id' => '392',
        'source_revision' =>
            poultry_production_entry_provenance_revision([
                'total_cost' => '43333.34',
            ]),
        'effective_date' => '2026-09-11',
    ],
];

$built =
    poultry_production_entry_provenance_build(
        $context,
        $sources
    );

$assert(
    isset($built['manifest_json'])
    && is_string($built['manifest_json']),
    'Build result does not expose canonical manifest JSON.'
);

$assert(
    hash(
        'sha256',
        (string)$built['manifest_json']
    )
        ===
    (string)$built['fingerprint'],
    'Persisted manifest bytes do not reproduce provenance fingerprint.'
);

$decoded =
    json_decode(
        (string)$built['manifest_json'],
        true
    );

$assert(
    is_array($decoded)
    && $decoded === $built['manifest'],
    'Persisted manifest JSON does not reproduce canonical manifest.'
);


/*
 * Transition semantics:
 * old approval -> legacy value comparison only;
 * true-provenance approval -> provenance comparison.
 */
$candidate = [
    'ready' => true,
    'source_fingerprint' =>
        str_repeat('a', 64),
    'provenance_fingerprint' =>
        str_repeat('b', 64),
];

$legacySnapshot = [
    'source_fingerprint' =>
        str_repeat('a', 64),
];

$legacyComparison =
    poultry_production_entry_snapshot_comparison(
        $legacySnapshot,
        $candidate
    );

$assert(
    empty($legacyComparison['changed'])
    && (
        $legacyComparison['comparison_basis']
        ?? ''
    ) === 'legacy_value'
    && empty(
        $legacyComparison['provenance_recorded']
    ),
    'Legacy snapshot did not preserve value-fingerprint comparison.'
);

$trueSnapshotChanged = [
    'source_fingerprint' =>
        str_repeat('a', 64),
    'provenance_fingerprint' =>
        str_repeat('c', 64),
];

$trueChangedComparison =
    poultry_production_entry_snapshot_comparison(
        $trueSnapshotChanged,
        $candidate
    );

$assert(
    !empty($trueChangedComparison['changed'])
    && (
        $trueChangedComparison['comparison_basis']
        ?? ''
    ) === 'provenance'
    && !empty(
        $trueChangedComparison['provenance_recorded']
    ),
    'True provenance composition change was not detected.'
);

$trueSnapshotSame = [
    'source_fingerprint' =>
        str_repeat('0', 64),
    'provenance_fingerprint' =>
        str_repeat('b', 64),
];

$trueSameComparison =
    poultry_production_entry_snapshot_comparison(
        $trueSnapshotSame,
        $candidate
    );

$assert(
    empty($trueSameComparison['changed']),
    'True provenance match incorrectly depends on legacy aggregate value fingerprint.'
);

$firstApproval =
    poultry_production_entry_snapshot_comparison(
        null,
        $candidate
    );

$assert(
    !empty($firstApproval['changed'])
    && (
        $firstApproval['comparison_basis']
        ?? ''
    ) === 'none',
    'First approval transition contract is invalid.'
);

if ($failures) {
    echo "RESULT=FAIL\n";

    foreach ($failures as $failure) {
        echo "FAIL={$failure}\n";
    }

    exit(1);
}

echo "RESULT=PASS\n";
echo "MIGRATION_ADDITIVE=PASS\n";
echo "HISTORICAL_BACKFILL_FORBIDDEN=PASS\n";
echo "LEGACY_VALUE_FINGERPRINT_PRESERVED=PASS\n";
echo "CANONICAL_MANIFEST_JSON=PASS\n";
echo "MANIFEST_HASH_REPRODUCIBLE=PASS\n";
echo "LEGACY_COMPARISON_FALLBACK=PASS\n";
echo "TRUE_PROVENANCE_COMPARISON=PASS\n";
echo "COMPOSITION_ONLY_CHANGE_DETECTED=PASS\n";
echo "SNAPSHOT_APPEND_ONLY=PASS\n";
echo "MANAGE_CYCLE_SHARED_POLICY=PASS\n";
echo "LEGACY_PROVENANCE_DISCLOSURE=PASS\n";
echo "DATABASE_CONNECTION_USED=NO\n";
echo "DATABASE_WRITE_PERFORMED=NO\n";
