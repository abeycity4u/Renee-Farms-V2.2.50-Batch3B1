<?php

$root = dirname(__DIR__);

try {
    $pdo = new PDO(
        'mysql:host='
        . getenv('DB_HOST')
        . ';dbname='
        . getenv('DB_NAME')
        . ';charset=utf8mb4',
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );

    $timezone =
        new DateTimeZone(
            getenv('APP_TIMEZONE')
                ?: 'Africa/Lagos'
        );

    $offset =
        (
            new DateTimeImmutable(
                'now',
                $timezone
            )
        )->format('P');

    $pdo->exec(
        'SET time_zone = '
        . $pdo->quote($offset)
    );

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

    /*
     * Pure provenance contract:
     * Reared boundary depends on opening + mortality.
     */
    $rearing = [
        'id' => 95,
        'record_date' =>
            '2026-09-14',
        'opening_stock' => 498,
        'mortality' => 2,
        'created_at' =>
            '2026-09-14 08:00:00',
        'remarks' => 'Original',
    ];

    $rearingPresentation =
        $rearing;

    $rearingPresentation[
        'created_at'
    ] = '2026-09-14 09:00:00';

    $rearingPresentation[
        'remarks'
    ] = 'Changed';

    $rearingMortality =
        $rearing;

    $rearingMortality[
        'mortality'
    ] = 3;

    $rearingOpening =
        $rearing;

    $rearingOpening[
        'opening_stock'
    ] = 499;

    $rearingSource =
        poultry_production_entry_daily_reconciliation_provenance_source(
            $rearing,
            'rearing_end_daily_reconciliation'
        );

    $rearingPresentationSource =
        poultry_production_entry_daily_reconciliation_provenance_source(
            $rearingPresentation,
            'rearing_end_daily_reconciliation'
        );

    $rearingMortalitySource =
        poultry_production_entry_daily_reconciliation_provenance_source(
            $rearingMortality,
            'rearing_end_daily_reconciliation'
        );

    $rearingOpeningSource =
        poultry_production_entry_daily_reconciliation_provenance_source(
            $rearingOpening,
            'rearing_end_daily_reconciliation'
        );

    $assert(
        $rearingSource[
            'source_revision'
        ] ===
        $rearingPresentationSource[
            'source_revision'
        ],
        'Rearing Daily presentation fields changed provenance.'
    );

    $assert(
        $rearingSource[
            'source_revision'
        ] !==
        $rearingMortalitySource[
            'source_revision'
        ],
        'Rearing Daily mortality did not change provenance.'
    );

    $assert(
        $rearingSource[
            'source_revision'
        ] !==
        $rearingOpeningSource[
            'source_revision'
        ],
        'Rearing Daily opening stock did not change provenance.'
    );

    /*
     * Production-start reconciliation depends only on
     * date + opening stock. Mortality after that opening
     * boundary is deliberately not a PE boundary fact.
     */
    $production = [
        'id' => 100,
        'record_date' =>
            '2026-09-15',
        'opening_stock' => 496,
        'mortality' => 2,
        'created_at' =>
            '2026-09-15 08:00:00',
    ];

    $productionUnrelated =
        $production;

    $productionUnrelated[
        'mortality'
    ] = 99;

    $productionUnrelated[
        'created_at'
    ] = '2026-09-15 09:00:00';

    $productionOpening =
        $production;

    $productionOpening[
        'opening_stock'
    ] = 495;

    $productionSource =
        poultry_production_entry_daily_reconciliation_provenance_source(
            $production,
            'production_start_daily_reconciliation'
        );

    $productionUnrelatedSource =
        poultry_production_entry_daily_reconciliation_provenance_source(
            $productionUnrelated,
            'production_start_daily_reconciliation'
        );

    $productionOpeningSource =
        poultry_production_entry_daily_reconciliation_provenance_source(
            $productionOpening,
            'production_start_daily_reconciliation'
        );

    $assert(
        $productionSource[
            'source_revision'
        ] ===
        $productionUnrelatedSource[
            'source_revision'
        ],
        'Production-start unrelated fields changed provenance.'
    );

    $assert(
        $productionSource[
            'source_revision'
        ] !==
        $productionOpeningSource[
            'source_revision'
        ],
        'Production-start opening stock did not change provenance.'
    );

    /*
     * Different boundary roles for the same source facts must
     * remain independently represented in a manifest.
     */
    $roleManifest =
        poultry_production_entry_provenance_build(
            [
                'farm_id' => 4,
                'cycle_id' => 55,
                'mode' => 'reared',
                'production_entry_date' =>
                    '2026-09-15',
                'rearing_start_date' =>
                    '2026-09-10',
                'rearing_end_date' =>
                    '2026-09-14',
            ],
            [
                $rearingSource,
                poultry_production_entry_daily_reconciliation_provenance_source(
                    $rearing,
                    'production_start_daily_reconciliation'
                ),
            ]
        );

    $assert(
        count(
            $roleManifest[
                'manifest'
            ]['sources'] ?? []
        ) === 2,
        'Daily reconciliation roles collapsed in the manifest.'
    );

    $pdo->exec(
        'SET TRANSACTION READ ONLY'
    );

    $pdo->beginTransaction();

    $economics =
        poultry_rearing_economics(
            $pdo,
            4,
            55
        );

    $assert(
        !empty(
            $economics['available']
        ),
        'Economics unavailable.'
    );

    $assert(
        (string)(
            $economics['mode']
            ?? ''
        ) === 'reared',
        'Expected farm-reared mode.'
    );

    $assert(
        abs(
            (float)(
                $economics[
                    'rearing_investment'
                ] ?? 0
            )
            - 927833.36
        ) < 0.005,
        'Attributed investment changed.'
    );

    $assert(
        (int)(
            $economics[
                'production_entry_headcount'
            ] ?? 0
        ) === 496,
        'Canonical Production-Entry headcount changed.'
    );

    $assert(
        (string)(
            $economics[
                'production_entry_headcount_source'
            ] ?? ''
        )
        ===
        'Canonical population ledger at Rearing close, reconciled to exact Daily Record boundary',
        'Canonical headcount authority/source text changed.'
    );

    $sources =
        is_array(
            $economics[
                'provenance_sources'
            ] ?? null
        )
            ? $economics[
                'provenance_sources'
            ]
            : [];

    $populationSources =
        is_array(
            $economics[
                'population_provenance_sources'
            ] ?? null
        )
            ? $economics[
                'population_provenance_sources'
            ]
            : [];

    $roles = [];
    $rearingIds = [];
    $productionIds = [];
    $dailyInPopulation = [];

    foreach (
        $sources
        as $source
    ) {
        $normalized =
            poultry_production_entry_provenance_source(
                $source
            );

        $role =
            (string)$normalized[
                'role'
            ];

        $roles[$role] =
            ($roles[$role] ?? 0)
            + 1;

        if (
            $role
            ===
            'rearing_end_daily_reconciliation'
        ) {
            $rearingIds[] =
                (int)$normalized[
                    'source_id'
                ];
        }

        if (
            $role
            ===
            'production_start_daily_reconciliation'
        ) {
            $productionIds[] =
                (int)$normalized[
                    'source_id'
                ];
        }
    }

    foreach (
        $populationSources
        as $source
    ) {
        $normalized =
            poultry_production_entry_provenance_source(
                $source
            );

        if (
            (string)$normalized[
                'source_type'
            ] === 'layer_daily_record'
        ) {
            $dailyInPopulation[] =
                (int)$normalized[
                    'source_id'
                ];
        }
    }

    sort(
        $rearingIds,
        SORT_NUMERIC
    );

    sort(
        $productionIds,
        SORT_NUMERIC
    );

    ksort(
        $roles,
        SORT_STRING
    );

    $assert(
        count($sources) === 19,
        'Expected 19 generic provenance sources.'
    );

    $assert(
        count($populationSources)
        === 8,
        'Canonical population provenance count changed.'
    );

    $assert(
        $rearingIds === [95],
        'Rearing-end Daily Record provenance identity changed.'
    );

    $assert(
        $productionIds === [100],
        'Production-start Daily Record provenance identity changed.'
    );

    $assert(
        $dailyInPopulation === [],
        'Daily reconciliation evidence leaked into canonical population provenance.'
    );

    $assert(
        ($roles[
            'rearing_end_daily_reconciliation'
        ] ?? 0) === 1,
        'Expected one rearing-end reconciliation source.'
    );

    $assert(
        ($roles[
            'production_start_daily_reconciliation'
        ] ?? 0) === 1,
        'Expected one production-start reconciliation source.'
    );

    /*
     * Boundary helper itself must expose the two streams separately.
     */
    $boundary =
        poultry_production_entry_population_boundary(
            $pdo,
            4,
            55,
            '2026-09-14',
            '2026-09-15'
        );

    $assert(
        (int)(
            $boundary['headcount']
            ?? 0
        ) === 496,
        'Boundary canonical headcount changed.'
    );

    $assert(
        count(
            $boundary[
                'provenance_sources'
            ] ?? []
        ) === 8,
        'Boundary canonical population source count changed.'
    );

    $assert(
        count(
            $boundary[
                'reconciliation_provenance_sources'
            ] ?? []
        ) === 2,
        'Boundary Daily reconciliation source count is not two.'
    );

    $assert(
        (int)(
            $boundary[
                'rearing_closing'
            ] ?? 0
        ) === 496,
        'Rearing reconciliation value changed.'
    );

    $assert(
        (int)(
            $boundary[
                'production_opening'
            ] ?? 0
        ) === 496,
        'Production reconciliation value changed.'
    );

    $manifest =
        poultry_production_entry_provenance_build(
            [
                'farm_id' => 4,
                'cycle_id' => 55,
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
            (string)(
                $manifest[
                    'fingerprint'
                ] ?? ''
            )
        ) === 1,
        'Combined provenance manifest fingerprint is invalid.'
    );

    if ($failures) {
        echo "RESULT=FAIL\n";

        foreach (
            $failures
            as $failure
        ) {
            echo 'FAIL='
                . $failure
                . "\n";
        }

        if (
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        exit(1);
    }

    $roleParts = [];

    foreach (
        $roles
        as $role => $count
    ) {
        $roleParts[] =
            $role
            . ':'
            . $count;
    }

    echo "RESULT=PASS\n";
    echo "READ_ONLY=PASS\n";
    echo "ECONOMICS_UNCHANGED=PASS\n";
    echo "CANONICAL_AUTHORITY_UNCHANGED=PASS\n";
    echo "DAILY_GENERIC_ONLY=PASS\n";

    echo "ATTRIBUTED_INVESTMENT="
        . (string)$economics[
            'rearing_investment'
        ]
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
        . count(
            $populationSources
        )
        . "\n";

    echo "REARING_DAILY_IDS="
        . implode(
            ',',
            $rearingIds
        )
        . "\n";

    echo "PRODUCTION_DAILY_IDS="
        . implode(
            ',',
            $productionIds
        )
        . "\n";

    echo "REARING_CLOSING="
        . (string)$boundary[
            'rearing_closing'
        ]
        . "\n";

    echo "PRODUCTION_OPENING="
        . (string)$boundary[
            'production_opening'
        ]
        . "\n";

    echo "PROVENANCE_ROLES="
        . implode(
            ',',
            $roleParts
        )
        . "\n";

    echo "DAILY_PRESENTATION_INVARIANCE=PASS\n";
    echo "DAILY_MATERIAL_FACT_SENSITIVITY=PASS\n";
    echo "DAILY_ROLE_SEPARATION=PASS\n";
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

    echo 'FAIL='
        . str_replace(
            ["\r", "\n"],
            ' ',
            $e->getMessage()
        )
        . "\n";

    exit(1);
}
