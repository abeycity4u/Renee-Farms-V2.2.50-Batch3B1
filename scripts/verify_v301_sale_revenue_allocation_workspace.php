<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$paths = [
    'workspace' =>
        $root
        . '/lib/sale_revenue_allocation_workspace.php',

    'page' =>
        $root
        . '/management/sale_revenue_allocation.php',

    'api' =>
        $root
        . '/api/update_sale_revenue_allocation.php',

    'js' =>
        $root
        . '/assets/js/financial-allocation-workspace.js',

    'service' =>
        $root
        . '/lib/sale_revenue_allocation_service.php',

    'persistence' =>
        $root
        . '/lib/sale_revenue_allocation_persistence.php',

    'permissions' =>
        $root
        . '/includes/permission_catalog.php',
];

$src = [];

foreach (
    $paths
    as $key => $path
) {
    $src[$key] =
        is_file($path)
            ? file_get_contents($path)
            : false;
}

$checks = [];

$check =
    static function (
        string $name,
        bool $ok
    ) use (&$checks): void {
        $checks[$name] =
            $ok;
    };

$has =
    static function (
        $source,
        string $needle
    ): bool {
        return
            is_string($source)
            &&
            strpos(
                $source,
                $needle
            ) !== false;
    };

$count =
    static function (
        $source,
        string $needle
    ): int {
        return
            is_string($source)
                ? substr_count(
                    $source,
                    $needle
                )
                : 0;
    };


/*
 * =========================================================
 * FILE / AUTHORITY BOUNDARY
 * =========================================================
 */

foreach (
    $src
    as $key => $source
) {
    $check(
        'FILE_'
        . strtoupper($key)
        . '_PRESENT',
        is_string($source)
    );
}

$check(
    'WORKSPACE_REUSES_CANONICAL_PERSISTENCE',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_persistence.php'
    )
);

$check(
    'WORKSPACE_HAS_ACCESS_ADAPTER',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_workspace_can_access'
    )
);

$check(
    'WORKSPACE_HAS_URL_ADAPTER',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_workspace_url'
    )
);

$check(
    'WORKSPACE_HAS_SNAPSHOT_ADAPTER',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_workspace_snapshot'
    )
);


/*
 * =========================================================
 * READER CONTRACT
 * =========================================================
 */

$check(
    'WORKSPACE_LOADS_PARENT_CANONICALLY',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_persistence_parent'
    )
);

$check(
    'WORKSPACE_READS_CURRENT_PROJECTION_CANONICALLY',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_persistence_current_rows'
    )
);

$check(
    'WORKSPACE_FAILS_CLOSED_ON_FOREIGN_PROJECTION',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_persistence_assert_manual_projection'
    )
);

$check(
    'WORKSPACE_CHECKS_REVISION_PROVENANCE',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_persistence_assert_revision_consistency'
    )
);

$check(
    'WORKSPACE_READS_ANIMAL_OVERLAP_CANONICALLY',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_persistence_animal_count'
    )
);

$check(
    'WORKSPACE_USES_TARGET_POLICY',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_service_target_contract'
    )
);

$check(
    'WORKSPACE_USES_VALIDATION_POLICY',
    $has(
        $src['workspace'],
        'sale_revenue_allocation_service_validate_desired_rows'
    )
);

$check(
    'WORKSPACE_ACCESS_REQUIRES_SALES_VIEW',
    $has(
        $src['workspace'],
        "'sales'"
    )
);

$check(
    'WORKSPACE_ACCESS_REQUIRES_SALES_EDIT',
    $has(
        $src['workspace'],
        "'sales_edit'"
    )
);


/*
 * =========================================================
 * THIN PAGE
 * =========================================================
 */

$check(
    'PAGE_USES_SHARED_WORKSPACE',
    $has(
        $src['page'],
        'sale_revenue_allocation_workspace.php'
    )
);

$check(
    'PAGE_USES_WORKSPACE_SNAPSHOT',
    $has(
        $src['page'],
        'sale_revenue_allocation_workspace_snapshot'
    )
);

$check(
    'PAGE_USES_SHARED_JS',
    $has(
        $src['page'],
        '/assets/js/financial-allocation-workspace.js'
    )
);

$check(
    'PAGE_DECLARES_GENERIC_PARENT_ID',
    $has(
        $src['page'],
        'data-parent-id-field="sale_id"'
    )
);

$check(
    'PAGE_DECLARATION_PRESERVES_REASON_POLICY',
    $has(
        $src['page'],
        'data-reason-required='
    )
);

$check(
    'PAGE_HAS_NO_DIRECT_PREPARE',
    !$has(
        $src['page'],
        '->prepare('
    )
);

$check(
    'PAGE_HAS_NO_DIRECT_QUERY',
    !$has(
        $src['page'],
        '->query('
    )
);

$check(
    'PAGE_HAS_NO_DIRECT_EXEC',
    !$has(
        $src['page'],
        '->exec('
    )
);


/*
 * =========================================================
 * THIN API / TRANSACTION OWNER
 * =========================================================
 */

