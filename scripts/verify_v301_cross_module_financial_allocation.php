<?php

require_once dirname(__DIR__)
    . '/lib/financial_allocation_service.php';

$checks = [];

function record_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] = $passed;
}

function throws_with(
    callable $callback,
    string $expected
): bool {
    try {
        $callback();

    } catch (Throwable $e) {
        return strpos(
            $e->getMessage(),
            $expected
        ) !== false;
    }

    return false;
}

$bothParent = [
    'id' =>
        999001,

    'farm_id' =>
        4,

    'farm_type' =>
        'both',

    'production_type' =>
        'shared',

    'attribution_scope' =>
        'farm',

    'cycle_id' =>
        null,

    'category' =>
        'misc',

    'poultry_category' =>
        '',

    'amount' =>
        '100.00',

    'unit' =>
        '1.00',
];

$poultryLayer = [
    'id' =>
        900001,

    'farm_id' =>
        4,

    'farm_type' =>
        'poultry',

    'production_type' =>
        'layer',

    'status' =>
        'active',
];

$ruminantGoat = [
    'id' =>
        900002,

    'farm_id' =>
        4,

    'farm_type' =>
        'ruminant',

    'production_type' =>
        'goat',

    'status' =>
        'active',
];

$generalTarget = [
    'id' =>
        900003,

    'farm_id' =>
        4,

    'farm_type' =>
        'general',

    'production_type' =>
        'shared',

    'status' =>
        'active',
];

$closedPoultryLayer =
    array_replace(
        $poultryLayer,
        [
            'id' =>
                900004,

            'status' =>
                'closed',
        ]
    );

$bothContract = null;

try {
    $bothContract =
        financial_allocation_service_parent_contract(
            $bothParent
        );

    record_check(
        $checks,
        'BOTH_SHARED_FARM_PARENT_ACCEPTED',
        true
    );

} catch (Throwable $e) {
    record_check(
        $checks,
        'BOTH_SHARED_FARM_PARENT_ACCEPTED',
        false
    );
}

record_check(
    $checks,
    'BOTH_PARENT_GROSS_100',
    is_array($bothContract)
    &&
    (int)(
        $bothContract[
            'gross_cents'
        ]
        ?? 0
    ) === 10000
);

record_check(
    $checks,
    'BOTH_CONCRETE_PRODUCTION_REJECTED',
    throws_with(
        static function () use (
            $bothParent
        ): void {
            financial_allocation_service_parent_contract(
                array_replace(
                    $bothParent,
                    [
                        'production_type' =>
                            'layer',

                        'attribution_scope' =>
                            'production_type',
                    ]
                )
            );
        },
        'production type is not eligible'
    )
);

record_check(
    $checks,
    'BOTH_WRONG_SCOPE_REJECTED',
    throws_with(
        static function () use (
            $bothParent
        ): void {
            financial_allocation_service_parent_contract(
                array_replace(
                    $bothParent,
                    [
                        'attribution_scope' =>
                            'production_type',
                    ]
                )
            );
        },
        'attribution scope is inconsistent'
    )
);

record_check(
    $checks,
    'GENERAL_PARENT_STILL_REJECTED',
    throws_with(
        static function () use (
            $bothParent
        ): void {
            financial_allocation_service_parent_contract(
                array_replace(
                    $bothParent,
                    [
                        'farm_type' =>
                            'general',
                    ]
                )
            );
        },
        'Financial allocation supports Poultry and Ruminant shared expenses only.'
    )
);

record_check(
    $checks,
    'BOTH_TO_POULTRY_LAYER_ACCEPTED',
    is_array($bothContract)
    &&
    (function () use (
        $bothContract,
        $poultryLayer
    ): bool {
        try {
            $result =
                financial_allocation_service_target_contract(
                    $bothContract,
                    $poultryLayer
                );

            return
                ($result['farm_type'] ?? '')
                === 'poultry'
                &&
                ($result['production_type'] ?? '')
                === 'layer';

        } catch (Throwable $e) {
            return false;
        }
    })()
);

