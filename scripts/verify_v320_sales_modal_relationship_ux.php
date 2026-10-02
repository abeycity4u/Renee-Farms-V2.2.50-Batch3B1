<?php

$root = dirname(__DIR__);

$pagePath =
    $root . '/management/sales_records.php';

$jsPath =
    $root . '/assets/js/management-sales-records.js';

$page = file_get_contents($pagePath);
$js = file_get_contents($jsPath);

if ($page === false || $js === false) {
    fwrite(STDERR, "VERIFY_SETUP_FAILED\n");
    exit(2);
}

$passes = 0;
$failures = 0;

function verify_relationship(
    bool $condition,
    string $label
): void {
    global $passes, $failures;

    if ($condition) {
        $passes++;
        echo "[PASS] {$label}\n";
        return;
    }

    $failures++;
    echo "[FAIL] {$label}\n";
}

verify_relationship(
    substr_count(
        $page,
        '<label>Physical Stock Impact</label>'
    ) === 2,
    'Add and Edit use Physical Stock Impact label'
);

verify_relationship(
    strpos(
        $page,
        'id="addSaleStockSourceHelp"'
    ) !== false
    &&
    strpos(
        $page,
        'id="editSaleStockSourceHelp"'
    ) !== false,
    'Add and Edit expose shared stock-source guidance'
);

verify_relationship(
    strpos(
        $js,
        'function saleStockSourceGuidanceSelectors'
    ) !== false
    &&
    strpos(
        $js,
        'function refreshSaleStockSourceGuidance'
    ) !== false,
    'Stock-source guidance is centralized across Add and Edit'
);

verify_relationship(
    strpos(
        $js,
        'sale value and revenue allocation'
    ) !== false
    &&
    strpos(
        $js,
        'population impact'
    ) !== false
    &&
    strpos(
        $js,
        'physically leaves the farm'
    ) !== false,
    'Financial-only guidance separates revenue from physical impact'
);

verify_relationship(
    strpos(
        $js,
        'The selected General Inventory item will be deducted.'
    ) === false,
    'Livestock stock-source guidance does not advertise General Inventory'
);

verify_relationship(
    strpos(
        $js,
        'The selected slaughter-output lot will be consumed.'
    ) !== false
    &&
    strpos(
        $js,
        'Live population was already changed by the slaughter record'
    ) !== false,
    'Slaughter-output guidance prevents double population interpretation'
);

verify_relationship(
    strpos(
        $js,
        "source === 'slaughter_output'"
    ) !== false
    &&
    strpos(
        $js,
        "source === 'general_inventory'"
    ) !== false,
    'Existing stock-source mode values remain unchanged'
);

verify_relationship(
    substr_count(
        $js,
        'refreshSaleStockSourceGuidance'
    ) >= 5
    &&
    strpos(
        $js,
        "'add'"
    ) !== false
    &&
    strpos(
        $js,
        "'edit'"
    ) !== false
    &&
    strpos(
        $js,
        '#addSaleStockSource'
    ) !== false
    &&
    strpos(
        $js,
        '#editSaleStockSource'
    ) !== false,
    'Add and Edit source changes refresh guidance'
);

verify_relationship(
    strpos(
        $js,
        ".val(\n                'financial_only'\n            );"
    ) !== false
    &&
    strpos(
        $js,
        ".addClass(\n                'd-none'\n            );"
    ) !== false,
    'Slaughter mode still forces financial-only population handling and hides population UI'
);

echo "\n=== RESULT ===\n";
echo "PASS={$passes}\n";
echo "FAIL={$failures}\n";

exit($failures === 0 ? 0 : 1);
