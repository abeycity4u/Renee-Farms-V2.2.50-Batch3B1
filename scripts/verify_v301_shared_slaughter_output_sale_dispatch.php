<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$dispatchFile =
    $root
    .
    '/lib/slaughter_output_sale_dispatch.php';

$poultryFile =
    $root
    .
    '/lib/poultry_slaughter_sale_consumption.php';

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

foreach (
    [
        $dispatchFile,
        $poultryFile,
        $ruminantFile,
    ]
    as $file
) {
    if (!is_readable($file)) {
        $fail(
            'Required dual-domain slaughter-sale source is missing.'
        );
    }
}

$dispatch =
    (string)file_get_contents(
        $dispatchFile
    );

$required = [
    'function slaughter_output_sale_domain_from_post(',
    'function slaughter_output_sale_selection_from_post(',
    'function slaughter_output_sale_available_lots(',
    'function slaughter_output_sale_history_for_sales(',
    'function slaughter_output_sale_active_state(',
    'function slaughter_output_sale_sync(',
    'function slaughter_output_sale_assert_deletable(',

    'poultry_slaughter_sale_selection(',
    'ruminant_slaughter_sale_selection(',

    'poultry_slaughter_sale_available_lots(',
    'ruminant_slaughter_sale_available_lots(',

    'poultry_slaughter_sale_history_for_sales(',
    'ruminant_slaughter_sale_history_for_sales(',

    'poultry_slaughter_sale_sync(',
    'ruminant_slaughter_sale_sync(',

    'poultry_slaughter_sale_assert_deletable(',
    'ruminant_slaughter_sale_assert_deletable(',

    "'slaughter_domain'",
];

foreach ($required as $needle) {
    if (
        strpos(
            $dispatch,
            $needle
        ) === false
    ) {
        $fail(
            'Dual-domain dispatcher missing contract: '
            .
            $needle
        );
    }
}

$pass(
    'Dispatcher centrally owns Poultry/Ruminant lot routing'
);


if (
    preg_match(
        '/->\s*(?:prepare|query|exec)\s*\(/i',
        $dispatch
    )
    ||
    preg_match(
        '/\bINSERT\s+INTO\b/i',
        $dispatch
    )
    ||
    preg_match(
        '/\bUPDATE\s+[A-Za-z0-9_`]+\s+SET\b/i',
        $dispatch
    )
    ||
    preg_match(
        '/\bDELETE\s+FROM\b/i',
        $dispatch
    )
) {
    $fail(
        'Dual-domain dispatcher must not duplicate domain persistence SQL.'
    );
}

$pass(
    'Dispatcher remains orchestration-only with no duplicate persistence SQL'
);


if (
    strpos(
        $dispatch,
        'production_population_projection_sync('
    ) !== false
    ||
    strpos(
        $dispatch,
        'sale_population_effect_sync('
    ) !== false
    ||
    strpos(
        $dispatch,
        'stock_apply_movement('
    ) !== false
    ||
    strpos(
        $dispatch,
        'stock_reverse_transaction('
    ) !== false
) {
    $fail(
        'Dispatcher must not own population or physical stock mutation.'
    );
}

$pass(
    'Dispatcher owns neither population nor physical Inventory mutation'
);


if (
    strpos(
        $dispatch,
        'conflicting active Poultry and Ruminant slaughter-output histories'
    ) === false
) {
    $fail(
        'Dispatcher does not fail closed on conflicting dual-domain active history.'
    );
}

$pass(
    'Dispatcher fails closed if one Sale somehow has two active source domains'
);


$poultryReverse =
    strpos(
        $dispatch,
        "previousDomain === 'poultry'"
    );

$ruminantReverse =
    strpos(
        $dispatch,
        "previousDomain === 'ruminant'"
    );

$domainApply =
    strrpos(
        $dispatch,
        "if (\$domain === 'poultry')"
    );

if (
    $poultryReverse === false
    ||
    $ruminantReverse === false
    ||
    $domainApply === false
    ||
    $poultryReverse >= $domainApply
    ||
    $ruminantReverse >= $domainApply
) {
    $fail(
        'Cross-domain correction does not close the old source before applying the new source.'
    );
}

$pass(
    'Cross-domain edits reverse old lot provenance before appending replacement provenance'
);


require_once $dispatchFile;


if (
    slaughter_output_sale_domain_from_post(
        [
            'sale_stock_source' =>
                'financial_only',
        ]
    ) !== null
) {
    $fail(
        'Financial-only Sale unexpectedly owns a slaughter domain.'
    );
}

if (
    slaughter_output_sale_domain_from_post(
        [
            'sale_stock_source' =>
                'slaughter_output',

            'slaughter_output_domain' =>
                'poultry',
        ]
    ) !== 'poultry'
    ||
    slaughter_output_sale_domain_from_post(
        [
            'sale_stock_source' =>
                'slaughter_output',

            'slaughter_output_domain' =>
                'ruminant',
        ]
    ) !== 'ruminant'
) {
    $fail(
        'Explicit Poultry/Ruminant slaughter domain normalization failed.'
    );
}

$rejected =
    false;

try {
    slaughter_output_sale_domain_from_post(
        [
            'sale_stock_source' =>
                'slaughter_output',

            'slaughter_output_domain' =>
                'other',
        ]
    );

} catch (SlaughterOutputSaleException $e) {
    $rejected = true;
}

if (!$rejected) {
    $fail(
        'Unknown slaughter-output domain was not rejected.'
    );
}

$pass(
    'Browser domain input is only an explicit validated source selector'
);


if (
    strpos(
        $dispatch,
        "row[\n                'slaughter_domain'"
    ) === false
    &&
    strpos(
        $dispatch,
        "row[\n                    'slaughter_domain'"
    ) === false
) {
    $fail(
        'Combined lot/history readers do not tag durable source domain.'
    );
}

$pass(
    'Combined lot/history payloads carry explicit source domain identity'
);


if (
    strpos(
        $dispatch,
        'Any durable history in either domain blocks hard deletion'
    ) === false
    ||
    strpos(
        $dispatch,
        'poultry_slaughter_sale_assert_deletable('
    ) === false
    ||
    strpos(
        $dispatch,
        'ruminant_slaughter_sale_assert_deletable('
    ) === false
) {
    $fail(
        'Shared hard-delete protection does not cover both domains.'
    );
}

$pass(
    'Hard-delete guard protects durable history from either livestock domain'
);


echo
    "PASS: Stage 14E-4G shared dual-domain slaughter-sale dispatcher\n";