record_check(
    $checks,
    'BOTH_TO_RUMINANT_GOAT_ACCEPTED',
    is_array($bothContract)
    &&
    (function () use (
        $bothContract,
        $ruminantGoat
    ): bool {
        try {
            $result =
                financial_allocation_service_target_contract(
                    $bothContract,
                    $ruminantGoat
                );

            return
                ($result['farm_type'] ?? '')
                === 'ruminant'
                &&
                ($result['production_type'] ?? '')
                === 'goat';

        } catch (Throwable $e) {
            return false;
        }
    })()
);

record_check(
    $checks,
    'BOTH_TO_GENERAL_TARGET_REJECTED',
    is_array($bothContract)
    &&
    throws_with(
        static function () use (
            $bothContract,
            $generalTarget
        ): void {
            financial_allocation_service_target_contract(
                $bothContract,
                $generalTarget
            );
        },
        'target production cycle does not match the expense farm type'
    )
);

$poultrySharedParent = [
    'id' =>
        999002,

    'farm_id' =>
        4,

    'farm_type' =>
        'poultry',

    'production_type' =>
        'shared',

    'attribution_scope' =>
        'farm',

    'cycle_id' =>
        null,

    'category' =>
        'misc',

    'poultry_category' =>
        'shared',

    'amount' =>
        '100.00',

    'unit' =>
        '1.00',
];

$ruminantSharedParent = [
    'id' =>
        999003,

    'farm_id' =>
        4,

    'farm_type' =>
        'ruminant',

    'production_type' =>
        'shared',

    'attribution_scope' =>
        'farm',

    'cycle_id' =>
        null,

    'category' =>
        'misc',

    'amount' =>
        '100.00',

    'unit' =>
        '1.00',
];

$poultryContract =
    financial_allocation_service_parent_contract(
        $poultrySharedParent
    );

$ruminantContract =
    financial_allocation_service_parent_contract(
        $ruminantSharedParent
    );

record_check(
    $checks,
    'POULTRY_TO_RUMINANT_STILL_REJECTED',
    throws_with(
        static function () use (
            $poultryContract,
            $ruminantGoat
        ): void {
            financial_allocation_service_target_contract(
                $poultryContract,
                $ruminantGoat
            );
        },
        'target production cycle does not match the expense farm type'
    )
);

record_check(
    $checks,
    'RUMINANT_TO_POULTRY_STILL_REJECTED',
    throws_with(
        static function () use (
            $ruminantContract,
            $poultryLayer
        ): void {
            financial_allocation_service_target_contract(
                $ruminantContract,
                $poultryLayer
            );
        },
        'target production cycle does not match the expense farm type'
    )
);

$poultryLayerParent =
    array_replace(
        $poultrySharedParent,
        [
            'id' =>
                999004,

            'production_type' =>
                'layer',

            'attribution_scope' =>
                'production_type',

            'poultry_category' =>
                'layer',
        ]
    );

$poultryLayerContract =
    financial_allocation_service_parent_contract(
        $poultryLayerParent
    );

$poultryBroiler = [
    'id' =>
        900005,

    'farm_id' =>
        4,

    'farm_type' =>
        'poultry',

    'production_type' =>
        'broiler',

    'status' =>
        'active',
];

record_check(
    $checks,
    'POULTRY_LAYER_TO_BROILER_STILL_REJECTED',
    throws_with(
        static function () use (
            $poultryLayerContract,
            $poultryBroiler
        ): void {
            financial_allocation_service_target_contract(
                $poultryLayerContract,
                $poultryBroiler
            );
        },
        'target cycle production type does not match the expense production type'
    )
);

$partial = null;

try {
    $partial =
        financial_allocation_service_validate_desired_rows(
            $bothParent,
            [
                $poultryLayer,
                $ruminantGoat,
            ],
            [
                [
                    'cycle_id' =>
                        900001,

                    'allocated_amount' =>
                        '40.00',
                ],
                [
                    'cycle_id' =>
                        900002,

                    'allocated_amount' =>
                        '35.00',
                ],
            ],
            0
        );

    record_check(
        $checks,
        'CROSS_MODULE_PARTIAL_ALLOCATION_ACCEPTED',
        true
    );

} catch (Throwable $e) {
    record_check(
        $checks,
        'CROSS_MODULE_PARTIAL_ALLOCATION_ACCEPTED',
        false
    );
}

