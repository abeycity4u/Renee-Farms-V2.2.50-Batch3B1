<?php

$root =
    dirname(__DIR__);

$files = [
    'workspace' =>
        $root
        . '/lib/financial_allocation_workspace.php',

    'api' =>
        $root
        . '/api/update_financial_allocation.php',

    'page' =>
        $root
        . '/management/expense_allocation.php',

    'js' =>
        $root
        . '/assets/js/financial-allocation-workspace.js',

    'management' =>
        $root
        . '/management/expenses.php',

    'layer' =>
        $root
        . '/poultry/layer_expenses.php',

    'broiler' =>
        $root
        . '/poultry/broiler_expenses.php',

    'ruminant' =>
        $root
        . '/ruminant/ruminant_expenses.php',
];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            'Missing file: '
            . $name
            . PHP_EOL
        );

        exit(1);
    }
}

$content = [];

foreach ($files as $name => $path) {
    $value =
        file_get_contents(
            $path
        );

    if (!is_string($value)) {
        fwrite(
            STDERR,
            'Unreadable file: '
            . $name
            . PHP_EOL
        );

        exit(1);
    }

    $content[$name] =
        $value;
}

$checks = [];

$checks['WORKSPACE_CENTRAL_READER'] =
    strpos(
        $content['workspace'],
        'function financial_allocation_workspace_snapshot('
    ) !== false;

$checks['WORKSPACE_CENTRAL_PERMISSION'] =
    strpos(
        $content['workspace'],
        'function financial_allocation_workspace_can_access('
    ) !== false;

$checks['WORKSPACE_PARENT_CONTRACT_AUTHORITY'] =
    strpos(
        $content['workspace'],
        'financial_allocation_service_parent_contract('
    ) !== false;

$checks['WORKSPACE_TARGET_CONTRACT_AUTHORITY'] =
    strpos(
        $content['workspace'],
        'financial_allocation_service_target_contract('
    ) !== false;

$checks['WORKSPACE_CURRENT_ROWS_AUTHORITY'] =
    strpos(
        $content['workspace'],
        'financial_allocation_service_current_rows('
    ) !== false;

$checks['WORKSPACE_VISIBLE_REMAINDER'] =
    strpos(
        $content['workspace'],
        "'remaining_amount'"
    ) !== false;

$checks['WORKSPACE_NO_CYCLE_STATUS_RESTRICTION'] =
    strpos(
        $content['workspace'],
        'Closed cycles remain visible'
    ) !== false;

$checks['API_POST_ONLY'] =
    strpos(
        $content['api'],
        "require_http_method('POST')"
    ) !== false;

$checks['API_CSRF_REQUIRED'] =
    strpos(
        $content['api'],
        'require_csrf_token();'
    ) !== false;

$checks['API_RATE_LIMITED'] =
    strpos(
        $content['api'],
        "'update_financial_allocation'"
    ) !== false;

$checks['API_TENANT_SCOPED'] =
    strpos(
        $content['api'],
        'requireCurrentFarmId()'
    ) !== false;

$checks['API_LOCKED_PERMISSION_CHECK'] =
    strpos(
        $content['api'],
        'financial_allocation_service_parent('
    ) !== false
    &&
    strpos(
        $content['api'],
        'financial_allocation_workspace_can_access('
    ) !== false;

$checks['API_CANONICAL_PERSISTENCE_ONLY'] =
    strpos(
        $content['api'],
        'financial_allocation_persistence_apply('
    ) !== false;

$checks['API_NO_DIRECT_ALLOCATION_SQL'] =
    preg_match(
        '/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+financial_allocations/i',
        $content['api']
    ) !== 1;

$checks['API_CALLER_OWNS_TRANSACTION'] =
    strpos(
        $content['api'],
        '$pdo->beginTransaction();'
    ) !== false
    &&
    strpos(
        $content['api'],
        '$pdo->commit();'
    ) !== false
    &&
    strpos(
        $content['api'],
        '$pdo->rollBack();'
    ) !== false;

$checks['PAGE_THIN_NO_SQL'] =
    strpos(
        $content['page'],
        '->prepare('
    ) === false
    &&
    strpos(
        $content['page'],
        '->query('
    ) === false;

