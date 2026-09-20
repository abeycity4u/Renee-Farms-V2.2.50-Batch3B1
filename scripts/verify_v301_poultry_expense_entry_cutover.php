<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$paths = [
    'layer' =>
        $root
        . '/poultry/layer_expenses.php',

    'broiler' =>
        $root
        . '/poultry/broiler_expenses.php',

    'hub' =>
        $root
        . '/poultry/expenses.php',

    'hub_js' =>
        $root
        . '/assets/js/poultry-expenses.js',

    'service' =>
        $root
        . '/lib/poultry_expense_entry.php',
];

$src = [];

foreach ($paths as $key => $path) {
    $src[$key] =
        is_file($path)
            ? (string)file_get_contents(
                $path
            )
            : '';
}

$checks = 0;
$failed = 0;

function poultry_expense_cutover_check(
    string $name,
    bool $ok
): void {
    global $checks, $failed;

    $checks++;

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
        $failed++;
    }
}


foreach (
    [
        'layer',
        'broiler',
    ]
    as $key
) {
    $upper =
        strtoupper(
            $key
        );

    poultry_expense_cutover_check(
        $upper
        . '_LOADS_CANONICAL_SERVICE_FOR_ADD_CAPABILITY',
        strpos(
            $src[$key],
            'poultry_expense_entry.php'
        ) !== false
        &&
        strpos(
            $src[$key],
            '$canAddExpenses = poultry_expense_entry_can('
        ) !== false
    );


    poultry_expense_cutover_check(
        $upper
        . '_OWNS_NO_CREATE_WRITER_CALL',
        strpos(
            $src[$key],
            'poultry_expense_entry_create('
        ) === false
    );


    poultry_expense_cutover_check(
        $upper
        . '_OWNS_NO_LOCAL_ADD_POST_HANDLER',
        strpos(
            $src[$key],
            "isset(\$_POST['add_expense'])"
        ) === false
    );


    poultry_expense_cutover_check(
        $upper
        . '_OWNS_NO_LOCAL_ADD_MODAL',
        strpos(
            $src[$key],
            'id="addExpenseModal"'
        ) === false
    );


    poultry_expense_cutover_check(
        $upper
        . '_OWNS_NO_DIRECT_EXPENSE_INSERT',
        strpos(
            $src[$key],
            'INSERT INTO farm_expenses'
        ) === false
    );


    poultry_expense_cutover_check(
        $upper
        . '_OWNS_NO_DIRECT_REFERENCE_OR_CREATE_REVISION',
        strpos(
            $src[$key],
            'record_reference_persistence_assign_existing('
        ) === false
        &&
        strpos(
            $src[$key],
            'expense_revision_service_record_created('
        ) === false
        &&
        strpos(
            $src[$key],
            '$pdo->lastInsertId()'
        ) === false
    );


    poultry_expense_cutover_check(
        $upper
        . '_ADD_SHORTCUTS_TO_HUB',
        strpos(
            $src[$key],
            "/poultry/expenses.php?"
        ) !== false
        &&
        strpos(
            $src[$key],
            "'add' =>"
        ) !== false
        &&
        strpos(
            $src[$key],
            "'production_type' =>"
        ) !== false
    );


    poultry_expense_cutover_check(
        $upper
        . '_FILTERED_READ_VIEW_PRESERVED',
        strpos(
            $src[$key],
            "AND e.poultry_category = '"
            . $key
            . "'"
        ) !== false
    );


    poultry_expense_cutover_check(
        $upper
        . '_PDF_REPORT_PRESERVED',
        strpos(
            $src[$key],
            'PDF Report'
        ) !== false
        &&
        strpos(
            $src[$key],
            'pdf_report_finish('
        ) !== false
    );


    poultry_expense_cutover_check(
        $upper
        . '_EDIT_SURFACE_PRESERVED',
        strpos(
            $src[$key],
            'id="editExpenseModal"'
        ) !== false
        &&
        strpos(
            $src[$key],
            '../api/update_expense.php'
        ) === false
    );
}


poultry_expense_cutover_check(
    'LAYER_SHORTCUT_FIXES_LAYER_TARGET',
    strpos(
        $src['layer'],
        "'tab' =>\n                                                'layer'"
    ) !== false
    &&
    strpos(
        $src['layer'],
        "'production_type' =>\n                                                'layer'"
    ) !== false
);


poultry_expense_cutover_check(
    'BROILER_SHORTCUT_FIXES_BROILER_TARGET',
    strpos(
        $src['broiler'],
        "'tab' =>\n                                                'broiler'"
    ) !== false
    &&
    strpos(
        $src['broiler'],
        "'production_type' =>\n                                                'broiler'"
    ) !== false
);


poultry_expense_cutover_check(
    'HUB_IS_SINGLE_UI_CREATE_DELEGATE',
    substr_count(
        $src['hub'],
        'poultry_expense_entry_create('
    ) === 1
    &&
    strpos(
        $src['layer'],
        'poultry_expense_entry_create('
    ) === false
    &&
    strpos(
        $src['broiler'],
        'poultry_expense_entry_create('
    ) === false
);


poultry_expense_cutover_check(
    'HUB_SUPPORTS_DEEP_LINK_PRODUCTION_SELECTION',
    strpos(
        $src['hub_js'],
        "params.get(\n                    'production_type'"
    ) !== false
    &&
    strpos(
        $src['hub_js'],
        "params.get(\n            'add'"
    ) !== false
);


poultry_expense_cutover_check(
    'CANONICAL_SERVICE_PRESERVES_SAFE_CYCLE_VALIDATION',
    preg_match(
        '/attribution_validate_cycle\s*\([\s\S]*?catch\s*\(\s*PDOException\s+\$e\s*\)[\s\S]*?throw\s+\$e\s*;[\s\S]*?catch\s*\(\s*RuntimeException\s+\$e\s*\)[\s\S]*?throw\s+new\s+InvalidArgumentException\s*\(/',
        $src['service']
    ) === 1
);


poultry_expense_cutover_check(
    'CANONICAL_SERVICE_REMAINS_SINGLE_EXPENSE_INSERT_AUTHORITY',
    substr_count(
        $src['service'],
        'INSERT INTO farm_expenses'
    ) === 1
    &&
    strpos(
        $src['service'],
        'record_reference_persistence_assign_existing('
    ) !== false
    &&
    strpos(
        $src['service'],
        'expense_revision_service_record_created('
    ) !== false
);


poultry_expense_cutover_check(
    'OLD_FILTERED_PAGES_KEEP_NO_SHARED_ENTRY',
    strpos(
        $src['layer'],
        'value="shared"'
    ) === false
    &&
    strpos(
        $src['broiler'],
        'value="shared"'
    ) === false
);


echo
    'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . $failed
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
