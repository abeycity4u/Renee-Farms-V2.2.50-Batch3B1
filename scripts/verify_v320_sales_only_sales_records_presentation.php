<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$pagePath =
    $root . '/management/sales_records.php';

$jsPath =
    $root . '/assets/js/management-sales-records.js';

$pdfPath =
    $root . '/management/sales_report_pdf.php';

foreach ([$pagePath, $jsPath, $pdfPath] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "FAIL: missing {$file}\n");
        exit(1);
    }
}

$page =
    file_get_contents($pagePath);

$js =
    file_get_contents($jsPath);

$pdf =
    file_get_contents($pdfPath);

if ($page === false || $js === false || $pdf === false) {
    fwrite(STDERR, "FAIL: unable to read Sales presentation files\n");
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
        '<select id="farmTypeFilter" class="d-none"'
    )
        && str_contains(
            $page,
            '<select id="productionTypeFilter" class="d-none"'
        ),
    'Sales-only report keeps livestock scope filters structural but hidden'
);

$check(
    str_contains(
        $page,
        '<?php if (!$salesOnlyWorkspace): ?>'
    )
        && str_contains(
            $page,
            '<th>Animal Revenue Attribution</th>'
        )
        && str_contains(
            $page,
            '<th>Population Effect</th>'
        ),
    'livestock Sales table columns are conditional on non-Sales-only workspace'
);

$check(
    str_contains(
        $page,
        'id="addSaleStockSource"'
    )
        && str_contains(
            $page,
            'value="general_inventory"'
        )
        && str_contains(
            $page,
            'Inventory Item / Product'
        ),
    'Sales-only Add Sale presents General Inventory as product authority'
);

$check(
    str_contains(
        $page,
        'id="editSaleStockSource"'
    )
        && substr_count(
            $page,
            'value="general_inventory"'
        ) >= 2,
    'Sales-only Edit Sale pins General Inventory source'
);

$check(
    substr_count(
        $page,
        'class="d-none" aria-hidden="true"'
    ) >= 6,
    'Sales-only attribution controls remain hidden structural inputs'
);

$check(
    str_contains(
        $page,
        "if (!\$salesOnlyWorkspace) \$renderSalePopulationEffectControls('add');"
    )
        && str_contains(
            $page,
            "if (!\$salesOnlyWorkspace) \$renderSalePopulationEffectControls('edit');"
        ),
    'Sales-only Add/Edit forms do not render livestock population controls'
);

$check(
    str_contains(
        $js,
        'if (salesRecordsConfig.salesOnlyWorkspace)'
    )
        && str_contains(
            $js,
            "setGeneralInventorySaleMode(\n                'add',\n                true"
        ),
    'Sales-only browser initialization enters General Inventory mode'
);

$check(
    str_contains(
        $js,
        'if (!salesRecordsConfig.salesOnlyWorkspace)'
    ),
    'livestock browser initialization remains available outside Sales-only'
);

$check(
    str_contains(
        $pdf,
        'current_farm_is_sales_only()'
    )
        && str_contains(
            $pdf,
            '$salesOnlyWorkspace'
        ),
    'Sales PDF uses canonical Sales-only workspace contract'
);

$check(
    str_contains(
        $pdf,
        '<?php if ($salesOnlyWorkspace): ?>'
    )
        && str_contains(
            $pdf,
            '<?php if (!$salesOnlyWorkspace): ?><th>Farm Type</th><th>Production Type</th><th>Cycle</th><?php endif; ?>'
        ),
    'Sales-only PDF removes livestock scope columns'
);

if ($failures) {
    fwrite(
        STDERR,
        sprintf(
            "\n%d Sales-only Sales presentation check(s) failed.\n",
            count($failures)
        )
    );
    exit(1);
}

echo "\nSales-only Sales Records presentation contract passed.\n";
