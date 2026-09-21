<?php

$root = dirname(__DIR__);

$paths = [
    'stock' => $root . '/lib/stock_service.php',
    'poultry_hub' => $root . '/poultry/expenses.php',
    'layer_compat' => $root . '/poultry/layer_expenses.php',
    'broiler_compat' => $root . '/poultry/broiler_expenses.php',
    'poultry' => $root . '/lib/poultry_expense_entry.php',
    'ruminant' => $root . '/ruminant/ruminant_expenses.php',
    'sales' => $root . '/management/sales_records.php',
];

$src = [];

foreach ($paths as $key => $path) {
    $src[$key] =
        is_file($path)
            ? (string)file_get_contents($path)
            : '';
}

$checks = 0;
$failures = 0;

$check =
    static function (
        bool $ok,
        string $label
    ) use (&$checks, &$failures): void {
        $checks++;

        echo
            ($ok ? '[PASS] ' : '[FAIL] ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };

$insertColumns =
    static function (
        string $text,
        string $table
    ): array {
        $matches = [];

        preg_match_all(
            '/INSERT\s+INTO\s+'
            . preg_quote($table, '/')
            . '\s*\(([\s\S]*?)\)\s*VALUES\s*\(/i',
            $text,
            $matches
        );

        return $matches[1] ?? [];
    };

foreach ($src as $key => $text) {
    $check(
        $text !== '',
        'Source readable: ' . $key
    );
}

$writers = [
    'stock' => [
        'table' => 'stock_transactions',
        'entity' => 'stock_movement',
        'count' => 2,
    ],
    'poultry' => [
        'table' => 'farm_expenses',
        'entity' => 'expense',
        'count' => 1,
    ],
    'ruminant' => [
        'table' => 'farm_expenses',
        'entity' => 'expense',
        'count' => 1,
    ],
    'sales' => [
        'table' => 'sales_records',
        'entity' => 'sale',
        'count' => 1,
    ],
];

$totalInsertAuthorities = 0;
$totalPreinsertCalls = 0;

foreach ($writers as $key => $contract) {
    $text = $src[$key];

    $columns =
        $insertColumns(
            $text,
            $contract['table']
        );

    $totalInsertAuthorities += count($columns);

    $check(
        count($columns) === $contract['count'],
        $key . ' direct insert count'
    );

    foreach ($columns as $index => $list) {
        $check(
            preg_match(
                '/\bpublic_reference\b/i',
                $list
            ) === 1,
            $key
                . ' insert '
                . ($index + 1)
                . ' includes public_reference'
        );

        $check(
            preg_match(
                '/\bcreated_at\b/i',
                $list
            ) === 1,
            $key
                . ' insert '
                . ($index + 1)
                . ' includes created_at'
        );
    }

    $helperCount =
        substr_count(
            $text,
            'record_reference_persistence_insert_new('
        );

    $totalPreinsertCalls += $helperCount;

    $check(
        $helperCount === $contract['count'],
        $key . ' uses central pre-insert authority'
    );

    $entityPattern =
        '/record_reference_persistence_insert_new'
        . '\s*\(\s*\$pdo\s*,\s*\''
        . preg_quote(
            $contract['entity'],
            '/'
        )
        . '\'/';

    $check(
        preg_match_all(
            $entityPattern,
            $text
        ) === $contract['count'],
        $key . ' uses correct reference entity'
    );

    $check(
        strpos(
            $text,
            'record_reference_persistence_assign_existing('
        ) === false,
        $key . ' has no post-insert assignment'
    );
}

$check(
    $totalInsertAuthorities === 5,
    'Exactly five production insert authorities remain'
);

$check(
    $totalPreinsertCalls === 5,
    'Exactly five central pre-insert calls remain'
);

$businessWriters =
    $src['stock']
    . "\n"
    . $src['poultry']
    . "\n"
    . $src['ruminant']
    . "\n"
    . $src['sales'];

$check(
    substr_count(
        $businessWriters,
        'string $publicReference'
    ) === 5,
    'All five callbacks receive public reference'
);

$check(
    substr_count(
        $businessWriters,
        'string $createdAt'
    ) === 5,
    'All five callbacks receive canonical created_at'
);

$check(
    strpos(
        $businessWriters,
        'record_reference_generate('
    ) === false,
    'Business writers never generate references locally'
);

$check(
    preg_match(
        '/SET\s+public_reference\s*=/i',
        $businessWriters
    ) !== 1,
    'Business writers own no reference UPDATE SQL'
);

$check(
    substr_count(
        $src['poultry_hub'],
        'poultry_expense_entry_create('
    ) === 1
    &&
    strpos(
        $src['poultry_hub'],
        'record_reference_persistence_insert_new('
    ) === false,
    'Poultry hub delegates creation only'
);

foreach (
    [
        'layer_compat',
        'broiler_compat',
    ]
    as $key
) {
    $check(
        strpos(
            $src[$key],
            'record_reference_persistence_insert_new('
        ) === false,
        $key
            . ' owns no reference creation'
    );
}

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
