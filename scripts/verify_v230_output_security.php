<?php

$root = dirname(__DIR__);
$helperPath = $root . '/includes/output_security.php';
$initPath = $root . '/init.php';

$checks = [];

$add = static function (string $label, bool $ok) use (&$checks): void {
    $checks[] = [$label, $ok];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
};

$helper = is_file($helperPath) ? file_get_contents($helperPath) : '';
$init = is_file($initPath) ? file_get_contents($initPath) : '';

$sales = file_get_contents($root . '/management/sales_records.php');
$expenses = file_get_contents($root . '/management/expenses.php');
$poultryExpenses = file_get_contents($root . '/poultry/expenses.php');
$layerExpenseRoute = file_get_contents($root . '/poultry/layer_expenses.php');
$broilerExpenseRoute = file_get_contents($root . '/poultry/broiler_expenses.php');
$ruminantExpenses = file_get_contents($root . '/ruminant/ruminant_expenses.php');

$layerFeeds = file_get_contents($root . '/poultry/layer_feeds.php');
$broilerFeeds = file_get_contents($root . '/poultry/broiler_feeds.php');
$ruminantFeeds = file_get_contents($root . '/ruminant/ruminant_feeds_record.php');
$inventory = file_get_contents($root . '/inventory.php');
$dashboard = file_get_contents($root . '/dashboard.php');
$profitability = file_get_contents($root . '/management/profitability.php');
$reports = file_get_contents($root . '/management/reports.php');
$ruminantDaily = file_get_contents($root . '/ruminant/ruminant_daily_record.php');
$ruminantExpenseAllocationJs = (string)file_get_contents(
    $root . '/assets/js/ruminant-expenses-allocation.js'
);
$inventoryJs = (string)file_get_contents(
    $root . '/assets/js/inventory.js'
);

$add('Central output-security helper exists', is_file($helperPath));
$add('HTML text helper exists', str_contains($helper, 'function app_html('));
$add('HTML attribute helper exists', str_contains($helper, 'function app_attr('));
$add('Script JSON helper exists', str_contains($helper, 'function app_json_script('));

$add(
    'HTML helper uses htmlspecialchars',
    str_contains($helper, 'htmlspecialchars(')
);

$add(
    'HTML helper uses ENT_QUOTES',
    str_contains($helper, 'ENT_QUOTES')
);

$add(
    'HTML helper uses ENT_SUBSTITUTE',
    str_contains($helper, 'ENT_SUBSTITUTE')
);

$add(
    'HTML helper explicitly uses UTF-8',
    str_contains($helper, "'UTF-8'")
);

$add(
    'Attribute helper delegates to canonical HTML escaping',
    str_contains($helper, 'return app_html($value);')
);

foreach ([
    'JSON_HEX_TAG',
    'JSON_HEX_AMP',
    'JSON_HEX_APOS',
    'JSON_HEX_QUOT',
] as $flag) {
    $add("Script JSON uses {$flag}", str_contains($helper, $flag));
}

$add(
    'Script JSON substitutes invalid UTF-8',
    str_contains($helper, 'JSON_INVALID_UTF8_SUBSTITUTE')
);

$add(
    'Bootstrap explicitly loads central output-security contract',
    str_contains(
        $init,
        "require_once __DIR__ . '/includes/output_security.php';"
    )
);

$add(
    'Sales product type is HTML-escaped',
    str_contains($sales, "app_html(\$sale['product_type'])")
);

$add(
    'Sales customer name is HTML-escaped',
    str_contains($sales, "app_html(\$sale['customer_name'] ?: '--')")
);

$add(
    'Sales remarks preview is HTML-escaped',
    str_contains($sales, "app_html(substr(\$sale['remarks'], 0, 20))")
);

$add(
    'Sales seller attribution is HTML-escaped',
    str_contains(
        $sales,
        'app_html(transaction_recorded_by_label('
    )
    && str_contains(
        $sales,
        "\$sale['seller'] ?? null"
    )
    && str_contains(
        $sales,
        "\$sale['seller_user_type'] ?? null"
    )
);

$add(
    'General expense text and actor attribution are HTML-escaped',
    str_contains(
        $expenses,
        "app_html(\$expense['description'] ?: '--')"
    )
    && str_contains(
        $expenses,
        'app_html(transaction_recorded_by_label_from_row('
    )
);

$add(
    'Canonical Poultry expense text and actor attribution are HTML-escaped',
    preg_match(
        '/echo\s+app_html\s*\(\s*'
        . '\(string\)\s*\$expense\s*'
        . '\[\s*[\'"]description[\'"]\s*\]'
        . '\s*\)/s',
        $poultryExpenses
    ) === 1
    &&
    preg_match(
        '/echo\s+app_html\s*\(\s*'
        . 'transaction_recorded_by_label_from_row\s*\(/s',
        $poultryExpenses
    ) === 1
);

