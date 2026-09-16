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
            $failures[] = $message;
        }
    };

    /*
     * Direct expense provenance:
     * description/audit metadata are presentation-only.
     */
    $directA = [
        'id' => 59,
        'expense_date' => '2026-09-14',
        'cycle_id' => 55,
        'category' => 'misc',
        'amount' => '2000.00',
        'unit' => '1.00',
        'description' => 'Original note',
        'created_at' =>
            '2026-09-14 08:00:00',
    ];

    $directPresentation =
        $directA;

    $directPresentation['description'] =
        'Changed display note';

    $directPresentation['created_at'] =
        '2026-09-14 09:00:00';

    $directEconomic =
        $directA;

    $directEconomic['amount'] =
        '2100.00';

    $directSourceA =
        poultry_production_entry_direct_expense_provenance_source(
            $directA
        );

    $directPresentationSource =
        poultry_production_entry_direct_expense_provenance_source(
            $directPresentation
        );

    $directEconomicSource =
        poultry_production_entry_direct_expense_provenance_source(
            $directEconomic
        );

    $assert(
        $directSourceA['source_revision']
            ===
        $directPresentationSource[
            'source_revision'
        ],
        'Direct-expense presentation fields changed provenance.'
    );

    $assert(
        $directSourceA['source_revision']
            !==
        $directEconomicSource[
            'source_revision'
        ],
        'Direct-expense economic fact change did not change provenance.'
    );

    /*
     * Explicit allocation provenance includes joined expense
     * boundary/category facts because those determine eligibility.
     */
    $allocationA = [
        'id' => 9001,
        'expense_id' => 7001,
        'cycle_id' => 55,
        'allocation_percent' => '40.00',
        'allocated_amount' => '4000.00',
        'expense_date' => '2026-09-13',
        'category' => 'fuel',
        'notes' => 'Display note',
        'created_by' => 1,
        'created_at' =>
            '2026-09-13 08:00:00',
    ];

    $allocationPresentation =
        $allocationA;

    $allocationPresentation['notes'] =
        'Changed display note';

    $allocationPresentation['created_by'] =
        99;

    $allocationPercentOnly =
        $allocationA;

    $allocationPercentOnly[
        'allocation_percent'
    ] = '41.00';

    $allocationEconomic =
        $allocationA;

    $allocationEconomic[
        'allocated_amount'
    ] = '4500.00';

    $allocationBoundary =
        $allocationA;

    $allocationBoundary[
        'expense_date'
    ] = '2026-09-15';

    $allocationSourceA =
        poultry_production_entry_explicit_allocation_provenance_source(
            $allocationA
        );

    $allocationPresentationSource =
        poultry_production_entry_explicit_allocation_provenance_source(
            $allocationPresentation
        );

    $allocationPercentOnlySource =
        poultry_production_entry_explicit_allocation_provenance_source(
            $allocationPercentOnly
        );

    $allocationEconomicSource =
        poultry_production_entry_explicit_allocation_provenance_source(
            $allocationEconomic
        );

    $allocationBoundarySource =
        poultry_production_entry_explicit_allocation_provenance_source(
            $allocationBoundary
        );

    $assert(
        $allocationSourceA[
            'source_revision'
        ]
            ===
        $allocationPresentationSource[
            'source_revision'
        ],
        'Allocation presentation fields changed provenance.'
    );

    $assert(
        $allocationSourceA[
            'source_revision'
        ]
            ===
        $allocationPercentOnlySource[
            'source_revision'
        ],
        'Allocation percent-only metadata changed causal provenance.'
    );

    $assert(
        $allocationSourceA[
            'source_revision'
        ]
            !==
        $allocationEconomicSource[
            'source_revision'
        ],
        'Allocation amount change did not change provenance.'
    );

    $assert(
        $allocationSourceA[
            'source_revision'
        ]
            !==
        $allocationBoundarySource[
            'source_revision'
        ],
        'Allocation expense-boundary change did not change provenance.'
    );

    /*
     * Shared-pool expense provenance captures all fields used by
     * pool eligibility and gross-value calculation.
     */
    $sharedExpenseA = [
        'id' => 7001,
        'expense_date' => '2026-09-12',
        'farm_type' => 'poultry',
        'production_type' => 'layer',
        'cycle_id' => null,
        'category' => 'fuel',
        'amount' => '10000.00',
        'unit' => '1.00',
        'description' => 'Display note',
        'created_at' =>
            '2026-09-12 08:00:00',
    ];

    $sharedExpensePresentation =
        $sharedExpenseA;

    $sharedExpensePresentation[
        'description'
    ] = 'Changed display note';

    $sharedExpenseCaseOnly =
        $sharedExpenseA;

    $sharedExpenseCaseOnly[
        'production_type'
    ] = 'LAYER';

    $sharedExpenseEconomic =
        $sharedExpenseA;

    $sharedExpenseEconomic['amount'] =
        '11000.00';

    $sharedExpenseEligibility =
        $sharedExpenseA;

    $sharedExpenseEligibility[
        'production_type'
    ] = 'broiler';

    $sharedExpenseSourceA =
        poultry_production_entry_shared_pool_expense_provenance_source(
            $sharedExpenseA
        );

    $sharedExpensePresentationSource =
        poultry_production_entry_shared_pool_expense_provenance_source(
            $sharedExpensePresentation
        );

    $sharedExpenseCaseOnlySource =
        poultry_production_entry_shared_pool_expense_provenance_source(
            $sharedExpenseCaseOnly
        );

    $sharedExpenseEconomicSource =
        poultry_production_entry_shared_pool_expense_provenance_source(
            $sharedExpenseEconomic
        );

    $sharedExpenseEligibilitySource =
        poultry_production_entry_shared_pool_expense_provenance_source(
            $sharedExpenseEligibility
        );

    $assert(
        $sharedExpenseSourceA[
            'source_revision'
        ]
            ===
        $sharedExpensePresentationSource[
            'source_revision'
        ],
        'Shared-pool expense presentation fields changed provenance.'
    );

    $assert(
        $sharedExpenseSourceA[
            'source_revision'
        ]
            ===
        $sharedExpenseCaseOnlySource[
            'source_revision'
        ],
        'Shared-pool production-type case-only change altered provenance.'
    );

    $assert(
        $sharedExpenseSourceA[
            'source_revision'
        ]
            !==
        $sharedExpenseEconomicSource[
            'source_revision'
        ],
        'Shared-pool expense value change did not change provenance.'
    );

    $assert(
        $sharedExpenseSourceA[
            'source_revision'
        ]
            !==
        $sharedExpenseEligibilitySource[
            'source_revision'
        ],
        'Shared-pool eligibility change did not change provenance.'
    );

    /*
     * Every allocation against a shared-pool expense matters,
     * including one assigned to another cycle.
     */
    $sharedAllocationA = [
        'id' => 8001,
        'expense_id' => 7001,
        'cycle_id' => 99,
        'allocation_percent' => '25.00',
        'allocated_amount' => '2500.00',
        'notes' => 'Display note',
        'created_by' => 1,
    ];

    $sharedAllocationPresentation =
        $sharedAllocationA;

    $sharedAllocationPresentation['notes'] =
        'Changed note';

    $sharedAllocationPercentOnly =
        $sharedAllocationA;

    $sharedAllocationPercentOnly[
        'allocation_percent'
    ] = '26.00';

    $sharedAllocationEconomic =
        $sharedAllocationA;

    $sharedAllocationEconomic[
        'allocated_amount'
    ] = '3000.00';

    $sharedAllocationSourceA =
        poultry_production_entry_shared_pool_allocation_provenance_source(
            $sharedAllocationA,
            '2026-09-12'
        );

    $sharedAllocationPresentationSource =
        poultry_production_entry_shared_pool_allocation_provenance_source(
            $sharedAllocationPresentation,
            '2026-09-12'
        );

    $sharedAllocationPercentOnlySource =
        poultry_production_entry_shared_pool_allocation_provenance_source(
            $sharedAllocationPercentOnly,
            '2026-09-12'
        );

    $sharedAllocationEconomicSource =
        poultry_production_entry_shared_pool_allocation_provenance_source(
            $sharedAllocationEconomic,
            '2026-09-12'
        );

    $assert(
        $sharedAllocationSourceA[
            'source_revision'
        ]
            ===
        $sharedAllocationPresentationSource[
            'source_revision'
        ],
        'Shared-pool allocation presentation fields changed provenance.'
    );

    $assert(
        $sharedAllocationSourceA[
            'source_revision'
        ]
            ===
        $sharedAllocationPercentOnlySource[
            'source_revision'
        ],
        'Shared allocation percent-only metadata changed causal provenance.'
    );

    $assert(
        $sharedAllocationSourceA[
            'source_revision'
        ]
            !==
        $sharedAllocationEconomicSource[
            'source_revision'
        ],
        'Shared-pool allocation amount change did not change provenance.'
    );

    /*
     * Synthetic dependency manifest:
     * one pool expense + allocations to two different cycles must
     * retain all three dependencies.
     */
    $otherCycleAllocation =
        $sharedAllocationA;

    $otherCycleAllocation['id'] = 8002;
    $otherCycleAllocation['cycle_id'] = 100;
    $otherCycleAllocation[
        'allocated_amount'
    ] = '1000.00';

    $syntheticSharedSources = [
        $sharedExpenseSourceA,
        $sharedAllocationSourceA,
        poultry_production_entry_shared_pool_allocation_provenance_source(
            $otherCycleAllocation,
            '2026-09-12'
        ),
    ];

    $syntheticManifest =
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
            $syntheticSharedSources
        );

    $assert(
        count(
            $syntheticManifest['manifest']['sources']
            ?? []
        ) === 3,
        'Shared-pool dependency manifest lost an allocation dependency.'
    );

    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();

    $economics =
        poultry_rearing_economics(
            $pdo,
            4,
            55
        );

    $assert(
        !empty($economics['available']),
        'Layer economics are unavailable.'
    );

    $assert(
        (string)($economics['mode'] ?? '')
            === 'reared',
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
        'Production-Entry headcount changed.'
    );

    $assert(
        abs(
            (float)(
                $economics[
                    'direct_expenses'
                ] ?? 0
            )
            - 2000.00
        ) < 0.005,
        'Direct expenses changed.'
    );

    $assert(
        abs(
            (float)(
                $economics[
                    'allocated_shared_expenses'
                ] ?? 0
            )
        ) < 0.005,
        'Allocated shared expenses changed.'
    );

    $assert(
        abs(
            (float)(
                $economics[
                    'unallocated_shared_expense_pool'
                ] ?? 0
            )
        ) < 0.005,
        'Unallocated shared pool changed.'
    );

    $sources =
        isset($economics['provenance_sources'])
        && is_array(
            $economics['provenance_sources']
        )
            ? $economics['provenance_sources']
            : [];

    $populationSources =
        isset(
            $economics[
                'population_provenance_sources'
            ]
        )
        && is_array(
            $economics[
                'population_provenance_sources'
            ]
        )
            ? $economics[
                'population_provenance_sources'
            ]
            : [];

    $roleCounts = [];
    $directIds = [];
    $allocationIds = [];
    $sharedExpenseIds = [];
    $sharedAllocationIds = [];
    $feedIds = [];
    $operatingIds = [];

    foreach ($sources as $source) {
        $normalized =
            poultry_production_entry_provenance_source(
                $source
            );

        $assert(
            preg_match(
                '/^[a-f0-9]{64}$/',
                (string)(
                    $normalized[
                        'source_revision'
                    ] ?? ''
                )
            ) === 1,
            'A wired source lacks a valid revision digest.'
        );

        $role =
            (string)$normalized['role'];

        $roleCounts[$role] =
            ($roleCounts[$role] ?? 0) + 1;

        if ($role === 'direct_expense') {
            $directIds[] =
                (int)$normalized['source_id'];
        }

        if (
            $role
            === 'explicit_shared_allocation'
        ) {
            $allocationIds[] =
                (int)$normalized['source_id'];
        }

        if (
            $role
            === 'shared_pool_expense'
        ) {
            $sharedExpenseIds[] =
                (int)$normalized['source_id'];
        }

        if (
            $role
            === 'shared_pool_allocation'
        ) {
            $sharedAllocationIds[] =
                (int)$normalized['source_id'];
        }

        if ($role === 'feed_use') {
            $feedIds[] =
                (int)$normalized['source_id'];
        }

        if (
            $role
            === 'operating_inventory_use'
        ) {
            $operatingIds[] =
                (int)$normalized['source_id'];
        }
    }

    sort($directIds, SORT_NUMERIC);
    sort($allocationIds, SORT_NUMERIC);
    sort($sharedExpenseIds, SORT_NUMERIC);
    sort($sharedAllocationIds, SORT_NUMERIC);
    sort($feedIds, SORT_NUMERIC);
    sort($operatingIds, SORT_NUMERIC);
    ksort($roleCounts, SORT_STRING);

    $assert(
        count($sources) === 19,
        'Expected 19 live provenance sources.'
    );

    $assert(
        count($populationSources) === 8,
        'Population provenance changed.'
    );

    $assert(
        $directIds === [59],
        'Direct-expense provenance identity changed.'
    );

    $assert(
        $allocationIds === [],
        'Unexpected explicit allocation provenance exists.'
    );

    $assert(
        $sharedExpenseIds === [],
        'Unexpected shared-pool expense provenance exists.'
    );

    $assert(
        $sharedAllocationIds === [],
        'Unexpected shared-pool allocation provenance exists.'
    );

    $assert(
        $feedIds === [
            392,
            393,
            394,
            428,
        ],
        'Existing Feed provenance changed.'
    );

    $assert(
        $operatingIds === [418],
        'Existing operating inventory provenance changed.'
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
            (string)$manifest['fingerprint']
        ) === 1,
        'Financial-aware manifest is invalid.'
    );

    /*
     * Old aggregate financial queries must be gone.
     */
    $sourceText =
        file_get_contents(
            $root
            . '/lib/poultry_rearing_economics.php'
        );

    $assert(
        strpos(
            $sourceText,
            'SELECT category, COALESCE(SUM(amount*unit),0) total'
        ) === false,
        'Old direct-expense aggregate remains.'
    );

    $assert(
        strpos(
            $sourceText,
            'SELECT e.category, COALESCE(SUM(fa.allocated_amount),0) total'
        ) === false,
        'Old allocation aggregate remains.'
    );

    $assert(
        strpos(
            $sourceText,
            'SELECT COALESCE(SUM(GREATEST((e.amount*e.unit)-COALESCE(a.allocated,0),0)),0)'
        ) === false,
        'Old shared-pool aggregate remains.'
    );

    $assert(
        strpos(
            $sourceText,
            'allocation_percent'
        ) === false,
        'Production-Entry economics still treats allocation_percent as a causal input.'
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

    foreach (
        $roleCounts
        as $role => $count
    ) {
        $roleParts[] =
            $role . ':' . $count;
    }

    echo "RESULT=PASS\n";
    echo "READ_ONLY=PASS\n";
    echo "ECONOMICS_UNCHANGED=PASS\n";

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

    echo "DIRECT_EXPENSES="
        . (string)$economics[
            'direct_expenses'
        ]
        . "\n";

    echo "ALLOCATED_SHARED="
        . (string)$economics[
            'allocated_shared_expenses'
        ]
        . "\n";

    echo "UNALLOCATED_SHARED_POOL="
        . (string)$economics[
            'unallocated_shared_expense_pool'
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

    echo "DIRECT_IDS="
        . implode(',', $directIds)
        . "\n";

    echo "EXPLICIT_ALLOCATION_IDS="
        . (
            $allocationIds
                ? implode(',', $allocationIds)
                : 'NONE'
        )
        . "\n";

    echo "SHARED_POOL_EXPENSE_IDS="
        . (
            $sharedExpenseIds
                ? implode(',', $sharedExpenseIds)
                : 'NONE'
        )
        . "\n";

    echo "SHARED_POOL_ALLOCATION_IDS="
        . (
            $sharedAllocationIds
                ? implode(
                    ',',
                    $sharedAllocationIds
                )
                : 'NONE'
        )
        . "\n";

    echo "FINANCIAL_PRESENTATION_ONLY_INVARIANCE=PASS\n";
    echo "SHARED_POOL_PRODUCTION_TYPE_CASE_INVARIANCE=PASS\n";
    echo "ALLOCATION_PERCENT_NONCAUSAL_INVARIANCE=PASS\n";
    echo "ALLOCATED_AMOUNT_CAUSAL_SENSITIVITY=PASS\n";
    echo "FINANCIAL_ECONOMIC_FACT_SENSITIVITY=PASS\n";
    echo "ALLOCATION_BOUNDARY_SENSITIVITY=PASS\n";
    echo "SHARED_POOL_DEPENDENCY_CONTRACT=PASS\n";
    echo "ROW_LEVEL_FINANCIAL_POLICY=PASS\n";
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
