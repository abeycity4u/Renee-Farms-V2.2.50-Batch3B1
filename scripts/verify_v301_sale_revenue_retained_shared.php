<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$paths = [
    'persistence' =>
        $root
        . '/lib/sale_revenue_allocation_persistence.php',

    'workspace' =>
        $root
        . '/lib/sale_revenue_allocation_workspace.php',

    'api' =>
        $root
        . '/api/update_sale_revenue_allocation.php',

    'page' =>
        $root
        . '/management/sale_revenue_allocation.php',

    'js' =>
        $root
        . '/assets/js/financial-allocation-workspace.js',

    'profit_reader' =>
        $root
        . '/lib/profitability_unallocated_shared.php',

    'profit_page' =>
        $root
        . '/management/profitability.php',
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


foreach ($src as $key => $source) {
    $check(
        'FILE_'
        . strtoupper($key)
        . '_PRESENT',
        is_string($source)
    );
}


/* Canonical persistence decision. */
$check(
    'PERSISTENCE_HAS_RETAIN_SHARED_WRITER',
    $has(
        $src['persistence'],
        'function sale_revenue_allocation_persistence_retain_shared'
    )
);

$check(
    'PERSISTENCE_REQUIRES_CALLER_TRANSACTION',
    $has(
        $src['persistence'],
        'sale_revenue_allocation_persistence_require_transaction('
    )
);

$check(
    'PERSISTENCE_REQUIRES_EMPTY_CYCLE_PROJECTION',
    $has(
        $src['persistence'],
        'Only fully unallocated shared revenue can be retained entirely as shared.'
    )
);

$check(
    'PERSISTENCE_REJECTS_ANIMAL_OVERLAP',
    $has(
        $src['persistence'],
        'Revenue allocated to individual animals cannot also be retained as shared revenue.'
    )
);

$check(
    'PERSISTENCE_USES_EXISTING_REVISION_LEDGER',
    $has(
        $src['persistence'],
        "sale_revenue_allocation_persistence_append_revision("
    )
    &&
    $has(
        $src['persistence'],
        "'retain_shared'"
    )
);

$check(
    'PERSISTENCE_REUSES_REVISION_REASON_POLICY',
    $has(
        $src['persistence'],
        "sale_revenue_allocation_persistence_reason("
    )
);

$check(
    'PERSISTENCE_RETAIN_DECISION_DOES_NOT_WRITE_PROJECTION',
    preg_match(
        '/function\s+sale_revenue_allocation_persistence_retain_shared[\s\S]*?function\s+sale_revenue_allocation_persistence_apply/',
        (string)$src['persistence'],
        $m
    ) === 1
    &&
    strpos(
        (string)($m[0] ?? ''),
        'sale_revenue_allocation_persistence_write_projection('
    ) === false
);

$check(
    'PERSISTENCE_RETAIN_DECISION_IS_IDEMPOTENT',
    $has(
        $src['persistence'],
        "=== 'retain_shared'"
    )
    &&
    $has(
        $src['persistence'],
        "'changed' =>\n                false"
    )
);


/* Workspace read state. */
$check(
    'WORKSPACE_DERIVES_RETAINED_STATE_FROM_REVISION',
    $has(
        $src['workspace'],
        "\$latestRevisionAction"
    )
    &&
    $has(
        $src['workspace'],
        "=== 'retain_shared'"
    )
);

$check(
    'WORKSPACE_EXPOSES_RESOLUTION_STATUS',
    $has(
        $src['workspace'],
        "'resolution_status'"
    )
);

$check(
    'WORKSPACE_EXPOSES_RETAINED_REASON',
    $has(
        $src['workspace'],
        "'retained_shared_reason'"
    )
);


/* API remains thin/security-owned. */
$check(
    'API_SUPPORTS_RETAIN_DECISION',
    $has(
        $src['api'],
        "'retain_shared'"
    )
    &&
    $has(
        $src['api'],
        'sale_revenue_allocation_persistence_retain_shared('
    )
);

$check(
    'API_CANONICAL_RETAIN_WRITER_ONCE',
    $count(
        $src['api'],
        'sale_revenue_allocation_persistence_retain_shared('
    ) === 1
);

$check(
    'API_CANONICAL_ALLOCATION_WRITER_ONCE',
    $count(
        $src['api'],
        'sale_revenue_allocation_persistence_apply('
    ) === 1
);

$check(
    'API_RETAIN_REJECTS_ROWS',
    $has(
        $src['api'],
        'Clear cycle amounts before retaining this revenue as shared.'
    )
);

$check(
    'API_STILL_POST_CSRF_RATE_LIMITED',
    $has(
        $src['api'],
        "require_http_method('POST')"
    )
    &&
    $has(
        $src['api'],
        'require_csrf_token()'
    )
    &&
    $has(
        $src['api'],
        'require_rate_limit('
    )
);

$check(
    'API_HAS_NO_DIRECT_SQL',
    !$has(
        $src['api'],
        '->prepare('
    )
    &&
    !$has(
        $src['api'],
        '->query('
    )
    &&
    !$has(
        $src['api'],
        '->exec('
    )
);


/* UI / interaction. */
$check(
    'PAGE_HAS_KEEP_SHARED_ACTION',
    $has(
        $src['page'],
        'financialAllocationRetainSharedButton'
    )
    &&
    $has(
        $src['page'],
        'Keep as Shared Revenue'
    )
);


$check(
    'PAGE_RETAIN_ACTION_AVAILABLE_WITH_ZERO_COMPATIBLE_CYCLES',
    $has(
        $src['page'],
        "<?php if (!\$workspace['cycles']): ?>"
    )
    &&
    $has(
        $src['page'],
        "<?php endif; ?>\n\n            <form\n                id=\"financialAllocationWorkspaceForm\""
    )
);

$check(
    'PAGE_CYCLE_ALLOCATION_CONTROLS_DISABLE_WITH_ZERO_TARGETS',
    substr_count(
        (string)$src['page'],
        "!\$workspace['cycles']"
    ) >= 3
);

$check(
    'PAGE_SHOWS_RETAINED_STATE',
    $has(
        $src['page'],
        'Retained as shared revenue.'
    )
);

$check(
    'PAGE_EXPLAINS_FARM_LEVEL_REVENUE',
    $has(
        $src['page'],
        'not assigned to an individual production cycle'
    )
);

$check(
    'JS_READS_SUBMITTER_DECISION',
    $has(
        $src['js'],
        'submitter.dataset.allocationDecision'
    )
);

$check(
    'JS_REQUIRES_RETAIN_REASON',
    $has(
        $src['js'],
        "decisionAction === 'retain_shared'"
    )
    &&
    $has(
        $src['js'],
        'Enter a reason for keeping this revenue at shared-operation level.'
    )
);

$check(
    'JS_SENDS_DECISION_ACTION',
    $has(
        $src['js'],
        "'decision_action'"
    )
);



$check(
    'JS_NORMAL_SAVE_STAYS_DISABLED_WITH_ZERO_TARGETS',
    $has(
        $src['js'],
        'const hasAllocationTargets ='
    )
    &&
    $has(
        $src['js'],
        '!hasAllocationTargets'
    )
);

/* Profitability state clarity. */
$check(
    'PROFITABILITY_DERIVES_RETAINED_FROM_REVISION',
    $has(
        $src['profit_reader'],
        "=== 'retain_shared'"
    )
    &&
    $has(
        $src['profit_reader'],
        "'retained_shared'"
    )
);

$check(
    'PROFITABILITY_HAS_PARTIAL_STATE',
    $has(
        $src['profit_reader'],
        "'partially_allocated'"
    )
);

$check(
    'PROFITABILITY_RENDERER_LABELS_RETAINED',
    $has(
        $src['profit_page'],
        'Retained as shared'
    )
);

$check(
    'PROFITABILITY_RENDERER_LABELS_PARTIAL',
    $has(
        $src['profit_page'],
        'Partially allocated'
    )
);


/* No parallel schema/storage authority introduced. */
$combined =
    (string)$src['persistence']
    . "\n"
    . (string)$src['workspace']
    . "\n"
    . (string)$src['api'];

$check(
    'NO_PARALLEL_RESOLUTION_TABLE',
    strpos(
        $combined,
        'sale_revenue_resolution'
    ) === false
    &&
    strpos(
        $combined,
        'CREATE TABLE'
    ) === false
);


$failed = [];

foreach ($checks as $name => $ok) {
    echo
        $name
        . '='
        . ($ok ? 'PASS' : 'FAIL')
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
    . ($failed ? 'FAIL' : 'PASS')
    . PHP_EOL;

exit(
    $failed
        ? 1
        : 0
);