$add(
    'Legacy Layer/Broiler expense routes own no rendering surface',
    str_contains(
        $layerExpenseRoute,
        'poultry_expense_compatibility_redirect('
    )
    &&
    str_contains(
        $broilerExpenseRoute,
        'poultry_expense_compatibility_redirect('
    )
    &&
    !str_contains(
        $layerExpenseRoute,
        '<html'
    )
    &&
    !str_contains(
        $broilerExpenseRoute,
        '<html'
    )
    &&
    !str_contains(
        $layerExpenseRoute,
        '<form'
    )
    &&
    !str_contains(
        $broilerExpenseRoute,
        '<form'
    )
);

$add(
    'Ruminant expense text and actor attribution are HTML-escaped',
    str_contains(
        $ruminantExpenses,
        "app_html(\$expense['description'] ?: '--')"
    )
    && str_contains(
        $ruminantExpenses,
        'app_html(transaction_recorded_by_label_from_row('
    )
);

$add(
    'Layer feed item names are HTML-escaped',
    str_contains($layerFeeds, "app_html(\$item['item_name'])")
    && str_contains($layerFeeds, "app_html(\$trans['item_name'])")
);

$add(
    'Layer feed units are HTML-escaped',
    str_contains($layerFeeds, "app_html(\$item['unit'])")
);

$add(
    'Broiler feed item names are HTML-escaped',
    str_contains($broilerFeeds, "app_html(\$item['item_name'])")
    && str_contains($broilerFeeds, "app_html(\$trans['item_name'])")
);

$add(
    'Broiler feed units are HTML-escaped',
    str_contains($broilerFeeds, "app_html(\$item['unit'])")
);

$add(
    'Ruminant feed item names are HTML-escaped',
    str_contains($ruminantFeeds, "app_html(\$item['item_name'])")
);

$add(
    'Ruminant feed units are HTML-escaped',
    str_contains($ruminantFeeds, "app_html(\$item['unit'])")
);

$add(
    'Inventory unit attribute uses attribute escaping',
    str_contains($inventory, "app_attr(\$item['unit'])")
);

$add(
    'Inventory visible unit uses HTML escaping',
    str_contains($inventory, "app_html(\$item['unit'])")
);

$add(
    'Dashboard user full name is HTML-escaped',
    str_contains($dashboard, "app_html(\$_SESSION['full_name'])")
);

$add(
    'Dashboard inventory item names are HTML-escaped',
    str_contains($dashboard, "app_html(\$item['item_name'])")
);

$add(
    'Dashboard transaction item names are HTML-escaped',
    str_contains($dashboard, "app_html(\$trans['item_name'])")
);

$add(
    'Dashboard inventory units are HTML-escaped',
    str_contains($dashboard, "app_html(\$item['unit'])")
    && str_contains($dashboard, "app_html(\$trans['unit'])")
);

$add(
    'Dashboard sales product type is HTML-escaped',
    str_contains($dashboard, "app_html(\$sale['product_type'])")
);

$add(
    'Dashboard seller attribution is HTML-escaped',
    str_contains(
        $dashboard,
        'app_html(transaction_recorded_by_label('
    )
    && str_contains(
        $dashboard,
        "\$sale['seller'] ?? null"
    )
    && str_contains(
        $dashboard,
        "\$sale['seller_user_type'] ?? null"
    )
);

$add(
    'Sales script data uses central JSON encoding',
    str_contains($sales, "app_json_script(array_keys(sales_unit_presets()))")
    && str_contains($sales, "app_json_script(\$allSalesCycles)")
    && str_contains($sales, "app_json_script(\$ruminantSaleAnimals)")
    && str_contains($sales, "app_json_script(\$ruminantSaleAnimalAllocations)")
    && str_contains($sales, "app_json_script(\$ruminantSaleExitEvents)")
);

$add(
    'Profitability dynamic config uses context-safe encoding',
    str_contains(
        $profitability,
        "app_json_script(\$cycles)"
    )
    && str_contains(
        $profitability,
        'data-production-type='
    )
    && str_contains(
        $profitability,
        "\$productionType,"
    )
    && str_contains(
        $profitability,
        'ENT_QUOTES | ENT_SUBSTITUTE'
    )
);

$add(
    'Reports chart data uses central JSON encoding',
    substr_count($reports, 'app_json_script(') >= 6
);

$add(
    'Ruminant daily selected-cycle data uses escaped attribute encoding',
    str_contains(
        $ruminantDaily,
        'data-selected-cycle-animal-type='
    )
    && str_contains(
        $ruminantDaily,
        "\$normalizeAnimalType(\$selectedCycle['production_type'] ?? '')"
    )
    && str_contains(
        $ruminantDaily,
        'ENT_QUOTES | ENT_SUBSTITUTE'
    )
);

