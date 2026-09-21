<?php

require_once __DIR__
    . '/../lib/shared_cost_contract.php';

require_once __DIR__
    . '/../lib/stock_consumption_allocation_persistence.php';

$fail = 0;

$check =
    static function (
        string $name,
        bool $passed
    ) use (&$fail): void {
        echo $name
            . '='
            . (
                $passed
                    ? 'PASS'
                    : 'FAIL'
            )
            . PHP_EOL;

        if (!$passed) {
            $fail = 1;
        }
    };

$future = [
    'id' => 56,
    'start_date' => '2026-09-11',
];

$sameDay = [
    'id' => 55,
    'start_date' => '2026-09-10',
];

$older = [
    'id' => 43,
    'start_date' => '2026-08-19',
];

$futureRow = [
    'cycle_id' => 56,
    'allocated_amount' => '100.00',
];

$olderRow = [
    'cycle_id' => 43,
    'allocated_amount' => '100.00',
];

$check(
    'FUTURE_TARGET_IS_PRE_CYCLE',
    shared_cost_contract_is_pre_cycle(
        '2026-09-10',
        $future
    ) === true
);

$check(
    'SAME_DAY_TARGET_IS_NOT_PRE_CYCLE',
    shared_cost_contract_is_pre_cycle(
        '2026-09-10',
        $sameDay
    ) === false
);

$check(
    'ALREADY_STARTED_TARGET_IS_NOT_PRE_CYCLE',
    shared_cost_contract_is_pre_cycle(
        '2026-09-10',
        $older
    ) === false
);

$blankRejected = false;

try {
    shared_cost_contract_assert_pre_cycle_reason(
        '2026-09-10',
        [$future],
        [$futureRow],
        null
    );
} catch (InvalidArgumentException $e) {
    $blankRejected =
        strpos(
            $e->getMessage(),
            'future-start cycle'
        ) !== false;
}

$check(
    'PRE_CYCLE_WITHOUT_REASON_REJECTED',
    $blankRejected
);

$reasonAccepted = true;

try {
    shared_cost_contract_assert_pre_cycle_reason(
        '2026-09-10',
        [$future],
        [$futureRow],
        'Disinfected the pen before birds were placed.'
    );
} catch (Throwable $e) {
    $reasonAccepted = false;
}

$check(
    'PRE_CYCLE_WITH_REASON_ACCEPTED',
    $reasonAccepted
);

$ordinaryAccepted = true;

try {
    shared_cost_contract_assert_pre_cycle_reason(
        '2026-09-10',
        [$older],
        [$olderRow],
        null
    );
} catch (Throwable $e) {
    $ordinaryAccepted = false;
}

$check(
    'ORDINARY_TARGET_NEEDS_NO_PRE_CYCLE_REASON',
    $ordinaryAccepted
);

$check(
    'STOCK_FIRST_ALLOCATION_REASON_PRESERVED',
    stock_consumption_allocation_persistence_reason(
        'create',
        'Prepared housing before cycle start.'
    ) === 'Prepared housing before cycle start.'
);

$check(
    'STOCK_ORDINARY_FIRST_ALLOCATION_BLANK_REASON_ALLOWED',
    stock_consumption_allocation_persistence_reason(
        'create',
        null
    ) === null
);

$root =
    dirname(__DIR__);

$shared =
    file_get_contents(
        $root . '/lib/shared_cost_contract.php'
    );

$stockPersistence =
    file_get_contents(
        $root
        . '/lib/stock_consumption_allocation_persistence.php'
    );

$stockWorkspace =
    file_get_contents(
        $root
        . '/lib/stock_consumption_allocation_workspace.php'
    );

$stockPage =
    file_get_contents(
        $root
        . '/management/stock_consumption_allocation.php'
    );

$stockJs =
    file_get_contents(
        $root
        . '/assets/js/stock-consumption-allocation-workspace.js'
    );

$expenseService =
    file_get_contents(
        $root
        . '/lib/financial_allocation_service.php'
    );

$expensePersistence =
    file_get_contents(
        $root
        . '/lib/financial_allocation_persistence.php'
    );

$expenseWorkspace =
    file_get_contents(
        $root
        . '/lib/financial_allocation_workspace.php'
    );

$expensePage =
    file_get_contents(
        $root
        . '/management/expense_allocation.php'
    );

