<?php

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/lib/stock_consumption_source_resolver.php';

if (!is_file($servicePath)) {
    echo "RESULT=FAIL\n";
    echo "FAIL=MISSING_SOURCE_RESOLVER\n";
    exit(1);
}

require_once $servicePath;

$failures = [];
$checks = 0;

$check =
    static function (
        string $name,
        bool $condition
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        if (!$condition) {
            $failures[] =
                $name;
        }
    };

$reject =
    static function (
        string $name,
        callable $callback
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        try {
            $callback();

            $failures[] =
                $name
                . ':EXPECTED_REJECTION';

        } catch (Throwable $e) {
            // Rejection itself is the contract.
        }
    };

foreach (
    [
        'inventory_manual',
        'inventory_api',
        'manual_feed',
    ]
    as $sourceType
) {
    $definition =
        stock_consumption_source_resolver_definition(
            $sourceType
        );

    $check(
        'MOVEMENT_AUTHORITY_'
        . strtoupper($sourceType),
        $definition['supported']
        &&
        $definition['allocatable']
        &&
        $definition['mode']
            === 'movement_authority'
    );
}

$daily = [
    'daily_layer_record' => [
        'table' =>
            'layer_daily_records',

        'farm_type' =>
            'poultry',

        'production_type' =>
            'layer',
    ],

    'daily_broiler_record' => [
        'table' =>
            'broiler_daily_records',

        'farm_type' =>
            'poultry',

        'production_type' =>
            'broiler',
    ],

    'daily_ruminant_record' => [
        'table' =>
            'ruminant_daily_records',

        'farm_type' =>
            'ruminant',

        'production_type' =>
            null,
    ],
];

foreach ($daily as $sourceType => $expected) {
    $definition =
        stock_consumption_source_resolver_definition(
            $sourceType
        );

    $check(
        'DAILY_MAP_'
        . strtoupper($sourceType),
        $definition['supported']
        &&
        $definition['allocatable']
        &&
        $definition['mode']
            === 'linked_daily_record'
        &&
        $definition['table']
            === $expected['table']
        &&
        $definition[
            'expected_farm_type'
        ] === $expected['farm_type']
        &&
        $definition[
            'expected_production_type'
        ] === $expected[
            'production_type'
        ]
    );
}

$reversal =
    stock_consumption_source_resolver_definition(
        'daily_layer_record_reversal'
    );

$check(
    'REVERSAL_NOT_ALLOCATABLE',
    $reversal['supported']
    &&
    !$reversal['allocatable']
    &&
    $reversal['mode']
        === 'reversal'
);

$unknown =
    stock_consumption_source_resolver_definition(
        'mystery_source'
    );

$check(
    'UNKNOWN_FAILS_CLOSED',
    !$unknown['supported']
    &&
    !$unknown['allocatable']
);

$reject(
    'DAILY_SOURCE_ID_REQUIRED',
    static function (): void {
        stock_consumption_source_resolver_assert_allocatable([
            'source_type' =>
                'daily_layer_record',

            'source_id' =>
                null,
        ]);
    }
);

$passMovement = [
    'source_type' =>
        'inventory_manual',

    'source_id' =>
        500,
];

$definition =
    stock_consumption_source_resolver_assert_allocatable(
        $passMovement
    );

$check(
    'MANUAL_MOVEMENT_ACCEPTED',
    $definition['mode']
        === 'movement_authority'
);

$reject(
    'REVERSAL_SOURCE_REJECTED',
    static function (): void {
        stock_consumption_source_resolver_assert_allocatable([
            'source_type' =>
                'manual_feed_reversal',

            'source_id' =>
                10,
        ]);
    }
);

$reject(
    'UNKNOWN_SOURCE_REJECTED',
    static function (): void {
        stock_consumption_source_resolver_assert_allocatable([
            'source_type' =>
                'legacy_unknown',

            'source_id' =>
                10,
        ]);
    }
);

$linkedResolution = [
    'mode' =>
        'linked_daily_record',

    'authoritative_source' => [
        'cycle_id' =>
            41,
    ],
];

$check(
    'MATCHING_DAILY_CYCLE_ACCEPTED',
    (
        static function () use (
            $linkedResolution
        ): bool {
            try {
                stock_consumption_source_resolver_assert_cycle_consistency(
                    [
                        'cycle_id' =>
                            41,
                    ],
                    $linkedResolution
                );

                return true;

            } catch (Throwable $e) {
                return false;
            }
        }
    )()
);

$reject(
    'DAILY_CYCLE_DRIFT_REJECTED',
    static function () use (
        $linkedResolution
    ): void {
        stock_consumption_source_resolver_assert_cycle_consistency(
            [
                'cycle_id' =>
                    null,
            ],
            $linkedResolution
        );
    }
);

/*
 * Prove the runtime callers still use the exact source identities
 * this resolver owns.
 */
$callerChecks = [
    $root
        . '/poultry/layers_daily_record.php' => [
            "sync_daily_feed_usage",
            "'daily_layer_record'",
        ],

    $root
        . '/poultry/broiler_daily_record.php' => [
            "sync_daily_feed_usage",
            "'daily_broiler_record'",
        ],

    $root
        . '/ruminant/ruminant_daily_record.php' => [
            "sync_daily_feed_usage",
            "'daily_ruminant_record'",
        ],
];

foreach ($callerChecks as $path => $needles) {
    $text =
        is_file($path)
            ? file_get_contents(
                $path
            )
            : false;

    $ok =
        is_string($text);

    if ($ok) {
        foreach ($needles as $needle) {
            if (
                strpos(
                    $text,
                    $needle
                ) === false
            ) {
                $ok = false;
                break;
            }
        }
    }

    $check(
        'RUNTIME_CALLER_'
        . strtoupper(
            basename(
                $path,
                '.php'
            )
        ),
        $ok
    );
}

$dailyFeedPath =
    $root
    . '/lib/daily_feed_sync.php';

$dailyFeedText =
    is_file($dailyFeedPath)
        ? file_get_contents(
            $dailyFeedPath
        )
        : false;

$check(
    'DAILY_FEED_SOURCE_ID_WIRING',
    is_string($dailyFeedText)
    &&
    strpos(
        $dailyFeedText,
        '$sourceType'
    ) !== false
    &&
    strpos(
        $dailyFeedText,
        '$recordId'
    ) !== false
    &&
    strpos(
        $dailyFeedText,
        'stock_apply_movement'
    ) !== false
);

$serviceText =
    file_get_contents(
        $servicePath
    );

$writerFound =
    !is_string($serviceText)
    ||
    preg_match(
        '/\b(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+[a-z_][a-z0-9_]*)\b/i',
        (string)$serviceText
    ) === 1;

$check(
    'SOURCE_RESOLVER_READ_ONLY',
    !$writerFound
);

$result =
    $failures
        ? 'FAIL'
        : 'PASS';

echo "RESULT={$result}\n";
echo "CHECK_COUNT={$checks}\n";

echo "MOVEMENT_AUTHORITY_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "DAILY_SOURCE_MAP_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "REVERSAL_SOURCE_POLICY="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "SOURCE_DRIFT_GUARD="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "RUNTIME_CALLER_WIRING="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "DATABASE_WRITES="
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
