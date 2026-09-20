<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$paths = [
    'layer' =>
        $root
        . '/poultry/layer_expenses.php',

    'broiler' =>
        $root
        . '/poultry/broiler_expenses.php',

    'service' =>
        $root
        . '/lib/poultry_expense_entry.php',
];

$src = [];

foreach ($paths as $key => $path) {
    $src[$key] =
        is_file($path)
            ? (string)file_get_contents($path)
            : '';
}

$checks = 0;
$failed = 0;

function poultry_expense_cutover_check(
    string $name,
    bool $ok
): void {
    global $checks, $failed;

    $checks++;

    echo
        $name
        . '='
        . (
            $ok
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$ok) {
        $failed++;
    }
}


foreach (
    [
        'layer',
        'broiler',
    ]
    as $key
) {
    $upper =
        strtoupper(
            $key
        );

    poultry_expense_cutover_check(
        $upper
        . '_LOADS_CANONICAL_SERVICE',
        strpos(
            $src[$key],
            'poultry_expense_entry.php'
        ) !== false
    );

    poultry_expense_cutover_check(
        $upper
        . '_DELEGATES_CREATE_ONCE',
        substr_count(
            $src[$key],
            'poultry_expense_entry_create('
        ) === 1
    );

    poultry_expense_cutover_check(
        $upper
        . '_OWNS_NO_DIRECT_EXPENSE_INSERT',
        strpos(
            $src[$key],
            'INSERT INTO farm_expenses'
        ) === false
    );

    poultry_expense_cutover_check(
        $upper
        . '_OWNS_NO_DIRECT_REFERENCE_OR_CREATE_REVISION',
        strpos(
            $src[$key],
            'record_reference_persistence_assign_existing('
        ) === false
        &&
        strpos(
            $src[$key],
            'expense_revision_service_record_created('
        ) === false
        &&
        strpos(
            $src[$key],
            '$pdo->lastInsertId()'
        ) === false
    );

    poultry_expense_cutover_check(
        $upper
        . '_RETAINS_OUTER_TRANSACTION',
        strpos(
            $src[$key],
            '$pdo->beginTransaction();'
        ) !== false
        &&
        strpos(
            $src[$key],
            '$pdo->commit();'
        ) !== false
        &&
        strpos(
            $src[$key],
            '$pdo->rollBack();'
        ) !== false
    );

    poultry_expense_cutover_check(
        $upper
        . '_USES_CANONICAL_ADD_PERMISSION',
        strpos(
            $src[$key],
            '$canAddExpenses = poultry_expense_entry_can('
        ) !== false
        &&
        substr_count(
            $src[$key],
            'if ($canAddExpenses)'
        ) >= 2
    );
}


poultry_expense_cutover_check(
    'LAYER_ROUTE_FIXES_PRODUCTION_TYPE',
    strpos(
        $src['layer'],
        "'production_type' =>\n                    'layer'"
    ) !== false
);


poultry_expense_cutover_check(
    'BROILER_ROUTE_FIXES_PRODUCTION_TYPE',
    strpos(
        $src['broiler'],
        "'production_type' =>\n                    'broiler'"
    ) !== false
);


poultry_expense_cutover_check(
    'LAYER_EXISTING_READ_FILTER_PRESERVED',
    strpos(
        $src['layer'],
        "AND e.poultry_category = 'layer'"
    ) !== false
);


poultry_expense_cutover_check(
    'BROILER_EXISTING_READ_FILTER_PRESERVED',
    strpos(
        $src['broiler'],
        "AND e.poultry_category = 'broiler'"
    ) !== false
);



poultry_expense_cutover_check(
    'CANONICAL_SERVICE_PRESERVES_SAFE_CYCLE_VALIDATION',
    preg_match(
        '/attribution_validate_cycle\s*\([\s\S]*?catch\s*\(\s*PDOException\s+\$e\s*\)[\s\S]*?throw\s+\$e\s*;[\s\S]*?catch\s*\(\s*RuntimeException\s+\$e\s*\)[\s\S]*?throw\s+new\s+InvalidArgumentException\s*\(/',
        $src['service']
    ) === 1
);


poultry_expense_cutover_check(
    'CANONICAL_SERVICE_IS_SINGLE_POULTRY_WRITER',
    substr_count(
        $src['service'],
        'INSERT INTO farm_expenses'
    ) === 1
    &&
    strpos(
        $src['service'],
        'record_reference_persistence_assign_existing('
    ) !== false
    &&
    strpos(
        $src['service'],
        'expense_revision_service_record_created('
    ) !== false
);


poultry_expense_cutover_check(
    'OLD_PAGES_KEEP_NO_SHARED_ENTRY_YET',
    strpos(
        $src['layer'],
        "value=\"shared\""
    ) === false
    &&
    strpos(
        $src['broiler'],
        "value=\"shared\""
    ) === false
);


echo
    'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . $failed
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
