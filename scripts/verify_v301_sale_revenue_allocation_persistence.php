<?php

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/lib/sale_revenue_allocation_service.php';

$provenancePath =
    $root
    . '/lib/sale_revenue_allocation_provenance.php';

$persistencePath =
    $root
    . '/lib/sale_revenue_allocation_persistence.php';

foreach (
    [
        $servicePath,
        $provenancePath,
        $persistencePath,
    ]
    as $path
) {
    if (!is_file($path)) {
        echo "RESULT=FAIL\n";
        echo "CHECK_COUNT=0\n";
        echo "FAILED=SOURCE_FILE_MISSING\n";
        exit(1);
    }
}

$service =
    file_get_contents(
        $servicePath
    );

$provenance =
    file_get_contents(
        $provenancePath
    );

$persistence =
    file_get_contents(
        $persistencePath
    );

if (
    !is_string($service)
    ||
    !is_string($provenance)
    ||
    !is_string($persistence)
) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_READ_FAILED\n";
    exit(1);
}

require_once $provenancePath;
require_once $persistencePath;

$checks = [];

$check =
    static function (
        string $name,
        bool $passed
    ) use (&$checks): void {
        $checks[$name] =
            $passed;
    };

$throws =
    static function (
        callable $callback,
        string $messagePart
    ): bool {
        try {
            $callback();

        } catch (Throwable $e) {
            return
                strpos(
                    strtolower(
                        $e->getMessage()
                    ),
                    strtolower(
                        $messagePart
                    )
                ) !== false;
        }

        return false;
    };


/* =========================================================
 * PURE PROVENANCE
 * ========================================================= */

$sale = [
    'id' => 33,
    'farm_id' => 4,
    'public_reference' =>
        'RA-SA-20260918-TGWHX42JZD',
    'sale_date' =>
        '2026-09-18',
    'farm_type' =>
        'poultry',
    'production_type' =>
        'shared',
    'attribution_scope' =>
        'farm',
    'cycle_id' =>
        null,
    'product_type' =>
        'Manure',
    'quantity' =>
        '2.00',
    'unit_of_measure' =>
        'Bag',
    'unit_price' =>
        '2500.00',
    'total_amount' =>
        '5000.00',
];

$rowsA = [
    [
        'cycle_id' => 55,
        'allocated_amount' => '3000.00',
        'allocation_percent' => '60.0000',
        'allocation_basis' =>
            'manual_shared_revenue',
        'allocated_quantity' => null,
        'allocation_unit' => null,
        'notes' => 'Layer share',
    ],
    [
        'cycle_id' => 40,
        'allocated_amount' => '1000.00',
        'allocation_percent' => '20.0000',
        'allocation_basis' =>
            'manual_shared_revenue',
        'allocated_quantity' => null,
        'allocation_unit' => null,
        'notes' => 'Broiler share',
    ],
];

$rowsB =
    array_reverse(
        $rowsA
    );

$a =
    sale_revenue_allocation_provenance_build(
        $sale,
        $rowsA
    );

$b =
    sale_revenue_allocation_provenance_build(
        $sale,
        $rowsB
    );

$check(
    'PROVENANCE_PARENT_5000',
    ($a['parent_amount'] ?? '')
        === '5000.00'
);

$check(
    'PROVENANCE_ALLOCATED_4000',
    ($a['allocated_amount'] ?? '')
        === '4000.00'
);

$check(
    'PROVENANCE_UNALLOCATED_1000',
    ($a['unallocated_amount'] ?? '')
        === '1000.00'
);

$check(
    'PROVENANCE_ROW_ORDER_DETERMINISTIC',
    ($a['causal_fingerprint'] ?? '')
        === ($b['causal_fingerprint'] ?? '')
    &&
    ($a['state_fingerprint'] ?? '')
        === ($b['state_fingerprint'] ?? '')
);

$check(
    'PROVENANCE_SHA256_LENGTH',
    strlen(
        (string)(
            $a['causal_fingerprint']
            ?? ''
        )
    ) === 64
    &&
    strlen(
        (string)(
            $a['state_fingerprint']
            ?? ''
        )
    ) === 64
);

$check(
    'PROVENANCE_INCLUDES_PUBLIC_REFERENCE',
    strpos(
        (string)$a['state_manifest_json'],
        'RA-SA-20260918-TGWHX42JZD'
    ) !== false
);

$check(
    'PROVENANCE_INCLUDES_PARENT_SCOPE',
    strpos(
        (string)$a['state_manifest_json'],
        '"production_type":"shared"'
    ) !== false
    &&
    strpos(
        (string)$a['state_manifest_json'],
        '"attribution_scope":"farm"'
    ) !== false
);

