<?php

$root =
    dirname(__DIR__);

$helper =
    file_get_contents(
        $root
        . '/lib/poultry_production_entry_clarity.php'
    );

$page =
    file_get_contents(
        $root
        . '/management/poultry_cycle.php'
    );

$snapshots =
    file_get_contents(
        $root
        . '/lib/poultry_production_entry_snapshots.php'
    );

$economics =
    file_get_contents(
        $root
        . '/lib/poultry_rearing_economics.php'
    );

if (
    $helper === false
    ||
    $page === false
    ||
    $snapshots === false
    ||
    $economics === false
) {
    echo "RESULT=FAIL\n";
    echo "CHECK_COUNT=0\n";
    echo "FAILED=SOURCE_LOAD\n";
    exit(1);
}

$checks = [];

function f3_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

f3_check(
    $checks,
    'CLARITY_HELPER_PRESENT',
    strpos(
        $helper,
        'poultry_production_entry_clarity_manifest_summary('
    ) !== false
);

f3_check(
    $checks,
    'CLARITY_HELPER_HAS_NO_DATABASE_ACCESS',
    strpos(
        $helper,
        'PDO'
    ) === false
    &&
    preg_match(
        '/\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b/i',
        $helper
    ) !== 1
);

f3_check(
    $checks,
    'CURRENT_SUMMARY_CONSUMES_CANONICAL_MANIFEST',
    strpos(
        $helper,
        "\$candidate[\n            'provenance_manifest'\n        ]"
    ) !== false
);

f3_check(
    $checks,
    'APPROVED_SUMMARY_CONSUMES_IMMUTABLE_MANIFEST_JSON',
    strpos(
        $helper,
        "'provenance_manifest_json'"
    ) !== false
    &&
    strpos(
        $helper,
        'JSON_THROW_ON_ERROR'
    ) !== false
);

f3_check(
    $checks,
    'DIRECT_FEED_PROVENANCE_CLASSIFIED',
    strpos(
        $helper,
        'feed_use|stock_transaction'
    ) !== false
);

f3_check(
    $checks,
    'ALLOCATED_FEED_PROVENANCE_CLASSIFIED',
    strpos(
        $helper,
        'feed_use|stock_consumption_allocation'
    ) !== false
);

f3_check(
    $checks,
    'DIRECT_OPERATING_PROVENANCE_CLASSIFIED',
    strpos(
        $helper,
        'operating_inventory_use|stock_transaction'
    ) !== false
);

f3_check(
    $checks,
    'ALLOCATED_OPERATING_PROVENANCE_CLASSIFIED',
    strpos(
        $helper,
        'operating_inventory_use|stock_consumption_allocation'
    ) !== false
);

f3_check(
    $checks,
    'DIRECT_EXPENSE_PROVENANCE_CLASSIFIED',
    strpos(
        $helper,
        'direct_expense|farm_expense'
    ) !== false
);

f3_check(
    $checks,
    'SHARED_EXPENSE_ALLOCATION_CLASSIFIED',
    strpos(
        $helper,
        'explicit_shared_allocation|financial_allocation'
    ) !== false
);

f3_check(
    $checks,
    'LIFECYCLE_BOUNDARY_CLASSIFIED',
    strpos(
        $helper,
        'lifecycle_phase|production_cycle_phase'
    ) !== false
);

f3_check(
    $checks,
    'POPULATION_AUTHORITY_CLASSIFIED',
    strpos(
        $helper,
        'population_baseline|production_population_baseline'
    ) !== false
    &&
    strpos(
        $helper,
        'population_movement|production_population_movement'
    ) !== false
);

f3_check(
    $checks,
    'DAILY_RECONCILIATION_CLASSIFIED',
    strpos(
        $helper,
        'production_start_daily_reconciliation|layer_daily_record'
    ) !== false
    &&
    strpos(
        $helper,
        'rearing_end_daily_reconciliation|layer_daily_record'
    ) !== false
);

f3_check(
    $checks,
    'OUTSIDE_CYCLE_DISCLOSURE_CLASSIFIED',
    strpos(
        $helper,
        'shared_pool_expense|farm_expense'
    ) !== false
    &&
    strpos(
        $helper,
        'shared_pool_allocation|financial_allocation'
    ) !== false
);

f3_check(
    $checks,
    'HELPER_DOES_NOT_RECONSTRUCT_MONEY',
    strpos(
        $helper,
        "'economic_amount'"
    ) === false
    &&
    strpos(
        $helper,
        "'allocated_amount'"
    ) === false
    &&
    strpos(
        $helper,
        "'total_cost'"
    ) === false
    &&
    strpos(
        $helper,
        "'gross_amount'"
    ) === false
);

f3_check(
    $checks,
    'PAGE_REQUIRES_CLARITY_HELPER',
    strpos(
        $page,
        "poultry_production_entry_clarity.php"
    ) !== false
);

f3_check(
    $checks,
    'PAGE_CONSUMES_CURRENT_SUMMARY',
    strpos(
        $page,
        'poultry_production_entry_clarity_candidate_summary('
    ) !== false
);

f3_check(
    $checks,
    'PAGE_CONSUMES_APPROVED_SUMMARY',
    strpos(
        $page,
        'poultry_production_entry_clarity_snapshot_summary('
    ) !== false
);

f3_check(
    $checks,
    'PAGE_RENDERS_PROVENANCE_BREAKDOWN',
    strpos(
        $page,
        'Provenance Source Breakdown'
    ) !== false
    &&
    strpos(
        $page,
        'Current Source Provenance'
    ) !== false
);

f3_check(
    $checks,
    'PAGE_RENDERS_APPROVED_PROVENANCE',
    strpos(
        $page,
        'Approved Provenance'
    ) !== false
);

f3_check(
    $checks,
    'PAGE_EXPLAINS_PROVENANCE_NOT_AMOUNT_LEDGER',
    strpos(
        $page,
        'Provenance is evidence, not an amount ledger.'
    ) !== false
);

f3_check(
    $checks,
    'PAGE_RENDERS_INCLUSION_BOUNDARY',
    strpos(
        $page,
        'Inclusion boundary'
    ) !== false
    &&
    strpos(
        $page,
        'Production entry'
    ) !== false
);

f3_check(
    $checks,
    'PAGE_PRESERVES_F1_COST_COMPOSITION',
    strpos(
        $page,
        'Direct/native cycle stock use:'
    ) !== false
    &&
    strpos(
        $page,
        'Explicit consumed-stock allocation:'
    ) !== false
);

f3_check(
    $checks,
    'PAGE_PRESERVES_OUTSIDE_CYCLE_DISCLOSURE',
    strpos(
        $page,
        'Any unallocated shared balance remains outside this cycle'
    ) !== false
);

f3_check(
    $checks,
    'CANONICAL_CANDIDATE_STILL_OWNS_MONEY',
    strpos(
        $snapshots,
        "\$candidate['attributed_investment']="
    ) !== false
    &&
    strpos(
        $economics,
        "\$base['known_attributable_rearing_cost'] = round"
    ) !== false
);

$failed = [];

foreach (
    $checks
    as $name => $passed
) {
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

foreach (
    $checks
    as $name => $passed
) {
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
