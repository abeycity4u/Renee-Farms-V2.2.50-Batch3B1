<?php
/**
 * Renee Farms V3.0.1
 * Expense human-facing record-reference display verifier.
 *
 * Source-only verifier.
 */

$root =
    dirname(__DIR__);

$paths = [
    'layer' =>
        $root . '/poultry/layer_expenses.php',

    'broiler' =>
        $root . '/poultry/broiler_expenses.php',

    'ruminant' =>
        $root . '/ruminant/ruminant_expenses.php',

    'management' =>
        $root . '/management/expenses.php',

    'pdf' =>
        $root . '/management/expense_report_pdf.php',

    'allocation' =>
        $root . '/management/expense_allocation.php',

    'allocation_service' =>
        $root . '/lib/financial_allocation_service.php',
];

$source = [];

foreach ($paths as $key => $path) {
    $source[$key] =
        is_file($path)
            ? (string)file_get_contents($path)
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


foreach (
    [
        'layer' =>
            'Layer Expenses',

        'broiler' =>
            'Broiler Expenses',

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
        $source['layer'],
        '? 10 : 9;'
    ) !== false,
    'Layer empty state accounts for Reference column'
);

$check(
    strpos(
        $source['broiler'],
        '? 10 : 9;'
    ) !== false,
    'Broiler empty state accounts for Reference column'
);

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
        $source['layer'],
        '<td colspan="5"><strong>TOTAL</strong></td>'
    ) !== false,
    'Layer total remains aligned after Reference column'
);

$check(
    strpos(
        $source['broiler'],
        '<td colspan="5"><strong>TOTAL</strong></td>'
    ) !== false,
    'Broiler total remains aligned after Reference column'
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
    'Expense PDF query carries canonical expense row'
);

$check(
    strpos(
        $source['pdf'],
        '<th>Reference</th>'
    ) !== false,
    'Expense PDF has Reference column'
);

$check(
    strpos(
        $source['pdf'],
        "\$expense['public_reference']"
    ) !== false,
    'Expense PDF renders public reference'
);

$check(
    strpos(
        $source['pdf'],
        'colspan="10"'
    ) !== false,
    'Expense PDF empty state matches ten columns'
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


foreach (
    [
        'layer',
        'broiler',
        'ruminant',
    ]
    as $key
) {
    $check(
        strpos(
            $source[$key],
            'pdf_report_finish('
        ) !== false,
        ucfirst($key)
            . ' page-generated PDF uses the same Reference-bearing expense table'
    );
}


$displaySource =
    implode(
        "\n",
        [
            $source['layer'],
            $source['broiler'],
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
    substr_count(
        $source['layer'],
        'record_reference_persistence_assign_existing('
    ) === 1
    &&
    substr_count(
        $source['broiler'],
        'record_reference_persistence_assign_existing('
    ) === 1
    &&
    substr_count(
        $source['ruminant'],
        'record_reference_persistence_assign_existing('
    ) === 1
    &&
    strpos(
        $source['management'],
        'record_reference_persistence_assign_existing('
    ) === false
    &&
    strpos(
        $source['pdf'],
        'record_reference_persistence_assign_existing('
    ) === false
    &&
    strpos(
        $source['allocation'],
        'record_reference_persistence_assign_existing('
    ) === false,
    'Expense display preserves only the three established canonical writer assignments'
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
