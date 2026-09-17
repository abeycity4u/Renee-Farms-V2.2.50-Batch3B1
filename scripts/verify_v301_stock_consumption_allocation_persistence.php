<?php

$root =
    dirname(__DIR__);

$provenancePath =
    $root
    . '/lib/stock_consumption_allocation_provenance.php';

$persistencePath =
    $root
    . '/lib/stock_consumption_allocation_persistence.php';

if (
    !is_file($provenancePath)
    ||
    !is_file($persistencePath)
) {
    echo "RESULT=FAIL\n";
    echo "FAIL=MISSING_PERSISTENCE_FOUNDATION\n";
    exit(1);
}

require_once $provenancePath;
require_once $persistencePath;

$failures = [];
$checks = 0;

$check =
    static function (
        string $name,
        bool $value
    ) use (
        &$failures,
        &$checks
    ): void {
        $checks++;

        if (!$value) {
            $failures[] =
                $name;
        }
    };

$movement = [
    'id' => 5001,
    'farm_id' => 4,
    'stock_item_id' => 53,
    'transaction_type' => 'used',
    'transaction_date' => '2026-09-10',
    'quantity' => '10.00',
    'unit_cost' => '10000.0000',
    'total_cost' => '100000.00',
    'farm_type' => 'poultry',
    'production_type' => 'layer',
    'attribution_scope' => 'production_type',
    'financial_classification' => 'consumables',
    'source_type' => 'inventory_manual',
    'source_id' => 5001,
    'remarks' => 'Shared Layer use',
];

$rowsA = [
    [
        'cycle_id' => 41,
        'allocated_amount' => '40000.00',
        'allocation_percent' => '40.0000',
        'notes' => 'Cycle A',
    ],
    [
        'cycle_id' => 45,
        'allocated_amount' => '30000.00',
        'allocation_percent' => '30.0000',
        'notes' => 'Cycle B',
    ],
];

$a =
    stock_consumption_allocation_provenance_build(
        $movement,
        $rowsA
    );

$check(
    'CONSERVATION',
    $a['parent_amount'] === '100000.00'
    &&
    $a['allocated_amount'] === '70000.00'
    &&
    $a['unallocated_amount'] === '30000.00'
);

$rowsReordered =
    array_reverse(
        $rowsA
    );

$b =
    stock_consumption_allocation_provenance_build(
        $movement,
        $rowsReordered
    );

$check(
    'ROW_ORDER_INVARIANT',
    $a['causal_fingerprint']
        === $b['causal_fingerprint']
    &&
    $a['state_fingerprint']
        === $b['state_fingerprint']
);

$rowsNote = $rowsA;
$rowsNote[0]['notes'] =
    'Different explanation';

$c =
    stock_consumption_allocation_provenance_build(
        $movement,
        $rowsNote
    );

$check(
    'NOTE_NON_CAUSAL',
    $a['causal_fingerprint']
        === $c['causal_fingerprint']
    &&
    $a['state_fingerprint']
        !== $c['state_fingerprint']
);

$rowsPercent = $rowsA;
$rowsPercent[0]['allocation_percent'] =
    '39.9999';

$d =
    stock_consumption_allocation_provenance_build(
        $movement,
        $rowsPercent
    );

$check(
    'PERCENT_NON_CAUSAL',
    $a['causal_fingerprint']
        === $d['causal_fingerprint']
    &&
    $a['state_fingerprint']
        !== $d['state_fingerprint']
);

$rowsAmount = $rowsA;
$rowsAmount[0]['allocated_amount'] =
    '40001.00';

$e =
    stock_consumption_allocation_provenance_build(
        $movement,
        $rowsAmount
    );

$check(
    'AMOUNT_CAUSAL',
    $a['causal_fingerprint']
        !== $e['causal_fingerprint']
);

$movementCost = $movement;
$movementCost['total_cost'] =
    '100001.00';

$f =
    stock_consumption_allocation_provenance_build(
        $movementCost,
        $rowsA
    );

$check(
    'PARENT_COST_CAUSAL',
    $a['causal_fingerprint']
        !== $f['causal_fingerprint']
);

$provenanceText =
    file_get_contents(
        $provenancePath
    );

$persistenceText =
    file_get_contents(
        $persistencePath
    );

/*
 * Strip PHP comments before lexical purity checks.
 *
 * The provenance file deliberately documents that it has "no PDO";
 * comments must not be mistaken for executable dependencies.
 * String literals remain visible so embedded SQL would still be detected.
 */
$provenanceExecutable = '';

if (is_string($provenanceText)) {
    foreach (
        token_get_all($provenanceText)
        as $token
    ) {
        if (
            is_array($token)
            &&
            in_array(
                $token[0],
                [
                    T_COMMENT,
                    T_DOC_COMMENT,
                ],
                true
            )
        ) {
            continue;
        }

        $provenanceExecutable .=
            is_array($token)
                ? $token[1]
                : $token;
    }
}

$check(
    'PROVENANCE_PURE',
    is_string($provenanceText)
    &&
    preg_match(
        '/\bPDO\b/',
        $provenanceExecutable
    ) !== 1
    &&
    preg_match(
        '/\b(?:SELECT|INSERT|UPDATE|DELETE)\b/i',
        $provenanceExecutable
    ) !== 1
);

$check(
    'CALLER_OWNS_TRANSACTION',
    is_string($persistenceText)
    &&
    strpos(
        $persistenceText,
        'beginTransaction'
    ) === false
    &&
    strpos(
        $persistenceText,
        '->commit('
    ) === false
    &&
    strpos(
        $persistenceText,
        '->rollBack('
    ) === false
);

