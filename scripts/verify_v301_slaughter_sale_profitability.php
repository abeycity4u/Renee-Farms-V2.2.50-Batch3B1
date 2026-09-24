<?php

$root =
    dirname(
        __DIR__
    );

$files = [
    'shared_reader' =>
        $root
        .
        '/lib/slaughter_output_sale_economics.php',

    'poultry_reader' =>
        $root
        .
        '/lib/poultry_slaughter_sale_economics.php',

    'ruminant_reader' =>
        $root
        .
        '/lib/ruminant_slaughter_sale_economics.php',

    'financial' =>
        $root
        .
        '/includes/financial.php',

    'intelligence' =>
        $root
        .
        '/lib/farm_intelligence.php',

    'profitability' =>
        $root
        .
        '/management/profitability.php',

    'inventory_financial' =>
        $root
        .
        '/lib/inventory_financial.php',
];

$source = [];

foreach (
    $files
    as $key => $path
) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            "Missing source file: {$path}\n"
        );

        exit(1);
    }

    $source[
        $key
    ] =
        file_get_contents(
            $path
        );
}

$checks = [];

$check =
    static function (
        bool $condition,
        string $label
    ) use (
        &$checks
    ): void {
        $checks[] = [
            'ok' =>
                $condition,

            'label' =>
                $label,
        ];
    };

$contains =
    static fn(
        string $haystack,
        string $needle
    ): bool =>
        strpos(
            $haystack,
            $needle
        ) !== false;


$shared =
    $source[
        'shared_reader'
    ];

$poultry =
    $source[
        'poultry_reader'
    ];

$ruminant =
    $source[
        'ruminant_reader'
    ];


$check(
    $contains(
        $shared,
        'function slaughter_output_sale_economics_summary('
    ),
    'shared dual-domain slaughter-sale economics reader exists'
);


$check(
    $contains(
        $shared,
        'poultry_slaughter_sale_economics_summary('
    )
    &&
    $contains(
        $shared,
        'ruminant_slaughter_sale_economics_summary('
    ),
    'shared reader delegates to both livestock domain readers'
);


$check(
    $contains(
        $shared,
        "'poultry'"
    )
    &&
    $contains(
        $shared,
        "'ruminant'"
    )
    &&
    $contains(
        $shared,
        "'domain_breakdown'"
    ),
    'shared reader preserves separate Poultry and Ruminant disclosures'
);


$check(
    $contains(
        $shared,
        'Combined slaughter-sale valuation decomposition does not conserve full cost.'
    ),
    'combined full-cost valuation is conserved'
);


$check(
    !$contains(
        strtoupper(
            $shared
        ),
        'INSERT INTO'
    )
    &&
    !$contains(
        strtoupper(
            $shared
        ),
        'UPDATE '
    )
    &&
    !$contains(
        strtoupper(
            $shared
        ),
        'DELETE FROM'
    ),
    'shared economics aggregator is read-only'
);


foreach (
    [
        'poultry' =>
            [
                'source' =>
                    $poultry,

                'allocation' =>
                    'poultry_slaughter_sale_allocations',

                'stock_source' =>
                    "poultry_slaughter_sale'",

                'capital' =>
                    'capital_basis_transferred',

                'embedded' =>
                    'embedded_operating_basis_transferred',

                'processing' =>
                    'processing_operating_cost',
            ],

        'ruminant' =>
            [
                'source' =>
                    $ruminant,

                'allocation' =>
                    'ruminant_slaughter_sale_allocations',

                'stock_source' =>
                    "ruminant_slaughter_sale'",

                'capital' =>
                    'batch_cost_basis_purchase',

                'embedded' =>
                    'batch_cost_basis_direct_expense',

                'processing' =>
                    'batch_cost_basis_shared',
            ],
    ]
    as $domain => $contract
) {
    $reader =
        $contract[
            'source'
        ];

    $check(
        $contains(
            $reader,
            $contract[
                'allocation'
            ]
        )
        &&
        $contains(
            $reader,
            'a.is_active=1'
        ),
        ucfirst(
            $domain
        )
        .
        ' recognises only explicit active sale-lot allocations'
    );

    $check(
        $contains(
            $reader,
            $contract[
                'stock_source'
            ]
        )
        &&
        $contains(
            $reader,
            'source_id'
        )
        &&
        $contains(
            $reader,
            'is_reversed'
        )
        &&
        $contains(
            $reader,
            'reversal_of_id'
        ),
        ucfirst(
            $domain
        )
        .
        ' COGS requires canonical effective stock provenance'
    );

    $check(
        $contains(
            $reader,
            'total_cost_snapshot'
        )
        &&
        $contains(
            $reader,
            'tx_total_cost'
        ),
        ucfirst(
            $domain
        )
        .
        ' frozen sold valuation reconciles to canonical stock ledger cost'
    );

    $check(
        $contains(
            $reader,
            $contract[
                'capital'
            ]
        )
        &&
        $contains(
            $reader,
            $contract[
                'embedded'
            ]
        )
        &&
        $contains(
            $reader,
            $contract[
                'processing'
            ]
        ),
        ucfirst(
            $domain
        )
        .
        ' decomposes its frozen cost basis'
    );

    $check(
        $contains(
            $reader,
            'full_cost_valuation_cents'
        )
        &&
        $contains(
            $reader,
            'embedded_operating_cost_cents'
        )
        &&
        $contains(
            $reader,
            'recognized_cogs_cents'
        ),
        ucfirst(
            $domain
        )
        .
        ' separates P&L COGS from embedded operating valuation'
    );
}


