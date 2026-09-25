<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$checks = 0;
$failed = 0;

$check =
    static function (
        string $label,
        bool $ok
    ) use (&$checks, &$failed): void {
        $checks++;

        echo
            ($ok ? '[PASS] ' : '[FAIL] ')
            .
            $label
            .
            PHP_EOL;

        if (!$ok) {
            $failed++;
        }
    };

$read =
    static function (
        string $relative
    ) use ($root): string {
        $path =
            $root
            .
            '/'
            .
            $relative;

        return
            is_file(
                $path
            )
                ? (string)file_get_contents(
                    $path
                )
                : '';
    };


$classificationMigration =
    $read(
        'migrations/025_inventory_financial_classification.sql'
    );

$stockService =
    $read(
        'lib/stock_service.php'
    );

$inventoryPage =
    $read(
        'inventory.php'
    );

$stockApi =
    $read(
        'api/update_stock.php'
    );

$inventoryFinancial =
    $read(
        'lib/inventory_financial.php'
    );

$poultryExpenses =
    $read(
        'poultry/expenses.php'
    );

$ruminantExpenses =
    $read(
        'ruminant/ruminant_expenses.php'
    );

$updateExpense =
    $read(
        'api/update_expense.php'
    );

$poultryEntry =
    $read(
        'lib/poultry_expense_entry.php'
    );

$layerRoute =
    $read(
        'poultry/layer_expenses.php'
    );

$broilerRoute =
    $read(
        'poultry/broiler_expenses.php'
    );

$catalogPath =
    $root
    .
    '/includes/expense_category_catalog.php';


$check(
    'financial classification migration',
    strpos(
        $classificationMigration,
        'financial_classification'
    ) !== false
);

$check(
    'stock movement snapshots financial classification',
    strpos(
        $stockService,
        'financial_classification,'
    ) !== false
);

$check(
    'inventory category financial classification source',
    strpos(
        $inventoryPage,
        'name="category_financial_type"'
    ) !== false
);

$check(
    'inventory update inherits item usage classification',
    strpos(
        $inventoryPage,
        "(string)\$movementItem['feed_category']"
    ) !== false
);

$check(
    'API update inherits item usage classification',
    strpos(
        $stockApi,
        "(string)\$item['feed_category']"
    ) !== false
);

$check(
    'combined spending helper remains available',
    strpos(
        $inventoryFinancial,
        'function inventory_financial_combined_spending_totals('
    ) !== false
);

$check(
    'canonical expense category catalog exists',
    is_file(
        $catalogPath
    )
);


if (
    is_file(
        $catalogPath
    )
) {
    require_once
        $catalogPath;

    $manual =
        expense_category_options(
            'manual'
        );

    $report =
        expense_category_options(
            'report'
        );

    $processing =
        expense_category_options(
            'slaughter_processing'
        );

} else {
    $manual = [];
    $report = [];
    $processing = [];
}


$check(
    'manual category authority is canonical',
    array_keys(
        $manual
    )
    ===
    [
        'salary',
        'labour',
        'logistic',
        'fuel',
        'processing_materials',
        'misc',
    ]
);

$check(
    'new Feed and Medication remain Inventory-owned',
    !array_key_exists(
        'feeds',
        $manual
    )
    &&
    !array_key_exists(
        'medication',
        $manual
    )
);

$check(
    'historical Feed and Medication remain reportable',
    array_key_exists(
        'feeds',
        $report
    )
    &&
    array_key_exists(
        'medication',
        $report
    )
);

$check(
    'Labour and Processing Materials are current manual categories',
    ($manual['labour'] ?? null) === 'Labour Cost'
    &&
    ($manual['processing_materials'] ?? null)
        === 'Processing Materials'
);

$check(
    'Slaughter processing uses canonical processing subset',
    array_keys(
        $processing
    )
    ===
    [
        'labour',
        'logistic',
        'fuel',
        'processing_materials',
        'misc',
    ]
);

$check(
    'Poultry Expenses consumes central category authority',
    strpos(
        $poultryExpenses,
        'expense_category_options('
    ) !== false
    &&
    strpos(
        $poultryExpenses,
        "'manual'"
    ) !== false
);

$check(
    'Ruminant Expenses consumes central category authority',
    strpos(
        $ruminantExpenses,
        'expense_category_options('
    ) !== false
    &&
    strpos(
        $ruminantExpenses,
        "'manual'"
    ) !== false
);

$check(
    'shared expense update endpoint consumes update category authority',
    strpos(
        $updateExpense,
        'expense_category_normalize_for_update('
    ) !== false
);

$check(
    'Poultry expense writer consumes manual category normalizer',
    strpos(
        $poultryEntry,
        'expense_category_normalize('
    ) !== false
);

$check(
    'Inventory combined-spending bridge consumes category catalog',
    strpos(
        $inventoryFinancial,
        'expense_category_catalog.php'
    ) !== false
    &&
    strpos(
        $inventoryFinancial,
        'expense_category_is_historical('
    ) !== false
    &&
    strpos(
        $inventoryFinancial,
        'expense_category_label('
    ) !== false
);

$check(
    'legacy Layer expense route delegates to canonical hub',
    strpos(
        $layerRoute,
        'poultry_expense_compatibility_redirect('
    ) !== false
    &&
    stripos(
        $layerRoute,
        '<form'
    ) === false
    &&
    preg_match(
        '/\b(?:SELECT|INSERT|UPDATE|DELETE)\b/i',
        $layerRoute
    ) !== 1
);

$check(
    'legacy Broiler expense route delegates to canonical hub',
    strpos(
        $broilerRoute,
        'poultry_expense_compatibility_redirect('
    ) !== false
    &&
    stripos(
        $broilerRoute,
        '<form'
    ) === false
    &&
    preg_match(
        '/\b(?:SELECT|INSERT|UPDATE|DELETE)\b/i',
        $broilerRoute
    ) !== 1
);

$check(
    'retired local allowedCategories policy is absent from active writers',
    strpos(
        $poultryExpenses,
        '$allowedCategories ='
    ) === false
    &&
    strpos(
        $ruminantExpenses,
        '$allowedCategories ='
    ) === false
    &&
    strpos(
        $updateExpense,
        '$allowedManualCategories ='
    ) === false
);


echo PHP_EOL;

echo
    'CHECK_COUNT='
    .
    $checks
    .
    PHP_EOL;

echo
    'FAILED_COUNT='
    .
    $failed
    .
    PHP_EOL;

echo
    'RESULT='
    .
    (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    .
    PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
