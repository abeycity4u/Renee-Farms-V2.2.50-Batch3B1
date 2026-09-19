<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$paths = [
    'reader' =>
        $root
        . '/lib/profitability_unallocated_shared.php',

    'renderer' =>
        $root
        . '/management/profitability.php',

    'workspace' =>
        $root
        . '/lib/sale_revenue_allocation_workspace.php',

    'service' =>
        $root
        . '/lib/sale_revenue_allocation_service.php',

    'persistence' =>
        $root
        . '/lib/sale_revenue_allocation_persistence.php',
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
 * FILES
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


/*
 * =========================================================
 * REVENUE QUEUE WIRING
 * =========================================================
 */

$check(
    'READER_REQUIRES_REVENUE_WORKSPACE',
    $has(
        $src['reader'],
        "sale_revenue_allocation_workspace.php"
    )
);

$check(
    'REVENUE_QUERY_LOADS_FARM_ID',
    preg_match(
        '/SELECT[\s\S]*?\bfarm_id\b[\s\S]*?FROM\s+sales_records/i',
        (string)$src['reader']
    ) === 1
);

$check(
    'REVENUE_QUERY_LOADS_SALE_DATE',
    preg_match(
        '/SELECT[\s\S]*?\bsale_date\b[\s\S]*?FROM\s+sales_records/i',
        (string)$src['reader']
    ) === 1
);

$check(
    'REVENUE_QUERY_LOADS_CYCLE_ID',
    preg_match(
        '/SELECT[\s\S]*?\bcycle_id\b[\s\S]*?FROM\s+sales_records/i',
        (string)$src['reader']
    ) === 1
);

$check(
    'READER_USES_REVENUE_ELIGIBILITY_ADAPTER',
    $has(
        $src['reader'],
        'sale_revenue_allocation_workspace_parent_is_eligible'
    )
);

$check(
    'READER_USES_REVENUE_ACCESS_ADAPTER',
    $has(
        $src['reader'],
        'sale_revenue_allocation_workspace_can_access'
    )
);

$check(
    'READER_USES_REVENUE_URL_ADAPTER',
    $has(
        $src['reader'],
        'sale_revenue_allocation_workspace_url'
    )
);

$check(
    'READER_EXPOSES_REVENUE_KIND',
    preg_match(
        "/'allocation_kind'\s*=>[\s\S]*?'revenue'/",
        (string)$src['reader']
    ) === 1
);

$check(
    'READER_EXPOSES_ALLOCATION_URL',
    $has(
        $src['reader'],
        "'allocation_url'"
    )
);


/*
 * =========================================================
 * NON-ACTIONABLE SAFETY
 * =========================================================
 */

$check(
    'READER_CONDITIONS_URL_ON_ELIGIBILITY',
    preg_match(
        '/if\s*\([\s\S]*?sale_revenue_allocation_workspace_parent_is_eligible[\s\S]*?sale_revenue_allocation_workspace_can_access[\s\S]*?\)\s*\{[\s\S]*?sale_revenue_allocation_workspace_url/',
        (string)$src['reader']
    ) === 1
);

$check(
    'REVENUE_KIND_REQUIRES_URL',
    preg_match(
        "/'allocation_kind'\s*=>[\s\S]*?\\\$revenueAllocationUrl\s*!==\s*null[\s\S]*?\\?\s*'revenue'\s*:\s*null/",
        (string)$src['reader']
    ) === 1
);

$check(
    'SERVICE_REJECTS_DIRECT_CYCLE_AUTHORITY',
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
    'SERVICE_REJECTS_FUTURE_TARGET',
    $has(
        $src['service'],
        'starts after the sale date'
    )
);


/*
 * =========================================================
 * RENDERER
 * =========================================================
 */

$check(
    'RENDERER_READS_ALLOCATION_KIND',
    $has(
        $src['renderer'],
        "'allocation_kind'"
    )
);

$check(
    'RENDERER_READS_ALLOCATION_URL',
    $has(
        $src['renderer'],
        "'allocation_url'"
    )
);

$check(
    'RENDERER_HAS_REVENUE_TITLE',
    $has(
        $src['renderer'],
        "'Open shared-revenue allocation workspace'"
    )
);

$check(
    'RENDERER_RETAINS_EXPENSE_TITLE',
    $has(
        $src['renderer'],
        "'Open shared-expense allocation workspace'"
    )
);

$check(
    'RENDERER_RETAINS_STOCK_TITLE',
    $has(
        $src['renderer'],
        "'Open consumed-stock allocation workspace'"
    )
);


/*
 * =========================================================
 * AUTHORITY BOUNDARY
 * =========================================================
 */

$check(
    'PROFITABILITY_DOES_NOT_CALL_REVENUE_WRITER',
    $count(
        $src['reader'],
        'sale_revenue_allocation_persistence_apply('
    ) === 0
    &&
    $count(
        $src['renderer'],
        'sale_revenue_allocation_persistence_apply('
    ) === 0
);

$check(
    'WORKSPACE_URL_POINTS_TO_REVENUE_PAGE',
    $has(
        $src['workspace'],
        '/management/sale_revenue_allocation.php?'
    )
);

$check(
    'WORKSPACE_ACCESS_USES_SALES_EDIT',
    $has(
        $src['workspace'],
        "'sales_edit'"
    )
);

$check(
    'CANONICAL_WRITER_STILL_EXISTS',
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
