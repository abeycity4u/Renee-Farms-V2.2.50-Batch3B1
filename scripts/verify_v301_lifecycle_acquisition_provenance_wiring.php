<?php

$root = dirname(__DIR__);

$_SERVER['DOCUMENT_ROOT'] =
    getenv('HOME') . '/public_html';

ob_start();
require getenv('HOME') . '/public_html/config.php';
ob_end_clean();

require_once
    $root . '/lib/poultry_rearing_economics.php';

$failures = [];

$assert = static function (
    bool $condition,
    string $message
) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

try {
    /*
     * Pure mapping contract:
     * presentation/audit fields must not alter economic provenance.
     */
    $phaseA = [
        'id' => 10,
        'phase' => 'rearing',
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-14',
        'notes' => 'Original note',
        'created_by' => 1,
        'created_at' => '2026-09-10 08:00:00',
    ];

    $phaseB = $phaseA;
    $phaseB['notes'] = 'Presentation-only note changed';
    $phaseB['created_by'] = 99;
    $phaseB['created_at'] = '2026-09-10 09:00:00';

    $phaseEconomicChange = $phaseA;
    $phaseEconomicChange['end_date'] = '2026-09-13';

    $phaseSourceA =
        poultry_production_entry_lifecycle_provenance_source(
            $phaseA
        );

    $phaseSourceB =
        poultry_production_entry_lifecycle_provenance_source(
            $phaseB
        );

    $phaseChanged =
        poultry_production_entry_lifecycle_provenance_source(
            $phaseEconomicChange
        );

    $assert(
        $phaseSourceA['source_revision']
            === $phaseSourceB['source_revision'],
        'Lifecycle presentation-only fields changed provenance.'
    );

    $assert(
        $phaseSourceA['source_revision']
            !== $phaseChanged['source_revision'],
        'Lifecycle economic boundary change did not change provenance.'
    );

    $acquisitionA = [
        'id' => 10,
        'acquisition_type' => 'purchased',
        'acquisition_date' => '2026-09-10',
        'quantity' => 500,
        'age_days' => 70,
        'total_cost' => '750000.00',
        'source_name' => 'Supplier A',
        'reference_no' => 'INV-1',
        'notes' => 'Original note',
        'created_by' => 1,
        'created_at' => '2026-09-10 08:00:00',
    ];

    $acquisitionB = $acquisitionA;
    $acquisitionB['source_name'] = 'Display name changed';
    $acquisitionB['reference_no'] = 'DISPLAY-ONLY';
    $acquisitionB['notes'] = 'Presentation-only note';
    $acquisitionB['created_by'] = 99;

    $acquisitionEconomicChange = $acquisitionA;
    $acquisitionEconomicChange['total_cost'] =
        '751000.00';

    $acquisitionSourceA =
        poultry_production_entry_acquisition_provenance_source(
            $acquisitionA
        );

    $acquisitionSourceB =
        poultry_production_entry_acquisition_provenance_source(
            $acquisitionB
        );

    $acquisitionChanged =
        poultry_production_entry_acquisition_provenance_source(
            $acquisitionEconomicChange
        );

    $assert(
        $acquisitionSourceA['source_revision']
            === $acquisitionSourceB['source_revision'],
        'Acquisition presentation-only fields changed provenance.'
    );

    $assert(
        $acquisitionSourceA['source_revision']
            !== $acquisitionChanged['source_revision'],
        'Acquisition economic fact change did not change provenance.'
    );

    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();

    $farmId = 4;
    $cycleId = 55;

    $economics =
        poultry_rearing_economics(
            $pdo,
            $farmId,
            $cycleId
        );

    $assert(
        !empty($economics['available']),
        'Layer economics are unavailable.'
    );

    $assert(
        (string)($economics['mode'] ?? '')
            === 'reared',
        'Expected farm-reared economics mode.'
    );

    $assert(
        abs(
            (float)($economics['rearing_investment'] ?? 0)
            - 927833.36
        ) < 0.005,
        'Attributed investment changed.'
    );

    $assert(
        (int)($economics['production_entry_headcount'] ?? 0)
            === 496,
        'Production-Entry headcount changed.'
    );

    $populationSources =
        isset(
            $economics['population_provenance_sources']
        )
        && is_array(
            $economics['population_provenance_sources']
        )
            ? $economics[
                'population_provenance_sources'
            ]
            : [];

    $sources =
        isset($economics['provenance_sources'])
        && is_array($economics['provenance_sources'])
            ? $economics['provenance_sources']
            : [];

    $assert(
        count($populationSources) === 8,
        'Existing population provenance source count changed.'
    );

    $assert(
        count($sources) === 11,
        'Expected 2 lifecycle + 1 acquisition + 8 population sources.'
    );

    $roleCounts = [];
    $lifecycleIds = [];
    $acquisitionIds = [];
    $populationIds = [];

    foreach ($sources as $source) {
        $normalized =
            poultry_production_entry_provenance_source(
                $source
            );

        $assert(
            preg_match(
                '/^[a-f0-9]{64}$/',
                (string)(
                    $normalized['source_revision']
                    ?? ''
                )
            ) === 1,
            'A wired source lacks a valid revision digest.'
        );

        $role =
            (string)$normalized['role'];

        $roleCounts[$role] =
            ($roleCounts[$role] ?? 0) + 1;

        if ($role === 'lifecycle_phase') {
            $lifecycleIds[] =
                (int)$normalized['source_id'];
        }

        if ($role === 'acquisition') {
            $acquisitionIds[] =
                (int)$normalized['source_id'];
        }

        if ($role === 'population_movement') {
            $populationIds[] =
                (int)$normalized['source_id'];
        }
    }

    sort($lifecycleIds, SORT_NUMERIC);
    sort($acquisitionIds, SORT_NUMERIC);
    sort($populationIds, SORT_NUMERIC);
    ksort($roleCounts, SORT_STRING);

    $assert(
        $lifecycleIds === [10, 11],
        'Unexpected lifecycle provenance identities.'
    );

    $assert(
        $acquisitionIds === [10],
        'Unexpected acquisition provenance identity.'
    );

    $assert(
        $populationIds === [
            1,
            2,
            3,
            15,
            16,
            20,
            21,
        ],
        'Existing population movement provenance changed.'
    );

    $manifest =
        poultry_production_entry_provenance_build(
            [
                'farm_id' => $farmId,
                'cycle_id' => $cycleId,
                'mode' => 'reared',
                'production_entry_date' =>
                    '2026-09-15',
                'rearing_start_date' =>
                    '2026-09-10',
                'rearing_end_date' =>
                    '2026-09-14',
            ],
            $sources
        );

    $assert(
        preg_match(
            '/^[a-f0-9]{64}$/',
            (string)$manifest['fingerprint']
        ) === 1,
        'Combined wired sources do not build a valid manifest.'
    );

    if ($failures) {
        echo "RESULT=FAIL\n";

        foreach ($failures as $failure) {
            echo "FAIL={$failure}\n";
        }

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        exit(1);
    }

    $roleParts = [];

    foreach ($roleCounts as $role => $count) {
        $roleParts[] =
            $role . ':' . $count;
    }

    echo "RESULT=PASS\n";
    echo "READ_ONLY=PASS\n";
    echo "ECONOMICS_UNCHANGED=PASS\n";
    echo "ATTRIBUTED_INVESTMENT="
        . (string)$economics['rearing_investment']
        . "\n";
    echo "PRODUCTION_ENTRY_HEADCOUNT="
        . (string)$economics[
            'production_entry_headcount'
        ]
        . "\n";
    echo "PROVENANCE_SOURCE_COUNT="
        . count($sources)
        . "\n";
    echo "POPULATION_SOURCE_COUNT="
        . count($populationSources)
        . "\n";
    echo "PROVENANCE_ROLES="
        . implode(',', $roleParts)
        . "\n";
    echo "LIFECYCLE_IDS="
        . implode(',', $lifecycleIds)
        . "\n";
    echo "ACQUISITION_IDS="
        . implode(',', $acquisitionIds)
        . "\n";
    echo "PRESENTATION_ONLY_INVARIANCE=PASS\n";
    echo "ECONOMIC_FACT_SENSITIVITY=PASS\n";
    echo "COMBINED_MANIFEST_COMPATIBILITY=PASS\n";

    $pdo->rollBack();

} catch (Throwable $e) {
    if (
        isset($pdo)
        && $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    echo "RESULT=FAIL\n";
    echo "FAIL="
        . str_replace(
            ["\r", "\n"],
            ' ',
            $e->getMessage()
        )
        . "\n";

    exit(1);
}
