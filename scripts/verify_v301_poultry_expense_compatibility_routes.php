<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

require_once
    $root
    . '/lib/poultry_expense_compatibility.php';


$layer =
    (string)file_get_contents(
        $root
        . '/poultry/layer_expenses.php'
    );

$broiler =
    (string)file_get_contents(
        $root
        . '/poultry/broiler_expenses.php'
    );

$navbar =
    (string)file_get_contents(
        $root
        . '/navbar.php'
    );

$cycle =
    (string)file_get_contents(
        $root
        . '/management/poultry_cycle.php'
    );

$hub =
    (string)file_get_contents(
        $root
        . '/poultry/expenses.php'
    );


$checks = 0;
$failed = 0;


function compatibility_check(
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


function parsed_target(
    string $production,
    array $query
): array {
    $target =
        poultry_expense_compatibility_target(
            $production,
            $query
        );

    $parts =
        parse_url(
            $target
        );

    $params = [];

    parse_str(
        (string)(
            $parts[
                'query'
            ]
            ?? ''
        ),
        $params
    );

    return [
        'path' =>
            (string)(
                $parts[
                    'path'
                ]
                ?? ''
            ),

        'params' =>
            $params,
    ];
}


$layerTarget =
    parsed_target(
        'layer',
        [
            'month' =>
                '2026-09',
        ]
    );

compatibility_check(
    'LAYER_TARGETS_CANONICAL_HUB',
    $layerTarget[
        'path'
    ] === 'expenses.php'
    &&
    (
        $layerTarget[
            'params'
        ][
            'tab'
        ]
        ?? ''
    ) === 'layer'
);


compatibility_check(
    'VALID_MONTH_IS_PRESERVED',
    (
        $layerTarget[
            'params'
        ][
            'month'
        ]
        ?? ''
    ) === '2026-09'
);


$invalidMonth =
    parsed_target(
        'layer',
        [
            'month' =>
                'not-a-month',
        ]
    );

compatibility_check(
    'INVALID_MONTH_IS_NOT_FORWARDED',
    !array_key_exists(
        'month',
        $invalidMonth[
            'params'
        ]
    )
);


$pdfTarget =
    parsed_target(
        'broiler',
        [
            'pdf' =>
                '1',
        ]
    );

compatibility_check(
    'PDF_INTENT_IS_PRESERVED',
    (
        $pdfTarget[
            'params'
        ][
            'tab'
        ]
        ?? ''
    ) === 'broiler'
    &&
    (
        $pdfTarget[
            'params'
        ][
            'pdf'
        ]
        ?? ''
    ) === '1'
);


$addTarget =
    parsed_target(
        'broiler',
        [
            'add' =>
                '1',
        ]
    );

compatibility_check(
    'ADD_INTENT_PRESELECTS_PRODUCTION',
    (
        $addTarget[
            'params'
        ][
            'add'
        ]
        ?? ''
    ) === '1'
    &&
    (
        $addTarget[
            'params'
        ][
            'production_type'
        ]
        ?? ''
    ) === 'broiler'
);


$invalidProductionRejected =
    false;

try {
    poultry_expense_compatibility_target(
        'shared',
        []
    );

} catch (InvalidArgumentException $error) {
    $invalidProductionRejected =
        true;
}

compatibility_check(
    'LEGACY_HELPER_REJECTS_UNSUPPORTED_PRODUCTION',
    $invalidProductionRejected
);


foreach (
    [
        'LAYER' =>
            $layer,

        'BROILER' =>
            $broiler,
    ]
    as $label => $route
) {
    compatibility_check(
        $label
        . '_ROUTE_IS_THIN',
        substr_count(
            $route,
            PHP_EOL
        ) < 20
    );

    compatibility_check(
        $label
        . '_ROUTE_DELEGATES_TO_SHARED_HELPER',
        strpos(
            $route,
            'poultry_expense_compatibility_redirect('
        ) !== false
    );

    compatibility_check(
        $label
        . '_ROUTE_OWNS_NO_SQL_OR_VISUAL_POLICY',
        preg_match(
            '/\b(?:SELECT|INSERT|UPDATE|DELETE)\b/i',
            $route
        ) !== 1
        &&
        stripos(
            $route,
            '<html'
        ) === false
        &&
        stripos(
            $route,
            '<form'
        ) === false
        &&
        strpos(
            $route,
            '-expenses.js'
        ) === false
    );
}


compatibility_check(
    'NAVBAR_HAS_ONE_CANONICAL_POULTRY_EXPENSE_LINK',
    substr_count(
        $navbar,
        '/poultry/expenses.php'
    ) === 1
);


compatibility_check(
    'NAVBAR_HAS_NO_LEGACY_EXPENSE_LINKS',
    strpos(
        $navbar,
        '/poultry/layer_expenses.php'
    ) === false
    &&
    strpos(
        $navbar,
        '/poultry/broiler_expenses.php'
    ) === false
);


compatibility_check(
    'NAVBAR_RETAINS_LAYER_BROILER_PERMISSION_INPUTS',
    strpos(
        $navbar,
        '$canViewLayerExpenses'
    ) !== false
    &&
    strpos(
        $navbar,
        '$canViewBroilerExpenses'
    ) !== false
    &&
    strpos(
        $navbar,
        '$canViewPoultryExpenses'
    ) !== false
);


compatibility_check(
    'CYCLE_WORKSPACE_TARGETS_CANONICAL_HUB',
    strpos(
        $cycle,
        '/poultry/expenses.php?'
    ) !== false
    &&
    strpos(
        $cycle,
        "'tab'"
    ) !== false
    &&
    strpos(
        $cycle,
        '$type'
    ) !== false
);


compatibility_check(
    'CYCLE_WORKSPACE_HAS_NO_LEGACY_EXPENSE_TARGET',
    strpos(
        $cycle,
        '/poultry/layer_expenses.php'
    ) === false
    &&
    strpos(
        $cycle,
        '/poultry/broiler_expenses.php'
    ) === false
);


compatibility_check(
    'HUB_REMAINS_SINGLE_CREATE_AUTHORITY',
    preg_match_all(
        '/poultry_expense_entry_create\s*\(/',
        $hub
    ) === 1
);


compatibility_check(
    'HUB_REMAINS_SINGLE_PDF_AUTHORITY',
    preg_match_all(
        '/pdf_report_finish\s*\(/',
        $hub
    ) === 1
);


compatibility_check(
    'HUB_REMAINS_FINANCIAL_SQL_FREE',
    preg_match(
        '/FROM\s+(?:farm_expenses|stock_transactions)\b/i',
        $hub
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
