<?php
/**
 * Renee Farms V3.0.1
 * Human-facing record-reference business-writer wiring verifier.
 *
 * Source-only:
 * - no PDO connection;
 * - no database writes;
 * - no business records created.
 */

$root =
    dirname(__DIR__);

$paths = [
    'stock' =>
        $root
        . '/lib/stock_service.php',

    'layer' =>
        $root
        . '/poultry/layer_expenses.php',

    'broiler' =>
        $root
        . '/poultry/broiler_expenses.php',

    'ruminant' =>
        $root
        . '/ruminant/ruminant_expenses.php',

    'sales' =>
        $root
        . '/management/sales_records.php',
];

$source = [];

foreach ($paths as $key => $path) {
    $source[$key] =
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
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo
            ($ok ? '[PASS] ' : '[FAIL] ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };

$check(
    $source['stock'] !== '',
    'Canonical stock service exists'
);

$check(
    strpos(
        $source['stock'],
        "require_once __DIR__ . '/record_reference_persistence.php';"
    ) !== false,
    'Stock service loads canonical reference persistence'
);

$check(
    preg_match(
        '/function\s+stock_apply_movement\s*\([\s\S]*?\)\s*:\s*int\s*\{\s*(?:\/\*[\s\S]*?\*\/\s*)?record_reference_persistence_require_transaction\s*\(\s*\$pdo\s*\)/',
        $source['stock']
    ) === 1,
    'Stock creation fails before mutation when transaction is absent'
);

$check(
    preg_match(
        '/function\s+stock_reverse_transaction\s*\([\s\S]*?\)\s*:\s*int\s*\{\s*(?:\/\*[\s\S]*?\*\/\s*)?record_reference_persistence_require_transaction\s*\(\s*\$pdo\s*\)/',
        $source['stock']
    ) === 1,
    'Stock reversal fails before locking or mutation when transaction is absent'
);

$check(
    substr_count(
        $source['stock'],
        'record_reference_persistence_assign_existing('
    ) === 2,
    'Both stock insert paths assign references centrally'
);

$check(
    preg_match(
        '/\$transactionId\s*=\s*\(int\)\$pdo->lastInsertId\(\);[\s\S]*?record_reference_persistence_assign_existing\s*\(\s*\$pdo\s*,\s*\'stock_movement\'\s*,\s*\$farmId\s*,\s*\$transactionId\s*\)/',
        $source['stock']
    ) === 1,
    'Normal stock movement receives reference immediately after insert'
);

$check(
    preg_match(
        '/\$reversalId\s*=\s*\(int\)\$pdo->lastInsertId\(\);[\s\S]*?record_reference_persistence_assign_existing\s*\(\s*\$pdo\s*,\s*\'stock_movement\'\s*,\s*\$farmId\s*,\s*\$reversalId\s*\)/',
        $source['stock']
    ) === 1,
    'Stock reversal receives its own human-facing reference'
);

foreach (
    [
        'layer' =>
            'Layer expense',

        'broiler' =>
            'Broiler expense',

        'ruminant' =>
            'Ruminant expense',
    ]
    as $key => $label
) {
    $check(
        strpos(
            $source[$key],
            "record_reference_persistence.php"
        ) !== false,
        $label
            . ' writer loads canonical persistence'
    );

    $check(
        substr_count(
            $source[$key],
            'record_reference_persistence_assign_existing('
        ) === 1
        &&
        preg_match(
            '/record_reference_persistence_assign_existing\s*\(\s*\$pdo\s*,\s*\'expense\'\s*,\s*\$tenantFarmId\s*,\s*\$expenseId\s*\)/',
            $source[$key]
        ) === 1,
        $label
            . ' assigns exactly one expense reference'
    );

    $lastInsert =
        strpos(
            $source[$key],
            '$pdo->lastInsertId()'
        );

    $assignment =
        strpos(
            $source[$key],
            'record_reference_persistence_assign_existing('
        );

    $commit =
        strpos(
            $source[$key],
            '$pdo->commit()'
        );

    $check(
        $lastInsert !== false
        &&
        $assignment !== false
        &&
        $commit !== false
        &&
        $lastInsert < $assignment
        &&
        $assignment < $commit,
        $label
            . ' reference assignment is inside creation transaction'
    );
}

$check(
    strpos(
        $source['sales'],
        "record_reference_persistence.php"
    ) !== false,
    'Sales writer loads canonical reference persistence'
);

$check(
    substr_count(
        $source['sales'],
        'record_reference_persistence_assign_existing('
    ) === 1
    &&
    preg_match(
        '/record_reference_persistence_assign_existing\s*\(\s*\$pdo\s*,\s*\'sale\'\s*,\s*\$tenantFarmId\s*,\s*\$saleId\s*\)/',
        $source['sales']
    ) === 1,
    'Sale creation assigns exactly one sale reference'
);

$saleInsert =
    strpos(
        $source['sales'],
        '$saleId = (int)$pdo->lastInsertId();'
    );

$saleAssignment =
    strpos(
        $source['sales'],
        'record_reference_persistence_assign_existing('
    );

$saleCommit =
    strpos(
        $source['sales'],
        '$pdo->commit()'
    );

$check(
    $saleInsert !== false
    &&
    $saleAssignment !== false
    &&
    $saleCommit !== false
    &&
    $saleInsert < $saleAssignment
    &&
    $saleAssignment < $saleCommit,
    'Sale reference assignment is inside creation transaction'
);

$allWriters =
    implode(
        "\n",
        $source
    );

$check(
    substr_count(
        $source['stock'],
        'INSERT INTO stock_transactions'
    ) === 2,
    'Stock direct writer count remains exactly two'
);

$expenseInsertCount = 0;

foreach (
    [
        'layer',
        'broiler',
        'ruminant',
    ]
    as $key
) {
    $expenseInsertCount +=
        substr_count(
            $source[$key],
            'INSERT INTO farm_expenses'
        );
}

$check(
    $expenseInsertCount === 3,
    'Expense direct writer count remains exactly three'
);

$check(
    substr_count(
        $source['sales'],
        'INSERT INTO sales_records'
    ) === 1,
    'Sales direct writer count remains exactly one'
);

$check(
    substr_count(
        $allWriters,
        'record_reference_persistence_assign_existing('
    ) === 6,
    'Exactly six business insert paths receive reference assignment'
);

$check(
    strpos(
        $allWriters,
        'record_reference_generate('
    ) === false,
    'Business writers never generate references themselves'
);

$check(
    preg_match(
        '/SET\s+public_reference\s*=/i',
        $allWriters
    ) !== 1,
    'Business writers own no duplicated public-reference UPDATE SQL'
);

$check(
    preg_match(
        '/record_reference_persistence_assign_existing\s*\([^)]*(transaction_date|expense_date|sale_date)/i',
        $allWriters
    ) !== 1,
    'Business dates are never supplied to reference assignment'
);

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
