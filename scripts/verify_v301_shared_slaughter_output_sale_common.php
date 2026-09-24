<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$commonFile =
    $root
    .
    '/lib/slaughter_output_sale_common.php';

$ruminantFile =
    $root
    .
    '/lib/ruminant_slaughter_sale_consumption.php';

$fail =
    static function (
        string $message
    ): void {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );

        exit(1);
    };

$pass =
    static function (
        string $message
    ): void {
        echo
            "PASS: {$message}\n";
    };

if (
    !is_readable(
        $commonFile
    )
    ||
    !is_readable(
        $ruminantFile
    )
) {
    $fail(
        'Shared slaughter-output Sales helper sources are not readable.'
    );
}

$common =
    (string)file_get_contents(
        $commonFile
    );

$ruminant =
    (string)file_get_contents(
        $ruminantFile
    );

$requiredCommon = [
    'function slaughter_output_sale_common_require_transaction(',
    'function slaughter_output_sale_common_sales_unit(',
    'function slaughter_output_sale_common_normalize_rows(',
    'function slaughter_output_sale_common_rows_from_post(',
    'function slaughter_output_sale_common_semantic_rows(',
    "'sale_stock_source'",
    "'slaughter_output_ids'",
    "'slaughter_output_quantities'",
];

foreach (
    $requiredCommon
    as $needle
) {
    if (
        strpos(
            $common,
            $needle
        )
        === false
    ) {
        $fail(
            'Shared Sales helper missing contract: '
            .
            $needle
        );
    }
}

$pass(
    'Shared helper owns species-neutral slaughter-output Sales request policy'
);


$requiredRuminant = [
    'slaughter_output_sale_common_require_transaction(',
    'slaughter_output_sale_common_sales_unit(',
    'slaughter_output_sale_common_normalize_rows(',
    'slaughter_output_sale_common_rows_from_post(',
    'slaughter_output_sale_common_semantic_rows(',
];

foreach (
    $requiredRuminant
    as $needle
) {
    if (
        strpos(
            $ruminant,
            $needle
        )
        === false
    ) {
        $fail(
            'Ruminant wrapper does not delegate shared contract: '
            .
            $needle
        );
    }
}

$pass(
    'Ruminant public API delegates species-neutral policy without changing function names'
);


if (
    preg_match(
        '/->\s*(?:prepare|query|exec)\s*\(/i',
        $common
    )
    ||
    preg_match(
        '/\bINSERT\s+INTO\b/i',
        $common
    )
    ||
    preg_match(
        '/\bDELETE\s+FROM\b/i',
        $common
    )
    ||
    preg_match(
        '/\bUPDATE\s+[A-Za-z0-9_`]+\s+SET\b/i',
        $common
    )
    ||
    preg_match(
        '/\bSELECT\b[^;"\']*\bFROM\b/i',
        $common
    )
) {
    $fail(
        'Species-neutral Sales helper must not own persistence SQL.'
    );
}

if (
    strpos(
        $common,
        'production_population_'
    )
    !== false
    ||
    strpos(
        $common,
        'stock_apply_movement('
    )
    !== false
) {
    $fail(
        'Species-neutral Sales helper must not own population or stock mutation.'
    );
}

$pass(
    'Shared request helper owns no persistence, population or Inventory mutation'
);


require_once $commonFile;

$factory =
    static function (
        string $message
    ): Throwable {
        return
            new RuntimeException(
                $message
            );
    };

if (
    slaughter_output_sale_common_sales_unit(
        'kg',
        $factory
    )
    !== 'Kg'
    ||
    slaughter_output_sale_common_sales_unit(
        'pcs',
        $factory
    )
    !== 'Piece'
) {
    $fail(
        'Shared Sales unit normalization changed established unit semantics.'
    );
}

$pass(
    'Shared Sales unit normalization preserves established semantics'
);


$rows =
    slaughter_output_sale_common_rows_from_post(
        [
            'sale_stock_source' =>
                'slaughter_output',

            'slaughter_output_ids' =>
                [
                    '9',
                    '3',
                ],

            'slaughter_output_quantities' =>
                [
                    '1.5',
                    '2',
                ],
        ],
        $factory
    );

if (
    array_keys(
        $rows
    )
    !== [
        3,
        9,
    ]
    ||
    abs(
        (float)$rows[3]['quantity']
        -
        2.00
    ) > 0.00001
    ||
    abs(
        (float)$rows[9]['quantity']
        -
        1.50
    ) > 0.00001
) {
    $fail(
        'Shared explicit lot POST normalization is not deterministic.'
    );
}

$pass(
    'Shared explicit lot selection normalizes deterministically'
);


$semantic =
    slaughter_output_sale_common_semantic_rows(
        [
            [
                'output_id' =>
                    9,

                'quantity' =>
                    1.5,
            ],
            [
                'output_id' =>
                    3,

                'quantity' =>
                    2,
            ],
        ]
    );

if (
    $semantic
    !== [
        3 =>
            '2.00',

        9 =>
            '1.50',
    ]
) {
    $fail(
        'Shared semantic-row comparison projection changed.'
    );
}

$pass(
    'Shared semantic row projection remains stable for idempotent synchronization'
);


$duplicateRejected =
    false;

try {
    slaughter_output_sale_common_normalize_rows(
        [
            [
                'output_id' =>
                    4,

                'quantity' =>
                    1,
            ],
            [
                'output_id' =>
                    4,

                'quantity' =>
                    2,
            ],
        ],
        $factory
    );

} catch (RuntimeException $e) {
    $duplicateRejected =
        str_contains(
            $e->getMessage(),
            'cannot be selected twice'
        );
}

if (!$duplicateRejected) {
    $fail(
        'Shared lot normalization did not reject duplicate lot identity.'
    );
}

$pass(
    'Shared lot normalization rejects duplicate source lots'
);


echo
    "PASS: Stage 14E-4E shared slaughter-output Sales semantics\n";
