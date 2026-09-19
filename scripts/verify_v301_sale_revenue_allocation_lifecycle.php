<?php

$root =
    dirname(__DIR__);

$allocationPath =
    $root
    . '/lib/sales_allocation.php';

$salesPath =
    $root
    . '/management/sales_records.php';

$deletePath =
    $root
    . '/api/delete_sale.php';

foreach (
    [
        $allocationPath,
        $salesPath,
        $deletePath,
    ]
    as $path
) {
    if (!is_file($path)) {
        echo "CHECK_COUNT=0\n";
        echo "FAILED=SOURCE_FILE_MISSING\n";
        echo "RESULT=FAIL\n";
        exit(1);
    }
}

$allocation =
    file_get_contents(
        $allocationPath
    );

$sales =
    file_get_contents(
        $salesPath
    );

$delete =
    file_get_contents(
        $deletePath
    );

$checks = [];

$check =
    static function (
        string $name,
        bool $passed
    ) use (&$checks): void {
        $checks[$name] =
            $passed;
    };


/* =========================================================
 * CENTRAL LIFECYCLE AUTHORITY
 * ========================================================= */

$check(
    'LIFECYCLE_EXCEPTION_PRESENT',
    strpos(
        $allocation,
        'class SaleRevenueAllocationLifecycleException'
    ) !== false
);

$check(
    'PROVENANCE_CONTRACT_LOADED',
    strpos(
        $allocation,
        "sale_revenue_allocation_provenance.php"
    ) !== false
);

$check(
    'LIFECYCLE_REQUIRES_TRANSACTION_FOR_LOCKED_MUTATION',
    strpos(
        $allocation,
        'sales_manual_revenue_allocation_require_transaction'
    ) !== false
    &&
    strpos(
        $allocation,
        '$pdo->inTransaction()'
    ) !== false
);

$check(
    'PARENT_LOCK_PRESENT',
    strpos(
        $allocation,
        'FROM sales_records'
    ) !== false
    &&
    strpos(
        $allocation,
        'FOR UPDATE'
    ) !== false
);

$check(
    'MANUAL_PROJECTION_STATE_SCOPED',
    strpos(
        $allocation,
        'FROM sales_allocations'
    ) !== false
    &&
    strpos(
        $allocation,
        "WHERE farm_id=?\n           AND sale_id=?"
    ) !== false
);

$check(
    'REVISION_HISTORY_STATE_SCOPED',
    strpos(
        $allocation,
        'FROM sales_allocation_revisions'
    ) !== false
);

$check(
    'ANIMAL_REVENUE_STATE_SCOPED',
    strpos(
        $allocation,
        'FROM ruminant_sale_animal_allocations'
    ) !== false
);

$check(
    'UNPROVENANCED_MANUAL_STATE_FAILS_CLOSED',
    strpos(
        $allocation,
        'exists without immutable revision provenance'
    ) !== false
);

$check(
    'MIXED_ALLOCATION_AUTHORITY_FAILS_CLOSED',
    strpos(
        $allocation,
        'conflicting revenue allocation ownership'
    ) !== false
);

$check(
    'ANIMAL_OVERLAP_FAILS_CLOSED',
    strpos(
        $allocation,
        'cannot overlap individual-animal revenue allocation'
    ) !== false
);


/* =========================================================
 * CLEAR / REFRESH PROTECTION
 * ========================================================= */

$clearStart =
    strpos(
        $allocation,
        'function sales_clear_allocations('
    );

$clearDelete =
    strpos(
        $allocation,
        'DELETE FROM sales_allocations',
        $clearStart === false
            ? 0
            : $clearStart
    );

$clearProtectedCheck =
    strpos(
        $allocation,
        "if (!empty(\$state['protected']))",
        $clearStart === false
            ? 0
            : $clearStart
    );

$check(
    'CLEAR_GUARD_PRECEDES_DELETE',
    $clearStart !== false
    &&
    $clearProtectedCheck !== false
    &&
    $clearDelete !== false
    &&
    $clearProtectedCheck < $clearDelete
);

$check(
    'CLEAR_PROTECTED_MESSAGE_PRESENT',
    strpos(
        $allocation,
        'protects this sale from automatic allocation clearing'
    ) !== false
);

$check(
    'REFRESH_LOCKS_PARENT_WHEN_TRANSACTIONAL',
    strpos(
        $allocation,
        '$sql .= " FOR UPDATE";'
    ) !== false
);

$check(
    'REFRESH_IDENTIFIES_DIRECT_AUTHORITY',
    strpos(
        $allocation,
        '$isDirect'
    ) !== false
);

$check(
    'REFRESH_IDENTIFIES_AUTO_LAYER_EGG_AUTHORITY',
    strpos(
        $allocation,
        '$isAutomaticLayerEgg'
    ) !== false
);

$check(
    'REFRESH_BLOCKS_MANUAL_TO_DIRECT_OR_AUTO',
    strpos(
        $allocation,
        'prevents changing this sale to direct or automatic allocation authority'
    ) !== false
);

$check(
    'REFRESH_PRESERVES_ORDINARY_MANUAL_ALLOCATION',
    strpos(
        $allocation,
        'Manual shared revenue allocation was preserved; automatic refresh was skipped.'
    ) !== false
);

