<?php

$root =
    dirname(__DIR__);

$path =
    $root
    . '/migrations/065_stock_consumption_allocation_provenance.sql';

if (!is_file($path)) {
    echo "RESULT=FAIL\n";
    echo "FAIL=MISSING_MIGRATION\n";
    exit(1);
}

$sql =
    file_get_contents($path);

if (!is_string($sql)) {
    echo "RESULT=FAIL\n";
    echo "FAIL=READ_FAILED\n";
    exit(1);
}

$failures = [];
$checks = 0;

$check =
    static function (
        string $name,
        bool $condition
    ) use (
        &$failures,
        &$checks
    ): void {
        $checks++;

        if (!$condition) {
            $failures[] =
                $name;
        }
    };

$check(
    'CURRENT_PROJECTION_TABLE',
    strpos(
        $sql,
        'CREATE TABLE IF NOT EXISTS stock_consumption_allocations'
    ) !== false
);

$check(
    'REVISION_HEADER_TABLE',
    strpos(
        $sql,
        'CREATE TABLE IF NOT EXISTS stock_consumption_allocation_revisions'
    ) !== false
);

$check(
    'REVISION_ROW_TABLE',
    strpos(
        $sql,
        'CREATE TABLE IF NOT EXISTS stock_consumption_allocation_revision_rows'
    ) !== false
);

$check(
    'CURRENT_SOURCE_CYCLE_UNIQUE',
    preg_match(
        '/UNIQUE\s+KEY\s+uniq_stock_consumption_allocation\s*\(\s*farm_id\s*,\s*stock_transaction_id\s*,\s*cycle_id\s*\)/is',
        $sql
    ) === 1
);

$check(
    'REVISION_SOURCE_NUMBER_UNIQUE',
    preg_match(
        '/UNIQUE\s+KEY\s+uniq_stock_consumption_allocation_revision\s*\(\s*farm_id\s*,\s*stock_transaction_id\s*,\s*revision_no\s*\)/is',
        $sql
    ) === 1
);

$check(
    'REVISION_CYCLE_UNIQUE',
    preg_match(
        '/UNIQUE\s+KEY\s+uniq_stock_consumption_allocation_revision_cycle\s*\(\s*revision_id\s*,\s*cycle_id\s*\)/is',
        $sql
    ) === 1
);

$check(
    'ALLOCATED_AMOUNT_AUTHORITY_PRESENT',
    substr_count(
        strtolower($sql),
        'allocated_amount decimal(14,2) not null'
    ) >= 2
);

$check(
    'DERIVED_PERCENT_PRESENT',
    substr_count(
        strtolower($sql),
        'allocation_percent decimal(7,4) not null'
    ) >= 2
);

$check(
    'CURRENT_REVISION_POINTER',
    stripos(
        $sql,
        'allocation_revision_no INT UNSIGNED NOT NULL'
    ) !== false
);

$check(
    'CONSERVATION_SNAPSHOT',
    stripos(
        $sql,
        'parent_amount DECIMAL(14,2) NOT NULL'
    ) !== false
    &&
    stripos(
        $sql,
        'unallocated_amount DECIMAL(14,2) NOT NULL'
    ) !== false
);

$check(
    'CAUSAL_PROVENANCE',
    stripos(
        $sql,
        'causal_fingerprint CHAR(64) NOT NULL'
    ) !== false
    &&
    stripos(
        $sql,
        'causal_manifest_json LONGTEXT NOT NULL'
    ) !== false
);

$check(
    'STATE_PROVENANCE',
    stripos(
        $sql,
        'state_fingerprint CHAR(64) NOT NULL'
    ) !== false
    &&
    stripos(
        $sql,
        'state_manifest_json LONGTEXT NOT NULL'
    ) !== false
);

$check(
    'REVISION_CHAIN',
    stripos(
        $sql,
        'previous_revision_id BIGINT UNSIGNED NULL'
    ) !== false
);

$check(
    'REVISION_REASON',
    stripos(
        $sql,
        'revision_reason VARCHAR(500) NULL'
    ) !== false
);

$check(
    'ACTOR_CAPTURE',
    stripos(
        $sql,
        'changed_by_user_id INT NULL'
    ) !== false
);

$check(
    'CURRENT_CYCLE_DELETE_RESTRICT',
    preg_match(
        '/FOREIGN\s+KEY\s*\(\s*cycle_id\s*\).*?REFERENCES\s+production_cycles\s*\(\s*id\s*\).*?ON\s+DELETE\s+RESTRICT/is',
        $sql
    ) === 1
);

$check(
    'HISTORY_HAS_NO_STOCK_SOURCE_FK',
    preg_match(
        '/FOREIGN\s+KEY\s*\(\s*stock_transaction_id\s*\)/i',
        $sql
    ) !== 1
);

$check(
    'HISTORY_ROWS_HAVE_NO_CYCLE_FK',
    preg_match(
        '/stock_consumption_allocation_revision_rows.*?FOREIGN\s+KEY\s*\(\s*cycle_id\s*\)/is',
        $sql
    ) !== 1
);

$check(
    'NO_EXISTING_TABLE_ALTER',
    preg_match(
        '/\bALTER\s+TABLE\b/i',
        $sql
    ) !== 1
);

$check(
    'NO_ALLOCATION_BACKFILL',
    preg_match(
        '/INSERT\s+INTO\s+(?:stock_consumption_allocations|stock_consumption_allocation_revisions|stock_consumption_allocation_revision_rows)\b/i',
        $sql
    ) !== 1
);

$check(
    'MIGRATION_REGISTERED',
    strpos(
        $sql,
        "065_stock_consumption_allocation_provenance.sql"
    ) !== false
);

$result =
    $failures
        ? 'FAIL'
        : 'PASS';

echo "RESULT={$result}\n";
echo "CHECK_COUNT={$checks}\n";

echo "CURRENT_PROJECTION_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "APPEND_ONLY_HISTORY_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "CONSERVATION_PROVENANCE_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "NO_HISTORICAL_BACKFILL="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "EXISTING_SCHEMA_MUTATION="
    . (
        preg_match(
            '/\bALTER\s+TABLE\b/i',
            $sql
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