$check(
    'PROVENANCE_IS_PURE_NO_SQL',
    strpos(
        $provenance,
        '->prepare('
    ) === false
    &&
    strpos(
        $provenance,
        '->query('
    ) === false
    &&
    strpos(
        $provenance,
        '->exec('
    ) === false
);


/* =========================================================
 * PERSISTENCE BOUNDARY
 * ========================================================= */

$check(
    'PERSISTENCE_REQUIRES_CALLER_TRANSACTION',
    strpos(
        $persistence,
        'sale_revenue_allocation_persistence_require_transaction('
    ) !== false
    &&
    strpos(
        $persistence,
        '$pdo->inTransaction()'
    ) !== false
);

$check(
    'PERSISTENCE_HAS_NO_TRANSACTION_OWNERSHIP',
    strpos(
        $persistence,
        'beginTransaction('
    ) === false
    &&
    strpos(
        $persistence,
        '->commit('
    ) === false
    &&
    strpos(
        $persistence,
        '->rollBack('
    ) === false
);

$createReason500 =
    str_repeat(
        'x',
        500
    );

$check(
    'CREATE_REASON_500_ACCEPTED',
    sale_revenue_allocation_persistence_reason(
        'create',
        $createReason500
    ) === $createReason500
);

$check(
    'CREATE_REASON_501_REJECTED',
    $throws(
        static function (): void {
            sale_revenue_allocation_persistence_reason(
                'create',
                str_repeat(
                    'x',
                    501
                )
            );
        },
        'cannot exceed 500 characters'
    )
);

$check(
    'PARENT_LOCKS_SALE_FOR_UPDATE',
    strpos(
        $persistence,
        "FROM sales_records"
    ) !== false
    &&
    strpos(
        $persistence,
        '" FOR UPDATE"'
    ) !== false
);

$check(
    'CURRENT_PROJECTION_LOCKED',
    strpos(
        $persistence,
        "FROM sales_allocations"
    ) !== false
    &&
    strpos(
        $persistence,
        'sale_revenue_allocation_persistence_current_rows('
    ) !== false
);

$check(
    'ANIMAL_ALLOCATION_STATE_LOCKED',
    strpos(
        $persistence,
        'FROM ruminant_sale_animal_allocations'
    ) !== false
);

$check(
    'TARGET_CYCLES_LOCKED',
    strpos(
        $persistence,
        'FROM production_cycles'
    ) !== false
    &&
    strpos(
        $persistence,
        'sale_revenue_allocation_persistence_target_cycles('
    ) !== false
);

$check(
    'LATEST_REVISION_LOCKED',
    strpos(
        $persistence,
        'FROM sales_allocation_revisions'
    ) !== false
    &&
    strpos(
        $persistence,
        'sale_revenue_allocation_persistence_latest_revision('
    ) !== false
);

$check(
    'REVISION_DETAIL_LOCKED',
    strpos(
        $persistence,
        'FROM sales_allocation_revision_rows'
    ) !== false
);

$check(
    'ONLY_MANUAL_BASIS_OWNED',
    strpos(
        $persistence,
        "!== 'manual_shared_revenue'"
    ) !== false
    &&
    strpos(
        $persistence,
        'owned by another allocation contract'
    ) !== false
);

$check(
    'PHYSICAL_QUANTITY_FORBIDDEN',
    strpos(
        $persistence,
        'must not invent physical quantity ownership'
    ) !== false
);

$check(
    'CURRENT_STATE_VALIDATED_BY_SERVICE',
    substr_count(
        $persistence,
        'sale_revenue_allocation_service_validate_desired_rows('
    ) >= 3
);

$check(
    'PROJECTION_CONSISTENCY_FAILS_CLOSED',
    strpos(
        $persistence,
        'current projection changed outside the canonical contract'
    ) !== false
);

$check(
    'LEGACY_UNPROVENANCED_MANUAL_STATE_REJECTED',
    strpos(
        $persistence,
        'exists without revision provenance'
    ) !== false
);

$check(
    'REVISION_FINGERPRINT_CHECKED',
    strpos(
        $persistence,
        "hash_equals("
    ) !== false
    &&
    strpos(
        $persistence,
        "['causal_fingerprint']"
    ) !== false
    &&
    strpos(
        $persistence,
        "['state_fingerprint']"
    ) !== false
);

$check(
    'REVISION_DETAIL_MATCH_CHECKED',
    strpos(
        $persistence,
        'revision detail does not match the current projection'
    ) !== false
);

$check(
    'NO_PARENT_SALE_MUTATION_SQL',
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM)\s+sales_records\b/i',
        $persistence
    ) !== 1
);

