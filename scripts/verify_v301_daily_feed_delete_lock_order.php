<?php

$root =
    dirname(__DIR__);

/*
 * Required because this standalone verifier directly calls
 * stock_consumption_source_resolver_definition().
 */
require_once $root
    . '/lib/stock_consumption_source_resolver.php';

$daily =
    file_get_contents(
        $root . '/lib/daily_feed_sync.php'
    );

$api =
    file_get_contents(
        $root . '/api/delete_record.php'
    );

$ruminant =
    file_get_contents(
        $root . '/ruminant/ruminant_daily_record.php'
    );

$ruminantPermissionRuntime =
    file_get_contents(
        $root . '/includes/permission_runtime.php'
    );

$ruminantDeleteService =
    file_get_contents(
        $root . '/lib/ruminant_daily_record_deletion.php'
    );

$persistence =
    file_get_contents(
        $root . '/lib/stock_consumption_allocation_persistence.php'
    );

if (
    !is_string($daily)
    ||
    !is_string($api)
    ||
    !is_string($ruminant)
    ||
    !is_string($ruminantPermissionRuntime)
    ||
    !is_string($ruminantDeleteService)
    ||
    !is_string($persistence)
) {
    echo "RESULT=FAIL\n";
    echo "FAIL=SOURCE_READ_FAILED\n";
    exit(1);
}

$checks = 0;
$failures = [];

$check =
    static function (
        string $name,
        bool $ok
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        if (!$ok) {
            $failures[] =
                $name;
        }
    };


/*
 * ------------------------------------------------------------
 * Canonical Daily feed delete function.
 * ------------------------------------------------------------
 */

$deleteStart =
    strpos(
        $daily,
        'function delete_daily_feed_usage('
    );

$deleteBody =
    $deleteStart === false
        ? ''
        : substr(
            $daily,
            $deleteStart
        );

$sourceLock =
    strpos(
        $deleteBody,
        'stock_consumption_source_resolver_resolve('
    );

$stockLock =
    strpos(
        $deleteBody,
        'SELECT * FROM stock_transactions'
    );

$reverseCall =
    strpos(
        $deleteBody,
        'stock_reverse_transaction('
    );

$check(
    'DELETE_FUNCTION_FOUND',
    $deleteBody !== ''
);

$check(
    'RESOLVER_REQUIRED',
    strpos(
        $daily,
        "require_once __DIR__ . '/stock_consumption_source_resolver.php';"
    ) !== false
);

$check(
    'LINKED_DAILY_ONLY',
    strpos(
        $deleteBody,
        "'linked_daily_record'"
    ) !== false
);

$check(
    'SOURCE_LOCK_BEFORE_STOCK',
    $sourceLock !== false
    &&
    $stockLock !== false
    &&
    $sourceLock < $stockLock
);

$check(
    'SOURCE_LOCK_USES_FOR_UPDATE',
    preg_match(
        '/stock_consumption_source_resolver_resolve\s*\([\s\S]*?\btrue\s*\);/',
        $deleteBody
    ) === 1
);

$check(
    'STOCK_LOCK_BEFORE_REVERSAL',
    $stockLock !== false
    &&
    $reverseCall !== false
    &&
    $stockLock < $reverseCall
);


/*
 * ------------------------------------------------------------
 * Poultry delete route.
 * ------------------------------------------------------------
 */

$apiFeed =
    strpos(
        $api,
        'delete_daily_feed_usage('
    );

$apiPopulation =
    strpos(
        $api,
        'daily_population_remove_mortality('
    );

$apiSourceDelete =
    strpos(
        $api,
        '$stmt->execute([$id, $farmId]);'
    );

$check(
    'POULTRY_SOURCE_STOCK_POPULATION_DELETE_ORDER',
    $apiFeed !== false
    &&
    $apiPopulation !== false
    &&
    $apiSourceDelete !== false
    &&
    $apiFeed < $apiPopulation
    &&
    $apiPopulation < $apiSourceDelete
);


/*
 * ------------------------------------------------------------
 * Ruminant delete: one shared service for both role paths.
 * Source lock -> feed/stock -> population reversal -> DELETE.
 * ------------------------------------------------------------
 */

$ruminantLock =
    strpos($ruminantDeleteService, 'LIMIT 1 FOR UPDATE');

$ruminantFeed =
    strpos($ruminantDeleteService, 'delete_daily_feed_usage(');

$ruminantPopulation =
    strpos($ruminantDeleteService, 'daily_population_remove_mortality(');

$ruminantSourceDelete =
    strpos($ruminantDeleteService, 'DELETE FROM ruminant_daily_records');

