<?php

$root =
    dirname(__DIR__);

$path =
    $root
    . '/lib/shared_cost_contract.php';

if (!is_file($path)) {
    echo "RESULT=FAIL\n";
    echo "FAIL=MISSING_SHARED_COST_CONTRACT\n";
    exit(1);
}

require_once $path;

$failures = [];
$checks = 0;

$pass = static function (
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

$reject = static function (
    string $name,
    callable $callback,
    string $message
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
        if (
            strpos(
                $e->getMessage(),
                $message
            ) === false
        ) {
            $failures[] =
                $name
                . ':WRONG_MESSAGE:'
                . $e->getMessage();
        }
    }
};

$layerPool = [
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'attribution_scope' => 'production_type',
    'cycle_id' => null,
];

$sharedPoultry = [
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'shared',
    'attribution_scope' => 'farm',
    'cycle_id' => null,
];

$sharedRuminant = [
    'farm_id' => 4,
    'farm_type' => 'ruminant',
    'production_type' => 'shared',
    'attribution_scope' => 'farm',
    'cycle_id' => null,
];

$crossModule = [
    'farm_id' => 4,
    'farm_type' => 'both',
    'production_type' => 'shared',
    'attribution_scope' => 'farm',
    'cycle_id' => null,
];

$layerCycle = [
    'id' => 41,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'status' => 'active',
];

$broilerCycle = [
    'id' => 61,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'broiler',
    'status' => 'active',
];

$cattleCycle = [
    'id' => 71,
    'farm_id' => 4,
    'farm_type' => 'ruminant',
    'production_type' => 'cattle',
    'status' => 'closed',
];

$goatCycle = [
    'id' => 72,
    'farm_id' => 4,
    'farm_type' => 'ruminant',
    'production_type' => 'goat',
    'status' => 'active',
];

$pass(
    'PRODUCTION_POOL_CLASSIFIED',
    static function () use ($layerPool): void {
        $p =
            shared_cost_contract_parent(
                $layerPool
            );

        if (
            $p['scope_type']
            !== 'production_pool'
        ) {
            throw new RuntimeException(
                'Wrong production scope.'
            );
        }
    }
);

$pass(
    'LAYER_POOL_TO_LAYER',
    static function () use (
        $layerPool,
        $layerCycle
    ): void {
        $p =
            shared_cost_contract_parent(
                $layerPool
            );

        shared_cost_contract_target(
            $p,
            $layerCycle
        );
    }
);

$reject(
    'LAYER_POOL_TO_BROILER_BLOCKED',
    static function () use (
        $layerPool,
        $broilerCycle
    ): void {
        $p =
            shared_cost_contract_parent(
                $layerPool
            );

        shared_cost_contract_target(
            $p,
            $broilerCycle
        );
    },
    'matching production cycles'
);

$pass(
    'SHARED_POULTRY_TO_LAYER_AND_BROILER',
    static function () use (
        $sharedPoultry,
        $layerCycle,
        $broilerCycle
    ): void {
        $p =
            shared_cost_contract_parent(
                $sharedPoultry
            );

        shared_cost_contract_target(
            $p,
            $layerCycle
        );

        shared_cost_contract_target(
            $p,
            $broilerCycle
        );
    }
);

$pass(
    'SHARED_RUMINANT_MULTI_SPECIES',
    static function () use (
        $sharedRuminant,
        $cattleCycle,
        $goatCycle
    ): void {
        $p =
            shared_cost_contract_parent(
                $sharedRuminant
            );

        shared_cost_contract_target(
            $p,
            $cattleCycle
        );

        shared_cost_contract_target(
            $p,
            $goatCycle
        );
    }
);

$pass(
    'CROSS_MODULE_TO_POULTRY_AND_RUMINANT',
    static function () use (
        $crossModule,
        $layerCycle,
        $cattleCycle
    ): void {
        $p =
            shared_cost_contract_parent(
                $crossModule
            );

        shared_cost_contract_target(
            $p,
            $layerCycle
        );

        shared_cost_contract_target(
            $p,
            $cattleCycle
        );
    }
);

$reject(
    'DIRECT_COST_NOT_SHARED',
    static function () use ($layerPool): void {
        $row = $layerPool;
        $row['cycle_id'] = 41;

        shared_cost_contract_parent(
            $row
        );
    },
    'already attributed'
);

