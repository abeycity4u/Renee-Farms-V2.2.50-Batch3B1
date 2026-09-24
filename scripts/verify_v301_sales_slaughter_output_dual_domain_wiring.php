<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$paths = [
    'sales' =>
        $root
        .
        '/management/sales_records.php',

    'js' =>
        $root
        .
        '/assets/js/management-sales-records.js',

    'delete' =>
        $root
        .
        '/api/delete_sale.php',

    'dispatch' =>
        $root
        .
        '/lib/slaughter_output_sale_dispatch.php',
];

$sources = [];

foreach (
    $paths
    as $key => $path
) {
    $sources[$key] =
        is_file($path)
        &&
        is_readable($path)
            ? (string)file_get_contents(
                $path
            )
            : '';
}

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
    in_array(
        '',
        $sources,
        true
    )
) {
    $fail(
        'Required dual-domain Sales wiring sources are unreadable.'
    );
}


if (
    substr_count(
        $sources['sales'],
        'slaughter_output_sale_sync('
    ) !== 2
    ||
    substr_count(
        $sources['sales'],
        'slaughter_output_sale_selection_from_post('
    ) !== 1
) {
    $fail(
        'Sales Add/Edit do not share the central dispatcher contract.'
    );
}

$pass(
    'Sales Add/Edit use one dual-domain slaughter-output dispatcher'
);


if (
    substr_count(
        $sources['sales'],
        'name="slaughter_output_domain"'
    ) !== 2
    ||
    strpos(
        $sources['js'],
        'function slaughterSaleKey('
    ) === false
    ||
    strpos(
        $sources['js'],
        'data-slaughter-domain'
    ) === false
) {
    $fail(
        'Browser lot identity is not domain scoped.'
    );
}

$pass(
    'Browser lot identity is composite domain plus output id'
);


if (
    strpos(
        $sources['js'],
        "firstDomain === 'poultry'"
    ) === false
    ||
    strpos(
        $sources['js'],
        "firstDomain"
    ) === false
    ||
    strpos(
        $sources['js'],
        'One sale line cannot mix Poultry and Ruminant slaughter-output lots.'
    ) === false
) {
    $fail(
        'Browser does not derive and enforce one livestock domain per sale line.'
    );
}

$pass(
    'Browser derives domain from first source lot and rejects mixed-domain rows'
);


if (
    strpos(
        $sources['sales'],
        "\$input['farm_type'] ="
    ) === false
    ||
    strpos(
        $sources['sales'],
        "\$selection['farm_type']"
    ) === false
    ||
    strpos(
        $sources['sales'],
        "\$input['population_effect_mode']"
    ) === false
    ||
    strpos(
        $sources['sales'],
        "'financial_only'"
    ) === false
) {
    $fail(
        'Server does not derive Sales identity while forcing processed sale population to financial-only.'
    );
}

$pass(
    'Server derives financial Sale identity and forbids second population removal'
);


if (
    strpos(
        $sources['delete'],
        'slaughter_output_sale_assert_deletable('
    ) === false
    ||
    strpos(
        $sources['delete'],
        'SlaughterOutputSaleException'
    ) === false
) {
    $fail(
        'Delete API is not protected by the dual-domain history guard.'
    );
}

$pass(
    'Delete API protects Poultry and Ruminant slaughter-output history centrally'
);


if (
    strpos(
        $sources['dispatch'],
        'conflicting active Poultry and Ruminant slaughter-output histories'
    ) === false
    ||
    strpos(
        $sources['dispatch'],
        "previousDomain === 'poultry'"
    ) === false
    ||
    strpos(
        $sources['dispatch'],
        "previousDomain === 'ruminant'"
    ) === false
) {
    $fail(
        'Cross-domain correction protection is not retained in the dispatcher.'
    );
}

$pass(
    'Cross-domain edits remain append-only through the dispatcher'
);


echo
    "PASS: Stage 14E-4H dual-domain Sales surface wiring\n";
