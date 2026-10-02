<?php

$root = dirname(__DIR__);

$pagePath =
    $root . '/management/sales_records.php';

$servicePath =
    $root . '/lib/general_sale_inventory.php';

$jsPath =
    $root . '/assets/js/management-sales-records.js';

$page = file_get_contents($pagePath);
$service = file_get_contents($servicePath);
$js = file_get_contents($jsPath);

if (
    $page === false
    || $service === false
    || $js === false
) {
    fwrite(STDERR, "VERIFY_SETUP_FAILED\n");
    exit(2);
}

$passes = 0;
$failures = 0;

function verify_scope(
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

verify_scope(
    strpos(
        $page,
        '$generalSaleInventoryItems ='
    ) !== false
    &&
    strpos(
        $page,
        '$salesOnlyWorkspace'
    ) !== false
    &&
    strpos(
        $page,
        '? general_sale_inventory_available_items('
    ) !== false,
    'General Inventory catalog loads only for Sales-only workspace'
);

preg_match_all(
    '/<select[^>]+name="sale_stock_source"[^>]*>(.*?)<\/select>/s',
    $page,
    $matches
);

$visibleStockSourceBodies =
    $matches[1] ?? [];

$livestockDropdownsClean =
    count($visibleStockSourceBodies) === 2;

foreach ($visibleStockSourceBodies as $body) {
    if (
        strpos(
            $body,
            'value="general_inventory"'
        ) !== false
    ) {
        $livestockDropdownsClean = false;
        break;
    }
}

verify_scope(
    $livestockDropdownsClean,
    'Livestock Add/Edit stock-source dropdowns exclude General Inventory'
);

verify_scope(
    substr_count(
        $page,
        'value="general_inventory"'
    ) >= 2
    &&
    strpos(
        $page,
        'type="hidden"'
    ) !== false
    &&
    strpos(
        $page,
        'id="addSaleStockSource"'
    ) !== false
    &&
    strpos(
        $page,
        'id="editSaleStockSource"'
    ) !== false,
    'Sales-only Add/Edit preserve hidden General Inventory source'
);

verify_scope(
    strpos(
        $service,
        'bool $allowGeneralInventory = true'
    ) !== false
    &&
    strpos(
        $service,
        'if (!$allowGeneralInventory)'
    ) !== false
    &&
    strpos(
        $service,
        'General Inventory sales are available only in a Sales-only workspace.'
    ) !== false,
    'General Inventory service has an explicit workspace policy guard'
);

verify_scope(
    substr_count(
        $page,
        'general_sale_inventory_selection_from_post('
    ) === 2,
    'Sales page has exactly Add and Edit General Inventory service calls'
);

verify_scope(
    substr_count(
        $page,
        '$salesOnlyWorkspace'
    ) >= 3
    &&
    preg_match(
        '/general_sale_inventory_selection_from_post\s*\(\s*\$pdo\s*,\s*\$tenantFarmId\s*,\s*\$_POST\s*,\s*\$salesOnlyWorkspace\s*\)/s',
        $page
    ) === 1,
    'Add/Edit General Inventory calls pass Sales-only policy'
);

verify_scope(
    strpos(
        $js,
        'The selected General Inventory item will be deducted.'
    ) === false,
    'Livestock Cut 2B guidance no longer advertises General Inventory'
);

verify_scope(
    strpos(
        $service,
        "\$input['farm_type'] ="
    ) !== false
    &&
    strpos(
        $service,
        "'general';"
    ) !== false
    &&
    strpos(
        $service,
        "\$input['population_effect_mode'] ="
    ) !== false
    &&
    strpos(
        $service,
        "'financial_only';"
    ) !== false,
    'Sales-only General Inventory remains neutral and population-safe'
);

echo "\n=== RESULT ===\n";
echo "PASS={$passes}\n";
echo "FAIL={$failures}\n";

exit($failures === 0 ? 0 : 1);
