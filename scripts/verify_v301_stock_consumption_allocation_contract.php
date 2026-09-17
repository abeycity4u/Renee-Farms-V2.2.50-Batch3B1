<?php

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/lib/stock_consumption_allocation_service.php';

if (!is_file($servicePath)) {
    echo "RESULT=FAIL\n";
    echo "FAIL=MISSING_STOCK_CONSUMPTION_SERVICE\n";
    exit(1);
}

require_once $servicePath;

$failures = [];
$checks = 0;

$pass =
    static function (
        string $name,
        callable $callback
    ) use (
        &$failures,
        &$checks
    ): void {
        $checks++;

        try {
            $callback();
        } catch (Throwable $e) {
            $failures[] =
                $name
                . ':'
                . get_class($e)
                . ':'
                . $e->getMessage();
        }
    };

$reject =
    static function (
        string $name,
        callable $callback
    ) use (
        &$failures,
        &$checks
    ): void {
        $checks++;

        try {
            $callback();

            $failures[] =
                $name
                . ':EXPECTED_REJECTION';

        } catch (Throwable $e) {
            /*
             * Behavior check only. Do not make this verifier brittle
             * against harmless wording changes.
             */
        }
    };

$base = [
    'id' => 5001,
    'farm_id' => 4,
    'cycle_id' => null,
    'transaction_type' => 'used',
    'is_reversed' => 0,
    'reversal_of_id' => null,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'attribution_scope' => 'production_type',
    'financial_classification' => 'consumables',
    'total_cost' => '100000.00',
    'source_type' => 'inventory_manual',
    'source_id' => 5001,
];

$layer = [
    'id' => 41,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'status' => 'active',
];

$broiler = [
    'id' => 51,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'broiler',
    'status' => 'closed',
];

$cattle = [
    'id' => 71,
    'farm_id' => 4,
    'farm_type' => 'ruminant',
    'production_type' => 'cattle',
    'status' => 'active',
];

$goat = [
    'id' => 72,
    'farm_id' => 4,
    'farm_type' => 'ruminant',
    'production_type' => 'goat',
    'status' => 'active',
];

$pass(
    'LAYER_POOL_TO_LAYER',
    static function () use (
        $base,
        $layer
    ): void {
        $result =
            stock_consumption_allocation_service_validate_desired_rows(
                $base,
                [
                    $layer,
                ],
                [
                    [
                        'cycle_id' => 41,
                        'allocated_amount' => '25000.00',
                    ],
                ]
            );

        if (
            $result['allocated_amount']
                !== '25000.00'
            ||
            $result['remaining_amount']
                !== '75000.00'
            ||
            $result['rows'][0][
                'allocation_percent'
            ] !== '25.0000'
        ) {
            throw new RuntimeException(
                'Layer allocation result mismatch.'
            );
        }
    }
);

$reject(
    'LAYER_POOL_TO_BROILER_BLOCKED',
    static function () use (
        $base,
        $broiler
    ): void {
        stock_consumption_allocation_service_validate_desired_rows(
            $base,
            [
                $broiler,
            ],
            [
                [
                    'cycle_id' => 51,
                    'allocated_amount' => '1000.00',
                ],
            ]
        );
    }
);

$pass(
    'SHARED_POULTRY_MULTI_PRODUCTION',
    static function () use (
        $base,
        $layer,
        $broiler
    ): void {
        $movement = $base;
        $movement['production_type'] = 'shared';
        $movement['attribution_scope'] = 'farm';

        $result =
            stock_consumption_allocation_service_validate_desired_rows(
                $movement,
                [
                    $layer,
                    $broiler,
                ],
                [
                    [
                        'cycle_id' => 41,
                        'allocated_amount' => '60000.00',
                    ],
                    [
                        'cycle_id' => 51,
                        'allocated_amount' => '40000.00',
                    ],
                ]
            );

        if (
            !$result['fully_allocated']
            ||
            $result['remaining_amount']
                !== '0.00'
        ) {
            throw new RuntimeException(
                'Shared Poultry conservation mismatch.'
            );
        }
    }
);

