<?php

$root = dirname(__DIR__);

$migrationPath =
    $root
    . '/migrations/068_human_facing_record_reference_not_null.sql';

$sql =
    is_file($migrationPath)
        ? (string)file_get_contents($migrationPath)
        : '';

$fail = 0;
$checks = 0;

$check =
    static function (
        string $name,
        bool $ok
    ) use (&$fail, &$checks): void {
        $checks++;

        echo
            $name
            . '='
            . ($ok ? 'PASS' : 'FAIL')
            . PHP_EOL;

        if (!$ok) {
            $fail = 1;
        }
    };

$check(
    'MIGRATION_READABLE',
    $sql !== ''
);

if ($sql === '') {
    echo "DATABASE_REQUIRED=NO\n";
    echo "DATABASE_WRITE=NO\n";
    echo "TASK4C3B_NOT_NULL_MIGRATION=FAIL\n";
    exit(1);
}

$check(
    'MIGRATION_066_PREREQUISITE',
    strpos(
        $sql,
        "filename = '066_human_facing_record_references.sql'"
    ) !== false
);

$check(
    'MIGRATION_068_DUPLICATE_GUARD',
    strpos(
        $sql,
        "filename = '068_human_facing_record_reference_not_null.sql'"
    ) !== false
);

$check(
    'THREE_COLUMN_CONTRACT',
    strpos(
        $sql,
        "SELECT COUNT(*) = 3"
    ) !== false
    &&
    strpos(
        $sql,
        "is_nullable IN ('YES', 'NO')"
    ) !== false
    &&
    strpos(
        $sql,
        "character_maximum_length = 32"
    ) !== false
    &&
    strpos(
        $sql,
        "character_set_name = 'ascii'"
    ) !== false
    &&
    strpos(
        $sql,
        "collation_name = 'ascii_bin'"
    ) !== false
);

foreach (
    [
        'stock' => 'stock_transactions',
        'expense' => 'farm_expenses',
        'sale' => 'sales_records',
    ]
    as $name => $table
) {
    $check(
        strtoupper($name)
        . '_NULLABLE_STATE_CAPTURED',
        strpos(
            $sql,
            '@rr_'
            . $name
            . "_nullable = ("
        ) !== false
        &&
        strpos(
            $sql,
            "table_name = '"
            . $table
            . "'"
        ) !== false
    );
}

$check(
    'THREE_UNIQUE_INDEX_CONTRACTS',
    strpos(
        $sql,
        'uniq_stock_transaction_public_reference'
    ) !== false
    &&
    strpos(
        $sql,
        'uniq_farm_expense_public_reference'
    ) !== false
    &&
    strpos(
        $sql,
        'uniq_sale_public_reference'
    ) !== false
);

foreach (
    [
        'stock' => '^RA-SM-',
        'expense' => '^RA-EX-',
        'sale' => '^RA-SA-',
    ]
    as $name => $prefix
) {
    $check(
        strtoupper($name)
        . '_DIRTY_DATA_GUARD',
        strpos(
            $sql,
            '@rr_'
            . $name
            . '_dirty = ('
        ) !== false
        &&
        strpos(
            $sql,
            $prefix
        ) !== false
    );

    $check(
        strtoupper($name)
        . '_DUPLICATE_GUARD',
        strpos(
            $sql,
            '@rr_'
            . $name
            . '_duplicates = ('
        ) !== false
    );
}

$requiredReadyChecks = [
    '@rr_066_present = 1',
    '@rr_068_absent = 1',
    '@rr_column_contract = 1',
    '@rr_unique_contract = 1',
    '@rr_stock_dirty = 0',
    '@rr_expense_dirty = 0',
    '@rr_sale_dirty = 0',
    '@rr_stock_duplicates = 0',
    '@rr_expense_duplicates = 0',
    '@rr_sale_duplicates = 0',
];

$readyComplete = true;