$check(
    'REFRESH_REPORTS_MANUAL_BASIS',
    strpos(
        $allocation,
        "\$status['allocation_basis'] =\n            'manual_shared_revenue';"
    ) !== false
);


/* =========================================================
 * SALES EDIT PROTECTION
 * ========================================================= */

$guardPos =
    strpos(
        $sales,
        'sales_assert_manual_revenue_edit_allowed('
    );

$receivablePos =
    strpos(
        $sales,
        'receivable_sync_sale_edit('
    );

$updatePos =
    strpos(
        $sales,
        'UPDATE sales_records'
    );

$check(
    'EDIT_GUARD_PRESENT',
    $guardPos !== false
);

$check(
    'EDIT_GUARD_PRECEDES_RECEIVABLE_MUTATION',
    $guardPos !== false
    &&
    $receivablePos !== false
    &&
    $guardPos < $receivablePos
);

$check(
    'EDIT_GUARD_PRECEDES_PARENT_UPDATE',
    $guardPos !== false
    &&
    $updatePos !== false
    &&
    $guardPos < $updatePos
);

$check(
    'EDIT_UNLOCKED_BEFORE_SNAPSHOT_REMOVED',
    strpos(
        $sales,
        '$beforeSaleStmt'
    ) === false
);

$check(
    'EDIT_GUARD_COVERS_SALE_DATE',
    strpos(
        $sales,
        "'sale_date' =>\n                        (string)\$_POST['sale_date']"
    ) !== false
);

$check(
    'EDIT_GUARD_COVERS_ATTRIBUTION',
    strpos(
        $sales,
        "'attribution_scope' =>\n                        \$scope"
    ) !== false
    &&
    strpos(
        $sales,
        "'cycle_id' =>"
    ) !== false
);

$check(
    'EDIT_GUARD_COVERS_ECONOMIC_FIELDS',
    strpos(
        $sales,
        "'quantity' =>"
    ) !== false
    &&
    strpos(
        $sales,
        "'unit_of_measure' =>"
    ) !== false
    &&
    strpos(
        $sales,
        "'unit_price' =>"
    ) !== false
    &&
    strpos(
        $sales,
        "'total_amount' =>"
    ) !== false
);

$check(
    'EDIT_GUARD_COVERS_PROPOSED_ANIMAL_ALLOCATION',
    strpos(
        $sales,
        "count(\n                    \$animalRevenueAllocation['rows']"
    ) !== false
);

$check(
    'EDIT_LIFECYCLE_ERROR_USER_VISIBLE',
    strpos(
        $sales,
        'catch (SaleRevenueAllocationLifecycleException $e)'
    ) !== false
    &&
    strpos(
        $sales,
        "\$_SESSION['error'] =\n                \$e->getMessage();"
    ) !== false
);


/* =========================================================
 * DELETE PROTECTION
 * ========================================================= */

$deleteGuardPos =
    strpos(
        $delete,
        'sales_assert_manual_revenue_delete_allowed('
    );

$parentDeletePos =
    strpos(
        $delete,
        'DELETE FROM sales_records'
    );

$ledgerDeletePos =
    strpos(
        $delete,
        'DELETE FROM customer_ledger_entries'
    );

$check(
    'DELETE_GUARD_PRESENT',
    $deleteGuardPos !== false
);

$check(
    'DELETE_GUARD_PRECEDES_LEDGER_DELETE',
    $deleteGuardPos !== false
    &&
    $ledgerDeletePos !== false
    &&
    $deleteGuardPos < $ledgerDeletePos
);

$check(
    'DELETE_GUARD_PRECEDES_PARENT_DELETE',
    $deleteGuardPos !== false
    &&
    $parentDeletePos !== false
    &&
    $deleteGuardPos < $parentDeletePos
);

$check(
    'DELETE_LIFECYCLE_CONFLICT_IS_409',
    strpos(
        $delete,
        'catch(SaleRevenueAllocationLifecycleException $e)'
    ) !== false
    &&
    strpos(
        $delete,
        "send_json(['success'=>false,'error'=>\$e->getMessage()],409)"
    ) !== false
);


/* =========================================================
 * MANUAL WRITER REMAINS SEPARATE
 * ========================================================= */

$check(
    'LIFECYCLE_DOES_NOT_CALL_MANUAL_PERSISTENCE_WRITER',
    strpos(
        $allocation,
        'sale_revenue_allocation_persistence_apply('
    ) === false
    &&
    strpos(
        $sales,
        'sale_revenue_allocation_persistence_apply('
    ) === false
    &&
    strpos(
        $delete,
        'sale_revenue_allocation_persistence_apply('
    ) === false
);

$check(
    'SALES_CLEAR_REMAINS_SINGLE_TARGETED_DELETE',
    substr_count(
        $allocation,
        'DELETE FROM sales_allocations'
    ) === 1
);

$check(
    'NO_REVISION_HISTORY_MUTATION_IN_LIFECYCLE',
    strpos(
        $allocation,
        'INSERT INTO sales_allocation_revisions'
    ) === false
    &&
    strpos(
        $allocation,
        'UPDATE sales_allocation_revisions'
    ) === false
    &&
    strpos(
        $allocation,
        'DELETE FROM sales_allocation_revisions'
    ) === false
);


$failed = [];

foreach ($checks as $name => $passed) {
    echo
        $name
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
