<?php

$root =
    dirname(__DIR__);

$file =
    $root
    . '/lib/stock_consumption_allocation_workspace.php';

$source =
    file_get_contents(
        $file
    );

if ($source === false) {
    echo "RESULT=FAIL\n";
    echo "FAILED=WORKSPACE_SOURCE_MISSING\n";
    exit(1);
}

$checks = [];

function check_result(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

check_result(
    $checks,
    'REQUIRES_ALLOCATION_SERVICE',
    strpos(
        $source,
        "stock_consumption_allocation_service.php"
    ) !== false
);

check_result(
    $checks,
    'REQUIRES_SOURCE_RESOLVER',
    strpos(
        $source,
        "stock_consumption_source_resolver.php"
    ) !== false
);

check_result(
    $checks,
    'REQUIRES_CANONICAL_PERSISTENCE',
    strpos(
        $source,
        "stock_consumption_allocation_persistence.php"
    ) !== false
);

check_result(
    $checks,
    'USES_UPDATE_STOCK_MUTATION_PERMISSION',
    strpos(
        $source,
        "'update_stock'"
    ) !== false
);

check_result(
    $checks,
    'OWNER_ADMIN_ACCESS_SUPPORTED',
    strpos(
        $source,
        'isPlatformOwner()'
    ) !== false
    &&
    strpos(
        $source,
        "hasRole('farm_admin')"
    ) !== false
);

check_result(
    $checks,
    'ACTION_STATE_CENTRALIZED',
    strpos(
        $source,
        'stock_consumption_allocation_workspace_action_state'
    ) !== false
);

check_result(
    $checks,
    'ACTION_STATE_USES_CANONICAL_ELIGIBILITY',
    strpos(
        $source,
        'stock_consumption_allocation_workspace_eligibility'
    ) !== false
);

check_result(
    $checks,
    'SOURCE_RESOLUTION_CENTRALIZED',
    strpos(
        $source,
        'stock_consumption_source_resolver_resolve'
    ) !== false
);

check_result(
    $checks,
    'SOURCE_DRIFT_CHECK_CENTRALIZED',
    strpos(
        $source,
        'stock_consumption_source_resolver_assert_cycle_consistency'
    ) !== false
);

check_result(
    $checks,
    'PARENT_CONTRACT_CENTRALIZED',
    strpos(
        $source,
        'stock_consumption_allocation_service_parent_contract'
    ) !== false
);

check_result(
    $checks,
    'SNAPSHOT_USES_CANONICAL_MOVEMENT_READER',
    strpos(
        $source,
        'stock_consumption_allocation_persistence_movement'
    ) !== false
);

check_result(
    $checks,
    'BROAD_TENANT_CYCLE_DISCOVERY',
    preg_match(
        '/FROM\s+production_cycles\s+WHERE\s+farm_id=\?/is',
        $source
    ) === 1
);

check_result(
    $checks,
    'TARGET_COMPATIBILITY_CENTRALIZED',
    strpos(
        $source,
        'stock_consumption_allocation_service_target_contract'
    ) !== false
);

check_result(
    $checks,
    'CURRENT_PROJECTION_READER_PRESENT',
    strpos(
        $source,
        'stock_consumption_allocation_workspace_current_rows'
    ) !== false
    &&
    strpos(
        $source,
        'FROM stock_consumption_allocations'
    ) !== false
);

check_result(
    $checks,
    'LATEST_REVISION_READER_PRESENT',
    strpos(
        $source,
        'stock_consumption_allocation_workspace_latest_revision'
    ) !== false
    &&
    strpos(
        $source,
        'FROM stock_consumption_allocation_revisions'
    ) !== false
);

check_result(
    $checks,
    'CURRENT_STATE_CONSISTENCY_CENTRALIZED',
    strpos(
        $source,
        'stock_consumption_allocation_persistence_assert_current_consistency'
    ) !== false
);

check_result(
    $checks,
    'DISPLAY_TOTALS_VALIDATED_CENTRALLY',
    strpos(
        $source,
        'stock_consumption_allocation_service_validate_desired_rows'
    ) !== false
);

check_result(
    $checks,
    'WORKSPACE_URL_IS_DEDICATED',
    strpos(
        $source,
        '/management/stock_consumption_allocation.php'
    ) !== false
);

check_result(
    $checks,
    'RETURN_URL_USES_STOCK_HISTORY',
    strpos(
        $source,
        '/api/stock_history.php'
    ) !== false
);

$mutationSql =
    preg_match(
        '/\b(INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?(?:stock_transactions|stock_consumption_allocations|stock_consumption_allocation_revisions)\b/i',
        $source
    ) === 1;

check_result(
    $checks,
    'WORKSPACE_OWNS_NO_MUTATION_SQL',
    !$mutationSql
);

$transactionOwnership =
    preg_match(
        '/->\s*(beginTransaction|commit|rollBack)\s*\(/',
        $source
    ) === 1;

check_result(
    $checks,
    'WORKSPACE_OWNS_NO_TRANSACTION',
    !$transactionOwnership
);

$dbBootstrap =
    preg_match(
        '/require(?:_once)?[^;]*config\.php|new\s+PDO\s*\(/i',
        $source
    ) === 1;

check_result(
    $checks,
    'NO_DATABASE_BOOTSTRAP',
    !$dbBootstrap
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