$pass(
    'SHARED_RUMINANT_MULTI_SPECIES',
    static function () use (
        $base,
        $cattle,
        $goat
    ): void {
        $movement = $base;
        $movement['farm_type'] = 'ruminant';
        $movement['production_type'] = 'shared';
        $movement['attribution_scope'] = 'farm';

        $result =
            stock_consumption_allocation_service_validate_desired_rows(
                $movement,
                [
                    $cattle,
                    $goat,
                ],
                [
                    [
                        'cycle_id' => 71,
                        'allocated_amount' => '40000.00',
                    ],
                    [
                        'cycle_id' => 72,
                        'allocated_amount' => '30000.00',
                    ],
                ]
            );

        if (
            $result['remaining_amount']
                !== '30000.00'
        ) {
            throw new RuntimeException(
                'Shared Ruminant remainder mismatch.'
            );
        }
    }
);

$pass(
    'CROSS_MODULE_POOL',
    static function () use (
        $base,
        $layer,
        $cattle
    ): void {
        $movement = $base;
        $movement['farm_type'] = 'both';
        $movement['production_type'] = 'shared';
        $movement['attribution_scope'] = 'farm';

        $result =
            stock_consumption_allocation_service_validate_desired_rows(
                $movement,
                [
                    $layer,
                    $cattle,
                ],
                [
                    [
                        'cycle_id' => 41,
                        'allocated_amount' => '50000.00',
                    ],
                    [
                        'cycle_id' => 71,
                        'allocated_amount' => '25000.00',
                    ],
                ]
            );

        if (
            $result['remaining_amount']
                !== '25000.00'
        ) {
            throw new RuntimeException(
                'Cross-module remainder mismatch.'
            );
        }
    }
);

$reject(
    'DIRECT_CYCLE_MOVEMENT_BLOCKED',
    static function () use ($base): void {
        $movement = $base;
        $movement['cycle_id'] = 41;
        $movement['attribution_scope'] = 'cycle';

        stock_consumption_allocation_service_parent_contract(
            $movement
        );
    }
);

$reject(
    'RECEIPT_NOT_CONSUMPTION',
    static function () use ($base): void {
        $movement = $base;
        $movement['transaction_type'] = 'received';

        stock_consumption_allocation_service_parent_contract(
            $movement
        );
    }
);

$reject(
    'REVERSED_ORIGINAL_BLOCKED',
    static function () use ($base): void {
        $movement = $base;
        $movement['is_reversed'] = 1;

        stock_consumption_allocation_service_parent_contract(
            $movement
        );
    }
);

$reject(
    'REVERSAL_ROW_BLOCKED',
    static function () use ($base): void {
        $movement = $base;
        $movement['reversal_of_id'] = 4999;

        stock_consumption_allocation_service_parent_contract(
            $movement
        );
    }
);

$reject(
    'NON_OPERATING_CLASS_BLOCKED',
    static function () use ($base): void {
        $movement = $base;
        $movement[
            'financial_classification'
        ] = 'equipment_tools';

        stock_consumption_allocation_service_parent_contract(
            $movement
        );
    }
);

$reject(
    'ZERO_COST_BLOCKED',
    static function () use ($base): void {
        $movement = $base;
        $movement['total_cost'] = '0.00';

        stock_consumption_allocation_service_parent_contract(
            $movement
        );
    }
);

$reject(
    'DAILY_SOURCE_REQUIRES_RESOLUTION',
    static function () use ($base): void {
        $movement = $base;
        $movement['id'] = 354;
        $movement['source_type'] =
            'daily_layer_record';

        stock_consumption_allocation_service_parent_contract(
            $movement,
            null
        );
    }
);

