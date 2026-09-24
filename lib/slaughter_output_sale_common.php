<?php

require_once __DIR__ . '/sales_units.php';

/**
 * Species-neutral Slaughter Output Sales helpers.
 *
 * Domain services retain ownership of:
 * - output/batch tables;
 * - lot availability;
 * - sale compatibility;
 * - allocation persistence;
 * - source-lot balance;
 * - batch status;
 * - hard-delete protection.
 *
 * This helper owns only shared request/unit/semantic policy.
 */

if (!function_exists('slaughter_output_sale_common_fail')) {
function slaughter_output_sale_common_fail(
    callable $exceptionFactory,
    string $message
): void {
    $exception =
        $exceptionFactory(
            $message
        );

    if (!$exception instanceof Throwable) {
        throw new LogicException(
            'Slaughter-output Sales exception factory must return a Throwable.'
        );
    }

    throw $exception;
}
}


if (!function_exists('slaughter_output_sale_common_require_transaction')) {
function slaughter_output_sale_common_require_transaction(
    PDO $pdo,
    callable $exceptionFactory
): void {
    if (!$pdo->inTransaction()) {
        slaughter_output_sale_common_fail(
            $exceptionFactory,
            'Slaughter-output Sales changes require a caller-owned database transaction.'
        );
    }
}
}


if (!function_exists('slaughter_output_sale_common_sales_unit')) {
function slaughter_output_sale_common_sales_unit(
    string $inventoryUnit,
    callable $exceptionFactory
): string {
    $unit =
        strtolower(
            trim(
                $inventoryUnit
            )
        );

    $map = [
        'kg' =>
            'Kg',

        'kilogram' =>
            'Kg',

        'kilograms' =>
            'Kg',

        'g' =>
            'Gram',

        'gram' =>
            'Gram',

        'grams' =>
            'Gram',

        'pc' =>
            'Piece',

        'pcs' =>
            'Piece',

        'piece' =>
            'Piece',

        'pieces' =>
            'Piece',

        'head' =>
            'Head',

        'litre' =>
            'Litre',

        'liter' =>
            'Litre',

        'litres' =>
            'Litre',

        'liters' =>
            'Litre',

        'ml' =>
            'Ml',

        'unit' =>
            'Unit',
    ];

    if (isset($map[$unit])) {
        return
            $map[$unit];
    }

    foreach (
        array_keys(
            sales_unit_presets()
        )
        as $preset
    ) {
        if (
            strtolower(
                $preset
            )
            ===
            $unit
        ) {
            return $preset;
        }
    }

    $clean =
        trim(
            (string)preg_replace(
                '/\s+/',
                ' ',
                $inventoryUnit
            )
        );

    if ($clean === '') {
        slaughter_output_sale_common_fail(
            $exceptionFactory,
            'The selected slaughter output has no usable unit of measure.'
        );
    }

    if (
        (
            function_exists(
                'mb_strlen'
            )
                ? mb_strlen(
                    $clean
                )
                : strlen(
                    $clean
                )
        )
        > 30
    ) {
        slaughter_output_sale_common_fail(
            $exceptionFactory,
            'The slaughter output unit is too long for Sales.'
        );
    }

    return $clean;
}
}


if (!function_exists('slaughter_output_sale_common_normalize_rows')) {
function slaughter_output_sale_common_normalize_rows(
    array $rows,
    callable $exceptionFactory
): array {
    $normalized = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            slaughter_output_sale_common_fail(
                $exceptionFactory,
                'Slaughter sale lot selection is invalid.'
            );
        }

        $outputId =
            (int)(
                $row[
                    'output_id'
                ]
                ?? 0
            );

        $quantity =
            round(
                (float)(
                    $row[
                        'quantity'
                    ]
                    ?? 0
                ),
                2
            );

        if (
            $outputId <= 0
            ||
            !is_finite(
                $quantity
            )
            ||
            $quantity <= 0
        ) {
            slaughter_output_sale_common_fail(
                $exceptionFactory,
                'Choose a valid slaughter output lot and quantity greater than zero.'
            );
        }

        if (
            isset(
                $normalized[
                    $outputId
                ]
            )
        ) {
            slaughter_output_sale_common_fail(
                $exceptionFactory,
                'The same slaughter output lot cannot be selected twice in one sale.'
            );
        }

        $normalized[
            $outputId
        ] = [
            'output_id' =>
                $outputId,

            'quantity' =>
                $quantity,
        ];
    }

    ksort(
        $normalized,
        SORT_NUMERIC
    );

    return $normalized;
}
}


if (!function_exists('slaughter_output_sale_common_rows_from_post')) {
function slaughter_output_sale_common_rows_from_post(
    array $input,
    callable $exceptionFactory
): array {
    $mode =
        strtolower(
            trim(
                (string)(
                    $input[
                        'sale_stock_source'
                    ]
                    ??
                    'financial_only'
                )
            )
        );

    if (
        $mode === ''
        ||
        $mode === 'financial_only'
    ) {
        return [];
    }

    if (
        $mode
        !== 'slaughter_output'
    ) {
        slaughter_output_sale_common_fail(
            $exceptionFactory,
            'Choose a valid Sales stock source.'
        );
    }

    $ids =
        $input[
            'slaughter_output_ids'
        ]
        ?? [];

    $quantities =
        $input[
            'slaughter_output_quantities'
        ]
        ?? [];

    if (!is_array($ids)) {
        $ids = [$ids];
    }

    if (!is_array($quantities)) {
        $quantities = [$quantities];
    }

    if (
        count($ids)
        !==
        count($quantities)
    ) {
        slaughter_output_sale_common_fail(
            $exceptionFactory,
            'Every slaughter output lot requires a sale quantity.'
        );
    }

    $rows = [];

    foreach (
        $ids
        as $index => $outputId
    ) {
        $rows[] = [
            'output_id' =>
                (int)$outputId,

            'quantity' =>
                (float)(
                    $quantities[
                        $index
                    ]
                    ?? 0
                ),
        ];
    }

    $normalized =
        slaughter_output_sale_common_normalize_rows(
            $rows,
            $exceptionFactory
        );

    if (!$normalized) {
        slaughter_output_sale_common_fail(
            $exceptionFactory,
            'Select at least one slaughter output lot.'
        );
    }

    return $normalized;
}
}


if (!function_exists('slaughter_output_sale_common_semantic_rows')) {
function slaughter_output_sale_common_semantic_rows(
    array $rows
): array {
    $semantic = [];

    foreach ($rows as $row) {
        $outputId =
            (int)(
                $row[
                    'output_id'
                ]
                ?? 0
            );

        $quantity =
            round(
                (float)(
                    $row[
                        'quantity'
                    ]
                    ?? 0
                ),
                2
            );

        if (
            $outputId > 0
            &&
            $quantity > 0
        ) {
            $semantic[
                $outputId
            ] =
                number_format(
                    $quantity,
                    2,
                    '.',
                    ''
                );
        }
    }

    ksort(
        $semantic,
        SORT_NUMERIC
    );

    return $semantic;
}
}
