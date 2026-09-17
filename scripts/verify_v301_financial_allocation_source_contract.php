<?php

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/lib/financial_allocation_service.php';

if (!is_file($servicePath)) {
    echo "RESULT=FAIL\n";
    echo "FAIL=MISSING_SERVICE\n";
    exit(1);
}

require_once $servicePath;

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
    string $messagePart
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
                $messagePart
            ) === false
        ) {
            $failures[] =
                $name
                . ':WRONG_MESSAGE:'
                . $e->getMessage();
        }
    }
};

$layerParent = [
    'id' => 9001,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'poultry_category' => 'layer',
    'attribution_scope' => 'production_type',
    'cycle_id' => null,
    'category' => 'fuel',
    'amount' => '100000.00',
    'unit' => '1.00',
];

$layerCycle = [
    'id' => 55,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'status' => 'closed',
];

$broilerCycle = [
    'id' => 56,
    'farm_id' => 4,
    'farm_type' => 'poultry',
    'production_type' => 'broiler',
    'status' => 'active',
];

$ruminantShared = [
    'id' => 9002,
    'farm_id' => 4,
    'farm_type' => 'ruminant',
    'production_type' => 'shared',
    'poultry_category' => null,
    'attribution_scope' => 'farm',
    'cycle_id' => null,
    'category' => 'salary',
    'amount' => '1000.00',
    'unit' => '1.00',
];

$cattleCycle = [
    'id' => 70,
    'farm_id' => 4,
    'farm_type' => 'ruminant',
    'production_type' => 'cattle',
    'status' => 'closed',
];

$goatCycle = [
    'id' => 71,
    'farm_id' => 4,
    'farm_type' => 'ruminant',
    'production_type' => 'goat',
    'status' => 'active',
];

$pass(
    'LAYER_PARENT_ACCEPTED',
    static function () use (
        $layerParent
    ): void {
        $contract =
            financial_allocation_service_parent_contract(
                $layerParent
            );

        if (
            $contract['production_type']
                !== 'layer'
            ||
            $contract['gross_amount']
                !== '100000.00'
        ) {
            throw new RuntimeException(
                'Unexpected parent contract.'
            );
        }
    }
);

$pass(
    'CLOSED_COMPATIBLE_CYCLE_ACCEPTED',
    static function () use (
        $layerParent,
        $layerCycle
    ): void {
        $parent =
            financial_allocation_service_parent_contract(
                $layerParent
            );

        $target =
            financial_allocation_service_target_contract(
                $parent,
                $layerCycle
            );

        if (
            $target['status']
            !== 'closed'
        ) {
            throw new RuntimeException(
                'Closed cycle status was altered.'
            );
        }
    }
);

$reject(
    'DIRECT_PARENT_REJECTED',
    static function () use (
        $layerParent
    ): void {
        $row = $layerParent;
        $row['cycle_id'] = 55;

        financial_allocation_service_parent_contract(
            $row
        );
    },
    'without a specific production cycle'
);

$reject(
    'MALFORMED_SCOPE_REJECTED',
    static function () use (
        $layerParent
    ): void {
        $row = $layerParent;
        $row['attribution_scope'] =
            'cycle';

        financial_allocation_service_parent_contract(
            $row
        );
    },
    'attribution scope'
);

$reject(
    'HISTORICAL_FEED_REJECTED',
    static function () use (
        $layerParent
    ): void {
        $row = $layerParent;
        $row['category'] = 'feeds';

        financial_allocation_service_parent_contract(
            $row
        );
    },
    'Feed expenses'
);

$reject(
    'POULTRY_CATEGORY_MISMATCH_REJECTED',
    static function () use (
        $layerParent
    ): void {
        $row = $layerParent;
        $row['poultry_category'] =
            'broiler';

        financial_allocation_service_parent_contract(
            $row
        );
    },
    'Poultry expense category'
);

$reject(
    'CONCRETE_CROSS_PRODUCTION_REJECTED',
    static function () use (
        $layerParent,
        $broilerCycle
    ): void {
        $parent =
            financial_allocation_service_parent_contract(
                $layerParent
            );

        financial_allocation_service_target_contract(
            $parent,
            $broilerCycle
        );
    },
    'does not match the expense production type'
);

$pass(
    'SHARED_RUMINANT_MULTI_SPECIES_ACCEPTED',
    static function () use (
        $ruminantShared,
        $cattleCycle,
        $goatCycle
    ): void {
        $validated =
            financial_allocation_service_validate_desired_rows(
                $ruminantShared,
                [
                    $cattleCycle,
                    $goatCycle,
                ],
                [
                    [
                        'cycle_id' => 70,
                        'allocated_amount' =>
                            '400.00',
                    ],
                    [
                        'cycle_id' => 71,
                        'allocated_amount' =>
                            '600.00',
                    ],
                ],
                0
            );

        if (
            $validated['allocated_amount']
                !== '1000.00'
            ||
            $validated['remaining_amount']
                !== '0.00'
            ||
            $validated['rows'][0][
                'allocation_percent'
            ] !== '40.0000'
            ||
            $validated['rows'][1][
                'allocation_percent'
            ] !== '60.0000'
        ) {
            throw new RuntimeException(
                'Shared allocation calculation is wrong.'
            );
        }
    }
);