$financial =
    $source[
        'financial'
    ];


$check(
    $contains(
        $financial,
        'slaughter_output_sale_economics.php'
    )
    &&
    !$contains(
        $financial,
        "require_once __DIR__ . '/../lib/ruminant_slaughter_sale_economics.php';"
    )
    &&
    !$contains(
        $financial,
        "require_once __DIR__ . '/../lib/poultry_slaughter_sale_economics.php';"
    ),
    'canonical profitability loads only the shared slaughter economics boundary'
);


$check(
    $contains(
        $financial,
        'slaughter_output_sale_economics_summary('
    ),
    'canonical profitability delegates combined slaughter COGS centrally'
);


$check(
    $contains(
        $financial,
        "'poultry_slaughter_output'"
    )
    &&
    $contains(
        $financial,
        "'ruminant_slaughter_output'"
    ),
    'canonical COGS breakdown exposes both livestock domains'
);


$check(
    $contains(
        $financial,
        'Profitability slaughter-output domain aggregation does not conserve its canonical totals.'
    ),
    'canonical profitability re-proves domain aggregation conservation'
);


$check(
    $contains(
        $financial,
        "'cost_of_goods_sold'=>\$slaughterOutputCogs"
    )
    &&
    $contains(
        $financial,
        "'slaughter_output_cogs'=>\$slaughterOutputCogs"
    ),
    'canonical summary preserves existing generic COGS contract'
);


$check(
    $contains(
        $financial,
        '$totalRecognizedCost=$totalOperatingCost+$slaughterOutputCogs;'
    ),
    'combined slaughter COGS is added to recognised cost exactly once'
);


$check(
    substr_count(
        $financial,
        '$totalRecognizedCost=$totalOperatingCost+$slaughterOutputCogs;'
    ) === 1,
    'there is exactly one recognised-cost COGS composition point'
);


$check(
    $contains(
        $financial,
        "'profit'=>\$revenue-\$totalRecognizedCost"
    ),
    'profit continues to use total recognised cost'
);


$check(
    $contains(
        $financial,
        'slaughter_output_full_cost_valuation'
    )
    &&
    $contains(
        $financial,
        'slaughter_output_embedded_operating_cost'
    ),
    'canonical profitability retains valuation and embedded-cost disclosures'
);


$check(
    $contains(
        $financial,
        'slaughter-output valuation decomposition does not conserve full cost'
    ),
    'canonical combined valuation decomposition still fails closed'
);


$intelligence =
    $source[
        'intelligence'
    ];

$check(
    $contains(
        $intelligence,
        'total_recognized_cost'
    ),
    'Farm Intelligence still consumes canonical recognised cost'
);


$check(
    $contains(
        $intelligence,
        'Cost of Goods Sold · Slaughter Output'
    ),
    'Farm Intelligence remains livestock-neutral for slaughter-output COGS'
);


$profitability =
    $source[
        'profitability'
    ];

$check(
    $contains(
        $profitability,
        'Cost of goods sold'
    )
    &&
    $contains(
        $profitability,
        "\$profitabilityAttribution['cost_of_goods_sold']"
    ),
    'Profitability UI continues to reuse canonical COGS attribution'
);


$check(
    $contains(
        $profitability,
        'Slaughter-sale allocations / canonical stock ledger'
    )
    &&
    $contains(
        $profitability,
        'Unsold slaughter-output value remains in Inventory'
    ),
    'Profitability UI preserves physical provenance and unsold Inventory boundary'
);


$check(
    $contains(
        $profitability,
        'Full-cost sold valuation'
    )
    &&
    $contains(
        $profitability,
        'Embedded operating cost already recognised elsewhere'
    ),
    'Profitability UI preserves P&L versus valuation explanation'
);


$inventory =
    $source[
        'inventory_financial'
    ];

$operatingBody = '';

if (
    preg_match(
        '/function\s+inventory_operating_consumption_classifications\s*\(\s*\)\s*:\s*array\s*\{(.*?)\n\}/s',
        $inventory,
        $match
    ) === 1
) {
    $operatingBody =
        (string)$match[
            1
        ];
}

$check(
    $operatingBody !== ''
    &&
    strpos(
        $operatingBody,
        "'other_stock'"
    ) === false,
    'slaughter-output other_stock remains excluded from generic operating consumption'
);


$passed = 0;
$failed = 0;

foreach (
    $checks
    as $result
) {
    if (
        $result[
            'ok'
        ]
    ) {
        $passed++;

    } else {
        $failed++;

        fwrite(
            STDERR,
            'FAIL: '
            .
            $result[
                'label'
            ]
            .
            PHP_EOL
        );
    }
}

echo
    'SLAUGHTER_PROFITABILITY='
    .
    (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    .
    ' CHECKS='
    .
    count(
        $checks
    )
    .
    ' FAILED='
    .
    $failed
    .
    PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
