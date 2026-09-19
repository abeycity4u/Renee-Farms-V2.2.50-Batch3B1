<?php

$root =
    dirname(__DIR__);

$migrationPath =
    $root
    . '/migrations/067_sales_revenue_allocation_provenance.sql';

$servicePath =
    $root
    . '/lib/sale_revenue_allocation_service.php';

if (
    !is_file($migrationPath)
    ||
    !is_file($servicePath)
) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_FILES_MISSING\n";
    exit(1);
}

$migration =
    file_get_contents(
        $migrationPath
    );

$service =
    file_get_contents(
        $servicePath
    );

if (
    !is_string($migration)
    ||
    !is_string($service)
) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_READ_FAILED\n";
    exit(1);
}

require_once $servicePath;

$checks = [];

$check =
    static function (
        string $name,
        bool $passed
    ) use (&$checks): void {
        $checks[$name] =
            $passed;
    };

$throws =
    static function (
        callable $callback,
        string $messagePart
    ): bool {
        try {
            $callback();
        } catch (Throwable $e) {
            return
                strpos(
                    strtolower($e->getMessage()),
                    strtolower($messagePart)
                ) !== false;
        }

        return false;
    };


/* =========================================================
 * SCHEMA FOUNDATION
 * ========================================================= */

$check(
    'MIGRATION_CREATES_REVISION_TABLE',
    strpos(
        $migration,
        'CREATE TABLE IF NOT EXISTS sales_allocation_revisions'
    ) !== false
);

$check(
    'MIGRATION_CREATES_REVISION_ROW_TABLE',
    strpos(
        $migration,
        'CREATE TABLE IF NOT EXISTS sales_allocation_revision_rows'
    ) !== false
);

$check(
    'REVISION_UNIQUE_BY_FARM_SALE_NUMBER',
    strpos(
        $migration,
        'uniq_sales_allocation_revision'
    ) !== false
    &&
    strpos(
        $migration,
        "farm_id,\n        sale_id,\n        revision_no"
    ) !== false
);

$check(
    'REVISION_STORES_CONSERVATION_TOTALS',
    strpos(
        $migration,
        'parent_amount DECIMAL(14,2)'
    ) !== false
    &&
    strpos(
        $migration,
        'allocated_amount DECIMAL(14,2)'
    ) !== false
    &&
    strpos(
        $migration,
        'unallocated_amount DECIMAL(14,2)'
    ) !== false
);

$check(
    'REVISION_STORES_FINGERPRINTS',
    strpos(
        $migration,
        'causal_fingerprint CHAR(64)'
    ) !== false
    &&
    strpos(
        $migration,
        'state_fingerprint CHAR(64)'
    ) !== false
);

$check(
    'HISTORICAL_ROWS_HAVE_NO_SALE_OR_CYCLE_FOREIGN_KEY',
    strpos(
        $migration,
        'FOREIGN KEY (sale_id)'
    ) === false
    &&
    strpos(
        $migration,
        'FOREIGN KEY (cycle_id)'
    ) === false
);

$check(
    'MIGRATION_DOES_NOT_BACKFILL_BUSINESS_ROWS',
    preg_match(
        '/\bUPDATE\s+(?:sales_records|sales_allocations)\b/i',
        $migration
    ) !== 1
);

$check(
    'MIGRATION_REGISTERS_067',
    strpos(
        $migration,
        "067_sales_revenue_allocation_provenance.sql"
    ) !== false
);


/* =========================================================
 * SYNTHETIC POLICY CONTRACT
 * ========================================================= */

$poultryShared = [
    'id' => 33,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'shared',
    'attribution_scope' => 'farm',
    'cycle_id' => null,
    'product_type' => 'Manure',
    'total_amount' => '5000.00',
];

$poultryLayerSpecific = [
    'id' => 34,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'attribution_scope' => 'production_type',
    'cycle_id' => null,
    'product_type' => 'Spent litter',
    'total_amount' => '1000.00',
];

$layerEgg = [
    'id' => 35,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'attribution_scope' => 'production_type',
    'cycle_id' => null,
    'product_type' => 'Eggs',
    'total_amount' => '60000.00',
];

