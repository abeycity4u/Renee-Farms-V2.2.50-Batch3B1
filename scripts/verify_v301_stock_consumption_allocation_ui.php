<?php

$root =
    dirname(__DIR__);

$files = [
    'workspace' =>
        $root
        . '/lib/stock_consumption_allocation_workspace.php',

    'page' =>
        $root
        . '/management/stock_consumption_allocation.php',

    'api' =>
        $root
        . '/api/update_stock_consumption_allocation.php',

    'history_api' =>
        $root
        . '/api/get_stock_history.php',

    'history_page' =>
        $root
        . '/api/stock_history.php',

    'history_js' =>
        $root
        . '/assets/js/stock-history.js',

    'workspace_js' =>
        $root
        . '/assets/js/stock-consumption-allocation-workspace.js',
];

$sources = [];

foreach ($files as $name => $file) {
    $content =
        file_get_contents(
            $file
        );

    if ($content === false) {
        echo "RESULT=FAIL\n";
        echo 'FAILED=MISSING_'
            . strtoupper($name)
            . PHP_EOL;
        exit(1);
    }

    $sources[$name] =
        $content;
}

$checks = [];

function record_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

record_check(
    $checks,
    'PAGE_REQUIRES_WORKSPACE',
    strpos(
        $sources['page'],
        'stock_consumption_allocation_workspace.php'
    ) !== false
);

record_check(
    $checks,
    'PAGE_PERMISSION_GUARD',
    strpos(
        $sources['page'],
        'stock_consumption_allocation_workspace_can_manage'
    ) !== false
    &&
    strpos(
        $sources['page'],
        '/no_access.php'
    ) !== false
);

record_check(
    $checks,
    'PAGE_USES_CANONICAL_SNAPSHOT',
    strpos(
        $sources['page'],
        'stock_consumption_allocation_workspace_snapshot'
    ) !== false
);

record_check(
    $checks,
    'PAGE_VISIBLE_PARENT_TOTAL',
    strpos(
        $sources['page'],
        'stockAllocationParentDisplay'
    ) !== false
);

record_check(
    $checks,
    'PAGE_VISIBLE_ALLOCATED_TOTAL',
    strpos(
        $sources['page'],
        'stockAllocationAllocatedDisplay'
    ) !== false
);

record_check(
    $checks,
    'PAGE_VISIBLE_REMAINDER',
    strpos(
        $sources['page'],
        'stockAllocationRemainderDisplay'
    ) !== false
);

record_check(
    $checks,
    'PAGE_EQUAL_SPLIT_EXPLICIT',
    strpos(
        $sources['page'],
        'stockConsumptionAllocationEqualSplit'
    ) !== false
);

record_check(
    $checks,
    'PAGE_REVISION_REASON_SURFACE',
    strpos(
        $sources['page'],
        'stockConsumptionAllocationRevisionReason'
    ) !== false
);

record_check(
    $checks,
    'PAGE_CSRF_TOKEN',
    strpos(
        $sources['page'],
        'csrf_token()'
    ) !== false
);

record_check(
    $checks,
    'PAGE_CANONICAL_API_ENDPOINT',
    strpos(
        $sources['page'],
        '/api/update_stock_consumption_allocation.php'
    ) !== false
);

record_check(
    $checks,
    'PAGE_EXTERNAL_WORKSPACE_JS',
    strpos(
        $sources['page'],
        '/assets/js/stock-consumption-allocation-workspace.js'
    ) !== false
);

record_check(
    $checks,
    'API_POST_ONLY',
    strpos(
        $sources['api'],
        "require_http_method('POST')"
    ) !== false
);

record_check(
    $checks,
    'API_CSRF_REQUIRED',
    strpos(
        $sources['api'],
        'require_csrf_token()'
    ) !== false
);

record_check(
    $checks,
    'API_RATE_LIMITED',
    strpos(
        $sources['api'],
        "require_rate_limit("
    ) !== false
    &&
    strpos(
        $sources['api'],
        'update_stock_consumption_allocation'
    ) !== false
);

record_check(
    $checks,
    'API_UPDATE_STOCK_PERMISSION',
    strpos(
        $sources['api'],
        'stock_consumption_allocation_workspace_can_manage'
    ) !== false
);

record_check(
    $checks,
    'API_OWNS_OUTER_TRANSACTION',
    strpos(
        $sources['api'],
        'beginTransaction()'
    ) !== false
    &&
    strpos(
        $sources['api'],
        'commit()'
    ) !== false
    &&
    strpos(
        $sources['api'],
        'rollBack()'
    ) !== false
);