$check(
    'RUMINANT_SOURCE_STOCK_POPULATION_DELETE_ORDER',
    $ruminantLock !== false
    && $ruminantFeed !== false
    && $ruminantPopulation !== false
    && $ruminantSourceDelete !== false
    && $ruminantLock < $ruminantFeed
    && $ruminantFeed < $ruminantPopulation
    && $ruminantPopulation < $ruminantSourceDelete
);

$check(
    'RUMINANT_SHARED_DELETE_TRANSACTION',
    str_contains($ruminantDeleteService, '$pdo->beginTransaction();')
    && str_contains($ruminantDeleteService, '$pdo->commit();')
    && str_contains($ruminantDeleteService, '$pdo->rollBack();')
    && str_contains($ruminantDeleteService, 'if ($deleteStmt->rowCount() !== 1)')
);

$check(
    'RUMINANT_ADMIN_USES_SHARED_DELETE_SERVICE',
    str_contains($ruminant, 'ruminant_daily_record_delete_canonical(')
    && !str_contains($ruminant, 'DELETE FROM ruminant_daily_records')
    && !str_contains($ruminant, 'delete_daily_feed_usage(')
);

$check(
    'RUMINANT_DELEGATE_USES_SHARED_DELETE_SERVICE',
    str_contains($ruminantPermissionRuntime, 'ruminant_daily_record_delete_canonical(')
    && str_contains($ruminantPermissionRuntime, "permission_runtime_has('ruminant_daily_delete')")
    && str_contains($ruminantPermissionRuntime, 'verify_csrf_token(')
    && !str_contains($ruminantPermissionRuntime, 'DELETE FROM ruminant_daily_records')
);

/*
 * ------------------------------------------------------------
 * Allocation persistence still uses source -> stock.
 * ------------------------------------------------------------
 */

$lockParentStart =
    strpos(
        $persistence,
        'function stock_consumption_allocation_persistence_lock_parent('
    );

$lockParentEnd =
    $lockParentStart === false
        ? false
        : strpos(
            $persistence,
            'function stock_consumption_allocation_persistence_target_cycles(',
            $lockParentStart
        );

$lockParentBody =
    (
        $lockParentStart !== false
        &&
        $lockParentEnd !== false
    )
        ? substr(
            $persistence,
            $lockParentStart,
            $lockParentEnd - $lockParentStart
        )
        : '';

$allocationSourceLock =
    strpos(
        $lockParentBody,
        'stock_consumption_source_resolver_resolve('
    );

$allocationStockLock =
    $allocationSourceLock === false
        ? false
        : strpos(
            $lockParentBody,
            'stock_consumption_allocation_persistence_movement(',
            $allocationSourceLock
        );

$check(
    'ALLOCATION_SOURCE_BEFORE_STOCK',
    $allocationSourceLock !== false
    &&
    $allocationStockLock !== false
    &&
    $allocationSourceLock < $allocationStockLock
);


/*
 * ------------------------------------------------------------
 * Closed resolver map still owns all Daily source identities.
 * ------------------------------------------------------------
 */

$dailySources = [
    'daily_layer_record' =>
        'layer_daily_records',

    'daily_broiler_record' =>
        'broiler_daily_records',

    'daily_ruminant_record' =>
        'ruminant_daily_records',
];

foreach (
    $dailySources
    as $sourceType => $expectedTable
) {
    $definition =
        stock_consumption_source_resolver_definition(
            $sourceType
        );

    $check(
        'RESOLVER_'
        . strtoupper($sourceType),
        !empty($definition['supported'])
        &&
        !empty($definition['allocatable'])
        &&
        ($definition['mode'] ?? '')
            === 'linked_daily_record'
        &&
        ($definition['table'] ?? '')
            === $expectedTable
    );
}


$result =
    $failures === []
        ? 'PASS'
        : 'FAIL';

echo "RESULT={$result}\n";
echo "CHECK_COUNT={$checks}\n";

echo "DELETE_LOCK_ORDER="
    . (
        $result === 'PASS'
            ? 'PASS'
            : 'FAIL'
    )
    . "\n";

echo "POULTRY_DELETE_PATH="
    . (
        $result === 'PASS'
            ? 'PASS'
            : 'FAIL'
    )
    . "\n";

echo "RUMINANT_DELETE_PATH="
    . (
        $result === 'PASS'
            ? 'PASS'
            : 'FAIL'
    )
    . "\n";

echo "ALLOCATION_LOCK_ORDER="
    . (
        $result === 'PASS'
            ? 'PASS'
            : 'FAIL'
    )
    . "\n";

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITES=NONE\n";

foreach (
    $failures
    as $failure
) {
    echo "FAIL={$failure}\n";
}

exit(
    $result === 'PASS'
        ? 0
        : 1
);