$check(
    'FOR_UPDATE_LOCKING',
    substr_count(
        (string)$persistenceText,
        'FOR UPDATE'
    ) >= 4
);

$check(
    'SOURCE_RESOLVER_USED',
    strpos(
        (string)$persistenceText,
        'stock_consumption_source_resolver_resolve'
    ) !== false
    &&
    strpos(
        (string)$persistenceText,
        'stock_consumption_source_resolver_assert_cycle_consistency'
    ) !== false
);

$check(
    'ADAPTER_USED',
    strpos(
        (string)$persistenceText,
        'stock_consumption_allocation_service_validate_desired_rows'
    ) !== false
);

$check(
    'PROVENANCE_USED',
    strpos(
        (string)$persistenceText,
        'stock_consumption_allocation_provenance_build'
    ) !== false
);

$check(
    'CURRENT_PROJECTION_WRITER',
    strpos(
        (string)$persistenceText,
        'INSERT INTO stock_consumption_allocations'
    ) !== false
    &&
    strpos(
        (string)$persistenceText,
        'UPDATE stock_consumption_allocations'
    ) !== false
    &&
    strpos(
        (string)$persistenceText,
        'DELETE FROM stock_consumption_allocations'
    ) !== false
);

$check(
    'APPEND_ONLY_REVISION_WRITER',
    strpos(
        (string)$persistenceText,
        'INSERT INTO stock_consumption_allocation_revisions'
    ) !== false
    &&
    strpos(
        (string)$persistenceText,
        'INSERT INTO stock_consumption_allocation_revision_rows'
    ) !== false
    &&
    strpos(
        (string)$persistenceText,
        'UPDATE stock_consumption_allocation_revisions'
    ) === false
    &&
    strpos(
        (string)$persistenceText,
        'DELETE FROM stock_consumption_allocation_revisions'
    ) === false
);

$check(
    'STOCK_LEDGER_NOT_MUTATED',
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+stock_transactions/i',
        (string)$persistenceText
    ) !== 1
);

$check(
    'NO_OP_FINGERPRINT_GUARD',
    preg_match(
        '/\[\s*[\'"]state_fingerprint[\'"]\s*\]/',
        (string)$persistenceText
    ) === 1
    &&
    preg_match(
        '/\bhash_equals\s*\(/',
        (string)$persistenceText
    ) === 1
);

$check(
    'ACTOR_POLICY',
    strpos(
        (string)$persistenceText,
        'Stock allocation revision actor is required.'
    ) !== false
);

$check(
    'REASON_POLICY',
    strpos(
        (string)$persistenceText,
        "'update'"
    ) !== false
    &&
    strpos(
        (string)$persistenceText,
        "'clear'"
    ) !== false
    &&
    strpos(
        (string)$persistenceText,
        "'source_reversal'"
    ) !== false
);

$check(
    'SOURCE_REVERSAL_HOOK',
    function_exists(
        'stock_consumption_allocation_persistence_before_source_reversal'
    )
    &&
    strpos(
        (string)$persistenceText,
        'before_source_reversal'
    ) !== false
);

$check(
    'REVISION_CHAIN',
    strpos(
        (string)$persistenceText,
        'previous_revision_id'
    ) !== false
    &&
    strpos(
        (string)$persistenceText,
        'revision_no'
    ) !== false
);

/*
 * Source reversal is intentionally broader than new-allocation eligibility:
 * correction must remain possible for direct-cycle or source-drifted stock.
 */
$reversalStart =
    strpos(
        (string)$persistenceText,
        'function stock_consumption_allocation_persistence_before_source_reversal'
    );

$reversalSnippet =
    $reversalStart === false
        ? ''
        : substr(
            (string)$persistenceText,
            $reversalStart
        );

$check(
    'GENERIC_SOURCE_REVERSAL_POLICY',
    $reversalSnippet !== ''
    &&
    strpos(
        $reversalSnippet,
        'stock_consumption_allocation_persistence_lock_parent('
    ) === false
    &&
    strpos(
        $reversalSnippet,
        'stock_consumption_source_resolver_definition('
    ) === false
    &&
    strpos(
        $reversalSnippet,
        'stock_consumption_source_resolver_resolve('
    ) === false
    &&
    strpos(
        $reversalSnippet,
        'stock_consumption_source_resolver_assert_cycle_consistency('
    ) === false
    &&
    strpos(
        $reversalSnippet,
        'stock_consumption_allocation_persistence_movement('
    ) !== false
);

$check(
    'REVERSAL_DRIFT_CORRECTION_POLICY',
    strpos(
        $reversalSnippet,
        'stock_consumption_source_resolver_assert_cycle_consistency('
    ) === false
    &&
    strpos(
        $reversalSnippet,
        'stock_consumption_allocation_provenance_build('
    ) !== false
    &&
    strpos(
        $reversalSnippet,
        "'source_reversal'"
    ) !== false
);

$result =
    $failures
        ? 'FAIL'
        : 'PASS';

echo "RESULT={$result}\n";
echo "CHECK_COUNT={$checks}\n";

echo "PROVENANCE_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "CONSERVATION_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "LOCKING_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "CURRENT_PROJECTION_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "APPEND_ONLY_REVISION_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "NO_OP_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "ACTOR_REASON_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "SOURCE_REVERSAL_CONTRACT="
    . ($failures ? 'SEE_FAILURES' : 'PASS')
    . "\n";

echo "STOCK_LEDGER_MUTATION="
    . (
        preg_match(
            '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+stock_transactions/i',
            (string)$persistenceText
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
