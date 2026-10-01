<?php

$root =
    dirname(__DIR__);

$fail = 0;

$assert = static function (
    bool $condition,
    string $label
) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    echo "FAIL: {$label}\n";
    $fail = 1;
};

$read = static function (string $path) use ($root): string {
    $content =
        file_get_contents(
            $root . '/' . $path
        );

    if (!is_string($content)) {
        throw new RuntimeException(
            'Unable to read ' . $path
        );
    }

    return $content;
};

$catalog =
    $read(
        'includes/expense_category_catalog.php'
    );

$permissionCatalog =
    $read(
        'includes/permission_catalog.php'
    );

$service =
    $read(
        'lib/general_expense_entry.php'
    );

$createRoute =
    $read(
        'api/create_general_expense.php'
    );

$updateRoute =
    $read(
        'api/update_expense.php'
    );

$page =
    $read(
        'management/expenses.php'
    );

$javascript =
    $read(
        'assets/js/management-expenses.js'
    );


/*
 * Central category authority.
 */
require_once $root
    . '/includes/expense_category_catalog.php';

$generalCategories =
    expense_category_options(
        'general_operating'
    );

$manualCategories =
    expense_category_options(
        'manual'
    );

foreach (
    [
        'salary',
        'labour',
        'logistic',
        'fuel',
        'rent',
        'utilities',
        'marketing',
        'repairs_maintenance',
        'bank_charges',
        'communication',
        'taxes_levies',
        'professional_fees',
        'misc',
    ]
    as $category
) {
    $assert(
        array_key_exists(
            $category,
            $generalCategories
        ),
        "General operating surface contains {$category}"
    );
}

foreach (
    [
        'feeds',
        'medication',
        'processing_materials',
    ]
    as $category
) {
    $assert(
        !array_key_exists(
            $category,
            $generalCategories
        ),
        "General operating surface excludes {$category}"
    );
}

$assert(
    array_key_exists(
        'processing_materials',
        $manualCategories
    ),
    'Legacy manual surface still contains Processing Materials'
);

$assert(
    !array_key_exists(
        'rent',
        $manualCategories
    ),
    'Legacy manual surface is not broadened with General-only Rent'
);


/*
 * Permission boundary.
 */
$assert(
    strpos(
        $permissionCatalog,
        "'expenses_add'"
    ) !== false,
    'Central expenses_add permission exists'
);

$assert(
    preg_match(
        "/'expenses_add'\\s*=>.*?'roles'\\s*=>\\s*\\['sales_rep'\\]/s",
        $permissionCatalog
    ) === 1,
    'expenses_add delegation is scoped to Sales Rep'
);


/*
 * General creator contract.
 */
$assert(
    strpos(
        $service,
        "'general_operating'"
    ) !== false,
    'General creator uses general_operating category surface'
);

$assert(
    strpos(
        $service,
        'record_reference_persistence_insert_new'
    ) !== false,
    'General creator uses shared record-reference persistence'
);

$assert(
    strpos(
        $service,
        'expense_revision_service_record_created'
    ) !== false,
    'General creator uses shared expense revision authority'
);


/*
 * Thin create route.
 */
$assert(
    strpos(
        $createRoute,
        'current_farm_is_sales_only()'
    ) !== false,
    'Create route is Sales-only guarded'
);

$assert(
    strpos(
        $createRoute,
        "'expenses_add'"
    ) !== false,
    'Create route requires expenses_add for delegated users'
);

$assert(
    strpos(
        $createRoute,
        'general_expense_entry_create'
    ) !== false,
    'Create route delegates to General expense service'
);

$assert(
    strpos(
        $createRoute,
        'INSERT INTO farm_expenses'
    ) === false,
    'Create route contains no farm_expenses INSERT'
);


/*
 * General edit contract.
 */
$assert(
    strpos(
        $updateRoute,
        "'general_operating'"
    ) !== false,
    'General edits use general_operating category authority'
);

$assert(
    strpos(
        $catalog,
        "string \$surface = 'manual'"
    ) !== false,
    'Existing category-update callers retain manual default'
);


/*
 * Sales-only page contract.
 */
$assert(
    strpos(
        $page,
        'current_farm_is_sales_only()'
    ) !== false,
    'Expense Report detects Sales-only workspace'
);

$assert(
    strpos(
        $page,
        'id="createGeneralExpenseForm"'
    ) !== false,
    'Sales-only Record Expense form exists'
);

$assert(
    strpos(
        $page,
        "expense_category_options(\n        'general_operating'"
    ) !== false,
    'Sales-only create UI uses General category authority'
);

$formMatch = [];

preg_match(
    '/<form\s+id="createGeneralExpenseForm".*?<\/form>/s',
    $page,
    $formMatch
);

$form =
    $formMatch[0]
    ?? '';

$assert(
    $form !== '',
    'Create General Expense form can be structurally located'
);

foreach (
    [
        'name="farm_type"',
        'name="production_type"',
        'name="cycle_id"',
        'name="animal_id"',
    ]
    as $forbidden
) {
    $assert(
        strpos(
            $form,
            $forbidden
        ) === false,
        "Create form excludes {$forbidden}"
    );
}

foreach (
    [
        'name="expense_date"',
        'name="category"',
        'name="unit"',
        'name="amount"',
        'name="description"',
    ]
    as $required
) {
    $assert(
        strpos(
            $form,
            $required
        ) !== false,
        "Create form contains {$required}"
    );
}

$assert(
    strpos(
        $page,
        'value="general"'
    ) !== false,
    'Sales-only edit UI pins farm type to General'
);

$assert(
    strpos(
        $javascript,
        '../api/create_general_expense.php'
    ) !== false,
    'Browser create workflow calls canonical General create route'
);


if ($fail) {
    exit(1);
}

echo "SALES_ONLY_GENERAL_EXPENSE_WORKSPACE=PASS\n";
