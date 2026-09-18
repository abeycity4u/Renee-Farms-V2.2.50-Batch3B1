<?php
/**
 * Renee Farms V3.0.1
 * Human-facing record-reference schema verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$migrationPath =
    $root
    . '/migrations/066_human_facing_record_references.sql';

$servicePath =
    $root
    . '/lib/record_reference.php';

$migration =
    is_file($migrationPath)
        ? (string)file_get_contents($migrationPath)
        : '';

$service =
    is_file($servicePath)
        ? (string)file_get_contents($servicePath)
        : '';

$checks = 0;
$failures = 0;

$check = static function (
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
    $migration !== '',
    'Migration 066 exists'
);

foreach ([
    'stock_transactions',
    'farm_expenses',
    'sales_records',
] as $table) {
    $pattern =
        '/ALTER\s+TABLE\s+'
        . preg_quote($table, '/')
        . '\s+'
        . '[\s\S]*?'
        . 'ADD\s+COLUMN\s+public_reference\s+VARCHAR\(32\)'
        . '[\s\S]*?'
        . 'CHARACTER\s+SET\s+ascii'
        . '[\s\S]*?'
        . 'COLLATE\s+ascii_bin'
        . '[\s\S]*?'
        . '\bNULL\b'
        . '/i';

    $check(
        preg_match(
            $pattern,
            $migration
        ) === 1,
        $table
            . ' receives nullable ASCII public_reference'
    );
}

$check(
    preg_match(
        '/ALTER\s+TABLE\s+stock_transactions[\s\S]*?ADD\s+UNIQUE\s+KEY\s+uniq_stock_transaction_public_reference\s*\(\s*public_reference\s*\)/i',
        $migration
    ) === 1,
    'Stock movement reference has database uniqueness'
);

$check(
    preg_match(
        '/ALTER\s+TABLE\s+farm_expenses[\s\S]*?ADD\s+UNIQUE\s+KEY\s+uniq_farm_expense_public_reference\s*\(\s*public_reference\s*\)/i',
        $migration
    ) === 1,
    'Expense reference has database uniqueness'
);

$check(
    preg_match(
        '/ALTER\s+TABLE\s+sales_records[\s\S]*?ADD\s+UNIQUE\s+KEY\s+uniq_sale_public_reference\s*\(\s*public_reference\s*\)/i',
        $migration
    ) === 1,
    'Sale reference has database uniqueness'
);

$check(
    substr_count(
        strtolower($migration),
        'add column public_reference'
    ) === 3,
    'Exactly three business parent tables receive public references'
);

$check(
    preg_match(
        '/\bUPDATE\s+(stock_transactions|farm_expenses|sales_records)\b/i',
        $migration
    ) !== 1,
    'Migration performs no historical business-record backfill'
);

$check(
    preg_match(
        '/\b(DELETE\s+FROM|TRUNCATE\s+TABLE|DROP\s+TABLE)\b/i',
        $migration
    ) !== 1,
    'Migration performs no destructive table operation'
);

$check(
    preg_match(
        '/public_reference\s+VARCHAR\(32\)[\s\S]*?\bNOT\s+NULL\b/i',
        $migration
    ) !== 1,
    'Public references remain nullable during staged cutover'
);

$check(
    strpos(
        $migration,
        "VALUES ('066_human_facing_record_references.sql')"
    ) !== false,
    'Migration records its schema_migrations identity'
);

$check(
    strpos(
        $service,
        "'stock_movement'"
    ) !== false
    &&
    strpos(
        $service,
        "'expense'"
    ) !== false
    &&
    strpos(
        $service,
        "'sale'"
    ) !== false,
    'Schema entities match the central reference service'
);

$check(
    strpos(
        $service,
        '{10})$/'
    ) !== false,
    'Central service retains 10-character reference suffix contract'
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