record_check(
    $checks,
    'API_USES_CANONICAL_PERSISTENCE',
    strpos(
        $sources['api'],
        'stock_consumption_allocation_persistence_apply'
    ) !== false
);

$directAllocationMutation =
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?stock_consumption_(?:allocations|allocation_revisions|allocation_revision_rows)\b/i',
        $sources['api']
    ) === 1;

record_check(
    $checks,
    'API_NO_DIRECT_ALLOCATION_SQL',
    !$directAllocationMutation
);

$directStockMutation =
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?stock_transactions\b/i',
        $sources['api']
    ) === 1;

record_check(
    $checks,
    'API_NEVER_MUTATES_STOCK_LEDGER',
    !$directStockMutation
);

record_check(
    $checks,
    'HISTORY_API_REQUIRES_WORKSPACE',
    strpos(
        $sources['history_api'],
        'stock_consumption_allocation_workspace.php'
    ) !== false
);

record_check(
    $checks,
    'HISTORY_API_CENTRAL_ACTION_STATE',
    strpos(
        $sources['history_api'],
        'stock_consumption_allocation_workspace_action_state'
    ) !== false
);

record_check(
    $checks,
    'HISTORY_API_EXPOSES_ALLOCATION_ACTION',
    strpos(
        $sources['history_api'],
        "['consumption_allocation']"
    ) !== false
);

record_check(
    $checks,
    'HISTORY_PAGE_ACTION_COLUMN',
    preg_match(
        '/<th>\s*Actions\s*<\/th>/i',
        $sources['history_page']
    ) === 1
);

record_check(
    $checks,
    'HISTORY_JS_ALLOCATE_COST_ACTION',
    strpos(
        $sources['history_js'],
        'Allocate Cost'
    ) !== false
    &&
    strpos(
        $sources['history_js'],
        'consumption_allocation'
    ) !== false
);

record_check(
    $checks,
    'HISTORY_JS_SERVER_URL_ESCAPED',
    strpos(
        $sources['history_js'],
        'escapeHtml(allocation.url)'
    ) !== false
);

record_check(
    $checks,
    'WORKSPACE_JS_EQUAL_SPLIT',
    strpos(
        $sources['workspace_js'],
        'splitEqually'
    ) !== false
    &&
    strpos(
        $sources['workspace_js'],
        'stockConsumptionAllocationEqualSplit'
    ) !== false
);

record_check(
    $checks,
    'WORKSPACE_JS_VISIBLE_REMAINDER',
    strpos(
        $sources['workspace_js'],
        'stockAllocationRemainderDisplay'
    ) !== false
);

record_check(
    $checks,
    'WORKSPACE_JS_PREVENTS_OVERALLOCATION',
    strpos(
        $sources['workspace_js'],
        'allocatedCents'
    ) !== false
    &&
    strpos(
        $sources['workspace_js'],
        '> parentCents'
    ) !== false
);

record_check(
    $checks,
    'WORKSPACE_JS_REASON_REQUIRED_AFTER_HISTORY',
    strpos(
        $sources['workspace_js'],
        'hasRevision'
    ) !== false
    &&
    strpos(
        $sources['workspace_js'],
        'Enter a reason for changing this stock allocation.'
    ) !== false
);

record_check(
    $checks,
    'WORKSPACE_JS_ROWS_JSON',
    strpos(
        $sources['workspace_js'],
        "'rows_json'"
    ) !== false
    &&
    strpos(
        $sources['workspace_js'],
        'JSON.stringify'
    ) !== false
);

record_check(
    $checks,
    'WORKSPACE_READER_STILL_NO_TRANSACTION',
    preg_match(
        '/->\s*(?:beginTransaction|commit|rollBack)\s*\(/',
        $sources['workspace']
    ) !== 1
);

record_check(
    $checks,
    'WORKSPACE_READER_STILL_NO_MUTATION_SQL',
    preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?(?:stock_transactions|stock_consumption_allocations|stock_consumption_allocation_revisions)\b/i',
        $sources['workspace']
    ) !== 1
);

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] =
            $name;
    }
}

echo 'RESULT='
    . (
        $failed
            ? 'FAIL'
            : 'PASS'
    )
    . PHP_EOL;

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

foreach ($checks as $name => $passed) {
    echo $name
        . '='
        . (
            $passed
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;
}

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

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