$reject(
    'MALFORMED_SCOPE_BLOCKED',
    static function () use ($layerPool): void {
        $row = $layerPool;
        $row['attribution_scope'] =
            'cycle';

        shared_cost_contract_parent(
            $row
        );
    },
    'production-type attribution'
);

$pass(
    'FULL_CONSERVATION',
    static function (): void {
        $x =
            shared_cost_contract_conservation(
                '100000.00',
                [
                    '40000.00',
                    '35000.00',
                    '25000.00',
                ]
            );

        if (
            $x['allocated_amount']
                !== '100000.00'
            ||
            $x['unallocated_amount']
                !== '0.00'
            ||
            !$x['fully_allocated']
        ) {
            throw new RuntimeException(
                'Full conservation mismatch.'
            );
        }
    }
);

$pass(
    'PARTIAL_ALLOCATION_VISIBLE',
    static function (): void {
        $x =
            shared_cost_contract_conservation(
                '100000.00',
                [
                    '60000.00',
                ]
            );

        if (
            $x['allocated_amount']
                !== '60000.00'
            ||
            $x['unallocated_amount']
                !== '40000.00'
            ||
            $x['fully_allocated']
        ) {
            throw new RuntimeException(
                'Partial conservation mismatch.'
            );
        }
    }
);

$reject(
    'OVER_ALLOCATION_BLOCKED',
    static function (): void {
        shared_cost_contract_conservation(
            '100000.00',
            [
                '60000.00',
                '40000.01',
            ]
        );
    },
    'cannot exceed'
);

$pass(
    'OPERATING_STOCK_CLASSES',
    static function (): void {
        foreach (
            [
                'feed',
                'medication_vaccine',
                'supplement',
                'consumables',
            ]
            as $classification
        ) {
            if (
                !shared_cost_contract_stock_is_operating(
                    $classification
                )
            ) {
                throw new RuntimeException(
                    'Operating class rejected.'
                );
            }
        }

        foreach (
            [
                'equipment_tools',
                'spare_parts',
                'other_stock',
            ]
            as $classification
        ) {
            if (
                shared_cost_contract_stock_is_operating(
                    $classification
                )
            ) {
                throw new RuntimeException(
                    'Non-operating class accepted.'
                );
            }
        }
    }
);

$pass(
    'SOURCE_DRIFT_IDENTIFIED',
    static function (): void {
        $status =
            shared_cost_contract_source_attribution_status(
                [
                    'cycle_id' => null,
                ],
                [
                    'cycle_id' => 41,
                ]
            );

        if (
            $status
            !== 'SOURCE_ATTRIBUTION_DRIFT'
        ) {
            throw new RuntimeException(
                'Source drift was not identified.'
            );
        }
    }
);

$pass(
    'SOURCE_MATCH_IDENTIFIED',
    static function (): void {
        $status =
            shared_cost_contract_source_attribution_status(
                [
                    'cycle_id' => 41,
                ],
                [
                    'cycle_id' => 41,
                ]
            );

        if (
            $status
            !== 'SOURCE_ATTRIBUTION_MATCH'
        ) {
            throw new RuntimeException(
                'Source match was not identified.'
            );
        }
    }
);

$text =
    file_get_contents(
        $path
    );

$checks++;

if (
    !is_string($text)
    ||
    preg_match(
        '/\b(?:INSERT\s+INTO|DELETE\s+FROM)\b/i',
        (string)$text
    ) === 1
) {
    $failures[] =
        'CONTRACT_MUST_NOT_WRITE_DATABASE';
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

echo "FARM_TYPE_SHARED_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "CROSS_MODULE_SHARED_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "CONSERVATION_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "SOURCE_DRIFT_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "STOCK_CLASSIFICATION_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "DATABASE_MUTATION="
    . (
        preg_match(
            '/\b(?:INSERT\s+INTO|DELETE\s+FROM)\b/i',
            (string)$text
        ) === 1
            ? 'FOUND'
            : 'NONE'
    )
    . "\n";

foreach ($failures as $failure) {
    echo "FAIL={$failure}\n";
}

exit(
    $result === 'PASS'
        ? 0
        : 1
);
