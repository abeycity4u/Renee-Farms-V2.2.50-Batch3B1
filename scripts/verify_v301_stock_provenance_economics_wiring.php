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
     * Pure stock-source mapping contract.
     * Presentation/audit fields are deliberately not economic identity.
     */
    $stockA = [
        'id' => 392,
        'stock_item_id' => 42,
        'transaction_date' => '2026-09-11',
        'transaction_type' => 'used',
        'quantity' => '2.00',
        'unit_cost' => '21666.6700',
        'total_cost' => '43333.34',
        'financial_classification' => 'feed',
        'source_type' => 'daily_feed_record',
        'source_id' => 'example-source-1',
        'remarks' => 'Original display note',
        'created_at' => '2026-09-11 08:00:00',
        'item_name' => 'Layer Feed',
        'category_name' => 'Feeds',
    ];

    $stockPresentation = $stockA;
    $stockPresentation['remarks'] =
        'Presentation-only note changed';
    $stockPresentation['created_at'] =
        '2026-09-11 09:00:00';
    $stockPresentation['item_name'] =
        'Display label changed';
    $stockPresentation['category_name'] =
        'Feed';

    $stockEconomicChange = $stockA;
    $stockEconomicChange['total_cost'] =
        '43334.34';

    $stockSourceLinkChange = $stockA;
    $stockSourceLinkChange['source_id'] =
        'generic-source-id-changed';

    $stockSourceA =
        poultry_production_entry_stock_use_provenance_source(
            $stockA,
            'feed_use'
        );

    $stockPresentationSource =
        poultry_production_entry_stock_use_provenance_source(
            $stockPresentation,
            'feed_use'
        );

    $stockEconomicSource =
        poultry_production_entry_stock_use_provenance_source(
            $stockEconomicChange,
            'feed_use'
        );

    $stockSourceLinkSource =
        poultry_production_entry_stock_use_provenance_source(
            $stockSourceLinkChange,
            'feed_use'
        );

    $stockOperatingRole =
        poultry_production_entry_stock_use_provenance_source(
            $stockA,
            'operating_inventory_use'
        );

    $assert(
        $stockSourceA['source_revision']
            ===
        $stockPresentationSource['source_revision'],
        'Stock presentation-only fields changed provenance.'
    );

    $assert(
        $stockSourceA['source_revision']
            !==
        $stockEconomicSource['source_revision'],
        'Stock economic fact change did not change provenance.'
    );

    $assert(
        $stockSourceA['source_revision']
            !==
        $stockSourceLinkSource['source_revision'],
        'Generic stock source linkage change did not change provenance.'
    );

    $assert(
        poultry_production_entry_provenance_source_key(
            $stockSourceA
        )
            !==
        poultry_production_entry_provenance_source_key(
            $stockOperatingRole
        ),
        'Stock provenance role did not affect source identity.'
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
            (float)(
                $economics['rearing_investment']
                ?? 0
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
                    'feed_consumed_cost'
                ] ?? 0
            )
            - 173333.36
        ) < 0.005,
        'Feed cost changed.'
    );

    $assert(
        (int)(
            $economics[
                'uncosted_feed_uses'
            ] ?? -1
        ) === 0,
        'Feed uncosted count changed.'
    );

    $assert(
        abs(
            (float)(
                $economics[
                    'inventory_operating_cost'
                ] ?? 0
            )
            - 2500.00
        ) < 0.005,
        'Operating inventory cost changed.'
    );

    $assert(
        (int)(
            $economics[
                'uncosted_operating_uses'
            ] ?? -1
        ) === 0,
        'Operating inventory uncosted count changed.'
    );

    $assert(
        abs(
            (float)(
                $economics[
                    'inventory_operating_breakdown'
                ]['medication_vaccine']
                ?? 0
            )
            - 2500.00
        ) < 0.005,
        'Operating inventory breakdown changed.'
    );

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

    $sources =
        isset($economics['provenance_sources'])
        && is_array(
            $economics['provenance_sources']
        )
            ? $economics['provenance_sources']
            : [];

    $assert(
        count($populationSources) === 8,
        'Existing population provenance count changed.'
    );

    $assert(
        count($sources) === 16,
        'Expected 16 wired provenance sources.'
    );

    $roleCounts = [];
    $feedIds = [];
    $operatingIds = [];
    $lifecycleIds = [];
    $acquisitionIds = [];
    $populationMovementIds = [];

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

        if ($role === 'lifecycle_phase') {
            $lifecycleIds[] =
                (int)$normalized['source_id'];
        }

        if ($role === 'acquisition') {
            $acquisitionIds[] =
                (int)$normalized['source_id'];
        }

        if (
            $role
            === 'population_movement'
        ) {
            $populationMovementIds[] =
                (int)$normalized['source_id'];
        }
    }

    sort($feedIds, SORT_NUMERIC);
    sort($operatingIds, SORT_NUMERIC);
    sort($lifecycleIds, SORT_NUMERIC);
    sort($acquisitionIds, SORT_NUMERIC);
    sort(
        $populationMovementIds,
        SORT_NUMERIC
    );
    ksort($roleCounts, SORT_STRING);

    $assert(
        $feedIds === [
            392,
            393,
            394,
            428,
        ],
        'Feed provenance identities changed.'
    );

    $assert(
        $operatingIds === [418],
        'Operating inventory provenance identity changed.'
    );

    $assert(
        $lifecycleIds === [10, 11],
        'Existing lifecycle provenance changed.'
    );

    $assert(
        $acquisitionIds === [10],
        'Existing acquisition provenance changed.'
    );

    $assert(
        $populationMovementIds === [
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

    $assert(
        ($roleCounts['feed_use'] ?? 0)
            === 4,
        'Expected four Feed provenance sources.'
    );

    $assert(
        (
            $roleCounts[
                'operating_inventory_use'
            ] ?? 0
        ) === 1,
        'Expected one operating inventory provenance source.'
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
        'Combined stock-aware manifest is invalid.'
    );

    /*
     * Static cutover contract: economics must now retain rows instead of
     * the old stock aggregate queries.
     */
    $economicsSource =
        file_get_contents(
            $root
            . '/lib/poultry_rearing_economics.php'
        );

    $oldFeedAggregate =
        'SELECT COALESCE(SUM(t.total_cost),0), SUM(CASE WHEN t.total_cost IS NULL THEN 1 ELSE 0 END)';

    $oldOperatingGroup =
        'GROUP BY t.financial_classification';

    $assert(
        strpos(
            $economicsSource,
            $oldFeedAggregate
        ) === false,
        'Old aggregate Feed query remains.'
    );

    $assert(
        strpos(
            $economicsSource,
            $oldOperatingGroup
        ) === false,
        'Old grouped operating-stock query remains.'
    );

    $assert(
        strpos(
            $economicsSource,
            'poultry_production_entry_stock_use_provenance_source('
        ) !== false,
        'Stock provenance mapper is not wired.'
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

    echo "FEED_COST="
        . (string)$economics[
            'feed_consumed_cost'
        ]
        . "\n";

    echo "OPERATING_COST="
        . (string)$economics[
            'inventory_operating_cost'
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

    echo "FEED_IDS="
        . implode(',', $feedIds)
        . "\n";

    echo "OPERATING_IDS="
        . implode(',', $operatingIds)
        . "\n";

    echo "STOCK_PRESENTATION_ONLY_INVARIANCE=PASS\n";
    echo "STOCK_ECONOMIC_FACT_SENSITIVITY=PASS\n";
    echo "STOCK_SOURCE_LINK_SENSITIVITY=PASS\n";
    echo "STOCK_ROLE_SENSITIVITY=PASS\n";
    echo "ROW_LEVEL_POLICY=PASS\n";
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
