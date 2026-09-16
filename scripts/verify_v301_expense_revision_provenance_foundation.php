<?php

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/lib/expense_revision_provenance.php';

$migrationPath =
    $root
    . '/migrations/063_expense_revision_provenance.sql';

$service =
    file_get_contents(
        $servicePath
    );

$migration =
    file_get_contents(
        $migrationPath
    );

if (
    !is_string($service)
    || !is_string($migration)
) {
    fwrite(
        STDERR,
        "Unable to read Expense Revision Provenance foundation files.\n"
    );
    exit(1);
}

require_once $servicePath;

$checks = [];

$checks['MIGRATION_REVISION_TABLE'] =
    strpos(
        $migration,
        'CREATE TABLE IF NOT EXISTS farm_expense_revisions'
    ) !== false;

$checks['MIGRATION_NULLABLE_PROJECTION_METADATA'] =
    strpos(
        $migration,
        'expense_revision_no INT UNSIGNED NULL'
    ) !== false
    &&
    strpos(
        $migration,
        'expense_causal_fingerprint CHAR(64) NULL'
    ) !== false;

$checks['MIGRATION_NO_CURRENT_ROW_BACKFILL'] =
    preg_match(
        '/\bUPDATE\s+farm_expenses\b/i',
        $migration
    ) !== 1;

$checks['MIGRATION_NO_SYNTHETIC_REVISION_BACKFILL'] =
    preg_match(
        '/\bINSERT\s+INTO\s+farm_expense_revisions\b/i',
        $migration
    ) !== 1;

$checks['MIGRATION_HISTORY_SURVIVES_EXPENSE_DELETE'] =
    preg_match(
        '/FOREIGN\s+KEY\s*\(\s*expense_id\s*\).*REFERENCES\s+farm_expenses/is',
        $migration
    ) !== 1;

$checks['MIGRATION_REGISTERED'] =
    strpos(
        $migration,
        "'063_expense_revision_provenance.sql'"
    ) !== false;

$checks['PURE_SERVICE_NO_SQL_MUTATION'] =
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?farm_/i',
        $service
    ) !== 1
    &&
    strpos(
        $service,
        'new PDO'
    ) === false
    &&
    strpos(
        $service,
        '->prepare('
    ) === false;

$expense = [
    'id' => 59,
    'farm_id' => 4,
    'expense_date' => '2026-09-14',
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'attribution_scope' => 'cycle',
    'poultry_category' => 'layer',
    'cycle_id' => 55,
    'category' => 'misc',
    'amount' => '2000.00',
    'unit' => '1.00',
    'description' => 'Original wording',
    'user_id' => 10,
    'created_at' => '2026-09-14 12:00:00',
];

$financial = [
    [
        'id' => 901,
        'farm_id' => 4,
        'expense_id' => 59,
        'cycle_id' => 55,
        'allocation_percent' => '100.0000',
        'allocated_amount' => '2000.00',
        'notes' => 'Allocation wording',
        'created_by' => 10,
        'created_at' => '2026-09-14 12:01:00',
    ],
];

$animals = [
    [
        'id' => 8001,
        'farm_id' => 4,
        'expense_id' => 59,
        'animal_id' => 301,
        'allocation_method' => 'equal',
        'allocation_percent' => '50.0000',
        'allocated_amount' => '1000.00',
        'created_by' => 10,
        'created_at' => '2026-09-14 12:02:00',
    ],
    [
        'id' => 8002,
        'farm_id' => 4,
        'expense_id' => 59,
        'animal_id' => 302,
        'allocation_method' => 'equal',
        'allocation_percent' => '50.0000',
        'allocated_amount' => '1000.00',
        'created_by' => 10,
        'created_at' => '2026-09-14 12:02:00',
    ],
];

$base =
    expense_revision_build(
        $expense,
        $financial,
        $animals
    );

$checks['CANONICAL_HASH_REPRODUCIBLE'] =
    $base['causal_fingerprint']
    === hash(
        'sha256',
        $base['causal_manifest_json']
    )
    &&
    $base['state_fingerprint']
    === hash(
        'sha256',
        $base['state_manifest_json']
    );

$wordingExpense =
    $expense;

$wordingExpense['description'] =
    'Completely different wording';

$wording =
    expense_revision_build(
        $wordingExpense,
        $financial,
        $animals
    );

$checks['WORDING_INVARIANCE_CAUSAL'] =
    hash_equals(
        $base['causal_fingerprint'],
        $wording['causal_fingerprint']
    );

$checks['WORDING_CHANGE_AUDIT_VISIBLE'] =
    !hash_equals(
        $base['state_fingerprint'],
        $wording['state_fingerprint']
    );

$amountExpense =
    $expense;

$amountExpense['amount'] =
    '2001.00';

$amountChanged =
    expense_revision_build(
        $amountExpense,
        $financial,
        $animals
    );

