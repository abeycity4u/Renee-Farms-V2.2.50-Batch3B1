<?php
/**
 * Renee Farms V3.0.1
 * Expense human-facing record-reference display verifier.
 *
 * Source-only verifier.
 *
 * Poultry Layer/Broiler/Shared display authority is consolidated in
 * poultry/expenses.php. Poultry row reading belongs to the shared workspace.
 */

$root =
    dirname(__DIR__);

$paths = [
    'poultry_hub' =>
        $root
        . '/poultry/expenses.php',

    'poultry_workspace' =>
        $root
        . '/lib/poultry_expense_workspace.php',

    'ruminant' =>
        $root
        . '/ruminant/ruminant_expenses.php',

    'management' =>
        $root
        . '/management/expenses.php',

    'pdf' =>
        $root
        . '/management/expense_report_pdf.php',

    'allocation' =>
        $root
        . '/management/expense_allocation.php',

    'allocation_service' =>
        $root
        . '/lib/financial_allocation_service.php',
];

$source = [];

foreach ($paths as $key => $sourcePath) {
    $source[$key] =
        is_file($sourcePath)
            ? (string)file_get_contents($sourcePath)
            : '';
}

$checks = 0;
$failures = 0;

$check =
    static function (
        bool $ok,
        string $label
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo
            ($ok ? '[PASS] ' : '[FAIL] ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };


$check(
    strpos(
        $source['poultry_workspace'],
        'SELECT'
    ) !== false
    &&
    strpos(
        $source['poultry_workspace'],
        'e.*'
    ) !== false
    &&
    strpos(
        $source['poultry_workspace'],
        'FROM farm_expenses e'
    ) !== false,
    'Poultry workspace query carries canonical expense row'
);

$check(
    substr_count(
        $source['poultry_hub'],
        '<th>Reference</th>'
    ) === 1,
    'Poultry hub has exactly one Expense Reference column'
);

$check(
    strpos(
        $source['poultry_hub'],
        "\$expense[\n                                                    'public_reference'"
    ) !== false
    ||
    strpos(
        $source['poultry_hub'],
        "\$expense['public_reference']"
    ) !== false,
    'Poultry hub renders public reference'
);

$check(
    strpos(
        $source['poultry_hub'],
        "data-expense-id"
    ) !== false
    &&
    strpos(
        $source['poultry_hub'],
        "(int)\$expense["
    ) !== false,
    'Poultry hub preserves internal numeric expense IDs'
);

$check(
    strpos(
        $source['poultry_hub'],
        'colspan="11"'
    ) !== false,
    'Poultry hub empty state matches unified expense columns'
);

$check(
    strpos(
        $source['poultry_hub'],
        'pdf_report_finish('
    ) !== false,
    'Poultry hub PDF uses the same Reference-bearing expense table'
);


foreach (
    [
        'ruminant' =>
            'Ruminant Expenses',

        'management' =>
            'Management Expenses',
    ]
    as $key => $label
) {
    $check(
        strpos(
            $source[$key],
            'SELECT e.*'
        ) !== false,
        $label
            . ' query carries canonical expense row'
    );

    $check(
        substr_count(
            $source[$key],
            '<th>Reference</th>'
        ) === 1,
        $label
            . ' has exactly one Expense Reference column'
    );

    $check(
        strpos(
            $source[$key],
            "\$expense['public_reference']"
        ) !== false,
        $label
            . ' renders public reference'
    );

    $check(
        strpos(
            $source[$key],
            'data-id="<?php echo $expense[\'id\']; ?>"'
        ) !== false
        ||
        strpos(
            $source[$key],
            'data-delete-expense-id="<?php echo (int)$expense[\'id\']; ?>"'
        ) !== false,
        $label
            . ' preserves internal expense IDs'
    );
}


$check(
    strpos(
        $source['ruminant'],
        '? 11 : 10;'
    ) !== false,
    'Ruminant empty state accounts for Reference column'
);

$check(
    strpos(
        $source['management'],
        "? '11' : '10';"
    ) !== false,
    'Management empty state accounts for Reference column'
);

$check(
    strpos(
        $source['ruminant'],
        '<td colspan="5"><strong>TOTAL</strong></td>'
    ) !== false,
    'Ruminant total remains aligned after Reference column'
);


$check(
    strpos(
        $source['pdf'],
        'SELECT e.*'
    ) !== false,
    'Expense report PDF query carries canonical expense row'
);

$check(
    strpos(
        $source['pdf'],
        '<th>Reference</th>'
    ) !== false,
    'Expense report PDF has Reference column'
);

$check(
    strpos(
        $source['pdf'],
        "\$expense['public_reference']"
    ) !== false,
    'Expense report PDF renders public reference'
);

$check(
    strpos(
        $source['pdf'],
        'colspan="10"'
    ) !== false,
    'Expense report PDF empty state matches ten columns'
);


$check(
    strpos(
        $source['allocation'],
        'Expense Reference'
    ) !== false
    &&
    strpos(
        $source['allocation'],
        "\$expense['public_reference']"
    ) !== false,
    'Expense Allocation displays parent expense reference'
);

$check(
    strpos(
        $source['allocation'],
        'data-expense-id="<?php echo (int)$expense[\'id\']; ?>"'
    ) !== false,
    'Expense Allocation preserves numeric routing ID'
);

$check(
    strpos(
        $source['allocation_service'],
        '"SELECT *'
    ) !== false
    &&
    strpos(
        $source['allocation_service'],
        'FROM farm_expenses'
    ) !== false,
    'Allocation parent reader carries public_reference through canonical row'
);


$check(
    strpos(
        $source['ruminant'],
        'pdf_report_finish('
    ) !== false,
    'Ruminant page-generated PDF preserves Reference-bearing expense table'
);


$displaySource =
    implode(
        "\n",
        [
            $source['poultry_hub'],
            $source['poultry_workspace'],
            $source['ruminant'],
            $source['management'],
            $source['pdf'],
            $source['allocation'],
        ]
    );


$check(
    strpos(
        $displaySource,
        'record_reference_generate('
    ) === false,
    'Expense display surfaces never generate references'
);


$check(
    strpos(
        $source['poultry_hub'],
        'record_reference_persistence_insert_new('
    ) === false
    &&
    strpos(
        $source['poultry_workspace'],
        'record_reference_persistence_insert_new('
    ) === false
    &&
    substr_count(
        $source['ruminant'],
        'record_reference_persistence_insert_new('
    ) === 1
    &&
    strpos(
        $source['management'],
        'record_reference_persistence_insert_new('
    ) === false
    &&
    strpos(
        $source['pdf'],
        'record_reference_persistence_insert_new('
    ) === false
    &&
    strpos(
        $source['allocation'],
        'record_reference_persistence_insert_new('
    ) === false,
    'Only the still-combined Ruminant display/writer owns reference persistence'
);


$check(
    strpos(
        $displaySource,
        'SET public_reference'
    ) === false,
    'Expense display surfaces never update public_reference'
);


$check(
    preg_match(
        '/(?:data-id|expense_id|data-expense-id)[^\\n>]*public_reference/i',
        $displaySource
    ) !== 1,
    'Public reference never replaces internal expense routing IDs'
);


echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
