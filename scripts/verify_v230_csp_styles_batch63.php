<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$headPath = $root . '/navbar_head.php';
$expensesPath = $root . '/management/expenses.php';
$salesPath = $root . '/management/sales_records.php';

$head = is_file($headPath) ? file_get_contents($headPath) : '';
$expenses = is_file($expensesPath) ? file_get_contents($expensesPath) : '';
$sales = is_file($salesPath) ? file_get_contents($salesPath) : '';

$check($head !== '', 'navbar_head.php exists');
$check($expenses !== '', 'management/expenses.php exists');
$check($sales !== '', 'management/sales_records.php exists');

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $head
    ) === 1,
    'navbar_head.php contains exactly one remaining inline style block'
);

$check(
    !str_contains($head, 'salesReceivableActionRules'),
    'Sales receivable CSS action-rule array is removed'
);

$check(
    !str_contains($head, 'managementExpenseActionRules'),
    'Management expense CSS action-rule array is removed'
);

$check(
    !str_contains(
        $head,
        'button[onclick="deleteExpense('
    ),
    'Stale expense delete onclick selector is removed'
);

$check(
    str_contains(
        $head,
        '$managementExpenseActionPermissions = [];'
    ),
    'Management expense permission map is initialized'
);

$check(
    str_contains(
        $head,
        "\$managementExpenseActionPermissions[\$expenseId] = ["
    ),
    'Per-row management expense permission map is populated'
);

$check(
    str_contains(
        $head,
        "'edit' => \$canEditExpenseRow"
    ),
    'Expense row Edit permission is preserved'
);

$check(
    str_contains(
        $head,
        "'delete' => \$canDeleteExpenseRow"
    ),
    'Expense row Delete permission is preserved'
);

$check(
    str_contains(
        $head,
        '$canManageExpenses = $canManageAnyExpenseAction;'
    ),
    'Expense Actions column remains governed by visible row actions'
);

$check(
    str_contains(
        $head,
        '$canEditLedger = $canViewReceivables && $canEditReceivables;'
    ),
    'Sales ledger Edit permission is aligned directly'
);

$check(
    str_contains(
        $head,
        '$canDeleteLedger = $canViewReceivables && $canDeleteReceivables;'
    ),
    'Sales ledger Delete permission is aligned directly'
);

$check(
    str_contains(
        $head,
        '$canManageLedger = $canEditLedger || $canDeleteLedger;'
    ),
    'Sales ledger Actions column remains derived from Edit/Delete'
);

$check(
    str_contains(
        $expenses,
        "\$managementExpenseActionPermissions[(int)\$expense['id']] ?? ["
    ),
    'Expense row reads its server-side action permission map'
);

$check(
    str_contains(
        $expenses,
        "<?php if (\$expenseActionPermission['edit']): ?>"
    ),
    'Expense Edit button is server-rendered only when authorized'
);

$check(
    str_contains(
        $expenses,
        "<?php if (\$expenseActionPermission['delete']): ?>"
    ),
    'Expense Delete button is server-rendered only when authorized'
);

$check(
    str_contains(
        $expenses,
        'data-delete-expense-id="<?php echo (int)$expense[\'id\']; ?>"'
    ),
    'Current data-delete-expense-id markup remains intact'
);

$check(
    str_contains(
        $sales,
        '<?php if ($canEditLedger): ?>'
    ),
    'Sales ledger Edit button remains independently rendered'
);

$check(
    str_contains(
        $sales,
        '<?php if ($canDeleteLedger): ?>'
    ),
    'Sales ledger Delete button remains independently rendered'
);

$check(
    str_contains(
        $sales,
        'name="delete_ledger_entry"'
    ),
    'Sales ledger delete POST control remains intact'
);

$check(
    str_contains(
        $head,
        '$tenantPrimaryColor'
    ),
    'Remaining navbar style source is still tenant theme driven'
);

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
