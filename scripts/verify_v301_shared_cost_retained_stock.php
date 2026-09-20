<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$persistence =
    (string)file_get_contents(
        $root
        . '/lib/stock_consumption_allocation_persistence.php'
    );

$api =
    (string)file_get_contents(
        $root
        . '/api/update_stock_consumption_allocation.php'
    );

$checks = 0;
$failed = 0;

function stock_retained_check(
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


stock_retained_check(
    'RETAIN_SHARED_REASON_ACTION_SUPPORTED',
    strpos(
        $persistence,
        "'retain_shared',"
    ) !== false
);


stock_retained_check(
    'RETAIN_SHARED_REASON_REQUIRED',
    strpos(
        $persistence,
        'Enter a reason for retaining this consumed stock cost as shared.'
    ) !== false
);


stock_retained_check(
    'DEDICATED_STOCK_RETAIN_WRITER',
    strpos(
        $persistence,
        'function stock_consumption_allocation_persistence_retain_shared'
    ) !== false
);


stock_retained_check(
    'RETAIN_SHARED_REQUIRES_ZERO_PROJECTION',
    strpos(
        $persistence,
        'Only fully unallocated consumed-stock costs can be retained entirely as shared.'
    ) !== false
);


stock_retained_check(
    'RETAIN_SHARED_USES_PARENT_LOCK',
    strpos(
        $persistence,
        'stock_consumption_allocation_persistence_lock_parent('
    ) !== false
);


stock_retained_check(
    'RETAIN_SHARED_USES_CURRENT_CONSISTENCY',
    strpos(
        $persistence,
        'stock_consumption_allocation_persistence_assert_current_consistency('
    ) !== false
);


stock_retained_check(
    'RETAIN_SHARED_USES_CANONICAL_ROW_VALIDATION',
    strpos(
        $persistence,
        'stock_consumption_allocation_persistence_validate_rows('
    ) !== false
);


stock_retained_check(
    'RETAIN_SHARED_USES_CANONICAL_PROVENANCE',
    strpos(
        $persistence,
        'stock_consumption_allocation_provenance_build('
    ) !== false
);


stock_retained_check(
    'RETAIN_SHARED_APPENDS_NATIVE_STOCK_REVISION',
    strpos(
        $persistence,
        'stock_consumption_allocation_persistence_insert_revision('
    ) !== false
);


$retainStart =
    strpos(
        $persistence,
        'function stock_consumption_allocation_persistence_retain_shared'
    );

$applyStart =
    strpos(
        $persistence,
        'function stock_consumption_allocation_persistence_apply',
        $retainStart !== false
            ? $retainStart
            : 0
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


stock_retained_check(
    'RETAIN_SHARED_DOES_NOT_WRITE_ALLOCATION_PROJECTION',
    $retainBlock !== ''
    &&
    strpos(
        $retainBlock,
        'stock_consumption_allocation_persistence_write_projection('
    ) === false
    &&
    strpos(
        $retainBlock,
        'INSERT INTO stock_consumption_allocations'
    ) === false
    &&
    strpos(
        $retainBlock,
        'UPDATE stock_consumption_allocations'
    ) === false
    &&
    strpos(
        $retainBlock,
        'DELETE FROM stock_consumption_allocations'
    ) === false
);


stock_retained_check(
    'RETAIN_SHARED_IS_IDEMPOTENT',
    preg_match(
        "/revision_action'[\\s\\S]{0,250}retain_shared[\\s\\S]{0,500}'changed'[\\s\\S]{0,80}false/",
        $retainBlock
    ) === 1
);


$revisionCallStart =
    strpos(
        $retainBlock,
        'stock_consumption_allocation_persistence_insert_revision('
    );

$revisionCall =
    '';

if ($revisionCallStart !== false) {
    $revisionCallEnd =
        strpos(
            $retainBlock,
            ');',
            $revisionCallStart
        );

    if ($revisionCallEnd !== false) {
        $revisionCall =
            substr(
                $retainBlock,
                $revisionCallStart,
                $revisionCallEnd
                    - $revisionCallStart
                    + 2
            );
    }
}

stock_retained_check(
    'RETAIN_SHARED_REVISION_HAS_ZERO_ROWS',
    $revisionCall !== ''
    &&
    strpos(
        $revisionCall,
        "'retain_shared'"
    ) !== false
    &&
    strpos(
        $revisionCall,
        '$desiredBuilt'
    ) !== false
    &&
    preg_match(
        '/\$desiredBuilt\s*,\s*\[\]\s*,\s*\$actorUserId/s',
        $revisionCall
    ) === 1
);


stock_retained_check(
    'API_ACCEPTS_RETAIN_SHARED',
    strpos(
        $api,
        "'retain_shared'"
    ) !== false
);


stock_retained_check(
    'API_DEFAULT_REMAINS_ALLOCATE',
    strpos(
        $api,
        "?? 'allocate'"
    ) !== false
);


stock_retained_check(
    'API_RETAIN_REQUIRES_EMPTY_ROWS',
    strpos(
        $api,
        'Clear cycle amounts before retaining this consumed stock cost as shared.'
    ) !== false
);


stock_retained_check(
    'API_ROUTES_TO_DEDICATED_STOCK_WRITER',
    strpos(
        $api,
        'stock_consumption_allocation_persistence_retain_shared('
    ) !== false
);


stock_retained_check(
    'NORMAL_STOCK_ALLOCATION_WRITER_PRESERVED',
    strpos(
        $api,
        'stock_consumption_allocation_persistence_apply('
    ) !== false
);


stock_retained_check(
    'API_RETAIN_SUCCESS_MESSAGE_EXISTS',
    strpos(
        $api,
        'Consumed stock cost retained as shared successfully.'
    ) !== false
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
