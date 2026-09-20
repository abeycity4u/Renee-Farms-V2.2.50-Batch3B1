<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$pages = [
    'layer' =>
        (string)file_get_contents(
            $root
            . '/poultry/layer_expenses.php'
        ),

    'broiler' =>
        (string)file_get_contents(
            $root
            . '/poultry/broiler_expenses.php'
        ),
];

$scripts = [
    'layer' =>
        (string)file_get_contents(
            $root
            . '/assets/js/layer-expenses.js'
        ),

    'broiler' =>
        (string)file_get_contents(
            $root
            . '/assets/js/broiler-expenses.js'
        ),
];

$updateApi =
    (string)file_get_contents(
        $root
        . '/api/update_expense.php'
    );

$deleteApi =
    (string)file_get_contents(
        $root
        . '/api/delete_expense.php'
    );

$checks = 0;
$failed = 0;

function filtered_action_check(
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
    as $production
) {
    $upper =
        strtoupper(
            $production
        );

    $page =
        $pages[
            $production
        ];

    $js =
        $scripts[
            $production
        ];


    filtered_action_check(
        $upper
        . '_BROAD_MANAGEMENT_ACTION_FLAG_RETIRED',
        strpos(
            $page,
            '$canManageExpenses'
        ) === false
        &&
        strpos(
            $page,
            'data-can-manage='
        ) === false
    );


    filtered_action_check(
        $upper
        . '_ROW_EDIT_USES_CANONICAL_AUTHORITY',
        strpos(
            $page,
            '$canEditExpense ='
        ) !== false
        &&
        strpos(
            $page,
            'permission_catalog_expense_operational_can('
        ) !== false
        &&
        strpos(
            $page,
            "'edit'"
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_ROW_DELETE_USES_CANONICAL_AUTHORITY',
        strpos(
            $page,
            '$canDeleteExpense ='
        ) !== false
        &&
        strpos(
            $page,
            "'delete'"
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_EDIT_BUTTON_IS_ROW_GATED',
        strpos(
            $page,
            '<?php if ($canEditExpense): ?>'
        ) !== false
        &&
        strpos(
            $page,
            'edit-expense-btn'
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_DELETE_BUTTON_IS_ROW_GATED',
        strpos(
            $page,
            '<?php if ($canDeleteExpense): ?>'
        ) !== false
        &&
        strpos(
            $page,
            'data-delete-expense-id'
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_ALLOCATION_REMAINS_INDEPENDENT',
        strpos(
            $page,
            '$canAllocateExpense'
        ) !== false
        &&
        strpos(
            $page,
            'financial_allocation_workspace_can_access('
        ) !== false
        &&
        strpos(
            $page,
            '<?php if ($canAllocateExpense): ?>'
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_ACTION_COLUMN_USES_ACTUAL_CAPABILITY',
        strpos(
            $page,
            '$hasAnyExpenseActions'
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_EDIT_MODAL_USES_ACTUAL_EDIT_CAPABILITY',
        strpos(
            $page,
            '<?php if ($hasAnyEditableExpenses): ?>'
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_ADD_PERMISSION_REMAINS_CANONICAL',
        strpos(
            $page,
            "\$canAddExpenses = poultry_expense_entry_can('"
            . $production
            . "', 'add');"
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_ADD_SHORTCUT_REMAINS_HUB_ONLY',
        strpos(
            $page,
            "/poultry/expenses.php?"
        ) !== false
        &&
        strpos(
            $page,
            'poultry_expense_entry_create('
        ) === false
    );


    filtered_action_check(
        $upper
        . '_JS_BROAD_CAN_MANAGE_RETIRED',
        strpos(
            $js,
            'canManage'
        ) === false
    );


    filtered_action_check(
        $upper
        . '_JS_SENDS_EXPLICIT_OPERATIONAL_SCOPE',
        substr_count(
            $js,
            "'operational'"
        ) >= 2
        &&
        strpos(
            $js,
            'permission_scope'
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_JS_DELEGATES_TO_CANONICAL_APIS',
        strpos(
            $js,
            '../api/update_expense.php'
        ) !== false
        &&
        strpos(
            $js,
            '../api/delete_expense.php'
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_JS_REQUIRES_REVISION_REASONS',
        strpos(
            $js,
            'Why are you changing this expense?'
        ) !== false
        &&
        strpos(
            $js,
            'Why are you deleting this expense record?'
        ) !== false
        &&
        strpos(
            $js,
            'revision_reason'
        ) !== false
    );


    filtered_action_check(
        $upper
        . '_JS_HAS_NO_NATIVE_DIALOG',
        preg_match(
            '/\b(?:alert|confirm)\s*\(/',
            $js
        ) !== 1
    );
}


filtered_action_check(
    'UPDATE_API_REAUTHORIZES_OPERATIONAL_EDIT',
    substr_count(
        $updateApi,
        'permission_catalog_expense_operational_can('
    ) >= 2
);


filtered_action_check(
    'DELETE_API_REAUTHORIZES_OPERATIONAL_DELETE',
    strpos(
        $deleteApi,
        'permission_catalog_expense_operational_can('
    ) !== false
);


filtered_action_check(
    'FILTERED_PAGES_OWN_NO_EXPENSE_MUTATION_SQL',
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+farm_expenses\b/i',
        $pages['layer']
        . "\n"
        . $pages['broiler']
    ) !== 1
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
