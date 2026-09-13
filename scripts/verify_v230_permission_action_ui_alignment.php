<?php

$root = dirname(__DIR__);
$checks = 0;
$failures = 0;

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    if (!is_file($path)) return '';
    $content = file_get_contents($path);
    return is_string($content) ? $content : '';
};

$check = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$prepaintCss = $read('assets/css/permission-prepaint.css');
$expenseDeleteCss = $read('assets/css/prepaint-expense-delete-readonly.css');
$runtimeJs = $read('assets/js/permission-runtime.js');
$runtimePhp = $read('includes/permission_runtime.php');
$navbar = $read('navbar_head.php');
$managementExpensesJs = $read('assets/js/management-expenses.js');
$animal = $read('ruminant/animal_registry.php');
$catalog = $read('includes/permission_catalog.php');
$appBehaviors = $read('assets/js/app-behaviors.js');
$deleteRecordApi = $read('api/delete_record.php');
$updateExpenseApi = $read('api/update_expense.php');
$deleteExpenseApi = $read('api/delete_expense.php');

$check(
    str_contains($appBehaviors, '[data-open-record-modal]'),
    'Shared application behavior uses CSP-safe Daily Record modal attribute'
);

$check(
    str_contains($prepaintCss, 'button[data-open-record-modal]'),
    'Daily Add prepaint targets current modal attribute'
);

$check(
    str_contains(
        $prepaintCss,
        '.calendar-day.add-record-btn:not(.has-record)'
    ),
    'Daily Add prepaint blocks empty calendar days when Add is denied'
);

$check(
    str_contains($prepaintCss, '.calendar-day.edit-record-btn')
    && str_contains(
        $prepaintCss,
        '.calendar-day.add-record-btn.has-record'
    ),
    'Daily Edit prepaint blocks existing-record calendar paths'
);

$check(
    str_contains($runtimeJs, 'button[data-open-record-modal]'),
    'Daily runtime cleanup targets current modal attribute'
);

$check(
    str_contains($expenseDeleteCss, 'button[data-delete-expense-id]'),
    'Operational expense Delete prepaint targets current delete attribute'
);

$check(
    str_contains($prepaintCss, 'button[data-delete-expense-id]'),
    'Global expense Delete prepaint targets current delete attribute'
);

$check(
    str_contains($runtimeJs, 'button[data-delete-expense-id]'),
    'Expense runtime cleanup targets current delete attribute'
);

$check(
    str_contains($prepaintCss, 'button[data-sale-delete-id]')
    && str_contains($runtimeJs, 'button[data-sale-delete-id]'),
    'Sales Delete permission guards target current delete attribute'
);

$check(
    str_contains($prepaintCss, 'button[data-ruminant-animal-new]')
    && str_contains($runtimeJs, 'button[data-ruminant-animal-new]'),
    'Ruminant Animal Add guards target current action attribute'
);

$check(
    str_contains($prepaintCss, 'button[data-ruminant-animal-edit]')
    && str_contains($runtimeJs, 'button[data-ruminant-animal-edit]'),
    'Ruminant Animal Edit guards target current action attribute'
);

$check(
    str_contains($prepaintCss, 'button[data-ruminant-animal-exit]')
    && str_contains($runtimeJs, 'button[data-ruminant-animal-exit]'),
    'Ruminant Animal Exit guards target current action attribute'
);

foreach ([
    'poultry_layer_expenses_add',
    'poultry_layer_expenses_edit',
    'poultry_layer_expenses_delete',
    'poultry_broiler_expenses_add',
    'poultry_broiler_expenses_edit',
    'poultry_broiler_expenses_delete',
    'ruminant_expenses_add',
    'ruminant_expenses_edit',
    'ruminant_expenses_delete',
] as $permission) {
    $check(
        str_contains(
            $runtimePhp,
            "permission_runtime_has('{$permission}')"
        ),
        "Runtime exposes/enforces {$permission}"
    );
}

$check(
    str_contains($runtimeJs, 'x.expenseEdit === false')
    && str_contains($runtimeJs, 'x.expenseDelete === false'),
    'Expense runtime independently removes unauthorized Edit/Delete controls'
);

foreach ([
    "'/poultry/layer_expenses.php' => [\n        'poultry_layer_expenses_edit',\n        'poultry_layer_expenses_delete',",
    "'/poultry/broiler_expenses.php' => [\n        'poultry_broiler_expenses_edit',\n        'poultry_broiler_expenses_delete',",
    "'/ruminant/ruminant_expenses.php' => [\n        'ruminant_expenses_edit',\n        'ruminant_expenses_delete',",
] as $needle) {
    $check(
        str_contains($navbar, $needle),
        'Legacy livestock expense Actions column uses exact Edit/Delete mapping'
    );
}

