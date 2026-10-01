<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$reportsPath =
    $root . '/management/reports.php';

$intelligencePath =
    $root . '/lib/farm_intelligence.php';

foreach ([$reportsPath, $intelligencePath] as $file) {
    if (!is_file($file)) {
        fwrite(
            STDERR,
            "FAIL: missing {$file}\n"
        );
        exit(1);
    }
}

$reports =
    file_get_contents($reportsPath);

$intelligence =
    file_get_contents($intelligencePath);

if ($reports === false || $intelligence === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read Analytics source files\n"
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
        $reports,
        'current_farm_is_sales_only()'
    ),
    'Analytics uses canonical Sales-only entitlement contract'
);

$check(
    str_contains(
        $reports,
        '<select'
    )
        && str_contains(
            $reports,
            'id="farmTypeFilter"'
        )
        && str_contains(
            $reports,
            'class="d-none"'
        ),
    'Sales-only Analytics hides Farm Type while preserving shared JS structure'
);

$check(
    str_contains(
        $intelligence,
        "'cost_of_goods_sold' =>"
    )
        && str_contains(
            $intelligence,
            "'gross_profit' =>"
        )
        && str_contains(
            $intelligence,
            "'operating_expenses' =>"
        ),
    'shared monthly intelligence exposes business P&L components'
);

$check(
    str_contains(
        $intelligence,
        'Cost of Goods Sold · General Inventory'
    )
        && str_contains(
            $intelligence,
            'Cost of Goods Sold · Slaughter Output'
        ),
    'shared cost breakdown distinguishes General Inventory and slaughter COGS'
);

$check(
    str_contains(
        $reports,
        '<th>COGS</th>'
    )
        && str_contains(
            $reports,
            '<th>Gross Profit</th>'
        )
        && str_contains(
            $reports,
            '<th>Operating Expenses</th>'
        )
        && str_contains(
            $reports,
            '<th>Net Profit / Loss</th>'
        )
        && str_contains(
            $reports,
            '<th>Profit Margin</th>'
        ),
    'Sales-only detailed Analytics uses business P&L columns'
);

$check(
    str_contains(
        $reports,
        '<th>Feed Consumed</th>'
    )
        && str_contains(
            $reports,
            '<th>Farm Type</th>'
        ),
    'livestock Analytics presentation remains available outside Sales-only'
);

$check(
    str_contains(
        $reports,
        "'Cost of Goods Sold'"
    )
        && str_contains(
            $reports,
            "'Gross Profit'"
        )
        && str_contains(
            $reports,
            "'Operating Expenses'"
        )
        && str_contains(
            $reports,
            "'Profit Margin'"
        ),
    'Sales-only Excel export uses business P&L columns'
);

$check(
    str_contains(
        $reports,
        'Cost & Expense Breakdown'
    ),
    'Sales-only cost breakdown title reflects COGS plus operating expenses'
);

if ($failures) {
    fwrite(
        STDERR,
        sprintf(
            "\n%d Sales-only Analytics check(s) failed.\n",
            count($failures)
        )
    );
    exit(1);
}

echo "\nSales-only Analytics presentation contract passed.\n";