record_check(
    $checks,
    'CROSS_MODULE_ALLOCATED_75',
    is_array($partial)
    &&
    ($partial['allocated_amount'] ?? '')
    === '75.00'
);

record_check(
    $checks,
    'CROSS_MODULE_REMAINDER_25',
    is_array($partial)
    &&
    ($partial['remaining_amount'] ?? '')
    === '25.00'
);

record_check(
    $checks,
    'CROSS_MODULE_PERCENTAGES_DERIVED',
    is_array($partial)
    &&
    ($partial['rows'][0]['allocation_percent'] ?? '')
    === '40.0000'
    &&
    ($partial['rows'][1]['allocation_percent'] ?? '')
    === '35.0000'
);

record_check(
    $checks,
    'CROSS_MODULE_OVERALLOCATION_REJECTED',
    throws_with(
        static function () use (
            $bothParent,
            $poultryLayer,
            $ruminantGoat
        ): void {
            financial_allocation_service_validate_desired_rows(
                $bothParent,
                [
                    $poultryLayer,
                    $ruminantGoat,
                ],
                [
                    [
                        'cycle_id' =>
                            900001,

                        'allocated_amount' =>
                            '60.00',
                    ],
                    [
                        'cycle_id' =>
                            900002,

                        'allocated_amount' =>
                            '50.00',
                    ],
                ],
                0
            );
        },
        'Total financial allocations cannot exceed the expense gross value.'
    )
);

record_check(
    $checks,
    'DUPLICATE_TARGET_REJECTED',
    throws_with(
        static function () use (
            $bothParent,
            $poultryLayer
        ): void {
            financial_allocation_service_validate_desired_rows(
                $bothParent,
                [
                    $poultryLayer,
                ],
                [
                    [
                        'cycle_id' =>
                            900001,

                        'allocated_amount' =>
                            '20.00',
                    ],
                    [
                        'cycle_id' =>
                            900001,

                        'allocated_amount' =>
                            '20.00',
                    ],
                ],
                0
            );
        },
        'same production cycle more than once'
    )
);

record_check(
    $checks,
    'ANIMAL_OVERLAP_REJECTED',
    throws_with(
        static function () use (
            $bothParent,
            $poultryLayer
        ): void {
            financial_allocation_service_validate_desired_rows(
                $bothParent,
                [
                    $poultryLayer,
                ],
                [
                    [
                        'cycle_id' =>
                            900001,

                        'allocated_amount' =>
                            '20.00',
                    ],
                ],
                1
            );
        },
        'allocated to individual animals cannot also be financially allocated'
    )
);

record_check(
    $checks,
    'CROSS_MODULE_CLOSED_CYCLE_ACCEPTED',
    is_array($bothContract)
    &&
    (function () use (
        $bothContract,
        $closedPoultryLayer
    ): bool {
        try {
            $result =
                financial_allocation_service_target_contract(
                    $bothContract,
                    $closedPoultryLayer
                );

            return
                ($result['status'] ?? '')
                === 'closed';

        } catch (Throwable $e) {
            return false;
        }
    })()
);

$empty = null;

try {
    $empty =
        financial_allocation_service_validate_desired_rows(
            $bothParent,
            [],
            [],
            0
        );

} catch (Throwable $e) {
    $empty = null;
}

record_check(
    $checks,
    'CROSS_MODULE_EMPTY_STATE_PRESERVES_REMAINDER',
    is_array($empty)
    &&
    ($empty['allocated_amount'] ?? '')
    === '0.00'
    &&
    ($empty['remaining_amount'] ?? '')
    === '100.00'
    &&
    count($empty['rows'] ?? []) === 0
);

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

echo 'RESULT='
    . ($failed ? 'FAIL' : 'PASS')
    . PHP_EOL;

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

foreach ($checks as $name => $passed) {
    echo $name
        . '='
        . ($passed ? 'PASS' : 'FAIL')
        . PHP_EOL;
}

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITES=NONE\n";

if ($failed) {
    echo 'FAILED='
        . implode(',', $failed)
        . PHP_EOL;

    exit(1);
}

exit(0);
