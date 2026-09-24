<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$paths = [
    'shared' =>
        $root . '/lib/slaughter_output_sale_stock.php',

    'stock' =>
        $root . '/lib/stock_service.php',

    'role' =>
        $root . '/lib/inventory_category_role.php',

    'ruminant' =>
        $root . '/lib/ruminant_slaughter_sale_consumption.php',
];

$sources = [];

foreach ($paths as $key => $path) {
    $sources[$key] =
        is_file($path)
        && is_readable($path)
            ? (string)file_get_contents($path)
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
        'Required shared slaughter-sale stock sources are missing.'
    );
}

require_once $paths['shared'];


if (
    slaughter_output_sale_stock_source(
        'ruminant'
    ) !== 'ruminant_slaughter_sale'
    ||
    slaughter_output_sale_stock_source(
        'poultry'
    ) !== 'poultry_slaughter_sale'
    ||
    slaughter_output_sale_stock_reversal_source(
        'ruminant'
    ) !== 'ruminant_slaughter_sale_reversal'
    ||
    slaughter_output_sale_stock_reversal_source(
        'poultry'
    ) !== 'poultry_slaughter_sale_reversal'
) {
    $fail(
        'Shared slaughter-sale stock provenance mapping is invalid.'
    );
}

$pass(
    'Shared slaughter-sale stock provenance is species scoped'
);


if (
    inventory_category_role_reversal_errors(
        inventory_category_slaughter_output_role()
    ) === []
) {
    $fail(
        'Generic Slaughter Output ledger reversal must remain blocked.'
    );
}

if (
    inventory_category_role_reversal_errors(
        inventory_category_slaughter_output_role(),
        'ruminant_slaughter_sale_reversal',
        1
    ) !== []
    ||
    inventory_category_role_reversal_errors(
        inventory_category_slaughter_output_role(),
        'poultry_slaughter_sale_reversal',
        1
    ) !== []
) {
    $fail(
        'Source-owned slaughter-sale reversal provenance is not allowed.'
    );
}

if (
    inventory_category_role_reversal_errors(
        inventory_category_slaughter_output_role(),
        'poultry_slaughter_sale_reversal',
        null
    ) === []
) {
    $fail(
        'Slaughter-sale reversal without durable allocation identity must be rejected.'
    );
}

$pass(
    'Generic reversal stays blocked while source-owned sale corrections are permitted'
);


if (
    strpos(
        $sources['stock'],
        'inventory_category_role_slaughter_used_sources()'
    ) === false
) {
    $fail(
        'Canonical stock writer does not use shared slaughter-sale outgoing-cost provenance.'
    );
}

$pass(
    'Canonical stock writer accepts explicit outgoing cost only through shared slaughter-sale provenance'
);


if (
    strpos(
        $sources['shared'],
        'stock_apply_movement('
    ) === false
    ||
    strpos(
        $sources['shared'],
        'stock_reverse_transaction('
    ) === false
) {
    $fail(
        'Shared slaughter-sale stock helper is not delegated to canonical stock service.'
    );
}

$pass(
    'Shared boundary delegates consumption and reversal to canonical stock service'
);


if (
    strpos(
        $sources['stock'],
        "inventory_category_role_slaughter_reversal_sources(),"
    ) === false
    ||
    strpos(
        $sources['stock'],
        "\$tx['total_cost']"
    ) === false
) {
    $fail(
        'Canonical reversal does not preserve exact source-owned slaughter-sale total cost.'
    );
}

$pass(
    'Source-owned reversal preserves exact frozen sale-lot total cost'
);


if (
    strpos(
        $sources['ruminant'],
        'slaughter_output_sale_stock_consume('
    ) === false
    ||
    strpos(
        $sources['ruminant'],
        'slaughter_output_sale_stock_reverse('
    ) === false
) {
    $fail(
        'Ruminant sale lot service is not delegated to the shared stock boundary.'
    );
}

if (
    strpos(
        $sources['ruminant'],
        'stock_apply_movement('
    ) !== false
    ||
    preg_match(
        '/UPDATE\s+stock_transactions/i',
        $sources['ruminant']
    )
) {
    $fail(
        'Ruminant sale lot service still owns duplicate direct stock mutation.'
    );
}

$pass(
    'Ruminant sale lot service retains domain ownership without duplicate stock mutation'
);


if (
    strpos(
        $sources['shared'],
        'production_population_projection_sync('
    ) !== false
    ||
    strpos(
        $sources['shared'],
        'production_population_record_movement('
    ) !== false
    ||
    preg_match(
        '/INSERT\s+INTO\s+sales_records/i',
        $sources['shared']
    )
) {
    $fail(
        'Shared physical stock boundary must not own population or Sales revenue.'
    );
}

$pass(
    'Shared sale stock boundary owns neither live population nor Sales revenue'
);


echo
    "PASS: Stage 14E-4C shared slaughter-output sale stock boundary\n";
