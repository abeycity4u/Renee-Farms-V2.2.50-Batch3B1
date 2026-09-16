<?php

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/lib/expense_revision_service.php';

$foundationVerifier =
    $root
    . '/scripts/verify_v301_expense_revision_provenance_foundation.php';

$service =
    file_get_contents(
        $servicePath
    );

if (!is_string($service)) {
    echo "RESULT=FAIL\n";
    echo "FAIL=SERVICE_UNREADABLE\n";
    exit(1);
}

$checks = [];

$checks['REQUIRES_PROVENANCE_FOUNDATION'] =
    strpos(
        $service,
        "require_once __DIR__ . '/expense_revision_provenance.php';"
    ) !== false;

$checks['CALLER_OWNS_TRANSACTION'] =
    strpos(
        $service,
        '$pdo->inTransaction()'
    ) !== false
    &&
    strpos(
        $service,
        'beginTransaction('
    ) === false
    &&
    strpos(
        $service,
        '->commit('
    ) === false
    &&
    strpos(
        $service,
        '->rollBack('
    ) === false;

$checks['EXPENSE_ROW_LOCK'] =
    strpos(
        $service,
        'FROM farm_expenses'
    ) !== false
    &&
    strpos(
        $service,
        'LIMIT 1'
    ) !== false
    &&
    strpos(
        $service,
        'FOR UPDATE'
    ) !== false;

$checks['ALLOCATION_ROWS_LOCKED'] =
    strpos(
        $service,
        'FROM financial_allocations'
    ) !== false
    &&
    strpos(
        $service,
        'FROM ruminant_expense_animal_allocations'
    ) !== false;

$checks['LATEST_REVISION_LOCK'] =
    strpos(
        $service,
        'FROM farm_expense_revisions'
    ) !== false
    &&
    strpos(
        $service,
        'ORDER BY revision_no DESC'
    ) !== false;

$checks['REVISION_LEDGER_APPEND_ONLY'] =
    substr_count(
        $service,
        'INSERT INTO farm_expense_revisions'
    ) === 1
    &&
    preg_match(
        '/\bUPDATE\s+farm_expense_revisions\b/i',
        $service
    ) !== 1
    &&
    preg_match(
        '/\bDELETE\s+FROM\s+farm_expense_revisions\b/i',
        $service
    ) !== 1;

$checks['PROJECTION_UPDATE_METADATA_ONLY'] =
    substr_count(
        $service,
        'UPDATE farm_expenses'
    ) === 1
    &&
    strpos(
        $service,
        'expense_revision_no=?'
    ) !== false
    &&
    strpos(
        $service,
        'expense_causal_fingerprint=?'
    ) !== false;

$checks['CREATE_REVISION_ONE'] =
    strpos(
        $service,
        "'create',\n            1,"
    ) !== false;

$checks['LAZY_LEGACY_BASELINE'] =
    strpos(
        $service,
        "'legacy_baseline',\n                1,"
    ) !== false
    &&
    strpos(
        $service,
        'Legacy expense metadata is inconsistent with revision history.'
    ) !== false;

$checks['PARTIAL_METADATA_FAILS_CLOSED'] =
    strpos(
        $service,
        'Expense revision metadata is partially populated.'
    ) !== false;

$checks['OUT_OF_BAND_MUTATION_DETECTED'] =
    strpos(
        $service,
        'Expense state changed outside the canonical revision contract.'
    ) !== false;

$checks['NOOP_UPDATE_NO_REVISION'] =
    strpos(
        $service,
        "'changed' =>\n                false"
    ) !== false
    &&
    strpos(
        $service,
        "'state_fingerprint'"
    ) !== false;

$checks['UPDATE_REVISION_APPEND'] =
    strpos(
        $service,
        "'update',\n            \$nextRevisionNo,"
    ) !== false;

$checks['DELETE_REVISION_APPEND'] =
    strpos(
        $service,
        "'delete',"
    ) !== false
    &&
    strpos(
        $service,
        'No projection metadata synchronization here.'
    ) !== false;

$checks['ALLOWED_ACTIONS_BOUNDED'] =
    strpos(
        $service,
        "'create',\n        'legacy_baseline',\n        'update',\n        'delete',"
    ) !== false;

$checks['CAUSAL_AND_STATE_PERSISTED'] =
    strpos(
        $service,
        'causal_manifest_json'
    ) !== false
    &&
    strpos(
        $service,
        'state_manifest_json'
    ) !== false
    &&
    strpos(
        $service,
        'state_fingerprint'
    ) !== false;

$checks['PREVIOUS_REVISION_CHAINED'] =
    strpos(
        $service,
        'previous_revision_id'
    ) !== false
    &&
    strpos(
        $service,
        'A later expense revision must reference its previous revision.'
    ) !== false;

$checks['DELETE_EXPECTS_CALLER_PHYSICAL_DELETE'] =
    strpos(
        $service,
        'The caller must physically delete farm_expenses within this same'
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
 * Re-run the pure provenance foundation contract.
 */
$foundationOutput = [];
$foundationRc = 1;

exec(
    escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg(
        $foundationVerifier
    )
    . ' 2>&1',
    $foundationOutput,
    $foundationRc
);

$foundationPass =
    $foundationRc === 0
    &&
    in_array(
        'RESULT=PASS',
        $foundationOutput,
        true
    );

echo 'FOUNDATION_REGRESSION='
    . (
        $foundationPass
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

if (!$foundationPass) {
    $failed[] =
        'FOUNDATION_REGRESSION';
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
