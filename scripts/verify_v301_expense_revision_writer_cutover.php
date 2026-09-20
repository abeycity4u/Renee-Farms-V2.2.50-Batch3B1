<?php

$root =
    dirname(__DIR__);

$paths = [
    'layer' =>
        $root
        . '/poultry/layer_expenses.php',

    'broiler' =>
        $root
        . '/poultry/broiler_expenses.php',

    'poultry_hub' =>
        $root
        . '/poultry/expenses.php',

    'poultry_workspace' =>
        $root
        . '/lib/poultry_expense_workspace.php',

    'poultry_entry' =>
        $root
        . '/lib/poultry_expense_entry.php',

    'ruminant' =>
        $root
        . '/ruminant/ruminant_expenses.php',

    'update' =>
        $root
        . '/api/update_expense.php',

    'delete' =>
        $root
        . '/api/delete_expense.php',
];

$files = [];

foreach ($paths as $name => $path) {
    $text =
        file_get_contents(
            $path
        );

    if (!is_string($text)) {
        echo "RESULT=FAIL\n";
        echo "FAIL=UNREADABLE_" . strtoupper($name) . "\n";
        exit(1);
    }

    $files[$name] =
        $text;
}


function expense_cutover_ordered(
    string $text,
    array $needles
): bool {
    $offset = 0;

    foreach ($needles as $needle) {
        $position =
            strpos(
                $text,
                $needle,
                $offset
            );

        if ($position === false) {
            return false;
        }

        $offset =
            $position
            + strlen(
                $needle
            );
    }

    return true;
}


$checks = [];


/*
 * Every actual expense writer must use one shared revision service.
 */
$allRequire = true;

foreach (
    [
        'poultry_entry',
        'ruminant',
        'update',
        'delete',
    ]
    as $writerKey
) {
    if (
        strpos(
            $files[$writerKey],
            'expense_revision_service.php'
        ) === false
    ) {
        $allRequire = false;
        break;
    }
}

$checks['ALL_WRITERS_USE_SHARED_SERVICE'] =
    $allRequire;


/*
 * Poultry create transactions:
 * transaction -> insert -> ID -> revision -> commit.
 */
$canonicalPoultryCreate =
    expense_cutover_ordered(
        $files['poultry_entry'],
        [
            'INSERT INTO farm_expenses',
            '$pdo->lastInsertId()',
            'expense_revision_service_record_created(',
        ]
    )
    &&
    strpos(
        $files['poultry_entry'],
        'record_reference_persistence_assign_existing('
    ) !== false;


$checks['POULTRY_HUB_CREATE_ATOMIC_REVISION'] =
    expense_cutover_ordered(
        $files['poultry_hub'],
        [
            '$pdo->beginTransaction();',
            'poultry_expense_entry_create(',
            '$pdo->commit();',
        ]
    )
    &&
    strpos(
        $files['poultry_hub'],
        '$pdo->rollBack();'
    ) !== false
    &&
    $canonicalPoultryCreate;


$checks['LAYER_CREATE_AUTHORITY_RETIRED'] =
    strpos(
        $files['layer'],
        'poultry_expense_entry_create('
    ) === false
    &&
    strpos(
        $files['layer'],
        "isset(\$_POST['add_expense'])"
    ) === false;


$checks['BROILER_CREATE_AUTHORITY_RETIRED'] =
    strpos(
        $files['broiler'],
        'poultry_expense_entry_create('
    ) === false
    &&
    strpos(
        $files['broiler'],
        "isset(\$_POST['add_expense'])"
    ) === false;


/*
 * Ruminant causal snapshot must see persisted animal allocations.
 */
$checks['RUMINANT_CREATE_ALLOCATION_BEFORE_REVISION'] =
    expense_cutover_ordered(
        $files['ruminant'],
        [
            '$pdo->beginTransaction();',
            'INSERT INTO farm_expenses',
            '$pdo->lastInsertId()',
            'ruminant_expense_save_animal_allocations(',
            'expense_revision_service_record_created(',
            '$pdo->commit();',
        ]
    );


/*
 * Existing expense update:
 * establish/validate prior state before mutation;
 * revision new complete state before commit.
 */
