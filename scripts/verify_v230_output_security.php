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
$layerExpenses = file_get_contents($root . '/poultry/layer_expenses.php');
$broilerExpenses = file_get_contents($root . '/poultry/broiler_expenses.php');
$ruminantExpenses = file_get_contents($root . '/ruminant/ruminant_expenses.php');

$layerFeeds = file_get_contents($root . '/poultry/layer_feeds.php');
$broilerFeeds = file_get_contents($root . '/poultry/broiler_feeds.php');
$ruminantFeeds = file_get_contents($root . '/ruminant/ruminant_feeds_record.php');
$inventory = file_get_contents($root . '/inventory.php');
$dashboard = file_get_contents($root . '/dashboard.php');

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
    'Sales seller is HTML-escaped',
    str_contains($sales, "app_html(\$sale['seller'])")
);

$add(
    'General expense text is HTML-escaped',
    str_contains($expenses, "app_html(\$expense['description'] ?: '--')")
    && str_contains($expenses, "app_html(\$expense['full_name'])")
);

$add(
    'Layer expense text is HTML-escaped',
    str_contains($layerExpenses, "app_html(\$expense['description'])")
    && str_contains($layerExpenses, "app_html(\$expense['full_name'])")
);

$add(
    'Broiler expense text is HTML-escaped',
    str_contains($broilerExpenses, "app_html(\$expense['description'] ?: '--')")
    && str_contains($broilerExpenses, "app_html(\$expense['full_name'])")
);

$add(
    'Ruminant expense text is HTML-escaped',
    str_contains($ruminantExpenses, "app_html(\$expense['description'] ?: '--')")
    && str_contains($ruminantExpenses, "app_html(\$expense['full_name'])")
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
    'Dashboard seller is HTML-escaped',
    str_contains($dashboard, "app_html(\$sale['seller'])")
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