$reject(
    'DAILY_SOURCE_DRIFT_NOT_ALLOCATED',
    static function () use ($base): void {
        /*
         * Mirrors the forensic shape of stock transaction 354:
         * stock movement has no cycle but Daily Record 59 says cycle 41.
         */
        $movement = $base;
        $movement['id'] = 354;
        $movement['source_type'] =
            'daily_layer_record';
        $movement['source_id'] = 59;
        $movement['total_cost'] =
            '89136.00';

        stock_consumption_allocation_service_parent_contract(
            $movement,
            [
                'cycle_id' => 41,
            ]
        );
    }
);

$pass(
    'DAILY_SOURCE_WITHOUT_CYCLE_CAN_REMAIN_POOL',
    static function () use ($base): void {
        $movement = $base;
        $movement['source_type'] =
            'daily_layer_record';

        $parent =
            stock_consumption_allocation_service_parent_contract(
                $movement,
                [
                    'cycle_id' => null,
                ]
            );

        if (
            $parent[
                'source_attribution_status'
            ] !== 'SOURCE_HAS_NO_CYCLE'
        ) {
            throw new RuntimeException(
                'Source status mismatch.'
            );
        }
    }
);

$reject(
    'DUPLICATE_TARGET_BLOCKED',
    static function () use (
        $base,
        $layer
    ): void {
        stock_consumption_allocation_service_validate_desired_rows(
            $base,
            [
                $layer,
            ],
            [
                [
                    'cycle_id' => 41,
                    'allocated_amount' => '10000.00',
                ],
                [
                    'cycle_id' => 41,
                    'allocated_amount' => '10000.00',
                ],
            ]
        );
    }
);

$reject(
    'OVER_ALLOCATION_BLOCKED',
    static function () use (
        $base,
        $layer
    ): void {
        stock_consumption_allocation_service_validate_desired_rows(
            $base,
            [
                $layer,
            ],
            [
                [
                    'cycle_id' => 41,
                    'allocated_amount' => '100000.01',
                ],
            ]
        );
    }
);

$pass(
    'INPUT_PERCENT_NOT_AUTHORITY',
    static function () use (
        $base,
        $layer
    ): void {
        $result =
            stock_consumption_allocation_service_validate_desired_rows(
                $base,
                [
                    $layer,
                ],
                [
                    [
                        'cycle_id' => 41,
                        'allocated_amount' => '25000.00',
                        'allocation_percent' => '99.9999',
                    ],
                ]
            );

        if (
            $result['rows'][0][
                'allocation_percent'
            ] !== '25.0000'
        ) {
            throw new RuntimeException(
                'Input percentage became authority.'
            );
        }
    }
);

$pass(
    'EMPTY_ALLOCATION_VISIBLE',
    static function () use ($base): void {
        $result =
            stock_consumption_allocation_service_validate_desired_rows(
                $base,
                [],
                []
            );

        if (
            $result['allocated_amount']
                !== '0.00'
            ||
            $result['remaining_amount']
                !== '100000.00'
            ||
            $result['fully_allocated']
        ) {
            throw new RuntimeException(
                'Empty allocation conservation mismatch.'
            );
        }
    }
);

$text =
    file_get_contents(
        $servicePath
    );

$checks++;

$writerFound =
    !is_string($text)
    ||
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i',
        (string)$text
    ) === 1;

if ($writerFound) {
    $failures[] =
        'FOUNDATION_DATABASE_WRITER_FOUND';
}

$result =
    $failures
        ? 'FAIL'
        : 'PASS';

echo "RESULT={$result}\n";
echo "CHECK_COUNT={$checks}\n";

echo "PRODUCTION_POOL_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "FARM_TYPE_POOL_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "CROSS_MODULE_POOL_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "EFFECTIVE_LEDGER_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "SOURCE_DRIFT_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "OPERATING_CLASS_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "ALLOCATED_AMOUNT_AUTHORITY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "UNALLOCATED_DISCLOSURE="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "FOUNDATION_DATABASE_WRITES="
    . ($writerFound ? 'FOUND' : 'NONE')
    . "\n";

foreach ($failures as $failure) {
    echo "FAIL={$failure}\n";
}

exit(
    $result === 'PASS'
        ? 0
        : 1
);