$check(
    str_contains(
        $navbar,
        '$canManageExpenses = $expensePrivileged'
    ),
    'View-only livestock expense pages suppress legacy Actions column'
);

$check(
    str_contains($navbar, "hasPermission(getUserType(), 'expenses_edit')")
    && str_contains($navbar, "hasPermission(getUserType(), 'expenses_delete')")
    && str_contains(
        $navbar,
        '$canEditExpenseRow = $canEditExpenseReport;'
    )
    && str_contains(
        $navbar,
        '$canDeleteExpenseRow = $canDeleteExpenseReport;'
    ),
    'Management Expense Report UI uses its own independent Edit/Delete permissions'
);

$check(
    str_contains(
        $managementExpensesJs,
        "const expensePermissionScope = 'expense_report';"
    )
    && str_contains(
        $managementExpensesJs,
        "formData.append('permission_scope', expensePermissionScope);"
    )
    && str_contains(
        $managementExpensesJs,
        'permission_scope: expensePermissionScope'
    ),
    'Management Expense Report sends explicit expense_report authorization scope'
);

$check(
    str_contains(
        $catalog,
        'permission_catalog_expense_report_row_accessible'
    ),
    'Expense Report API scope preserves report row visibility boundaries'
);

foreach ([
    'ruminant_animals_add',
    'ruminant_animals_edit',
    'ruminant_animals_exit',
] as $permission) {
    $check(
        str_contains(
            $animal,
            "hasPermission(getUserType(),'{$permission}')"
        ),
        "Animal Registry renders exact {$permission} capability"
    );
}

$check(
    str_contains(
        $animal,
        '$editId=$canEditAnimal ? (int)($_GET[\'edit\']??0) : 0;'
    ),
    'Direct Animal Registry edit query is gated by Edit permission'
);

$check(
    str_contains($animal, '<?php if($canAddAnimal): ?>'),
    'Animal Registry Add control uses Add permission'
);

$check(
    str_contains($animal, '<?php if($canEditAnimal): ?>'),
    'Animal Registry Edit control uses Edit permission'
);

$check(
    str_contains(
        $animal,
        "<?php if(\$canExitAnimal && \$a['status']==='active'): ?>"
    ),
    'Animal Registry Record Exit control uses Exit permission'
);

$check(
    str_contains(
        $animal,
        '$canSaveAnimal=$id>0 ? $canEditAnimal : $canAddAnimal;'
    ),
    'Animal Registry save POST distinguishes Add from Edit'
);

$check(
    str_contains(
        $animal,
        "if(!\$canExitAnimal){ http_response_code(403); exit('Access denied.'); }"
    ),
    'Animal Registry manual exit has page-level Exit guard'
);

foreach ([
    'poultry_daily_layer',
    'poultry_daily_layer_add',
    'poultry_daily_layer_edit',
    'poultry_daily_layer_delete',
    'poultry_daily_broiler',
    'poultry_daily_broiler_add',
    'poultry_daily_broiler_edit',
    'poultry_daily_broiler_delete',
    'ruminant_daily',
    'ruminant_daily_add',
    'ruminant_daily_edit',
    'ruminant_daily_delete',
] as $permission) {
    $check(
        str_contains($catalog, "'{$permission}' =>"),
        "Permission catalog preserves independent {$permission}"
    );
}

$check(
    str_contains($deleteRecordApi, '$viewPermission')
    && str_contains($deleteRecordApi, '$deletePermission')
    && str_contains(
        $deleteRecordApi,
        'View and Delete permissions are required'
    ),
    'Daily Record delete API still requires View and Delete'
);

$check(
    str_contains($updateExpenseApi, '$existingViewPermission')
    && str_contains($updateExpenseApi, '$existingPermission')
    && str_contains(
        $updateExpenseApi,
        "\$permissionScope === 'expense_report'"
    )
    && str_contains($updateExpenseApi, "'expenses_edit'"),
    'Expense update API separates Expense Report Edit from operational row Edit'
);

$check(
    str_contains($deleteExpenseApi, 'permission_catalog_expense_action_code')
    && str_contains(
        $deleteExpenseApi,
        "\$permissionScope==='expense_report'"
    )
    && str_contains($deleteExpenseApi, "'expenses_delete'")
    && str_contains(
        $deleteExpenseApi,
        'permission to delete this expense record'
    ),
    'Expense delete API separates Expense Report Delete from operational row Delete'
);

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