$check(
    'CENTRAL_POLICY_PRESENT',
    strpos(
        $shared,
        'function shared_cost_contract_is_pre_cycle'
    ) !== false
    &&
    strpos(
        $shared,
        'function shared_cost_contract_assert_pre_cycle_reason'
    ) !== false
);

$stockTargets =
    strpos(
        $stockPersistence,
        'function stock_consumption_allocation_persistence_target_cycles'
    );

$stockApply =
    strpos(
        $stockPersistence,
        'function stock_consumption_allocation_persistence_apply'
    );

$stockGate =
    strpos(
        $stockPersistence,
        'shared_cost_contract_assert_pre_cycle_reason',
        $stockApply
    );

$stockWrite =
    strpos(
        $stockPersistence,
        'stock_consumption_allocation_persistence_write_projection',
        $stockApply
    );

$check(
    'STOCK_LOCKED_TARGET_INCLUDES_START_DATE',
    $stockTargets !== false
    &&
    strpos(
        $stockPersistence,
        'start_date',
        $stockTargets
    ) !== false
);

$check(
    'STOCK_PRE_CYCLE_GATE_BEFORE_WRITE',
    $stockGate !== false
    &&
    $stockWrite !== false
    &&
    $stockGate < $stockWrite
);

$check(
    'STOCK_WORKSPACE_EXPOSES_PRE_CYCLE',
    strpos(
        $stockWorkspace,
        "'is_pre_cycle'"
    ) !== false
);

$check(
    'STOCK_UI_MARKS_PRE_CYCLE',
    strpos(
        $stockPage,
        'Pre-cycle preparation'
    ) !== false
    &&
    strpos(
        $stockPage,
        'data-pre-cycle='
    ) !== false
);

$check(
    'STOCK_CLIENT_VALIDATES_PRE_CYCLE_REASON',
    strpos(
        $stockJs,
        'dataset.preCycle'
    ) !== false
    &&
    strpos(
        $stockJs,
        'future-start cycle'
    ) !== false
);

$expenseTargets =
    strpos(
        $expenseService,
        'function financial_allocation_service_target_cycles'
    );

$expenseApply =
    strpos(
        $expensePersistence,
        'function financial_allocation_persistence_apply'
    );

$expenseGate =
    strpos(
        $expensePersistence,
        'shared_cost_contract_assert_pre_cycle_reason',
        $expenseApply
    );

/*
 * financial_allocation_persistence_apply() legitimately calls
 * expense_revision_service_prepare_existing_mutation() inside its
 * semantic no-op consistency branch before the real-mutation path.
 *
 * The pre-cycle gate is intentionally AFTER that no-op return.
 * Therefore verify against the first real-mutation provenance call
 * occurring AFTER the gate, not the earlier no-op consistency call.
 */
$expenseRevisionMutation =
    $expenseGate === false
        ? false
        : strpos(
            $expensePersistence,
            'expense_revision_service_prepare_existing_mutation',
            $expenseGate
        );

$check(
    'EXPENSE_LOCKED_TARGET_INCLUDES_START_DATE',
    $expenseTargets !== false
    &&
    strpos(
        $expenseService,
        'start_date',
        $expenseTargets
    ) !== false
);

$check(
    'EXPENSE_PRE_CYCLE_GATE_BEFORE_REVISION_MUTATION',
    $expenseGate !== false
    &&
    $expenseRevisionMutation !== false
    &&
    $expenseGate < $expenseRevisionMutation
);

$check(
    'EXPENSE_WORKSPACE_EXPOSES_PRE_CYCLE',
    strpos(
        $expenseWorkspace,
        "'is_pre_cycle'"
    ) !== false
);

$check(
    'EXPENSE_UI_MARKS_PRE_CYCLE',
    strpos(
        $expensePage,
        'Pre-cycle preparation'
    ) !== false
    &&
    strpos(
        $expensePage,
        'pre-cycle operating'
    ) !== false
);

echo "DATABASE_REQUIRED=NO\n";
echo "BUSINESS_ROW_MUTATION=NO\n";
echo "MIGRATION_REQUIRED=NO\n";
echo "HISTORICAL_ALLOCATION_AUTO_CORRECTION=NO\n";

echo 'PRE_CYCLE_POLICY_VERIFIER='
    . (
        $fail === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit($fail);