foreach ($requiredReadyChecks as $needle) {
    if (strpos($sql, $needle) === false) {
        $readyComplete = false;
        break;
    }
}

$check(
    'READY_GATE_CONTAINS_ALL_PRECONDITIONS',
    $readyComplete
);

$check(
    'THREE_NOT_NULL_ALTERS',
    substr_count(
        $sql,
        "'ALTER TABLE"
    ) === 3
    &&
    substr_count(
        $sql,
        'NOT NULL AFTER id'
    ) === 3
);

foreach (
    [
        'stock',
        'expense',
        'sale',
    ]
    as $name
) {
    $check(
        strtoupper($name)
        . '_ALTER_IS_RESUME_SAFE',
        strpos(
            $sql,
            '@rr_ready = 1'
            . PHP_EOL
            . '    AND @rr_'
            . $name
            . '_nullable = 1'
        ) !== false
    );
}

$check(
    'ALREADY_HARDENED_COLUMN_USES_NOOP',
    substr_count(
        $sql,
        "'SELECT 1'"
    ) === 3
);

$check(
    'POSTCONDITION_REQUIRES_THREE_NOT_NULL_COLUMNS',
    strpos(
        $sql,
        'SET @rr_post_contract = ('
    ) !== false
    &&
    strpos(
        $sql,
        "AND is_nullable = 'NO'"
    ) !== false
    &&
    strpos(
        $sql,
        'SELECT COUNT(*) = 3'
    ) !== false
);

$recordGate =
    strpos(
        $sql,
        'SET @rr_record_sql = IF('
    );

$recordReady =
    strpos(
        $sql,
        '@rr_ready = 1',
        $recordGate === false
            ? 0
            : $recordGate
    );

$recordPost =
    strpos(
        $sql,
        '@rr_post_contract = 1',
        $recordGate === false
            ? 0
            : $recordGate
    );

$recordInsert =
    strpos(
        $sql,
        'INSERT INTO schema_migrations (filename)',
        $recordGate === false
            ? 0
            : $recordGate
    );

$recordAbort =
    strpos(
        $sql,
        '__renee_record_reference_not_null_postcondition_failed__',
        $recordGate === false
            ? 0
            : $recordGate
    );

$check(
    'MIGRATION_RECORD_REQUIRES_READY_AND_POSTCONDITION',
    $recordGate !== false
    &&
    $recordReady !== false
    &&
    $recordPost !== false
    &&
    $recordInsert !== false
    &&
    $recordAbort !== false
    &&
    $recordGate < $recordReady
    &&
    $recordReady < $recordPost
    &&
    $recordPost < $recordInsert
);

$check(
    'DIRTY_PRECONDITION_CANNOT_RECORD_MIGRATION',
    strpos(
        $sql,
        '@rr_ready = IF('
    ) !== false
    &&
    $recordReady !== false
    &&
    $recordPost !== false
    &&
    $recordAbort !== false
);

$check(
    'NO_BUSINESS_ROW_BACKFILL',
    preg_match(
        '/\bUPDATE\s+(stock_transactions|farm_expenses|sales_records)\b/i',
        $sql
    ) !== 1
    &&
    preg_match(
        '/\bINSERT\s+INTO\s+(stock_transactions|farm_expenses|sales_records)\b/i',
        $sql
    ) !== 1
    &&
    preg_match(
        '/\bDELETE\s+FROM\s+(stock_transactions|farm_expenses|sales_records)\b/i',
        $sql
    ) !== 1
);

$check(
    'NO_DESTRUCTIVE_TABLE_OPERATION',
    preg_match(
        '/\b(?:DROP|TRUNCATE)\s+TABLE\b/i',
        $sql
    ) !== 1
);

echo "DATABASE_REQUIRED=NO\n";
echo "DATABASE_WRITE=NO\n";
echo "SCHEMA_MUTATION=NO\n";

echo
    'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'TASK4C3B_NOT_NULL_MIGRATION='
    . (
        $fail === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit($fail);
