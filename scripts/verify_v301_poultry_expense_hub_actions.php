<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$page =
    (string)file_get_contents(
        $root
        . '/poultry/expenses.php'
    );

$js =
    (string)file_get_contents(
        $root
        . '/assets/js/poultry-expenses.js'
    );

$update =
    (string)file_get_contents(
        $root
        . '/api/update_expense.php'
    );

$delete =
    (string)file_get_contents(
        $root
        . '/api/delete_expense.php'
    );

$checks = 0;
$failed = 0;

function poultry_expense_hub_action_check(
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


poultry_expense_hub_action_check(
    'ROW_EDIT_USES_CANONICAL_PERMISSION',
    strpos(
        $page,
        '$canEditExpense ='
    ) !== false
    &&
    strpos(
        $page,
        "'edit'"
    ) !== false
    &&
    strpos(
        $page,
        'permission_catalog_expense_operational_can('
    ) !== false
);


poultry_expense_hub_action_check(
    'ROW_DELETE_USES_CANONICAL_PERMISSION',
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


poultry_expense_hub_action_check(
    'ROW_EDIT_BUTTON_EXISTS',
    strpos(
        $page,
        'poultry-expense-edit-btn'
    ) !== false
);


poultry_expense_hub_action_check(
    'ROW_DELETE_BUTTON_EXISTS',
    strpos(
        $page,
        'poultry-expense-delete-btn'
    ) !== false
);


poultry_expense_hub_action_check(
    'EDIT_MODAL_EXISTS',
    strpos(
        $page,
        'id="editPoultryExpenseModal"'
    ) !== false
    &&
    strpos(
        $page,
        'id="editPoultryExpenseForm"'
    ) !== false
);


poultry_expense_hub_action_check(
    'EDIT_FORM_POSTS_CANONICAL_PRODUCTION',
    strpos(
        $page,
        'id="editPoultryProductionType"'
    ) !== false
    &&
    strpos(
        $page,
        'name="production_type"'
    ) !== false
);


poultry_expense_hub_action_check(
    'EDIT_FORM_DOES_NOT_POST_POULTRY_CATEGORY',
    strpos(
        $page,
        'name="poultry_category"'
    ) === false
);


poultry_expense_hub_action_check(
    'EDIT_FORM_SUPPORTS_SHARED_NO_CYCLE',
    strpos(
        $page,
        'id="editPoultryExpenseCycle"'
    ) !== false
    &&
    strpos(
        $page,
        'Poultry-wide shared — no specific production cycle'
    ) !== false
);


poultry_expense_hub_action_check(
    'EDIT_TARGETS_ARE_PERMISSION_DERIVED',
    strpos(
        $page,
        '$editableProductionTypes'
    ) !== false
    &&
    strpos(
        $page,
        'poultry_expense_workspace_production_row('
    ) !== false
);


poultry_expense_hub_action_check(
    'JS_USES_UPDATE_API',
    strpos(
        $js,
        '../api/update_expense.php'
    ) !== false
);


poultry_expense_hub_action_check(
    'JS_USES_DELETE_API',
    strpos(
        $js,
        '../api/delete_expense.php'
    ) !== false
);


poultry_expense_hub_action_check(
    'JS_SENDS_OPERATIONAL_SCOPE',
    substr_count(
        $js,
        "'operational'"
    ) >= 2
);


poultry_expense_hub_action_check(
    'JS_SENDS_CSRF',
    strpos(
        $js,
        'csrf_token'
    ) !== false
    &&
    strpos(
        $page,
        'data-csrf-token='
    ) !== false
);


poultry_expense_hub_action_check(
    'EDIT_REQUIRES_REVISION_REASON',
    strpos(
        $js,
        'Why are you changing this expense?'
    ) !== false
    &&
    strpos(
        $js,
        'revision_reason'
    ) !== false
);


poultry_expense_hub_action_check(
    'DELETE_REQUIRES_REVISION_REASON',
    strpos(
        $js,
        'Why are you deleting this expense record?'
    ) !== false
);


poultry_expense_hub_action_check(
    'SHARED_EDIT_EXPLICITLY_SENDS_ZERO_CYCLE',
    strpos(
        $js,
        "editProduction.value === 'shared'"
    ) !== false
    &&
    strpos(
        $js,
        "formData.set(\n                        'cycle_id',\n                        '0'"
    ) !== false
);


poultry_expense_hub_action_check(
    'UPDATE_API_REAUTHORIZES_EXISTING_AND_TARGET',
    substr_count(
        $update,
        'permission_catalog_expense_operational_can('
    ) >= 2
);


poultry_expense_hub_action_check(
    'DELETE_API_REAUTHORIZES_ROW',
    strpos(
        $delete,
        'permission_catalog_expense_operational_can('
    ) !== false
);


poultry_expense_hub_action_check(
    'HUB_OWNS_NO_UPDATE_OR_DELETE_SQL',
    preg_match(
        '/\b(?:UPDATE|DELETE\s+FROM)\s+farm_expenses\b/i',
        $page
    ) !== 1
);


poultry_expense_hub_action_check(
    'JS_HAS_NO_NATIVE_CONFIRM_OR_ALERT',
    preg_match(
        '/\b(?:confirm|alert)\s*\(/',
        $js
    ) !== 1
);


poultry_expense_hub_action_check(
    'HUB_REMAINS_CSP_EXTERNAL',
    preg_match(
        '/<script(?![^>]*\bsrc=)[^>]*>[\s\S]*?<\/script>/i',
        $page
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
