<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$revision =
    (string)file_get_contents(
        $root
        . '/lib/expense_revision_service.php'
    );

$persistence =
    (string)file_get_contents(
        $root
        . '/lib/financial_allocation_persistence.php'
    );

$api =
    (string)file_get_contents(
        $root
        . '/api/update_financial_allocation.php'
    );

$checks = 0;
$failed = 0;

function retained_cost_check(
    string $name,
    bool $condition
): void {
    global $checks, $failed;

    $checks++;

    echo
        $name
        . '='
        . (
            $condition
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$condition) {
        $failed++;
    }
}


retained_cost_check(
    'EXPENSE_REVISION_ALLOWS_RETAIN_SHARED',
    strpos(
        $revision,
        "'retain_shared',"
    ) !== false
);


retained_cost_check(
    'RETAIN_SHARED_REASON_REQUIRED',
    strpos(
        $revision,
        'Enter a reason for retaining this expense as shared.'
    ) !== false
);


retained_cost_check(
    'DEDICATED_EXPENSE_DECISION_REVISION_WRITER',
    strpos(
        $revision,
        'function expense_revision_service_record_retained_shared'
    ) !== false
);


retained_cost_check(
    'DEDICATED_COST_PERSISTENCE_WRITER',
    strpos(
        $persistence,
        'function financial_allocation_persistence_retain_shared'
    ) !== false
);


retained_cost_check(
    'RETAIN_SHARED_REQUIRES_ZERO_CYCLE_ALLOCATION',
    strpos(
        $persistence,
        'Only fully unallocated shared costs can be retained entirely as shared.'
    ) !== false
);


retained_cost_check(
    'RETAIN_SHARED_REJECTS_ANIMAL_ALLOCATION',
    strpos(
        $persistence,
        'A cost allocated to individual animals cannot also be retained as shared.'
    ) !== false
);


retained_cost_check(
    'RETAIN_SHARED_IS_IDEMPOTENT',
    preg_match(
        "/revision_action'[\\s\\S]{0,250}retain_shared[\\s\\S]{0,500}'changed'[\\s\\S]{0,80}false/",
        $persistence
    ) === 1
);


retained_cost_check(
    'RETAIN_SHARED_USES_CANONICAL_PARENT_SERVICE',
    strpos(
        $persistence,
        'financial_allocation_service_parent('
    ) !== false
);


retained_cost_check(
    'RETAIN_SHARED_USES_CANONICAL_VALIDATION',
    strpos(
        $persistence,
        'financial_allocation_service_validate_desired_rows('
    ) !== false
);


retained_cost_check(
    'RETAIN_SHARED_ESTABLISHES_OR_VERIFIES_EXPENSE_PROVENANCE',
    strpos(
        $persistence,
        'expense_revision_service_prepare_existing_mutation('
    ) !== false
);


retained_cost_check(
    'RETAIN_SHARED_RECORDS_DEDICATED_DECISION_REVISION',
    strpos(
        $persistence,
        'expense_revision_service_record_retained_shared('
    ) !== false
);


$retainStart =
    strpos(
        $persistence,
        'function financial_allocation_persistence_retain_shared'
    );

$applyStart =
    strpos(
        $persistence,
        'function financial_allocation_persistence_apply'
    );

$retainBlock =
    (
        $retainStart !== false
        && $applyStart !== false
        && $applyStart > $retainStart
    )
        ? substr(
            $persistence,
            $retainStart,
            $applyStart - $retainStart
        )
        : '';


retained_cost_check(
    'RETAIN_SHARED_DOES_NOT_WRITE_ALLOCATION_PROJECTION',
    $retainBlock !== ''
    &&
    strpos(
        $retainBlock,
        'INSERT INTO financial_allocations'
    ) === false
    &&
    strpos(
        $retainBlock,
        'UPDATE financial_allocations'
    ) === false
    &&
    strpos(
        $retainBlock,
        'DELETE FROM financial_allocations'
    ) === false
);


retained_cost_check(
    'API_ACCEPTS_RETAIN_SHARED_DECISION',
    strpos(
        $api,
        "'retain_shared'"
    ) !== false
);


retained_cost_check(
    'API_REQUIRES_EMPTY_ROWS_FOR_RETAIN_SHARED',
    strpos(
        $api,
        'Clear cycle amounts before retaining this cost as shared.'
    ) !== false
);


retained_cost_check(
    'API_ROUTES_TO_DEDICATED_RETAIN_WRITER',
    strpos(
        $api,
        'financial_allocation_persistence_retain_shared('
    ) !== false
);


retained_cost_check(
    'API_DEFAULT_REMAINS_ALLOCATION',
    strpos(
        $api,
        "?? 'allocate'"
    ) !== false
);


retained_cost_check(
    'API_RETAIN_SUCCESS_MESSAGE_EXISTS',
    strpos(
        $api,
        'Cost retained as shared successfully.'
    ) !== false
);


retained_cost_check(
    'NO_NEW_SHARED_COST_TABLE_REFERENCED',
    strpos(
        $persistence,
        'shared_cost_decisions'
    ) === false
    &&
    strpos(
        $revision,
        'shared_cost_decisions'
    ) === false
);


echo
    'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . $failed
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