$add(
    'Ruminant expense allocation config uses escaped JSON attributes',
    str_contains(
        $ruminantExpenses,
        'data-cycles='
    )
    && str_contains(
        $ruminantExpenses,
        'app_attr(json_encode($expenseCycles'
    )
    && str_contains(
        $ruminantExpenses,
        'data-animals='
    )
    && str_contains(
        $ruminantExpenses,
        'app_attr(json_encode($ruminantAnimals'
    )
    && substr_count(
        $ruminantExpenses,
        'JSON_INVALID_UTF8_SUBSTITUTE'
    ) >= 2
);

$add(
    'Inventory cycle script data uses central JSON encoding',
    str_contains($inventory, "app_json_script(\$inventoryActiveCycles)")
);

$add(
    'Dashboard low-stock script data uses central JSON encoding',
    str_contains($dashboard, "app_json_script(\$lowStockItems)")
);

$add(
    'Ruminant expense allocation uses DOM-safe external construction',
    str_contains(
        $ruminantExpenses,
        "versioned_asset('/assets/js/ruminant-expenses-allocation.js')"
    )
    && str_contains(
        $ruminantExpenseAllocationJs,
        'panel.replaceChildren()'
    )
    && str_contains(
        $ruminantExpenseAllocationJs,
        "tag.textContent=String(a.tag_no ?? '')"
    )
    && str_contains(
        $ruminantExpenseAllocationJs,
        "status.textContent=String(a.status ?? '')"
    )
    && str_contains(
        $ruminantExpenseAllocationJs,
        'amount.value=String(old)'
    )
);

$add(
    'Ruminant expense allocation no longer interpolates animal data into innerHTML',
    !str_contains(
        $ruminantExpenseAllocationJs,
        '<strong>${a.tag_no}</strong>'
    )
    && !str_contains(
        $ruminantExpenseAllocationJs,
        '${a.status}'
    )
    && !str_contains(
        $ruminantExpenseAllocationJs,
        'panel.innerHTML='
    )
);

$add(
    'Inventory cycle options use DOM-safe external Option construction',
    str_contains(
        $inventory,
        "versioned_asset('/assets/js/inventory.js')"
    )
    && str_contains(
        $inventoryJs,
        "cycleSelect.replaceChildren(new Option('No specific cycle / pooled usage', ''))"
    )
    && str_contains(
        $inventoryJs,
        "cycleSelect.add(new Option(String(cycle.cycle_code ?? ''), String(cycle.id ?? '')))"
    )
);

$add(
    'Inventory item details use external textContent construction',
    str_contains(
        $inventoryJs,
        "stockStrong.textContent=String(currentStock ?? '')+' '+String(unit ?? '')"
    )
    && str_contains(
        $inventoryJs,
        "itemStrong.textContent=selectedOption.textContent.split(' (Current:')[0].trim()"
    )
);

$add(
    'Inventory dynamic production options use DOM-safe external Option construction',
    preg_match_all(
        '/options\.forEach\s*\(\s*'
        . '\(\[\s*value\s*,\s*label\s*\]\)\s*'
        . '=>\s*select\.add\s*\(\s*'
        . 'new Option\s*\(\s*label\s*,\s*value\s*\)'
        . '\s*\)\s*\)/s',
        $inventoryJs
    ) >= 2
    &&
    substr_count(
        $inventoryJs,
        'select.replaceChildren'
    ) >= 2
);

$add(
    'Inventory no longer interpolates cycle/item data into innerHTML templates',
    !str_contains(
        $inventoryJs,
        '${cycle.cycle_code}'
    )
    && !str_contains(
        $inventoryJs,
        'Current stock: <strong>${currentStock} ${unit}</strong>'
    )
);

require_once $helperPath;

$probe = '<script>alert("x")</script> & \'test\'';

$html = app_html($probe);
$add(
    'HTML runtime probe escapes executable markup',
    !str_contains($html, '<script>')
    && str_contains($html, '&lt;script&gt;')
);

$attr = app_attr('" onmouseover="alert(1)');
$add(
    'Attribute runtime probe escapes quotes',
    !str_contains($attr, '" onmouseover=')
    && str_contains($attr, '&quot;')
);

$json = app_json_script([
    'payload' => '</script><script>alert(1)</script>',
]);

$add(
    'Script JSON runtime probe hex-escapes tag boundaries',
    !str_contains($json, '</script>')
    && str_contains($json, '\u003C')
);

$failures = count(array_filter(
    $checks,
    static fn(array $check): bool => !$check[1]
));

echo PHP_EOL . count($checks) . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