$check(
    'PROJECTION_DELETE_IS_TARGETED',
    strpos(
        $persistence,
        "DELETE FROM sales_allocations\n                 WHERE farm_id=?\n                   AND sale_id=?\n                   AND cycle_id=?"
    ) !== false
);

$check(
    'PROJECTION_UPDATE_IS_TARGETED',
    strpos(
        $persistence,
        "WHERE farm_id=?\n                       AND sale_id=?\n                       AND cycle_id=?"
    ) !== false
);

$check(
    'PROJECTION_INSERT_USES_MANUAL_BASIS',
    strpos(
        $persistence,
        "'manual_shared_revenue'"
    ) !== false
    &&
    strpos(
        $persistence,
        'INSERT INTO sales_allocations'
    ) !== false
);

$check(
    'PROJECTION_INSERT_QUANTITY_NULL',
    strpos(
        $persistence,
        "NULL,\n                        NULL,\n                        ?,\n                        'manual_shared_revenue'"
    ) !== false
);

$check(
    'REVISION_HEADER_APPEND_ONLY_INSERT',
    strpos(
        $persistence,
        'INSERT INTO sales_allocation_revisions'
    ) !== false
    &&
    preg_match(
        '/\bUPDATE\s+sales_allocation_revisions\b/i',
        $persistence
    ) !== 1
    &&
    preg_match(
        '/\bDELETE\s+FROM\s+sales_allocation_revisions\b/i',
        $persistence
    ) !== 1
);

$check(
    'REVISION_ROWS_APPEND_ONLY_INSERT',
    strpos(
        $persistence,
        'INSERT INTO sales_allocation_revision_rows'
    ) !== false
    &&
    preg_match(
        '/\bUPDATE\s+sales_allocation_revision_rows\b/i',
        $persistence
    ) !== 1
    &&
    preg_match(
        '/\bDELETE\s+FROM\s+sales_allocation_revision_rows\b/i',
        $persistence
    ) !== 1
);

$check(
    'REVISION_CHAIN_USES_PREVIOUS_ID',
    strpos(
        $persistence,
        'previous_revision_id'
    ) !== false
    &&
    strpos(
        $persistence,
        '$previousId'
    ) !== false
);

$check(
    'NOOP_DOES_NOT_APPEND_REVISION',
    strpos(
        $persistence,
        "if (\$currentSemantic === \$desiredSemantic)"
    ) !== false
    &&
    strpos(
        $persistence,
        "'action' =>\n                'noop'"
    ) !== false
);

$check(
    'CREATE_UPDATE_CLEAR_ACTIONS_SUPPORTED',
    strpos(
        $persistence,
        "? 'create'"
    ) !== false
    &&
    strpos(
        $persistence,
        "? 'clear'"
    ) !== false
    &&
    strpos(
        $persistence,
        ": 'update'"
    ) !== false
);

$check(
    'REVISION_REASON_REQUIRED_AFTER_CREATE',
    strpos(
        $persistence,
        "if (\$action === 'create')"
    ) !== false
    &&
    strpos(
        $persistence,
        'Enter a reason for changing this shared revenue allocation.'
    ) !== false
);

$check(
    'POST_WRITE_REVALIDATION_PRESENT',
    strpos(
        $persistence,
        'Shared revenue allocation projection did not reach the requested state.'
    ) !== false
);

$check(
    'POST_REVISION_CONSISTENCY_PRESENT',
    substr_count(
        $persistence,
        'sale_revenue_allocation_persistence_assert_revision_consistency('
    ) >= 3
);


/* =========================================================
 * FOUNDATIONAL SERVICE STILL SEPARATES AUTO LAYER EGGS
 * ========================================================= */

$check(
    'AUTOMATIC_LAYER_EGG_STILL_EXCLUDED',
    strpos(
        $service,
        'Layer egg revenue is managed by the automatic unsold-egg allocation contract.'
    ) !== false
);

$check(
    'PARENT_SALE_REMAINS_SINGLE_REVENUE_SOURCE',
    strpos(
        $persistence,
        'sales_records is never mutated here'
    ) !== false
);


$failed = [];

foreach ($checks as $name => $passed) {
    echo $name
        . '='
        . ($passed ? 'PASS' : 'FAIL')
        . PHP_EOL;

    if (!$passed) {
        $failed[] =
            $name;
    }
}

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITES=NONE\n";

if ($failed) {
    echo 'FAILED='
        . implode(
            ',',
            $failed
        )
        . PHP_EOL;

    echo "RESULT=FAIL\n";
    exit(1);
}

echo "RESULT=PASS\n";
exit(0);
