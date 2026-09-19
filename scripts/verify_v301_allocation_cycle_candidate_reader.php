<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$paths = [
    'cycle_service' =>
        $root
        . '/lib/production_cycle_service.php',

    'financial_workspace' =>
        $root
        . '/lib/financial_allocation_workspace.php',

    'stock_workspace' =>
        $root
        . '/lib/stock_consumption_allocation_workspace.php',

    'revenue_workspace' =>
        $root
        . '/lib/sale_revenue_allocation_workspace.php',
];

$src = [];

foreach ($paths as $key => $path) {
    $src[$key] =
        is_file($path)
            ? file_get_contents($path)
            : false;
}

$checks = [];

$check =
    static function (
        string $name,
        bool $ok
    ) use (&$checks): void {
        $checks[$name] = $ok;
    };

$has =
    static function (
        $source,
        string $needle
    ): bool {
        return
            is_string($source)
            &&
            strpos(
                $source,
                $needle
            ) !== false;
    };

$count =
    static function (
        $source,
        string $needle
    ): int {
        return
            is_string($source)
                ? substr_count(
                    $source,
                    $needle
                )
                : 0;
    };


foreach ($src as $key => $source) {
    $check(
        'FILE_'
        . strtoupper($key)
        . '_PRESENT',
        is_string($source)
    );
}


/*
 * Canonical reader contract.
 */
$check(
    'SHARED_READER_EXISTS',
    $has(
        $src['cycle_service'],
        'function production_cycle_list_for_farm'
    )
);

$check(
    'SHARED_READER_REUSES_CANONICAL_FARM_VALIDATION',
    preg_match(
        '/function\s+production_cycle_list_for_farm[\s\S]*?production_cycle_assert_farm_id\s*\(/',
        (string)$src['cycle_service']
    ) === 1
);

$check(
    'SHARED_READER_OWNS_CYCLE_SELECT',
    preg_match(
        '/function\s+production_cycle_list_for_farm[\s\S]*?FROM\s+production_cycles[\s\S]*?WHERE\s+farm_id\s*=\s*\?/i',
        (string)$src['cycle_service']
    ) === 1
);

$check(
    'SHARED_READER_ORDERS_NEWEST_FIRST',
    preg_match(
        '/function\s+production_cycle_list_for_farm[\s\S]*?ORDER\s+BY\s+start_date\s+DESC\s*,\s*id\s+DESC/i',
        (string)$src['cycle_service']
    ) === 1
);

$readerStart =
    strpos(
        (string)$src['cycle_service'],
        'function production_cycle_list_for_farm'
    );

$readerEnd =
    $readerStart === false
        ? false
        : strpos(
            (string)$src['cycle_service'],
            "if (!function_exists('production_cycle_display_type'))",
            $readerStart
        );

$readerBody =
    (
        $readerStart !== false
        &&
        $readerEnd !== false
    )
        ? substr(
            (string)$src['cycle_service'],
            $readerStart,
            $readerEnd - $readerStart
        )
        : '';

$check(
    'SHARED_READER_IS_READ_ONLY',
    stripos(
        $readerBody,
        'INSERT '
    ) === false
    &&
    stripos(
        $readerBody,
        'UPDATE '
    ) === false
    &&
    stripos(
        $readerBody,
        'DELETE '
    ) === false
);


/*
 * All allocation workspaces must consume the same cycle reader.
 */
foreach (
    [
        'financial_workspace',
        'stock_workspace',
        'revenue_workspace',
    ]
    as $key
) {
    $prefix =
        strtoupper(
            $key
        );

    $check(
        $prefix
        . '_REQUIRES_CYCLE_SERVICE',
        $has(
            $src[$key],
            'production_cycle_service.php'
        )
    );

    $check(
        $prefix
        . '_CALLS_SHARED_READER_ONCE',
        $count(
            $src[$key],
            'production_cycle_list_for_farm('
        ) === 1
    );

    $check(
        $prefix
        . '_NO_DIRECT_CYCLE_SQL',
        $count(
            $src[$key],
            'FROM production_cycles'
        ) === 0
    );
}


/*
 * Compatibility policy remains module-specific and central.
 */
$check(
    'FINANCIAL_TARGET_POLICY_REMAINS_CENTRAL',
    $has(
        $src['financial_workspace'],
        'financial_allocation_service_target_contract'
    )
);

$check(
    'STOCK_TARGET_POLICY_REMAINS_CENTRAL',
    $has(
        $src['stock_workspace'],
        'stock_consumption_allocation_service_target_contract'
    )
);

$check(
    'REVENUE_TARGET_POLICY_REMAINS_CENTRAL',
    $has(
        $src['revenue_workspace'],
        'sale_revenue_allocation_service_target_contract'
    )
);


$failed = [];

foreach ($checks as $name => $ok) {
    echo
        $name
        . '='
        . ($ok ? 'PASS' : 'FAIL')
        . PHP_EOL;

    if (!$ok) {
        $failed[] =
            $name;
    }
}

echo
    'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . count($failed)
    . PHP_EOL;

echo
    'RESULT='
    . ($failed ? 'FAIL' : 'PASS')
    . PHP_EOL;

exit(
    $failed
        ? 1
        : 0
);