$directSale = [
    'id' => 36,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'attribution_scope' => 'cycle',
    'cycle_id' => 55,
    'product_type' => 'Manure',
    'total_amount' => '5000.00',
];

$layer55 = [
    'id' => 55,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'status' => 'active',
];

$broiler40 = [
    'id' => 40,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'broiler',
    'status' => 'active',
];

$goat70 = [
    'id' => 70,
    'farm_id' => 4,
    'farm_type' => 'ruminant',
    'production_type' => 'goat',
    'status' => 'active',
];

$otherFarmLayer = [
    'id' => 90,
    'farm_id' => 9,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'status' => 'active',
];


$sharedContract =
    sale_revenue_allocation_service_parent_contract(
        $poultryShared
    );

$check(
    'SHARED_POULTRY_PARENT_ACCEPTED',
    ($sharedContract['sale_id'] ?? 0) === 33
    &&
    ($sharedContract['parent_amount'] ?? '') === '5000.00'
    &&
    ($sharedContract['allocation_basis'] ?? '')
        === 'manual_shared_revenue'
);

$check(
    'AUTOMATIC_LAYER_EGG_PARENT_REJECTED',
    $throws(
        static function () use ($layerEgg): void {
            sale_revenue_allocation_service_parent_contract(
                $layerEgg
            );
        },
        'automatic unsold-egg allocation contract'
    )
);

$check(
    'DIRECT_CYCLE_PARENT_REJECTED',
    $throws(
        static function () use ($directSale): void {
            sale_revenue_allocation_service_parent_contract(
                $directSale
            );
        },
        'cycle-attributed sale'
    )
);

$check(
    'SHARED_POULTRY_CAN_TARGET_LAYER',
    (function () use (
        $sharedContract,
        $layer55
    ): bool {
        try {
            sale_revenue_allocation_service_target_contract(
                $sharedContract,
                $layer55
            );

            return true;

        } catch (Throwable $e) {
            return false;
        }
    })()
);

$check(
    'SHARED_POULTRY_CAN_TARGET_BROILER',
    (function () use (
        $sharedContract,
        $broiler40
    ): bool {
        try {
            sale_revenue_allocation_service_target_contract(
                $sharedContract,
                $broiler40
            );

            return true;

        } catch (Throwable $e) {
            return false;
        }
    })()
);

$check(
    'SHARED_POULTRY_REJECTS_RUMINANT',
    $throws(
        static function () use (
            $sharedContract,
            $goat70
        ): void {
            sale_revenue_allocation_service_target_contract(
                $sharedContract,
                $goat70
            );
        },
        'does not match the sale farm type'
    )
);

$check(
    'CROSS_TENANT_TARGET_REJECTED',
    $throws(
        static function () use (
            $sharedContract,
            $otherFarmLayer
        ): void {
            sale_revenue_allocation_service_target_contract(
                $sharedContract,
                $otherFarmLayer
            );
        },
        'does not belong to this farm'
    )
);

$layerSpecificContract =
    sale_revenue_allocation_service_parent_contract(
        $poultryLayerSpecific
    );

$check(
    'PRODUCTION_SPECIFIC_PARENT_ACCEPTED',
    ($layerSpecificContract['production_type'] ?? '')
        === 'layer'
);

$check(
    'PRODUCTION_SPECIFIC_TARGET_MATCH_ACCEPTED',
    (function () use (
        $layerSpecificContract,
        $layer55
    ): bool {
        try {
            sale_revenue_allocation_service_target_contract(
                $layerSpecificContract,
                $layer55
            );

            return true;

        } catch (Throwable $e) {
            return false;
        }
    })()
);

$check(
    'PRODUCTION_SPECIFIC_TARGET_MISMATCH_REJECTED',
    $throws(
        static function () use (
            $layerSpecificContract,
            $broiler40
        ): void {
            sale_revenue_allocation_service_target_contract(
                $layerSpecificContract,
                $broiler40
            );
        },
        'does not match the sale production type'
    )
);


/* =========================================================
 * CONSERVATION
 * ========================================================= */

$partial =
    sale_revenue_allocation_service_validate_desired_rows(
        $poultryShared,
        [
            $layer55,
            $broiler40,
        ],
        [
            [
                'cycle_id' => 55,
                'allocated_amount' => '3000.00',
            ],
            [
                'cycle_id' => 40,
                'allocated_amount' => '1000.00',
            ],
        ],
        0
    );

