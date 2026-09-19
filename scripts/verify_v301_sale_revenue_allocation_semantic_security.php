<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$paths = [
    'workspace' =>
        $root . '/lib/sale_revenue_allocation_workspace.php',

    'page' =>
        $root . '/management/sale_revenue_allocation.php',

    'api' =>
        $root . '/api/update_sale_revenue_allocation.php',

    'js' =>
        $root . '/assets/js/financial-allocation-workspace.js',

    'expense_page' =>
        $root . '/management/expense_allocation.php',

    'profitability_reader' =>
        $root . '/lib/profitability_unallocated_shared.php',

    'profitability_page' =>
        $root . '/management/profitability.php',

    'service' =>
        $root . '/lib/sale_revenue_allocation_service.php',

    'persistence' =>
        $root . '/lib/sale_revenue_allocation_persistence.php',

    'provenance' =>
        $root . '/lib/sale_revenue_allocation_provenance.php',

    'permissions' =>
        $root . '/includes/permission_catalog.php',
];

$src = [];

foreach ($paths as $key => $path) {
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
        $checks[$name] = $ok;
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

$positionBefore =
    static function (
        $source,
        string $first,
        string $second
    ): bool {
        if (!is_string($source)) {
            return false;
        }

        $a = strpos($source, $first);
        $b = strpos($source, $second);

        return
            $a !== false
            &&
            $b !== false
            &&
            $a < $b;
    };


/*
 * =========================================================
 * FILE BOUNDARY
 * =========================================================
 */

foreach ($src as $key => $source) {
    $check(
        'FILE_' . strtoupper($key) . '_PRESENT',
        is_string($source)
    );
}


/*
 * =========================================================
 * TENANT / ACCESS CONTRACT
 * =========================================================
 */

$check(
    'WORKSPACE_PLATFORM_OWNER_ALLOWED',
    $has(
        $src['workspace'],
        'isPlatformOwner()'
    )
);

$check(
    'WORKSPACE_FARM_ADMIN_ALLOWED',
    $has(
        $src['workspace'],
        "hasRole('farm_admin')"
    )
);

$check(
    'WORKSPACE_REQUIRES_SALES_VIEW',
    $has(
        $src['workspace'],
        "'sales'"
    )
);

$check(
    'WORKSPACE_REQUIRES_SALES_EDIT',
    $has(
        $src['workspace'],
        "'sales_edit'"
    )
);

$check(
    'WORKSPACE_RECOGNISES_SALES_REP',
    $has(
        $src['workspace'],
        "hasRole('sales_rep')"
    )
);

$check(
    'WORKSPACE_MANAGER_SCOPE_USES_USER_FARM_TYPE',
    $has(
        $src['workspace'],
        'getUserFarmType()'
    )
);

$check(
    'PAGE_REQUIRES_LOGIN',
    $has(
        $src['page'],
        'requireLogin();'
    )
);

$check(
    'PAGE_DERIVES_FARM_FROM_SESSION_CONTEXT',
    $has(
        $src['page'],
        'requireCurrentFarmId()'
    )
);

$check(
    'PAGE_LOADS_PARENT_WITH_FARM_SCOPE',
    preg_match(
        '/sale_revenue_allocation_persistence_parent\s*\(\s*\$pdo\s*,\s*\$farmId\s*,\s*\$saleId/s',
        (string)$src['page']
    ) === 1
);

$check(
    'PAGE_CHECKS_ACCESS_BEFORE_SNAPSHOT',
    $positionBefore(
        $src['page'],
        'sale_revenue_allocation_workspace_can_access(',
        'sale_revenue_allocation_workspace_snapshot('
    )
);


/*
 * =========================================================
 * PUBLIC REFERENCE / USER-FACING SAFETY
 * =========================================================
 */

$check(
    'PAGE_DISPLAYS_PUBLIC_REFERENCE',
    $has(
        $src['page'],
        "['public_reference']"
    )
);

$check(
    'PAGE_ROUTE_STILL_USES_INTERNAL_SALE_ID',
    $has(
        $src['page'],
        "\$_GET['sale_id']"
    )
);

$check(
    'WORKSPACE_URL_USES_INTERNAL_SALE_ID',
    $has(
        $src['workspace'],
        "'sale_id'"
    )
);

$check(
    'PAGE_MASKS_PDO_DIAGNOSTICS',
    preg_match(
        "/catch\s*\(\s*PDOException[\s\S]*?'The shared revenue allocation workspace could not be opened\\.'/",
        (string)$src['page']
    ) === 1
);


/*
 * =========================================================
 * API SECURITY / TRANSACTION OWNERSHIP
 * =========================================================
 */

$check(
    'API_REQUIRES_LOGIN',
    $has(
        $src['api'],
        'requireLogin();'
    )
);

$check(
    'API_POST_ONLY',
    $has(
        $src['api'],
        "require_http_method('POST')"
    )
);

$check(
    'API_REQUIRES_CSRF',
    $has(
        $src['api'],
        'require_csrf_token();'
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
    'API_DERIVES_FARM_FROM_SESSION_CONTEXT',
    $has(
        $src['api'],
        'requireCurrentFarmId()'
    )
);

$check(
    'API_DERIVES_ACTOR_FROM_SESSION',
    $has(
        $src['api'],
        "\$_SESSION['user_id']"
    )
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
    'API_LOCKS_PARENT',
    preg_match(
        '/sale_revenue_allocation_persistence_parent\s*\(\s*\$pdo\s*,\s*\$farmId\s*,\s*\$saleId\s*,\s*true/s',
        (string)$src['api']
    ) === 1
);

$check(
    'API_PERMISSION_CHECK_BEFORE_WRITER',
    $positionBefore(
        $src['api'],
        'sale_revenue_allocation_workspace_can_access(',
        'sale_revenue_allocation_persistence_apply('
    )
);

$check(
    'API_CALLS_CANONICAL_WRITER_ONCE',
    $count(
        $src['api'],
        'sale_revenue_allocation_persistence_apply('
    ) === 1
);

$check(
    'API_MASKS_DATABASE_FAILURE',
    $has(
        $src['api'],
        'update_sale_revenue_allocation_database_failed'
    )
    &&
    $has(
        $src['api'],
        'The shared revenue allocation could not be saved.'
    )
);


/*
 * =========================================================
 * THIN ROUTE / NO DUPLICATED MUTATION SQL
 * =========================================================
 */

$check(
    'PAGE_HAS_NO_DIRECT_SQL_PREPARE',
    !$has(
        $src['page'],
        '->prepare('
    )
);

$check(
    'PAGE_HAS_NO_DIRECT_SQL_QUERY',
    !$has(
        $src['page'],
        '->query('
    )
);

$check(
    'PAGE_HAS_NO_DIRECT_SQL_EXEC',
    !$has(
        $src['page'],
        '->exec('
    )
);

$check(
    'API_HAS_NO_DIRECT_SQL_PREPARE',
    !$has(
        $src['api'],
        '->prepare('
    )
);

$check(
    'API_HAS_NO_DIRECT_SQL_QUERY',
    !$has(
        $src['api'],
        '->query('
    )
);

$check(
    'API_HAS_NO_DIRECT_SQL_EXEC',
    !$has(
        $src['api'],
        '->exec('
    )
);

$check(
    'PAGE_DOES_NOT_CALL_WRITER',
    !$has(
        $src['page'],
        'sale_revenue_allocation_persistence_apply('
    )
);


/*
 * =========================================================
 * CANONICAL REVENUE BUSINESS POLICY
 * =========================================================
 */

$check(
    'SERVICE_REJECTS_DIRECT_CYCLE_SALE',
    $has(
        $src['service'],
        'cycle-attributed sale cannot also use shared revenue allocation'
    )
);

$check(
    'SERVICE_REJECTS_AUTOMATIC_LAYER_EGG',
    $has(
        $src['service'],
        'automatic unsold-egg allocation contract'
    )
);

$check(
    'SERVICE_REJECTS_ANIMAL_OVERLAP',
    $has(
        $src['service'],
        'individual animals cannot also be allocated to production cycles'
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
    'SERVICE_REJECTS_FUTURE_START_TARGET',
    $has(
        $src['service'],
        'starts after the sale date'
    )
);

$check(
    'SERVICE_OWNS_MANUAL_BASIS',
    $has(
        $src['service'],
        "'manual_shared_revenue'"
    )
);


/*
 * =========================================================
 * PERSISTENCE / PROVENANCE
 * =========================================================
 */

$check(
    'PERSISTENCE_REQUIRES_CALLER_TRANSACTION',
    $has(
        $src['persistence'],
        'requires an active transaction'
    )
);

$check(
    'PERSISTENCE_LOCKS_PARENT',
    $has(
        $src['persistence'],
        'sale_revenue_allocation_persistence_parent('
    )
);

$check(
    'PERSISTENCE_LOCKS_CURRENT_PROJECTION',
    $has(
        $src['persistence'],
        'sale_revenue_allocation_persistence_current_rows('
    )
);

$check(
    'PERSISTENCE_CHECKS_ANIMAL_STATE',
    $has(
        $src['persistence'],
        'sale_revenue_allocation_persistence_animal_count('
    )
);

$check(
    'PERSISTENCE_HAS_REVISION_CONSISTENCY_GUARD',
    $has(
        $src['persistence'],
        'sale_revenue_allocation_persistence_assert_revision_consistency'
    )
);

$check(
    'PERSISTENCE_HAS_APPEND_ONLY_REVISION_WRITER',
    $has(
        $src['persistence'],
        'sale_revenue_allocation_persistence_append_revision'
    )
);

$check(
    'PROVENANCE_FILE_PRESENT_AND_USED',
    $has(
        $src['persistence'],
        'sale_revenue_allocation_provenance.php'
    )
    &&
    $has(
        $src['persistence'],
        'sale_revenue_allocation_provenance_build'
    )
);


/*
 * =========================================================
 * LIFECYCLE / WRONG-AUTHORITY BOUNDARIES
 * =========================================================
 */

$revenueSurface =
    (string)$src['workspace']
    . "\n"
    . (string)$src['page']
    . "\n"
    . (string)$src['api'];

$check(
    'REVENUE_SURFACE_DOES_NOT_USE_EXPENSE_STORAGE',
    strpos(
        $revenueSurface,
        'financial_allocations'
    ) === false
);

$check(
    'REVENUE_SURFACE_DOES_NOT_USE_EXPENSE_WRITER',
    strpos(
        $revenueSurface,
        'financial_allocation_persistence_apply'
    ) === false
);

$check(
    'REVENUE_SURFACE_DOES_NOT_CALL_LEGACY_CLEAR',
    strpos(
        $revenueSurface,
        'sales_clear_allocations('
    ) === false
);

$check(
    'REVENUE_SURFACE_DOES_NOT_CALL_AUTOMATIC_REFRESH',
    strpos(
        $revenueSurface,
        'sales_refresh_automatic_allocation('
    ) === false
);

$check(
    'API_DOES_NOT_DIRECTLY_MUTATE_REVISION_TABLES',
    strpos(
        (string)$src['api'],
        'sales_allocation_revisions'
    ) === false
    &&
    strpos(
        (string)$src['api'],
        'sales_allocation_revision_rows'
    ) === false
);

$check(
    'PAGE_DOES_NOT_DIRECTLY_MUTATE_REVISION_TABLES',
    strpos(
        (string)$src['page'],
        'sales_allocation_revisions'
    ) === false
    &&
    strpos(
        (string)$src['page'],
        'sales_allocation_revision_rows'
    ) === false
);


/*
 * =========================================================
 * PROFITABILITY QUEUE
 * =========================================================
 */

$check(
    'PROFITABILITY_USES_ELIGIBILITY_ADAPTER',
    $has(
        $src['profitability_reader'],
        'sale_revenue_allocation_workspace_parent_is_eligible'
    )
);

$check(
    'PROFITABILITY_USES_ACCESS_ADAPTER',
    $has(
        $src['profitability_reader'],
        'sale_revenue_allocation_workspace_can_access'
    )
);

$check(
    'PROFITABILITY_USES_URL_ADAPTER',
    $has(
        $src['profitability_reader'],
        'sale_revenue_allocation_workspace_url'
    )
);

$check(
    'PROFITABILITY_EXPOSES_REVENUE_KIND',
    $has(
        $src['profitability_reader'],
        "'revenue'"
    )
);

$check(
    'PROFITABILITY_NEVER_CALLS_REVENUE_WRITER',
    !$has(
        $src['profitability_reader'],
        'sale_revenue_allocation_persistence_apply('
    )
    &&
    !$has(
        $src['profitability_page'],
        'sale_revenue_allocation_persistence_apply('
    )
);

$check(
    'PROFITABILITY_RENDERER_HAS_REVENUE_DESTINATION',
    $has(
        $src['profitability_page'],
        'Open shared-revenue allocation workspace'
    )
);


/*
 * =========================================================
 * SHARED JS — ONE FIX, FIT IT ALL
 * =========================================================
 */

$check(
    'SHARED_JS_GENERIC_PARENT_FIELD',
    $has(
        $src['js'],
        'form.dataset.parentIdField'
    )
);

$check(
    'SHARED_JS_GENERIC_PARENT_ID',
    $has(
        $src['js'],
        'form.dataset.parentId'
    )
);

$check(
    'SHARED_JS_PRESERVES_EXPENSE_FALLBACK',
    $has(
        $src['js'],
        "|| 'expense_id'"
    )
    &&
    $has(
        $src['js'],
        'form.dataset.expenseId'
    )
);

$check(
    'SHARED_JS_PRESERVES_REASON_POLICY_ADAPTER',
    $has(
        $src['js'],
        'form.dataset.reasonRequired'
    )
);

$check(
    'EXPENSE_PAGE_STILL_USES_SHARED_JS',
    $has(
        $src['expense_page'],
        '/assets/js/financial-allocation-workspace.js'
    )
);

$check(
    'EXPENSE_PAGE_STILL_PROVIDES_EXPENSE_ID',
    $has(
        $src['expense_page'],
        'data-expense-id='
    )
);


/*
 * =========================================================
 * RESULT
 * =========================================================
 */

$failed = [];

foreach ($checks as $name => $ok) {
    echo
        $name
        . '='
        . ($ok ? 'PASS' : 'FAIL')
        . PHP_EOL;

    if (!$ok) {
        $failed[] = $name;
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
    . ($failed ? 'FAIL' : 'PASS')
    . PHP_EOL;

exit($failed ? 1 : 0);