$checks['UPDATE_PRIOR_REVISION_BEFORE_MUTATION'] =
    expense_cutover_ordered(
        $files['update'],
        [
            '$pdo->beginTransaction();',
            'expense_revision_service_prepare_existing_mutation(',
            'UPDATE farm_expenses',
        ]
    );


$checks['UPDATE_REVISION_BEFORE_COMMIT'] =
    expense_cutover_ordered(
        $files['update'],
        [
            'UPDATE farm_expenses',
            'expense_revision_service_record_updated(',
            '$pdo->commit();',
        ]
    );


$checks['UPDATE_RUMINANT_ALLOCATION_BEFORE_REVISION'] =
    expense_cutover_ordered(
        $files['update'],
        [
            'ruminant_expense_save_animal_allocations(',
            'expense_revision_service_record_updated(',
        ]
    );


$checks['MOVE_AWAY_FROM_RUMINANT_CLEARS_ALLOCATIONS'] =
    strpos(
        $files['update'],
        "elseif ((\$existing['farm_type'] ?? '') === 'ruminant')"
    ) !== false
    &&
    strpos(
        $files['update'],
        "'rows' => []"
    ) !== false;


/*
 * Delete:
 * lock existing current row, establish/check historical state,
 * append delete revision, preserve existing audit, then physical delete.
 */
$checks['DELETE_REVISION_BEFORE_PHYSICAL_DELETE'] =
    expense_cutover_ordered(
        $files['delete'],
        [
            '$pdo->beginTransaction();',
            'FOR UPDATE',
            'expense_revision_service_prepare_existing_mutation(',
            'expense_revision_service_record_deleted(',
            "audit_log_event('delete','expense'",
            'DELETE FROM farm_expenses',
            '$pdo->commit();',
        ]
    );


/*
 * Only the shared service may write the immutable revision ledger.
 */
$writerLedgerMutation = false;

foreach ($files as $text) {
    if (
        strpos(
            $text,
            'INSERT INTO farm_expense_revisions'
        ) !== false
        ||
        preg_match(
            '/\bUPDATE\s+farm_expense_revisions\b/i',
            $text
        ) === 1
        ||
        preg_match(
            '/\bDELETE\s+FROM\s+farm_expense_revisions\b/i',
            $text
        ) === 1
    ) {
        $writerLedgerMutation = true;
        break;
    }
}

$checks['NO_DUPLICATED_REVISION_SQL_IN_WRITERS'] =
    !$writerLedgerMutation;


/*
 * Current business projection remains the existing source of operational
 * reads. This cutover adds history; it does not rewrite reporting.
 */
$checks['CURRENT_PROJECTION_PRESERVED'] =
    strpos(
        $files['poultry_workspace'],
        'FROM farm_expenses e'
    ) !== false
    &&
    strpos(
        $files['poultry_hub'],
        'poultry_expense_workspace_rows('
    ) !== false
    &&
    strpos(
        $files['ruminant'],
        'FROM farm_expenses e'
    ) !== false;


/*
 * All transaction-owning cutover paths retain rollback.
 */
$checks['ROLLBACK_PATHS_RETAINED'] =
    strpos(
        $files['poultry_hub'],
        '$pdo->rollBack();'
    ) !== false
    &&
    strpos(
        $files['ruminant'],
        '$pdo->rollBack();'
    ) !== false
    &&
    strpos(
        $files['update'],
        '$pdo->rollBack();'
    ) !== false
    &&
    strpos(
        $files['delete'],
        '$pdo->rollBack();'
    ) !== false;


$failed = [];

foreach ($checks as $name => $pass) {
    echo $name
        . '='
        . (
            $pass
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$pass) {
        $failed[] =
            $name;
    }
}


/*
 * Preserve the shared-service and provenance foundation contracts.
 */
$serviceOutput = [];
$serviceRc = 1;

exec(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg(
        $root
        . '/scripts/verify_v301_expense_revision_service.php'
    )
    . ' 2>&1',
    $serviceOutput,
    $serviceRc
);

$servicePass =
    $serviceRc === 0
    &&
    in_array(
        'RESULT=PASS',
        $serviceOutput,
        true
    );

echo 'SERVICE_REGRESSION='
    . (
        $servicePass
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

if (!$servicePass) {
    $failed[] =
        'SERVICE_REGRESSION';
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
