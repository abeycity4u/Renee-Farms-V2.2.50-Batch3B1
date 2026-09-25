<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$checks = 0;
$failures = 0;

$check =
    static function (
        bool $ok,
        string $label
    ) use (&$checks, &$failures): void {
        $checks++;

        echo
            ($ok ? '[PASS] ' : '[FAIL] ')
            .
            $label
            .
            PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };

$read =
    static function (
        string $relative
    ) use ($root): string {
        $path =
            $root
            .
            '/'
            .
            $relative;

        return
            is_file(
                $path
            )
                ? (string)file_get_contents(
                    $path
                )
                : '';
    };


$behaviors =
    $read(
        'assets/js/app-behaviors.js'
    );

$managementPage =
    $read(
        'management/expenses.php'
    );

$managementJs =
    $read(
        'assets/js/management-expenses.js'
    );

$ruminantPage =
    $read(
        'ruminant/ruminant_expenses.php'
    );

$ruminantJs =
    $read(
        'assets/js/ruminant-expenses.js'
    );

$layerRoute =
    $read(
        'poultry/layer_expenses.php'
    );

$broilerRoute =
    $read(
        'poultry/broiler_expenses.php'
    );


$check(
    str_contains(
        $behaviors,
        '[data-delete-expense-id]'
    ),
    'Shared behavior layer recognizes data-delete-expense-id contract'
);

$check(
    str_contains(
        $behaviors,
        "typeof window.deleteExpense !== 'function'"
    ),
    'Shared behavior safely guards missing deleteExpense function'
);

$check(
    str_contains(
        $behaviors,
        'Number.parseInt(deleteExpenseTarget.dataset.deleteExpenseId, 10)'
    ),
    'Shared behavior parses delete-expense id from data attribute'
);

$check(
    str_contains(
        $behaviors,
        'Number.isInteger(expenseId) || expenseId <= 0'
    ),
    'Shared behavior rejects invalid delete-expense ids'
);

$check(
    str_contains(
        $behaviors,
        'window.deleteExpense(expenseId);'
    ),
    'Shared behavior delegates deletion to external page behavior'
);


$activePages = [
    [
        'management/expenses.php',
        $managementPage,
        '/assets/js/management-expenses.js',
        $managementJs,
    ],

    [
        'ruminant/ruminant_expenses.php',
        $ruminantPage,
        '/assets/js/ruminant-expenses.js',
        $ruminantJs,
    ],
];


foreach (
    $activePages
    as [
        $relative,
        $page,
        $asset,
        $js,
    ]
) {
    $check(
        !str_contains(
            $page,
            'onclick="deleteExpense('
        ),
        $relative
        .
        ' has no inline deleteExpense handler'
    );

    $check(
        str_contains(
            $page,
            'data-delete-expense-id='
        ),
        $relative
        .
        ' opts into centralized delete-expense behavior'
    );

    $check(
        str_contains(
            $page,
            $asset
        ),
        $relative
        .
        ' loads its external expense behavior'
    );

    $check(
        str_contains(
            $js,
            'async function deleteExpense(expenseId)'
        ),
        $relative
        .
        ' external JS owns deleteExpense implementation'
    );
}


foreach (
    [
        'poultry/layer_expenses.php' =>
            $layerRoute,

        'poultry/broiler_expenses.php' =>
            $broilerRoute,
    ]
    as $relative => $route
) {
    $check(
        str_contains(
            $route,
            'poultry_expense_compatibility_redirect('
        ),
        $relative
        .
        ' delegates to canonical Poultry expense hub'
    );

    $check(
        !str_contains(
            $route,
            'data-delete-expense-id='
        )
        &&
        !str_contains(
            $route,
            'onclick="deleteExpense('
        )
        &&
        !str_contains(
            $route,
            'function deleteExpense('
        ),
        $relative
        .
        ' owns no retired delete UI or behavior'
    );
}


echo PHP_EOL;

echo
    'CHECK_COUNT='
    .
    $checks
    .
    PHP_EOL;

echo
    'FAILED_COUNT='
    .
    $failures
    .
    PHP_EOL;

echo
    'RESULT='
    .
    (
        $failures === 0
            ? 'PASS'
            : 'FAIL'
    )
    .
    PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
