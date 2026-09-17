<?php

$root =
    dirname(__DIR__);

$paths = [
    'integrity' =>
        $root
        . '/lib/financial_allocation_integrity.php',

    'update' =>
        $root
        . '/api/update_expense.php',

    'animal' =>
        $root
        . '/lib/ruminant_expense_allocation.php',

    'delete' =>
        $root
        . '/api/delete_expense.php',
];

$content = [];

foreach ($paths as $name => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            'Missing file: '
            . $name
            . PHP_EOL
        );

        exit(1);
    }

    $value =
        file_get_contents(
            $path
        );

    if (!is_string($value)) {
        fwrite(
            STDERR,
            'Unreadable file: '
            . $name
            . PHP_EOL
        );

        exit(1);
    }

    $content[$name] =
        $value;
}

$checks = [];

$checks['CENTRAL_INTEGRITY_SERVICE'] =
    strpos(
        $content['integrity'],
        'function financial_allocation_integrity_assert_parent_update('
    ) !== false
    &&
    strpos(
        $content['integrity'],
        'function financial_allocation_integrity_assert_animal_mutation('
    ) !== false;

$checks['FINANCIAL_SERVICE_AUTHORITY'] =
    strpos(
        $content['integrity'],
        "financial_allocation_service.php"
    ) !== false;

$checks['ACTIVE_TRANSACTION_REQUIRED'] =
    strpos(
        $content['integrity'],
        'financial_allocation_integrity_require_transaction('
    ) !== false
    &&
    strpos(
        $content['integrity'],
        '$pdo->inTransaction()'
    ) !== false;

$parentPos =
    strpos(
        $content['integrity'],
        'financial_allocation_service_parent('
    );

$financialPos =
    strpos(
        $content['integrity'],
        'financial_allocation_service_current_rows('
    );

$animalPos =
    strpos(
        $content['integrity'],
        'financial_allocation_service_animal_count('
    );

$cyclePos =
    strpos(
        $content['integrity'],
        'financial_allocation_service_target_cycles('
    );

$checks['CANONICAL_LOCK_ORDER'] =
    $parentPos !== false
    &&
    $financialPos !== false
    &&
    $animalPos !== false
    &&
    $cyclePos !== false
    &&
    $parentPos < $financialPos
    &&
    $financialPos < $animalPos
    &&
    $animalPos < $cyclePos;

$checks['CURRENT_STATE_VALIDATED_CENTRALLY'] =
    substr_count(
        $content['integrity'],
        'financial_allocation_service_validate_desired_rows('
    ) >= 2;

$checks['PROPOSED_PARENT_VALIDATED_CENTRALLY'] =
    strpos(
        $content['integrity'],
        '$nextParent'
    ) !== false
    &&
    strpos(
        $content['integrity'],
        '$nextValidated'
    ) !== false;

$checks['GROSS_CHANGE_FAILS_CLOSED'] =
    strpos(
        $content['integrity'],
        '$currentGross !== $nextGross'
    ) !== false
    &&
    strpos(
        $content['integrity'],
        'before changing the expense amount or quantity'
    ) !== false;

$checks['ANIMAL_FINANCIAL_MUTUAL_EXCLUSION'] =
    substr_count(
        $content['integrity'],
        'Clear the current production-cycle financial allocation before allocating this expense to individual animals.'
    ) >= 2;

$checks['UPDATE_ROUTE_REQUIRES_INTEGRITY'] =
    strpos(
        $content['update'],
        "financial_allocation_integrity.php"
    ) !== false;

$checks['UPDATE_ROUTE_CALLS_CENTRAL_GUARD'] =
    strpos(
        $content['update'],
        'financial_allocation_integrity_assert_parent_update('
    ) !== false;

$beginPos =
    strpos(
        $content['update'],
        '$pdo->beginTransaction();'
    );

$guardPos =
    strpos(
        $content['update'],
        'financial_allocation_integrity_assert_parent_update('
    );

$preparePos =
    strpos(
        $content['update'],
        'expense_revision_service_prepare_existing_mutation('
    );