$check(
    'API_REQUIRES_POST',
    $has(
        $src['api'],
        "require_http_method('POST')"
    )
);

$check(
    'API_REQUIRES_CSRF',
    $has(
        $src['api'],
        'require_csrf_token()'
    )
);

$check(
    'API_REQUIRES_RATE_LIMIT',
    $has(
        $src['api'],
        "require_rate_limit("
    )
);

$check(
    'API_USES_WORKSPACE_ACCESS',
    $has(
        $src['api'],
        'sale_revenue_allocation_workspace_can_access'
    )
);

$check(
    'API_LOCKS_PARENT_BEFORE_ACCESS',
    $has(
        $src['api'],
        'sale_revenue_allocation_persistence_parent'
    )
);

$check(
    'API_CALLS_CANONICAL_WRITER_EXACTLY_ONCE',
    $count(
        $src['api'],
        'sale_revenue_allocation_persistence_apply('
    ) === 1
);

$check(
    'API_OWNS_OUTER_TRANSACTION',
    $has(
        $src['api'],
        'beginTransaction()'
    )
    &&
    $has(
        $src['api'],
        '->commit()'
    )
    &&
    $has(
        $src['api'],
        '->rollBack()'
    )
);

$check(
    'API_HAS_NO_DIRECT_PREPARE',
    !$has(
        $src['api'],
        '->prepare('
    )
);

$check(
    'API_HAS_NO_DIRECT_QUERY',
    !$has(
        $src['api'],
        '->query('
    )
);

$check(
    'API_HAS_NO_DIRECT_EXEC',
    !$has(
        $src['api'],
        '->exec('
    )
);


/*
 * =========================================================
 * WRONG AUTHORITY GUARDS
 * =========================================================
 */

$revenueSurface =
    (string)$src['workspace']
    . "\n"
    . (string)$src['page']
    . "\n"
    . (string)$src['api'];

$check(
    'REVENUE_DOES_NOT_USE_EXPENSE_FINANCIAL_ALLOCATIONS',
    strpos(
        $revenueSurface,
        'financial_allocations'
    ) === false
);

$check(
    'REVENUE_DOES_NOT_USE_EXPENSE_PERSISTENCE_WRITER',
    strpos(
        $revenueSurface,
        'financial_allocation_persistence_apply'
    ) === false
);

$check(
    'REVENUE_DOES_NOT_ROUTE_TO_EXPENSE_PAGE',
    strpos(
        $revenueSurface,
        'management/expense_allocation.php'
    ) === false
);


/*
 * =========================================================
 * SHARED JS CONTRACT
 * =========================================================
 */

$check(
    'SHARED_JS_SUPPORTS_GENERIC_PARENT_FIELD',
    $has(
        $src['js'],
        'form.dataset.parentIdField'
    )
);

$check(
    'SHARED_JS_SUPPORTS_GENERIC_PARENT_ID',
    $has(
        $src['js'],
        'form.dataset.parentId'
    )
);

$check(
    'SHARED_JS_PRESERVES_EXPENSE_ID_FALLBACK',
    $has(
        $src['js'],
        'form.dataset.expenseId'
    )
);

$check(
    'SHARED_JS_SUPPORTS_CANONICAL_REASON_POLICY',
    $has(
        $src['js'],
        'form.dataset.reasonRequired'
    )
);

$check(
    'SHARED_JS_USES_CONFIGURED_ENDPOINT',
    $has(
        $src['js'],
        'form.dataset.endpoint'
    )
);


/*
 * =========================================================
 * CANONICAL SERVICE/PERSISTENCE GUARDS
 * =========================================================
 */

$check(
    'SERVICE_REJECTS_AUTOMATIC_LAYER_EGG',
    $has(
        $src['service'],
        'sale_revenue_allocation_service_is_automatic_layer_egg'
    )
);

$check(
    'SERVICE_REJECTS_FUTURE_START_TARGET',
    $has(
        $src['service'],
        'starts after the sale date'
    )
);

$check(
    'SERVICE_REJECTS_DUPLICATE_TARGET',
    $has(
        $src['service'],
        'same production cycle cannot appear more than once'
    )
);

$check(
    'SERVICE_REJECTS_OVER_ALLOCATION',
    $has(
        $src['service'],
        'cannot exceed the sale total'
    )
);

$check(
    'PERSISTENCE_CALLER_OWNS_TRANSACTION',
    $has(
        $src['persistence'],
        'requires an active transaction'
    )
);

$check(
    'PERSISTENCE_HAS_CANONICAL_APPLY',
    $has(
        $src['persistence'],
        'function sale_revenue_allocation_persistence_apply'
    )
);


/*
 * =========================================================
 * RESULT
 * =========================================================
 */

$failed = [];

foreach (
    $checks
    as $name => $ok
) {
    echo
        $name
        . '='
        . (
            $ok
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$ok) {
        $failed[] =
            $name;
    }
}

echo
    'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . count($failed)
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failed
            ? 'FAIL'
            : 'PASS'
    )
    . PHP_EOL;

exit(
    $failed
        ? 1
        : 0
);
