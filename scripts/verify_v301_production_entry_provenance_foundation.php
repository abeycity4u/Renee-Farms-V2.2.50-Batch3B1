<?php

$root = dirname(__DIR__);

require_once
    $root . '/lib/poultry_production_entry_provenance.php';

$failures = [];

$assert = static function (
    bool $condition,
    string $message
) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$contextA = [
    'farm_id' => 4,
    'cycle_id' => 55,
    'mode' => 'reared',
    'production_entry_date' => '2026-09-15',
    'rearing_start_date' => '2026-09-10',
    'rearing_end_date' => '2026-09-14',
];

$contextB = [
    'rearing_end_date' => '2026-09-14',
    'cycle_id' => 55,
    'mode' => 'REARED',
    'farm_id' => 4,
    'production_entry_date' => '2026-09-15',
    'rearing_start_date' => '2026-09-10',
];

$revisionA =
    poultry_production_entry_provenance_revision([
        'amount' => '2000.00',
        'unit' => '1.00',
        'category' => 'fuel',
    ]);

$revisionB =
    poultry_production_entry_provenance_revision([
        'category' => 'fuel',
        'unit' => '1.00',
        'amount' => '2000.00',
    ]);

$revisionChanged =
    poultry_production_entry_provenance_revision([
        'category' => 'fuel',
        'unit' => '1.00',
        'amount' => '2500.00',
    ]);

$assert(
    $revisionA === $revisionB,
    'Row revision digest depends on associative key order.'
);

$assert(
    $revisionA !== $revisionChanged,
    'Economically relevant row change did not change revision digest.'
);

$sourcesA = [
    [
        'role' => 'population_movement',
        'source_type' =>
            'production_population_movement',
        'source_id' => 21,
        'source_version' => 3,
        'source_revision' =>
            poultry_production_entry_provenance_revision([
                'movement_type' => 'mortality',
                'quantity_delta' => -2,
            ]),
        'effective_date' => '2026-09-14',
    ],
    [
        'role' => 'acquisition',
        'source_type' =>
            'poultry_cycle_acquisition',
        'source_id' => 10,
        'source_revision' =>
            poultry_production_entry_provenance_revision([
                'quantity' => 500,
                'total_cost' => '750000.00',
            ]),
        'effective_date' => '2026-09-10',
    ],
    [
        'role' => 'feed_consumption',
        'source_type' => 'stock_transaction',
        'source_id' => 392,
        'source_revision' =>
            poultry_production_entry_provenance_revision([
                'total_cost' => '20000.00',
            ]),
        'effective_date' => '2026-09-12',
    ],
];

$builtA =
    poultry_production_entry_provenance_build(
        $contextA,
        $sourcesA
    );

$builtB =
    poultry_production_entry_provenance_build(
        $contextB,
        array_reverse($sourcesA)
    );

$assert(
    $builtA['manifest'] === $builtB['manifest'],
    'Manifest is not deterministic.'
);

$assert(
    $builtA['fingerprint'] === $builtB['fingerprint'],
    'Fingerprint is not deterministic.'
);

$assert(
    preg_match(
        '/^[a-f0-9]{64}$/',
        $builtA['fingerprint']
    ) === 1,
    'Fingerprint is not a SHA-256 digest.'
);

$identityChanged = $sourcesA;
$identityChanged[0]['source_id'] = 22;

$assert(
    poultry_production_entry_provenance_build(
        $contextA,
        $identityChanged
    )['fingerprint'] !== $builtA['fingerprint'],
    'Source identity change was not detected.'
);

$versionChanged = $sourcesA;
$versionChanged[0]['source_version'] = 4;

$assert(
    poultry_production_entry_provenance_build(
        $contextA,
        $versionChanged
    )['fingerprint'] !== $builtA['fingerprint'],
    'Source version change was not detected.'
);

$rowChanged = $sourcesA;

$rowChanged[1]['source_revision'] =
    poultry_production_entry_provenance_revision([
        'quantity' => 500,
        'total_cost' => '751000.00',
    ]);

$assert(
    poultry_production_entry_provenance_build(
        $contextA,
        $rowChanged
    )['fingerprint'] !== $builtA['fingerprint'],
    'Source row revision change was not detected.'
);

$dateChanged = $sourcesA;
$dateChanged[0]['effective_date'] = '2026-09-13';

$assert(
    poultry_production_entry_provenance_build(
        $contextA,
        $dateChanged
    )['fingerprint'] !== $builtA['fingerprint'],
    'Effective-date change was not detected.'
);

$duplicates = $sourcesA;
$duplicates[] = $sourcesA[0];

$assert(
    poultry_production_entry_provenance_build(
        $contextA,
        $duplicates
    )['manifest'] === $builtA['manifest'],
    'Duplicate source changed the normalized manifest.'
);

$invalidRevisionRejected = false;

try {
    poultry_production_entry_provenance_manifest(
        $contextA,
        [[
            'role' => 'direct_expense',
            'source_type' => 'farm_expense',
            'source_id' => 59,
            'source_revision' => 'invalid',
            'effective_date' => '2026-09-12',
        ]]
    );
} catch (InvalidArgumentException $e) {
    $invalidRevisionRejected = true;
}

$assert(
    $invalidRevisionRejected,
    'Invalid source revision was not rejected.'
);

$helperSource =
    file_get_contents(
        $root
        . '/lib/poultry_production_entry_provenance.php'
    );

$assert(
    strpos($helperSource, 'PDO ') === false
        && strpos($helperSource, '->prepare(') === false
        && stripos($helperSource, 'INSERT INTO') === false
        && stripos($helperSource, 'DELETE FROM') === false,
    'Foundation helper performs database work.'
);

$snapshotSource =
    file_get_contents(
        $root
        . '/lib/poultry_production_entry_snapshots.php'
    );

$assert(
    strpos(
        $snapshotSource,
        'Fingerprint the source-derived economic facts, not presentation text.'
    ) !== false,
    'Existing value-fingerprint contract is missing.'
);

$assert(
    strpos(
        $snapshotSource,
        "\$candidate['source_fingerprint']=hash('sha256', json_encode(\$fingerprintFacts, JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));"
    ) !== false,
    'Existing source_fingerprint calculation changed.'
);

if ($failures) {
    echo "RESULT=FAIL\n";

    foreach ($failures as $failure) {
        echo "FAIL={$failure}\n";
    }

    exit(1);
}

echo "RESULT=PASS\n";
echo "DETERMINISTIC_MANIFEST=PASS\n";
echo "DETERMINISTIC_FINGERPRINT=PASS\n";
echo "ROW_REVISION_DETERMINISM=PASS\n";
echo "SOURCE_IDENTITY_SENSITIVITY=PASS\n";
echo "SOURCE_VERSION_SENSITIVITY=PASS\n";
echo "SOURCE_REVISION_SENSITIVITY=PASS\n";
echo "SOURCE_DATE_SENSITIVITY=PASS\n";
echo "DUPLICATE_NORMALIZATION=PASS\n";
echo "INVALID_REVISION_REJECTION=PASS\n";
echo "READ_ONLY_FOUNDATION=PASS\n";
echo "LEGACY_SOURCE_FINGERPRINT_PRESERVED=PASS\n";
