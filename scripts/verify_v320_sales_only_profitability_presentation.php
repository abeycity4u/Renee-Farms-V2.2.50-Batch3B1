<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root . '/management/profitability.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing Profitability page\n"
    );
    exit(1);
}

$page =
    file_get_contents($path);

if ($page === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read Profitability page\n"
    );
    exit(1);
}

$failures = [];

$check =
    static function (
        bool $condition,
        string $label
    ) use (&$failures): void {
        echo ($condition ? 'PASS: ' : 'FAIL: ')
            . $label
            . PHP_EOL;

        if (!$condition) {
            $failures[] =
                $label;
        }
    };

$check(
    str_contains(
        $page,
        'current_farm_is_sales_only()'
    ),
    'Profitability uses canonical Sales-only workspace contract'
);

$check(
    str_contains(
        $page,
        "if (\$salesOnlyWorkspace) {\n    \$farmType =\n        'general';"
    )
        && str_contains(
            $page,
            "\$productionType =\n        'all';"
        )
        && str_contains(
            $page,
            "\$cycleId =\n        0;"
        ),
    'Sales-only Profitability pins General scope with no production or cycle dimension'
);

$check(
    str_contains(
        $page,
        '<input'
    )
        && str_contains(
            $page,
            'name="farm_type"'
        )
        && str_contains(
            $page,
            'value="general"'
        )
        && str_contains(
            $page,
            '<?php if ($salesOnlyWorkspace): ?>'
        ),
    'Sales-only Profitability filter keeps only hidden General scope'
);

$check(
    str_contains(
        $page,
        'Cost of Goods Sold'
    )
        && str_contains(
            $page,
            'Gross Profit'
        )
        && str_contains(
            $page,
            'Operating Expenses'
        )
        && str_contains(
            $page,
            'Net Profit / Loss'
        )
        && str_contains(
            $page,
            'Profit Margin'
        ),
    'Sales-only Profitability renders business P&L KPIs'
);

$check(
    str_contains(
        $page,
        "<?php if (!\$salesOnlyWorkspace): ?>\n    <div class=\"card mb-4\" id=\"unallocated-shared-balances\">"
    )
        && str_contains(
            $page,
            "<?php if (!\$salesOnlyWorkspace): ?>\n    <div class=\"card mb-4\" id=\"profitability-attribution\">"
        ),
    'shared allocation and attribution workspaces are excluded from Sales-only presentation'
);

$check(
    str_contains(
        $page,
        'Feed consumed'
    )
        && str_contains(
            $page,
            'Production cycle (optional)'
        )
        && str_contains(
            $page,
            'Unallocated Shared Balances'
        ),
    'livestock Profitability presentation remains available outside Sales-only'
);

$check(
    str_contains(
        $page,
        'Cost of goods sold is released from the frozen cost of General Inventory actually sold.'
    )
        && str_contains(
            $page,
            'Inventory purchases are not charged twice'
        ),
    'Sales-only Profitability explanation uses General business accounting language'
);

$check(
    str_contains(
        $page,
        "<?php if (!\$salesOnlyWorkspace): ?>\n<div\n    id=\"managementProfitabilityConfig\""
    ),
    'livestock filter JavaScript configuration is omitted for Sales-only'
);

if ($failures) {
    fwrite(
        STDERR,
        sprintf(
            "\n%d Sales-only Profitability check(s) failed.\n",
            count($failures)
        )
    );
    exit(1);
}

echo "\nSales-only Profitability presentation contract passed.\n";