$check(
    'PARTIAL_ALLOCATION_ACCEPTED',
    ($partial['allocated_amount'] ?? '') === '4000.00'
    &&
    ($partial['remaining_amount'] ?? '') === '1000.00'
);

$check(
    'PERCENTAGES_DERIVED_FROM_PARENT',
    ($partial['rows'][0]['allocation_percent'] ?? '') === '20.0000'
    &&
    ($partial['rows'][1]['allocation_percent'] ?? '') === '60.0000'
);

$check(
    'MANUAL_QUANTITY_NOT_INVENTED',
    isset($partial['rows'][0])
    &&
    array_key_exists(
        'allocated_quantity',
        $partial['rows'][0]
    )
    &&
    $partial['rows'][0]['allocated_quantity'] === null
    &&
    array_key_exists(
        'allocation_unit',
        $partial['rows'][0]
    )
    &&
    $partial['rows'][0]['allocation_unit'] === null
);

$check(
    'OVERALLOCATION_REJECTED',
    $throws(
        static function () use (
            $poultryShared,
            $layer55,
            $broiler40
        ): void {
            sale_revenue_allocation_service_validate_desired_rows(
                $poultryShared,
                [
                    $layer55,
                    $broiler40,
                ],
                [
                    [
                        'cycle_id' => 55,
                        'allocated_amount' => '4000.00',
                    ],
                    [
                        'cycle_id' => 40,
                        'allocated_amount' => '2000.00',
                    ],
                ],
                0
            );
        },
        'cannot exceed the sale total'
    )
);

$check(
    'DUPLICATE_TARGET_REJECTED',
    $throws(
        static function () use (
            $poultryShared,
            $layer55
        ): void {
            sale_revenue_allocation_service_validate_desired_rows(
                $poultryShared,
                [
                    $layer55,
                ],
                [
                    [
                        'cycle_id' => 55,
                        'allocated_amount' => '1000.00',
                    ],
                    [
                        'cycle_id' => 55,
                        'allocated_amount' => '1000.00',
                    ],
                ],
                0
            );
        },
        'same production cycle'
    )
);

$check(
    'ANIMAL_REVENUE_OVERLAP_REJECTED',
    $throws(
        static function () use (
            $poultryShared,
            $layer55
        ): void {
            sale_revenue_allocation_service_validate_desired_rows(
                $poultryShared,
                [
                    $layer55,
                ],
                [
                    [
                        'cycle_id' => 55,
                        'allocated_amount' => '1000.00',
                    ],
                ],
                1
            );
        },
        'individual animals'
    )
);

$empty =
    sale_revenue_allocation_service_validate_desired_rows(
        $poultryShared,
        [],
        [],
        0
    );

$check(
    'EMPTY_STATE_PRESERVES_FULL_REMAINDER',
    ($empty['allocated_amount'] ?? '') === '0.00'
    &&
    ($empty['remaining_amount'] ?? '') === '5000.00'
    &&
    count($empty['rows'] ?? []) === 0
);

$serviceTokens =
    token_get_all(
        $service
    );

$serviceExecutable =
    '';

foreach ($serviceTokens as $token) {
    if (
        is_array($token)
        &&
        in_array(
            $token[0],
            [
                T_COMMENT,
                T_DOC_COMMENT,
            ],
            true
        )
    ) {
        continue;
    }

    $serviceExecutable .=
        is_array($token)
            ? $token[1]
            : $token;
}

$check(
    'SERVICE_OWNS_NO_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE|REPLACE)\s+/i',
        $serviceExecutable
    ) !== 1
);


$failed = [];

foreach ($checks as $name => $passed) {
    echo $name
        . '='
        . ($passed ? 'PASS' : 'FAIL')
        . PHP_EOL;

    if (!$passed) {
        $failed[] =
            $name;
    }
}

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITES=NONE\n";

if ($failed) {
    echo 'FAILED='
        . implode(',', $failed)
        . PHP_EOL;

    echo "RESULT=FAIL\n";
    exit(1);
}

echo "RESULT=PASS\n";
exit(0);