$reject(
    'ANIMAL_OVERLAP_REJECTED',
    static function () use (
        $ruminantShared,
        $cattleCycle
    ): void {
        financial_allocation_service_validate_desired_rows(
            $ruminantShared,
            [
                $cattleCycle,
            ],
            [
                [
                    'cycle_id' => 70,
                    'allocated_amount' =>
                        '100.00',
                ],
            ],
            1
        );
    },
    'individual animals'
);

$reject(
    'OVER_ALLOCATION_REJECTED',
    static function () use (
        $layerParent,
        $layerCycle
    ): void {
        financial_allocation_service_validate_desired_rows(
            $layerParent,
            [
                $layerCycle,
            ],
            [
                [
                    'cycle_id' => 55,
                    'allocated_amount' =>
                        '100000.01',
                ],
            ],
            0
        );
    },
    'cannot exceed'
);

$reject(
    'DUPLICATE_TARGET_REJECTED',
    static function () use (
        $layerParent,
        $layerCycle
    ): void {
        financial_allocation_service_validate_desired_rows(
            $layerParent,
            [
                $layerCycle,
            ],
            [
                [
                    'cycle_id' => 55,
                    'allocated_amount' =>
                        '100.00',
                ],
                [
                    'cycle_id' => 55,
                    'allocated_amount' =>
                        '200.00',
                ],
            ],
            0
        );
    },
    'same production cycle more than once'
);

$reject(
    'CROSS_FARM_TARGET_REJECTED',
    static function () use (
        $layerParent,
        $layerCycle
    ): void {
        $cycle = $layerCycle;
        $cycle['farm_id'] = 6;

        $parent =
            financial_allocation_service_parent_contract(
                $layerParent
            );

        financial_allocation_service_target_contract(
            $parent,
            $cycle
        );
    },
    'does not belong to the expense farm'
);

$reject(
    'SHARED_TARGET_CYCLE_TYPE_REJECTED',
    static function () use (
        $ruminantShared
    ): void {
        $parent =
            financial_allocation_service_parent_contract(
                $ruminantShared
            );

        financial_allocation_service_target_contract(
            $parent,
            [
                'id' => 72,
                'farm_id' => 4,
                'farm_type' => 'ruminant',
                'production_type' =>
                    'shared',
                'status' => 'active',
            ]
        );
    },
    'production type is not eligible'
);

$pass(
    'DERIVED_PERCENT_NOT_INPUT_AUTHORITY',
    static function () use (
        $layerParent,
        $layerCycle
    ): void {
        $validated =
            financial_allocation_service_validate_desired_rows(
                $layerParent,
                [
                    $layerCycle,
                ],
                [
                    [
                        'cycle_id' => 55,
                        'allocated_amount' =>
                            '25000.00',
                        'allocation_percent' =>
                            '99.9999',
                    ],
                ],
                0
            );

        if (
            $validated['rows'][0][
                'allocation_percent'
            ] !== '25.0000'
        ) {
            throw new RuntimeException(
                'Input percentage became authority.'
            );
        }
    }
);

$serviceText =
    file_get_contents(
        $servicePath
    );

if (!is_string($serviceText)) {
    $failures[] =
        'SERVICE_READ_FAILED';
} else {
    $checks++;

    if (
        preg_match(
            '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?financial_allocations`?/i',
            $serviceText
        ) === 1
    ) {
        $failures[] =
            'FOUNDATION_WRITES_FINANCIAL_ALLOCATIONS';
    }
}

$result =
    $failures
        ? 'FAIL'
        : 'PASS';

echo "RESULT={$result}\n";
echo "CHECK_COUNT={$checks}\n";
echo "PARENT_ELIGIBILITY="
    . (
        $failures
            ? 'SEE_FAILURES'
            : 'PASS'
    )
    . "\n";

echo "TARGET_COMPATIBILITY="
    . (
        $failures
            ? 'SEE_FAILURES'
            : 'PASS'
    )
    . "\n";

echo "ALLOCATED_AMOUNT_AUTHORITY="
    . (
        $failures
            ? 'SEE_FAILURES'
            : 'PASS'
    )
    . "\n";

echo "ANIMAL_OVERLAP_POLICY="
    . (
        $failures
            ? 'SEE_FAILURES'
            : 'PASS'
    )
    . "\n";

echo "CLOSED_CYCLE_POLICY="
    . (
        $failures
            ? 'SEE_FAILURES'
            : 'PASS'
    )
    . "\n";

echo "FOUNDATION_ALLOCATION_WRITES="
    . (
        preg_match(
            '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?financial_allocations`?/i',
            (string)$serviceText
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
