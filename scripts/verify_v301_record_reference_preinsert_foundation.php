<?php

$root =
    dirname(__DIR__);

$path =
    $root
    . '/lib/record_reference_persistence.php';

$source =
    is_file($path)
        ? file_get_contents(
            $path
        )
        : false;

$fail = 0;

$check =
    static function (
        string $name,
        bool $passed
    ) use (&$fail): void {
        echo $name
            . '='
            . (
                $passed
                    ? 'PASS'
                    : 'FAIL'
            )
            . PHP_EOL;

        if (!$passed) {
            $fail = 1;
        }
    };

$check(
    'REFERENCE_PERSISTENCE_READABLE',
    is_string($source)
);

if (!is_string($source)) {
    echo "DATABASE_REQUIRED=NO\n";
    echo "BUSINESS_ROW_MUTATION=NO\n";
    echo "TASK4C1_PREINSERT_FOUNDATION=FAIL\n";
    exit(1);
}

$check(
    'DATABASE_CREATED_AT_HELPER_PRESENT',
    strpos(
        $source,
        "function record_reference_persistence_database_created_at("
    ) !== false
);

$check(
    'PREINSERT_HELPER_PRESENT',
    strpos(
        $source,
        "function record_reference_persistence_insert_new("
    ) !== false
);

$check(
    'PREINSERT_HELPER_REQUIRES_TRANSACTION',
    preg_match(
        '/function\s+record_reference_persistence_insert_new\s*\([\s\S]*?record_reference_persistence_require_transaction\s*\(\s*\$pdo\s*\)/',
        $source
    ) === 1
);

$check(
    'DATABASE_CLOCK_IS_CANONICAL',
    strpos(
        $source,
        "'SELECT CURRENT_TIMESTAMP'"
    ) !== false
);

$check(
    'DATABASE_CREATED_AT_REQUIRES_TRANSACTION',
    preg_match(
        '/function\s+record_reference_persistence_database_created_at\s*\([\s\S]*?record_reference_persistence_require_transaction\s*\(\s*\$pdo\s*\)/',
        $source
    ) === 1
);

$check(
    'PREINSERT_USES_DATABASE_CREATED_AT',
    preg_match(
        '/function\s+record_reference_persistence_insert_new\s*\([\s\S]*?record_reference_persistence_database_created_at\s*\(\s*\$pdo\s*\)/',
        $source
    ) === 1
);

$check(
    'PREINSERT_USES_CENTRAL_COLLISION_RETRY',
    preg_match(
        '/function\s+record_reference_persistence_insert_new\s*\([\s\S]*?record_reference_persistence_with_retry\s*\(/',
        $source
    ) === 1
);

$check(
    'REFERENCE_DATE_USES_SAME_CREATED_AT',
    preg_match(
        '/record_reference_persistence_with_retry\s*\([\s\S]*?\$createdAt\s*\)\s*;/',
        $source
    ) === 1
);

$check(
    'INSERT_CALLBACK_RECEIVES_REFERENCE_AND_CREATED_AT',
    preg_match(
        '/\$operation\s*\(\s*\$reference\s*,\s*\$createdAt\s*\)/',
        $source
    ) === 1
);

$check(
    'PREINSERT_VALIDATES_NEW_PRIMARY_KEY',
    strpos(
        $source,
        '$insertedId < 1'
    ) !== false
);

$check(
    'LEGACY_ASSIGN_EXISTING_PRESERVED',
    strpos(
        $source,
        "function record_reference_persistence_assign_existing("
    ) !== false
);

$check(
    'BACKFILL_AUTHORITY_PRESERVED',
    strpos(
        $source,
        "function record_reference_persistence_backfill_batch("
    ) !== false
);

$check(
    'NO_BUSINESS_DATE_INPUT_IN_PREINSERT_HELPER',
    preg_match(
        '/function\s+record_reference_persistence_insert_new\s*\([^)]*(transaction_date|expense_date|sale_date)/i',
        $source
    ) !== 1
);

echo "DATABASE_REQUIRED=NO\n";
echo "DATABASE_WRITE=NO\n";
echo "BUSINESS_ROW_MUTATION=NO\n";
echo "SCHEMA_MUTATION=NO\n";
echo "WRITER_CUTOVER=NO\n";

echo 'TASK4C1_PREINSERT_FOUNDATION='
    . (
        $fail === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit($fail);
