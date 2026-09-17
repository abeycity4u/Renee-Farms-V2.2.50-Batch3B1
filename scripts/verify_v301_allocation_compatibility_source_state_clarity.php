<?php

$root =
    dirname(__DIR__);

$paths = [
    'stock_workspace' =>
        $root
        . '/lib/stock_consumption_allocation_workspace.php',

    'financial_workspace' =>
        $root
        . '/lib/financial_allocation_workspace.php',

    'stock_api' =>
        $root
        . '/api/get_stock_history.php',

    'stock_js' =>
        $root
        . '/assets/js/stock-history.js',

    'stock_page' =>
        $root
        . '/management/stock_consumption_allocation.php',

    'expense_page' =>
        $root
        . '/management/expense_allocation.php',
];

$source = [];

foreach ($paths as $key => $path) {
    $value =
        file_get_contents(
            $path
        );

    if ($value === false) {
        echo "RESULT=FAIL\n";
        echo "CHECK_COUNT=0\n";
        echo "FAILED=SOURCE_MISSING_"
            . strtoupper($key)
            . "\n";
        exit(1);
    }

    $source[$key] =
        $value;
}

$checks = [];

function f2_check(
    array &$checks,
    string $name,
    bool $passed
): void {
    $checks[$name] =
        $passed;
}

f2_check(
    $checks,
    'STOCK_SOURCE_STATE_HELPER_PRESENT',
    strpos(
        $source['stock_workspace'],
        'stock_consumption_allocation_workspace_source_state('
    ) !== false
);

f2_check(
    $checks,
    'SOURCE_STATE_CONSUMES_CANONICAL_STATUS',
    strpos(
        $source['stock_workspace'],
        "'source_attribution_status'"
    ) !== false
    &&
    strpos(
        $source['stock_workspace'],
        "'source_state' =>"
    ) !== false
);

f2_check(
    $checks,
    'MOVEMENT_AUTHORITY_CLARITY_PRESENT',
    strpos(
        $source['stock_workspace'],
        'Movement-defined shared source'
    ) !== false
    &&
    strpos(
        $source['stock_workspace'],
        'passed the canonical shared-cost contract'
    ) !== false
);

f2_check(
    $checks,
    'STOCK_INCOMPATIBLE_CYCLES_PRESERVED',
    strpos(
        $source['stock_workspace'],
        '$incompatibleCycles[] = ['
    ) !== false
    &&
    strpos(
        $source['stock_workspace'],
        "'incompatible_cycles' =>"
    ) !== false
);

f2_check(
    $checks,
    'STOCK_INCOMPATIBILITY_REASON_FROM_EXCEPTION',
    strpos(
        $source['stock_workspace'],
        '(string)$e->getMessage()'
    ) !== false
);

f2_check(
    $checks,
    'FINANCIAL_INCOMPATIBLE_CYCLES_PRESERVED',
    strpos(
        $source['financial_workspace'],
        '$incompatibleCycles[] = ['
    ) !== false
    &&
    strpos(
        $source['financial_workspace'],
        "'incompatible_cycles' =>"
    ) !== false
);

f2_check(
    $checks,
    'FINANCIAL_INCOMPATIBILITY_REASON_FROM_EXCEPTION',
    strpos(
        $source['financial_workspace'],
        '(string)$e->getMessage()'
    ) !== false
);

f2_check(
    $checks,
    'STOCK_API_EXPOSES_CANONICAL_REASON',
    strpos(
        $source['stock_api'],
        "'reason' =>"
    ) !== false
    &&
    strpos(
        $source['stock_api'],
        "\$allocationAction['reason']"
    ) !== false
);

f2_check(
    $checks,
    'STOCK_HISTORY_RENDERS_REASON',
    strpos(
        $source['stock_js'],
        'allocation.reason'
    ) !== false
    &&
    strpos(
        $source['stock_js'],
        'escapeHtml(allocation.reason)'
    ) !== false
);

f2_check(
    $checks,
    'STOCK_PAGE_RENDERS_SOURCE_STATE',
    strpos(
        $source['stock_page'],
        'Source attribution:'
    ) !== false
    &&
    strpos(
        $source['stock_page'],
        "\$sourceState['label']"
    ) !== false
    &&
    strpos(
        $source['stock_page'],
        "\$sourceState['message']"
    ) !== false
);

f2_check(
    $checks,
    'STOCK_PAGE_RENDERS_INCOMPATIBLE_REASON',
    strpos(
        $source['stock_page'],
        'Incompatible Production Cycles'
    ) !== false
    &&
    strpos(
        $source['stock_page'],
        "\$cycle['reason']"
    ) !== false
);

f2_check(
    $checks,
    'FINANCIAL_PAGE_RENDERS_INCOMPATIBLE_REASON',
    strpos(
        $source['expense_page'],
        'Incompatible Production Cycles'
    ) !== false
    &&
    strpos(
        $source['expense_page'],
        "\$cycle['reason']"
    ) !== false
);

f2_check(
    $checks,
    'COMPATIBLE_STOCK_SECTION_PRESERVED',
    strpos(
        $source['stock_page'],
        'Compatible Production Cycles'
    ) !== false
);

f2_check(
    $checks,
    'COMPATIBLE_FINANCIAL_SECTION_PRESERVED',
    strpos(
        $source['expense_page'],
        'Compatible Production Cycles'
    ) !== false
);

f2_check(
    $checks,
    'STOCK_PAGE_DOES_NOT_CALL_TARGET_POLICY',
    strpos(
        $source['stock_page'],
        'stock_consumption_allocation_service_target_contract('
    ) === false
);

f2_check(
    $checks,
    'FINANCIAL_PAGE_DOES_NOT_CALL_TARGET_POLICY',
    strpos(
        $source['expense_page'],
        'financial_allocation_service_target_contract('
    ) === false
);

f2_check(
    $checks,
    'WORKSPACES_REMAIN_READ_ONLY',
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE\s+[A-Za-z_]|DELETE\s+FROM)\b/i',
        $source['stock_workspace']
        . "\n"
        . $source['financial_workspace']
    ) !== 1
);

f2_check(
    $checks,
    'NO_TRANSACTION_OWNERSHIP_ADDED',
    preg_match(
        '/->\s*(?:beginTransaction|commit|rollBack)\s*\(/',
        $source['stock_workspace']
        . "\n"
        . $source['financial_workspace']
    ) !== 1
);

f2_check(
    $checks,
    'EXISTING_STOCK_TOTAL_FIELDS_PRESERVED',
    strpos(
        $source['stock_workspace'],
        "'parent_amount' =>"
    ) !== false
    &&
    strpos(
        $source['stock_workspace'],
        "'allocated_amount' =>"
    ) !== false
    &&
    strpos(
        $source['stock_workspace'],
        "'remaining_amount' =>"
    ) !== false
);

f2_check(
    $checks,
    'EXISTING_FINANCIAL_TOTAL_FIELDS_PRESERVED',
    strpos(
        $source['financial_workspace'],
        "'gross_amount' =>"
    ) !== false
    &&
    strpos(
        $source['financial_workspace'],
        "'allocated_amount' =>"
    ) !== false
    &&
    strpos(
        $source['financial_workspace'],
        "'remaining_amount' =>"
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
