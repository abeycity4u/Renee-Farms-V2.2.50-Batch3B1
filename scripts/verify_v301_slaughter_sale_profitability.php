<?php

$root = dirname(__DIR__);

$files = [
    'reader' =>
        $root . '/lib/ruminant_slaughter_sale_economics.php',

    'financial' =>
        $root . '/includes/financial.php',

    'intelligence' =>
        $root . '/lib/farm_intelligence.php',

    'profitability' =>
        $root . '/management/profitability.php',

    'inventory_financial' =>
        $root . '/lib/inventory_financial.php',
];

$source = [];

foreach ($files as $key => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            "Missing source file: {$path}\n"
        );
        exit(1);
    }

    $source[$key] =
        file_get_contents($path);
}

$checks = [];

$check =
    static function (
        bool $condition,
        string $label
    ) use (&$checks): void {
        $checks[] = [
            'ok' => $condition,
            'label' => $label,
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

$reader =
    $source['reader'];

$check(
    $contains(
        $reader,
        'ruminant_slaughter_sale_economics_summary'
    ),
    'central slaughter-sale COGS reader exists'
);

$check(
    $contains(
        $reader,
        'a.is_active=1'
    ),
    'only active lot allocations are recognised'
);

$check(
    $contains(
        $reader,
        "ruminant_slaughter_sale'"
    ),
    'COGS requires canonical slaughter-sale stock provenance'
);

$check(
    $contains(
        $reader,
        'source_id'
    ),
    'allocation identity is checked against stock source identity'
);

$check(
    $contains(
        $reader,
        'is_reversed'
    )
    &&
    $contains(
        $reader,
        'reversal_of_id'
    ),
    'reversed stock movements are rejected'
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
    'frozen allocation COGS is reconciled to stock ledger COGS'
);

$check(
    $contains(
        $reader,
        'sale_date BETWEEN ? AND ?'
    ),
    'financial recognition is bounded by sale business date'
);

$check(
    !$contains(
        strtoupper($reader),
        'INSERT INTO'
    )
    &&
    !$contains(
        strtoupper($reader),
        'UPDATE '
    )
    &&
    !$contains(
        strtoupper($reader),
        'DELETE FROM'
    ),
    'COGS reader is read-only'
);

$financial =
    $source['financial'];

$check(
    $contains(
        $financial,
        "ruminant_slaughter_sale_economics.php"
    ),
    'canonical profitability loads the central COGS reader'
);

$check(
    $contains(
        $financial,
        'ruminant_slaughter_sale_economics_summary'
    ),
    'canonical profitability delegates slaughter COGS'
);

$check(
    $contains(
        $financial,
        "'cost_of_goods_sold'=>\$slaughterOutputCogs"
    ),
    'canonical summary exposes COGS separately'
);

$check(
    $contains(
        $financial,
        '$totalRecognizedCost=$totalOperatingCost+$slaughterOutputCogs;'
    ),
    'recognised cost composes operating cost plus COGS exactly once'
);

$check(
    $contains(
        $financial,
        "'profit'=>\$revenue-\$totalRecognizedCost"
    ),
    'profit uses total recognised cost'
);

$intelligence =
    $source['intelligence'];

$check(
    $contains(
        $intelligence,
        'total_recognized_cost'
    ),
    'Farm Intelligence consumes canonical recognised cost'
);

$check(
    $contains(
        $intelligence,
        'Cost of Goods Sold · Slaughter Output'
    ),
    'Farm Intelligence explains slaughter-output COGS'
);

$profitability =
    $source['profitability'];

$check(
    $contains(
        $profitability,
        'Cost of goods sold'
    ),
    'Profitability headline UI discloses COGS'
);

$check(
    $contains(
        $profitability,
        "\$profitabilityAttribution['cost_of_goods_sold']"
    ),
    'Profitability trace reuses canonical COGS attribution'
);

$check(
    $contains(
        $profitability,
        'Slaughter-sale allocations / canonical stock ledger'
    ),
    'Profitability source breakdown explains slaughter COGS provenance'
);

$check(
    $contains(
        $profitability,
        'Cost of goods sold (sold slaughter-output lots)'
    ),
    'Profitability calculation explanation includes COGS'
);

$check(
    $contains(
        $profitability,
        'Unsold slaughter-output value remains in Inventory'
    ),
    'Profitability explains unsold output carrying value boundary'
);

$inventory =
    $source['inventory_financial'];

$operatingBody = '';

if (
    preg_match(
        '/function\s+inventory_operating_consumption_classifications\s*\(\s*\)\s*:\s*array\s*\{(.*?)\n\}/s',
        $inventory,
        $match
    ) === 1
) {
    $operatingBody =
        (string)$match[1];
}

$check(
    $operatingBody !== ''
    &&
    strpos(
        $operatingBody,
        "'other_stock'"
    ) === false,
    'other_stock remains excluded from generic operating consumption'
);

$passed = 0;
$failed = 0;

foreach ($checks as $result) {
    if ($result['ok']) {
        $passed++;
    } else {
        $failed++;
        fwrite(
            STDERR,
            'FAIL: '
            . $result['label']
            . PHP_EOL
        );
    }
}

echo
    'SLAUGHTER_PROFITABILITY='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . ' CHECKS='
    . count($checks)
    . ' FAILED='
    . $failed
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