$checks['AMOUNT_CHANGE_CAUSAL'] =
    !hash_equals(
        $base['causal_fingerprint'],
        $amountChanged['causal_fingerprint']
    );

$dateExpense =
    $expense;

$dateExpense['expense_date'] =
    '2026-09-15';

$dateChanged =
    expense_revision_build(
        $dateExpense,
        $financial,
        $animals
    );

$checks['DATE_CHANGE_CAUSAL'] =
    !hash_equals(
        $base['causal_fingerprint'],
        $dateChanged['causal_fingerprint']
    );

$scopeExpense =
    $expense;

$scopeExpense['cycle_id'] =
    56;

$scopeChanged =
    expense_revision_build(
        $scopeExpense,
        $financial,
        $animals
    );

$checks['ATTRIBUTION_CHANGE_CAUSAL'] =
    !hash_equals(
        $base['causal_fingerprint'],
        $scopeChanged['causal_fingerprint']
    );

$percentOnly =
    $financial;

$percentOnly[0]['allocation_percent'] =
    '12.3456';

$percentChanged =
    expense_revision_build(
        $expense,
        $percentOnly,
        $animals
    );

$checks['DERIVED_PERCENT_INVARIANCE'] =
    hash_equals(
        $base['causal_fingerprint'],
        $percentChanged['causal_fingerprint']
    );

$allocationAmount =
    $financial;

$allocationAmount[0]['allocated_amount'] =
    '1999.00';

$allocationAmountChanged =
    expense_revision_build(
        $expense,
        $allocationAmount,
        $animals
    );

$checks['FINANCIAL_ALLOCATION_AMOUNT_CAUSAL'] =
    !hash_equals(
        $base['causal_fingerprint'],
        $allocationAmountChanged['causal_fingerprint']
    );

$recreatedAnimals =
    $animals;

$recreatedAnimals[0]['id'] =
    9001;

$recreatedAnimals[1]['id'] =
    9002;

$recreatedAnimals[0]['allocation_method'] =
    'custom';

$recreatedAnimals[1]['allocation_method'] =
    'custom';

$recreatedAnimals[0]['allocation_percent'] =
    '50';

$recreatedAnimals[1]['allocation_percent'] =
    '50';

$recreated =
    expense_revision_build(
        $expense,
        $financial,
        $recreatedAnimals
    );

$checks['RECREATED_ALLOCATION_ROW_INVARIANCE'] =
    hash_equals(
        $base['causal_fingerprint'],
        $recreated['causal_fingerprint']
    );

$animalAmountChanged =
    $animals;

$animalAmountChanged[0]['allocated_amount'] =
    '1100.00';

$animalAmountChanged[1]['allocated_amount'] =
    '900.00';

$animalChanged =
    expense_revision_build(
        $expense,
        $financial,
        $animalAmountChanged
    );

$checks['ANIMAL_ALLOCATION_COMPOSITION_CAUSAL'] =
    !hash_equals(
        $base['causal_fingerprint'],
        $animalChanged['causal_fingerprint']
    );

$reordered =
    expense_revision_build(
        $expense,
        array_reverse($financial),
        array_reverse($animals)
    );

$checks['ALLOCATION_ORDER_INVARIANCE'] =
    hash_equals(
        $base['causal_fingerprint'],
        $reordered['causal_fingerprint']
    );

$decimalExpense =
    $expense;

$decimalExpense['amount'] =
    '02000.0000';

$decimalExpense['unit'] =
    '1.00000';

$decimalNormalized =
    expense_revision_build(
        $decimalExpense,
        $financial,
        $animals
    );

$checks['DECIMAL_REPRESENTATION_INVARIANCE'] =
    hash_equals(
        $base['causal_fingerprint'],
        $decimalNormalized['causal_fingerprint']
    );

$checks['PROJECTION_METADATA_NOT_SELF_HASHED'] =
    strpos(
        $service,
        "'expense_revision_no' =>"
    ) === false
    &&
    strpos(
        $service,
        "'expense_causal_fingerprint' =>"
    ) === false;

$checks['FOUNDATION_SCHEMA_VERSIONED'] =
    strpos(
        $service,
        'renee.farm-expense.causal.v1'
    ) !== false
    &&
    strpos(
        $service,
        'renee.farm-expense.state.v1'
    ) !== false;

$failed = [];

foreach ($checks as $name => $passed) {
    echo $name
        . '='
        . (
            $passed
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$passed) {
        $failed[] =
            $name;
    }
}

echo 'RESULT='
    . (
        $failed
            ? 'FAIL'
            : 'PASS'
    )
    . PHP_EOL;

if ($failed) {
    echo 'FAILED='
        . implode(
            ',',
            $failed
        )
        . PHP_EOL;

    exit(1);
}

exit(0);
