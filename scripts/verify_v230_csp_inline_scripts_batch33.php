<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$php = file_get_contents($root . '/inventory.php');
$js = file_get_contents($root . '/assets/js/inventory.js');

$check(
    str_contains($php, 'id="inventoryPageConfig"'),
    'Inventory emits centralized browser config'
);

$check(
    str_contains($php, 'data-active-cycles="'),
    'Inventory config exposes active production cycles'
);

$check(
    str_contains($php, 'data-base-url="'),
    'Inventory config exposes application base URL'
);

$check(
    str_contains($php, "app_json_script(\$inventoryActiveCycles)"),
    'Inventory retains safe active-cycle JSON serialization'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Inventory config values are HTML-attribute escaped'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/inventory.js')"
    ),
    'Inventory loads versioned external browser asset'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'Inventory has zero literal inline script blocks'
);

$check(
    !str_contains($php, 'DOMContentLoaded'),
    'Inventory PHP contains no embedded DOM-ready behavior'
);

$check(
    str_contains(
        $js,
        "document.getElementById('inventoryPageConfig')"
    ),
    'Inventory JS reads centralized config element'
);

$check(
    str_contains($js, 'dataset.activeCycles'),
    'Inventory JS parses active-cycle config'
);

$check(
    str_contains($js, 'dataset.baseUrl'),
    'Inventory JS reads application base URL config'
);

$check(
    str_contains($js, 'inventoryActiveCycles.filter'),
    'Inventory JS retains cycle filtering'
);

$check(
    str_contains($js, 'updateTransactionCycleOptions'),
    'Inventory JS retains transaction cycle attribution'
);

$check(
    str_contains($js, 'updateTransactionProductionAttribution'),
    'Inventory JS retains production attribution behavior'
);

$check(
    str_contains($js, 'updateItemInfo'),
    'Inventory JS retains selected-item information behavior'
);

$check(
    str_contains($js, 'updateQuantityLabel'),
    'Inventory JS retains stock quantity/type behavior'
);

$check(
    str_contains($js, 'refreshDefaultProductionAttribution'),
    'Inventory JS retains default production attribution'
);

$check(
    str_contains($js, 'bootstrap.Modal'),
    'Inventory JS retains Bootstrap stock modal behavior'
);

$check(
    str_contains($js, 'AppConfirm.ask'),
    'Inventory JS retains modern delete confirmation'
);

$check(
    str_contains(
        $js,
        '`${inventoryBaseUrl}/api/stock_history.php?item_id=${itemId}`'
    ),
    'Inventory history navigation now uses config base URL'
);

$check(
    str_contains($js, 'window.viewHistory = viewHistory;'),
    'Inventory preserves global viewHistory contract'
);

$check(
    str_contains($js, 'window.deleteItem = deleteItem;'),
    'Inventory preserves global deleteItem contract'
);

$check(
    str_contains(
        $js,
        "document.addEventListener('DOMContentLoaded'"
    ),
    'Inventory external asset retains DOM-ready initialization'
);

$check(
    str_contains($js, "$('#inventoryTable').DataTable"),
    'Inventory retains DataTables initialization'
);

$check(
    str_contains($js, '.js-quick-update'),
    'Inventory retains delegated quick-update handling'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'Inventory external asset contains no PHP'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
