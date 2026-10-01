<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$salesPagePath =
    $root . '/management/sales_records.php';

$salesJsPath =
    $root . '/assets/js/management-sales-records.js';

$generalInventoryPath =
    $root . '/lib/general_sale_inventory.php';

$files = [
    $salesPagePath,
    $salesJsPath,
    $generalInventoryPath,
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(
            STDERR,
            "FAIL: missing required file {$file}\n"
        );
        exit(1);
    }
}

$page =
    file_get_contents(
        $salesPagePath
    );

$js =
    file_get_contents(
        $salesJsPath
    );

$generalInventory =
    file_get_contents(
        $generalInventoryPath
    );

if (
    $page === false
    || $js === false
    || $generalInventory === false
) {
    fwrite(
        STDERR,
        "FAIL: unable to read Sales workspace files\n"
    );
    exit(1);
}

$failures = [];

$check =
    static function (
        bool $condition,
        string $label
    ) use (&$failures): void {
        if ($condition) {
            echo "PASS: {$label}\n";
            return;
        }

        echo "FAIL: {$label}\n";
        $failures[] =
            $label;
    };

$check(
    str_contains(
        $page,
        'current_farm_is_sales_only()'
    ),
    'Sales Records uses canonical Sales-only entitlement contract'
);

$check(
    preg_match(
        '/\$salesOnlyScope\s*=\s*\$salesOnlyWorkspace\s*;/',
        $page
    ) === 1,
    'legacy Sales-only report scope is derived from canonical workspace flag'
);

$check(
    substr_count(
        $page,
        "\$_POST['sale_stock_source'] ="
    ) === 2,
    'create and update both force the Sales-only stock source'
);

$check(
    substr_count(
        $page,
        "'general_inventory'"
    ) >= 2,
    'Sales-only create and update force General Inventory mode'
);

$check(
    substr_count(
        $page,
        "\$_POST['farm_type'] ="
    ) === 2
        && substr_count(
            $page,
            "\$_POST['production_type'] ="
        ) === 2
        && substr_count(
            $page,
            "\$_POST['cycle_id'] ="
        ) === 2,
    'create and update both pin General attribution inputs'
);

$check(
    substr_count(
        $page,
        "\$populationEffectRows = [];"
    ) === 2,
    'Sales-only create and update suppress livestock population effects'
);

$check(
    substr_count(
        $page,
        "'mode' => 'financial_only'"
    ) >= 2,
    'Sales-only create and update suppress slaughter-output mode'
);

$check(
    substr_count(
        $page,
        '$prepareSlaughterSaleInput('
    ) >= 2,
    'livestock create and update paths still retain slaughter handling'
);

$check(
    substr_count(
        $page,
        'general_sale_inventory_selection_from_post('
    ) >= 2,
    'create and update still use canonical General Inventory selection service'
);

$check(
    str_contains(
        $page,
        'data-sales-only-workspace='
    ),
    'Sales-only workspace flag is exported to browser configuration'
);

$check(
    str_contains(
        $js,
        "configElement.dataset.salesOnlyWorkspace === '1'"
    ),
    'Sales Records JS consumes the Sales-only workspace flag'
);

$check(
    str_contains(
        $generalInventory,
        "\$input['farm_type'] ="
    )
        && str_contains(
            $generalInventory,
            "'general';"
        )
        && str_contains(
            $generalInventory,
            "\$input['production_type'] ="
        )
        && str_contains(
            $generalInventory,
            "\$input['cycle_id'] ="
        ),
    'General Inventory service remains authoritative for General attribution'
);

$check(
    str_contains(
        $generalInventory,
        "\$input['product_type'] ="
    )
        && str_contains(
            $generalInventory,
            "(string)\$item['item_name']"
        ),
    'General Inventory service remains authoritative for product identity'
);

$check(
    str_contains(
        $generalInventory,
        "\$input['population_effect_mode'] ="
    )
        && str_contains(
            $generalInventory,
            "\$input['sale_animal_allocation_mode'] ="
        ),
    'General Inventory service still strips livestock side effects'
);

if ($failures) {
    fwrite(
        STDERR,
        sprintf(
            "\n%d Sales-only Sales Records contract check(s) failed.\n",
            count($failures)
        )
    );
    exit(1);
}

echo "\nSales-only Sales Records contract passed.\n";
