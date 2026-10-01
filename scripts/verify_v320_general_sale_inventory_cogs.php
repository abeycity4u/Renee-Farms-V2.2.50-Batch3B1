<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;

function cogs_check(bool $ok, string $label): void
{
    global $failures;

    if (!$ok) {
        $failures++;
    }

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;
}

$service =
    (string)file_get_contents(
        $root . '/lib/general_sale_inventory_economics.php'
    );

$financial =
    (string)file_get_contents(
        $root . '/includes/financial.php'
    );

cogs_check(
    str_contains($service, "t.source_type='general_sale'"),
    'General-sale COGS is source-owned'
);

cogs_check(
    str_contains($service, "t.transaction_type='used'"),
    'General-sale COGS uses consumed stock only'
);

cogs_check(
    str_contains($service, 'stock_effective_sql_predicate('),
    'General-sale COGS uses effective ledger history'
);

cogs_check(
    str_contains(
        $financial,
        "general_sale_inventory_economics.php"
    ),
    'Profitability loads General-sale economics'
);

cogs_check(
    str_contains(
        $financial,
        '$slaughterOutputCogs'
        . PHP_EOL
        . '            + $generalSaleInventoryCogs'
    ),
    'Combined COGS includes General-sale Inventory'
);

cogs_check(
    str_contains(
        $financial,
        '$totalRecognizedCost=$totalOperatingCost+$costOfGoodsSold;'
    ),
    'Profit uses combined COGS'
);

cogs_check(
    substr_count(
        $financial,
        "'general_sale_inventory'=>$generalSaleInventoryCogs"
    ) === 2,
    'General-sale COGS appears in both breakdowns'
);

cogs_check(
    str_contains(
        $financial,
        "'cost_of_goods_sold'=>$costOfGoodsSold"
    ),
    'Canonical summary returns combined COGS'
);

if ($failures) {
    echo "GENERAL_SALE_INVENTORY_COGS=FAIL
";
    exit(1);
}

echo "GENERAL_SALE_INVENTORY_COGS=PASS
";
