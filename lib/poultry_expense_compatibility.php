<?php

declare(strict_types=1);


if (!function_exists(
    'poultry_expense_compatibility_target'
)) {
function poultry_expense_compatibility_target(
    string $productionType,
    array $query
): string {
    $productionType =
        strtolower(
            trim(
                $productionType
            )
        );

    if (
        !in_array(
            $productionType,
            [
                'layer',
                'broiler',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Unsupported Poultry expense compatibility route.'
        );
    }

    $params = [
        'tab' =>
            $productionType,
    ];

    $month =
        trim(
            (string)(
                $query[
                    'month'
                ]
                ?? ''
            )
        );

    if (
        preg_match(
            '/^\d{4}-\d{2}$/',
            $month
        ) === 1
    ) {
        $params[
            'month'
        ] =
            $month;
    }

    if (
        (string)(
            $query[
                'pdf'
            ]
            ?? ''
        ) === '1'
    ) {
        $params[
            'pdf'
        ] =
            '1';
    }

    if (
        (string)(
            $query[
                'add'
            ]
            ?? ''
        ) === '1'
    ) {
        $params[
            'add'
        ] =
            '1';

        $params[
            'production_type'
        ] =
            $productionType;
    }

    return
        'expenses.php?'
        . http_build_query(
            $params
        );
}
}


if (!function_exists(
    'poultry_expense_compatibility_redirect'
)) {
function poultry_expense_compatibility_redirect(
    string $productionType,
    array $query
): never {
    header(
        'Location: '
        . poultry_expense_compatibility_target(
            $productionType,
            $query
        ),
        true,
        302
    );

    exit();
}
}