$parentUpdatePos =
    strpos(
        $content['update'],
        'UPDATE farm_expenses'
    );

$checks['UPDATE_LOCK_ORDER_BEFORE_REVISION'] =
    $beginPos !== false
    &&
    $guardPos !== false
    &&
    $preparePos !== false
    &&
    $beginPos < $guardPos
    &&
    $guardPos < $preparePos;

$checks['UPDATE_GUARD_BEFORE_PARENT_WRITE'] =
    $guardPos !== false
    &&
    $parentUpdatePos !== false
    &&
    $guardPos < $parentUpdatePos;

$checks['UPDATE_PROPOSED_PARENT_COMPLETE'] =
    strpos(
        $content['update'],
        "'attribution_scope' =>"
    ) !== false
    &&
    strpos(
        $content['update'],
        "'production_type' =>"
    ) !== false
    &&
    strpos(
        $content['update'],
        "'cycle_id' =>"
    ) !== false
    &&
    strpos(
        $content['update'],
        "'amount' =>"
    ) !== false
    &&
    strpos(
        $content['update'],
        "'unit' =>"
    ) !== false;

$checks['ANIMAL_WRITER_REQUIRES_INTEGRITY'] =
    strpos(
        $content['animal'],
        'financial_allocation_integrity.php'
    ) !== false;

$animalGuardPos =
    strpos(
        $content['animal'],
        'financial_allocation_integrity_assert_animal_mutation('
    );

$animalDeletePos =
    strpos(
        $content['animal'],
        "DELETE FROM ruminant_expense_animal_allocations"
    );

$checks['ANIMAL_GUARD_BEFORE_PROJECTION_DELETE'] =
    $animalGuardPos !== false
    &&
    $animalDeletePos !== false
    &&
    $animalGuardPos < $animalDeletePos;

$checks['UPDATE_NO_DIRECT_FINANCIAL_SQL'] =
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+financial_allocations/i',
        $content['update']
    ) !== 1;

$checks['ANIMAL_WRITER_NO_DIRECT_FINANCIAL_SQL'] =
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+financial_allocations/i',
        $content['animal']
    ) !== 1;

$deleteRevisionPos =
    strpos(
        $content['delete'],
        'expense_revision_service_record_deleted('
    );

$deleteParentPos =
    strpos(
        $content['delete'],
        'DELETE FROM farm_expenses'
    );

$checks['DELETE_REVISION_BEFORE_PARENT_DELETE'] =
    $deleteRevisionPos !== false
    &&
    $deleteParentPos !== false
    &&
    $deleteRevisionPos < $deleteParentPos;

$checks['DELETE_ROUTE_LEAVES_CHILD_CLEANUP_TO_FK'] =
    strpos(
        $content['delete'],
        'DELETE FROM financial_allocations'
    ) === false
    &&
    strpos(
        $content['delete'],
        'DELETE FROM ruminant_expense_animal_allocations'
    ) === false;

$checks['INTEGRITY_SERVICE_OWNS_NO_TRANSACTION'] =
    strpos(
        $content['integrity'],
        '->commit('
    ) === false
    &&
    strpos(
        $content['integrity'],
        '->rollBack('
    ) === false;

$verifierSource =
    file_get_contents(
        __FILE__
    );

$checks['NO_DATABASE_BOOTSTRAP'] =
    strpos(
        $content['integrity'],
        'config.php'
    ) === false
    &&
    strpos(
        $content['integrity'],
        'new PDO'
    ) === false
    &&
    preg_match(
        '/\\brequire(?:_once)?\\s*(?:\\(\\s*)?[^;\\n]*config\\.php/i',
        $verifierSource
    ) !== 1
    &&
    preg_match(
        '/\\bnew\\s+PDO\\s*\\(/i',
        $verifierSource
    ) !== 1;

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] =
            $name;
    }
}

echo 'RESULT='
    . ($failed ? 'FAIL' : 'PASS')
    . PHP_EOL;

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

foreach ($checks as $name => $passed) {
    echo $name
        . '='
        . ($passed ? 'PASS' : 'FAIL')
        . PHP_EOL;
}

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITES=NONE\n";

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
