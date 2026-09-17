<?php

$root =
    dirname(__DIR__);

$path =
    $root
    . '/lib/financial_allocation_persistence.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "Missing financial allocation persistence source.\n"
    );
    exit(1);
}

$source =
    file_get_contents($path);

if (!is_string($source)) {
    fwrite(
        STDERR,
        "Unable to read financial allocation persistence source.\n"
    );
    exit(1);
}

$checks = [];

$checks['CENTRAL_PERSISTENCE_ENTRYPOINT'] =
    strpos(
        $source,
        'function financial_allocation_persistence_apply('
    ) !== false;

$checks['FINANCIAL_SERVICE_AUTHORITY'] =
    strpos(
        $source,
        "require_once __DIR__\n    . '/financial_allocation_service.php';"
    ) !== false;

$checks['EXPENSE_REVISION_AUTHORITY'] =
    strpos(
        $source,
        "require_once __DIR__\n    . '/expense_revision_service.php';"
    ) !== false;

$checks['ACTIVE_TRANSACTION_REQUIRED'] =
    strpos(
        $source,
        'if (!$pdo->inTransaction())'
    ) !== false;

$checks['PARENT_LOCKED'] =
    strpos(
        $source,
        'financial_allocation_service_parent('
    ) !== false
    &&
    strpos(
        $source,
        '$expenseId,' . PHP_EOL . '            true'
    ) !== false;

$checks['CURRENT_PROJECTION_LOCKED'] =
    strpos(
        $source,
        'financial_allocation_service_current_rows('
    ) !== false;

$checks['ANIMAL_OVERLAP_LOCKED'] =
    strpos(
        $source,
        'financial_allocation_service_animal_count('
    ) !== false;

$checks['TARGET_CYCLES_CENTRAL'] =
    strpos(
        $source,
        'financial_allocation_service_target_cycles('
    ) !== false;

$checks['DESIRED_ROWS_CENTRAL_VALIDATION'] =
    substr_count(
        $source,
        'financial_allocation_service_validate_desired_rows('
    ) >= 3;

$checks['CURRENT_PROJECTION_ASSERTED'] =
    strpos(
        $source,
        'financial_allocation_persistence_assert_projection('
    ) !== false
    &&
    strpos(
        $source,
        'changed outside the canonical contract'
    ) !== false;

$noopPosition =
    strpos(
        $source,
        'if ($currentSemantic === $desiredSemantic)'
    );

$realMutationPosition =
    strpos(
        $source,
        'A real allocation mutation becomes part'
    );

$preparePosition =
    strpos(
        $source,
        'expense_revision_service_prepare_existing_mutation('
    );

$checks['NOOP_BRANCH_BEFORE_REAL_MUTATION'] =
    $noopPosition !== false
    &&
    $realMutationPosition !== false
    &&
    $noopPosition < $realMutationPosition;

$checks['LEGACY_NOOP_HISTORY_GUARD'] =
    strpos(
        $source,
        '$legacyLatest ='
    ) !== false
    &&
    strpos(
        $source,
        'expense_revision_service_latest('
    ) !== false
    &&
    strpos(
        $source,
        'Legacy expense metadata is inconsistent with revision history.'
    ) !== false;

$checks['CANONICAL_NOOP_PROVENANCE_ASSERTED'] =
    strpos(
        $source,
        '$preparedNoop ='
    ) !== false
    &&
    substr_count(
        $source,
        'expense_revision_service_prepare_existing_mutation('
    ) >= 2;

$checks['EXPENSE_REVISION_PREPARED'] =
    $preparePosition !== false;

$checks['EXPENSE_REVISION_RECORDED'] =
    strpos(
        $source,
        'expense_revision_service_record_updated('
    ) !== false;

$checks['TARGETED_ROW_DELETE'] =
    substr_count(
        $source,
        'DELETE FROM financial_allocations'
    ) === 1
    &&
    preg_match(
        '/DELETE FROM financial_allocations\s+WHERE farm_id=\?\s+AND expense_id=\?\s+AND cycle_id=\?/s',
        $source
    ) === 1;

$checks['EXISTING_ROW_UPDATED_IN_PLACE'] =
    strpos(
        $source,
        'UPDATE financial_allocations'
    ) !== false
    &&
    strpos(
        $source,
        'AND cycle_id=?'
    ) !== false;

$checks['NEW_ROW_INSERTED'] =
    strpos(
        $source,
        'INSERT INTO financial_allocations'
    ) !== false
    &&
    strpos(
        $source,
        'created_by,'
    ) !== false
    &&
    strpos(
        $source,
        'created_at'
    ) !== false;

$checks['POST_WRITE_RELOAD'] =
    strpos(
        $source,
        '$writtenRows ='
    ) !== false
    &&
    strpos(
        $source,
        '$writtenSemantic'
    ) !== false;

$checks['VISIBLE_REMAINDER_RETURNED'] =
    substr_count(
        $source,
        "'remaining_amount'"
    ) >= 2;

$checks['NO_TRANSACTION_COMMIT'] =
    strpos(
        $source,
        '->commit('
    ) === false;

$checks['NO_TRANSACTION_ROLLBACK'] =
    strpos(
        $source,
        '->rollBack('
    ) === false;

$checks['NO_DATABASE_BOOTSTRAP'] =
    strpos(
        $source,
        'new PDO'
    ) === false
    &&
    strpos(
        $source,
        "require '../config"
    ) === false
    &&
    strpos(
        $source,
        "require_once '../config"
    ) === false;

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
        . implode(',', $failed)
        . PHP_EOL;

    exit(1);
}

exit(0);