$checks['PAGE_USES_WORKSPACE_SNAPSHOT'] =
    strpos(
        $content['page'],
        'financial_allocation_workspace_snapshot('
    ) !== false;

$checks['PAGE_EXPLICIT_NO_AUTO_SPREAD_COPY'] =
    strpos(
        $content['page'],
        'will not automatically or equally spread this cost'
    ) !== false;

$checks['PAGE_VISIBLE_REMAINDER'] =
    strpos(
        $content['page'],
        'Unallocated Remainder'
    ) !== false;

$checks['PAGE_REVISION_REASON'] =
    strpos(
        $content['page'],
        'financialAllocationRevisionReason'
    ) !== false
    &&
    strpos(
        $content['page'],
        'Required for the expense revision audit trail.'
    ) !== false;

$checks['PAGE_ANIMAL_OVERLAP_GUIDANCE'] =
    strpos(
        $content['page'],
        'It cannot also be allocated to production cycles.'
    ) !== false;

$checks['JS_EXPLICIT_POSITIVE_ROWS_ONLY'] =
    strpos(
        $content['js'],
        'amount <= 0'
    ) !== false
    &&
    strpos(
        $content['js'],
        'allocated_amount:'
    ) !== false;

$checks['JS_REMAINDER_PREVIEW'] =
    strpos(
        $content['js'],
        'gross - allocated'
    ) !== false;

$checks['JS_ANIMAL_OVERLAP_STAYS_BLOCKED'] =
    strpos(
        $content['page'],
        'data-mutation-blocked='
    ) !== false
    &&
    strpos(
        $content['js'],
        "form.dataset.mutationBlocked === '1'"
    ) !== false
    &&
    strpos(
        $content['js'],
        'mutationBlocked'
    ) !== false
    &&
    strpos(
        $content['js'],
        'individual-animal allocations and cannot also be allocated'
    ) !== false;

$checks['JS_SHARED_NOTIFICATION'] =
    strpos(
        $content['js'],
        'window.showAlert'
    ) !== false
    &&
    strpos(
        $content['js'],
        'window.AppNotify'
    ) !== false;

$checks['MANAGEMENT_LINKED'] =
    strpos(
        $content['management'],
        'financial_allocation_workspace.php'
    ) !== false
    &&
    strpos(
        $content['management'],
        "financial_allocation_workspace_url((int)\$expense['id'], 'expense_report')"
    ) !== false;

$checks['LAYER_LINKED'] =
    strpos(
        $content['layer'],
        'financial_allocation_workspace.php'
    ) !== false
    &&
    strpos(
        $content['layer'],
        "financial_allocation_workspace_url((int)\$expense['id'], 'operational')"
    ) !== false;

$checks['BROILER_LINKED'] =
    strpos(
        $content['broiler'],
        'financial_allocation_workspace.php'
    ) !== false
    &&
    strpos(
        $content['broiler'],
        "financial_allocation_workspace_url((int)\$expense['id'], 'operational')"
    ) !== false;

$checks['RUMINANT_LINKED'] =
    strpos(
        $content['ruminant'],
        'financial_allocation_workspace.php'
    ) !== false
    &&
    strpos(
        $content['ruminant'],
        "financial_allocation_workspace_url((int)\$expense['id'], 'operational')"
    ) !== false;

$checks['ALL_LINKS_USE_CENTRAL_ELIGIBILITY'] =
    substr_count(
        implode(
            "\n",
            [
                $content['management'],
                $content['layer'],
                $content['broiler'],
                $content['ruminant'],
            ]
        ),
        'financial_allocation_workspace_parent_is_eligible('
    ) >= 4;

$checks['ALL_LINKS_USE_CENTRAL_PERMISSION'] =
    substr_count(
        implode(
            "\n",
            [
                $content['management'],
                $content['layer'],
                $content['broiler'],
                $content['ruminant'],
            ]
        ),
        'financial_allocation_workspace_can_access('
    ) >= 4;

$failed = [];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        $failed[] =
            $name;
    }
}

echo 'RESULT='
    . ($failed ? 'FAIL' : 'PASS')
    . PHP_EOL;

echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

foreach ($checks as $name => $passed) {
    echo $name
        . '='
        . ($passed ? 'PASS' : 'FAIL')
        . PHP_EOL;
}

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITES=NONE\n";

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
