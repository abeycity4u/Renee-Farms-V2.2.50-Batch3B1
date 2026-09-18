<?php

$root = dirname(__DIR__);

$paths = [
    'resolver' =>
        $root . '/lib/stock_movement_attribution.php',

    'service' =>
        $root . '/lib/stock_service.php',

    'api' =>
        $root . '/api/update_stock.php',

    'inventory' =>
        $root . '/inventory.php',

    'inventory_js' =>
        $root . '/assets/js/inventory.js',

    'dashboard' =>
        $root . '/dashboard.php',

    'dashboard_js' =>
        $root . '/assets/js/dashboard.js',

    'dashboard_quick_js' =>
        $root . '/assets/js/dashboard-quick-stock.js',

    'dashboard_permissions' =>
        $root . '/includes/dashboard_action_permissions.php',

    'daily_feed' =>
        $root . '/lib/daily_feed_sync.php',

    'manual_feed' =>
        $root . '/lib/manual_feed_transactions.php',
];

$src = [];

foreach ($paths as $key => $path) {
    $src[$key] =
        is_file($path)
            ? file_get_contents($path)
            : false;
}

$checks = [];

$check =
    static function (
        string $name,
        bool $ok
    ) use (&$checks): void {
        $checks[$name] = $ok;
    };


/*
 * =========================================================
 * CANONICAL SERVER-SIDE ATTRIBUTION
 * =========================================================
 */

$check(
    'CANONICAL_ATTRIBUTION_RESOLVER_EXISTS',
    is_string($src['resolver'])
);

$check(
    'CANONICAL_RESOLVER_FUNCTION_EXISTS',
    is_string($src['resolver'])
    &&
    strpos(
        $src['resolver'],
        'function stock_movement_attribution_resolve('
    ) !== false
);

$check(
    'CANONICAL_RESOLVER_VALIDATES_CYCLE',
    is_string($src['resolver'])
    &&
    strpos(
        $src['resolver'],
        'production_cycles'
    ) !== false
    &&
    strpos(
        $src['resolver'],
        'cycle_id'
    ) !== false
);

$check(
    'CANONICAL_RESOLVER_RETURNS_PRODUCTION',
    is_string($src['resolver'])
    &&
    strpos(
        $src['resolver'],
        "'production_type'"
    ) !== false
);

$check(
    'CANONICAL_RESOLVER_RETURNS_SCOPE',
    is_string($src['resolver'])
    &&
    strpos(
        $src['resolver'],
        "'attribution_scope'"
    ) !== false
);

$check(
    'CANONICAL_RESOLVER_PINS_POULTRY_FEED_OWNER',
    is_string($src['resolver'])
    &&
    strpos(
        $src['resolver'],
        'Feed production attribution must match its inventory feed category.'
    ) !== false
    &&
    preg_match(
        '/\$feedCategory.*?[\'"]layer[\'"].*?[\'"]broiler[\'"].*?\$requestedProduction/s',
        $src['resolver']
    ) === 1
);

$check(
    'STOCK_SERVICE_RESOLVES_BEFORE_BALANCE_UPDATE',
    is_string($src['service'])
    &&
    strpos(
        $src['service'],
        'stock_movement_attribution_resolve('
    ) !== false
    &&
    strpos(
        $src['service'],
        'UPDATE stock_items SET current_stock'
    ) !== false
    &&
    strpos(
        $src['service'],
        'stock_movement_attribution_resolve('
    )
        <
    strpos(
        $src['service'],
        'UPDATE stock_items SET current_stock'
    )
);

$check(
    'STOCK_SERVICE_REQUIRES_CANONICAL_RESOLVER',
    is_string($src['service'])
    &&
    strpos(
        $src['service'],
        "stock_movement_attribution.php"
    ) !== false
);

$check(
    'STOCK_SERVICE_USES_CANONICAL_RESOLVER',
    is_string($src['service'])
    &&
    strpos(
        $src['service'],
        'stock_movement_attribution_resolve('
    ) !== false
);

$check(
    'STOCK_SERVICE_REMAINS_CANONICAL_WRITER',
    is_string($src['service'])
    &&
    strpos(
        $src['service'],
        'INSERT INTO stock_transactions'
    ) !== false
);


/*
 * =========================================================
 * INVENTORY ENTRY POINTS
 * =========================================================
 */

$check(
    'INVENTORY_HEADER_UPDATE_SHARED_MODAL',
    is_string($src['inventory'])
    &&
    strpos(
        $src['inventory'],
        'data-bs-target="#updateStockModal"'
    ) !== false
);

$check(
    'INVENTORY_PRIORITY_UPDATE_SHARED_MODAL',
    is_string($src['inventory'])
    &&
    strpos(
        $src['inventory'],
        'Update priority stock'
    ) !== false
    &&
    substr_count(
        $src['inventory'],
        'data-bs-target="#updateStockModal"'
    ) >= 2
);

$check(
    'INVENTORY_ROW_UPDATE_SHARED_MODAL',
    is_string($src['inventory'])
    &&
    strpos(
        $src['inventory'],
        'js-quick-update'
    ) !== false
    &&
    is_string($src['inventory_js'])
    &&
    strpos(
        $src['inventory_js'],
        "getElementById('updateStockModal')"
    ) !== false
);

$check(
    'INVENTORY_FORM_HAS_PRODUCTION_ATTRIBUTION',
    is_string($src['inventory'])
    &&
    strpos(
        $src['inventory'],
        'name="production_type"'
    ) !== false
);

$check(
    'INVENTORY_FORM_HAS_CYCLE_ATTRIBUTION',
    is_string($src['inventory'])
    &&
    strpos(
        $src['inventory'],
        'name="cycle_id"'
    ) !== false
);


/*
 * =========================================================
 * API CONTRACT
 * =========================================================
 */

$check(
    'API_USES_CANONICAL_STOCK_WRITER',
    is_string($src['api'])
    &&
    strpos(
        $src['api'],
        'stock_apply_movement('
    ) !== false
);

$check(
    'API_ACCEPTS_PRODUCTION_ATTRIBUTION',
    is_string($src['api'])
    &&
    preg_match(
        '/\\$data\\s*\\[\\s*[\'"]production_type[\'"]\\s*\\]/s',
        $src['api']
    ) === 1
);

$check(
    'API_ACCEPTS_CYCLE_ATTRIBUTION',
    is_string($src['api'])
    &&
    strpos(
        $src['api'],
        "\$data['cycle_id']"
    ) !== false
);

$check(
    'API_REQUIRES_RECEIPT_UNIT_COST',
    is_string($src['api'])
    &&
    strpos(
        $src['api'],
        'Received stock requires the actual unit cost'
    ) !== false
);


/*
 * =========================================================
 * DASHBOARD ACTIVE PATH
 * =========================================================
 */

$check(
    'DASHBOARD_LOADS_ACTIVE_RUNTIME',
    is_string($src['dashboard'])
    &&
    strpos(
        $src['dashboard'],
        "versioned_asset('/assets/js/dashboard.js')"
    ) !== false
);

$check(
    'DASHBOARD_PERMISSION_BRIDGE_OWNS_QUICK_STOCK_RUNTIME',
    is_string($src['dashboard_permissions'])
    &&
    strpos(
        $src['dashboard_permissions'],
        "dashboard-quick-stock.js"
    ) !== false
    &&
    strpos(
        $src['dashboard_permissions'],
        '$canUpdateStock'
    ) !== false
    &&
    strpos(
        $src['dashboard'],
        "dashboard-quick-stock.js"
    ) === false
);

$check(
    'DASHBOARD_EXPOSES_ACTIVE_CYCLES',
    is_string($src['dashboard'])
    &&
    strpos(
        $src['dashboard'],
        'data-active-cycles='
    ) !== false
);

$check(
    'DASHBOARD_CYCLE_METADATA_IS_UPDATE_PERMISSION_GATED',
    is_string($src['dashboard'])
    &&
    strpos(
        $src['dashboard'],
        '$dashboardCanUpdateStock'
    ) !== false
    &&
    strpos(
        $src['dashboard'],
        'if ($dashboardCanUpdateStock)'
    ) !== false
    &&
    strpos(
        $src['dashboard'],
        '$dashboardActiveCycles = [];'
    ) !== false
);

$check(
    'DASHBOARD_EXPOSES_CSRF_TOKEN',
    is_string($src['dashboard'])
    &&
    strpos(
        $src['dashboard'],
        'data-csrf-token='
    ) !== false
);

$check(
    'DASHBOARD_MODAL_HAS_PRODUCTION_ATTRIBUTION',
    is_string($src['dashboard'])
    &&
    strpos(
        $src['dashboard'],
        'id="quickStockProductionType"'
    ) !== false
);

$check(
    'DASHBOARD_MODAL_HAS_CYCLE_ATTRIBUTION',
    is_string($src['dashboard'])
    &&
    strpos(
        $src['dashboard'],
        'id="quickStockCycleId"'
    ) !== false
);

$check(
    'DASHBOARD_PERMISSION_BRIDGE_FORCES_USE_ONLY',
    is_string($src['dashboard_permissions'])
    &&
    strpos(
        $src['dashboard_permissions'],
        'id="transType" value="used"'
    ) !== false
    &&
    strpos(
        $src['dashboard_permissions'],
        'Quick Stock Use'
    ) !== false
);

$check(
    'DASHBOARD_QUICK_JS_SENDS_PRODUCTION_ATTRIBUTION',
    is_string($src['dashboard_quick_js'])
    &&
    strpos(
        $src['dashboard_quick_js'],
        'production_type'
    ) !== false
);

$check(
    'DASHBOARD_QUICK_JS_SENDS_CYCLE_ATTRIBUTION',
    is_string($src['dashboard_quick_js'])
    &&
    strpos(
        $src['dashboard_quick_js'],
        'cycle_id'
    ) !== false
);

$check(
    'DASHBOARD_QUICK_JS_IS_USE_ONLY',
    is_string($src['dashboard_quick_js'])
    &&
    strpos(
        $src['dashboard_quick_js'],
        "type:'used'"
    ) !== false
    &&
    strpos(
        $src['dashboard_quick_js'],
        'unit_cost'
    ) === false
);

$check(
    'DASHBOARD_QUICK_JS_LIMITS_ATTRIBUTION_TO_GENERAL_STOCK',
    is_string($src['dashboard_quick_js'])
    &&
    strpos(
        $src['dashboard_quick_js'],
        'form.dataset.feedCategory'
    ) !== false
    &&
    substr_count(
        $src['dashboard_quick_js'],
        "feedCategory==='general'"
    ) >= 2
);

$check(
    'DASHBOARD_QUICK_JS_SENDS_CSRF',
    is_string($src['dashboard_quick_js'])
    &&
    strpos(
        $src['dashboard_quick_js'],
        'X-CSRF-Token'
    ) !== false
);


$check(
    'DASHBOARD_JS_POPULATES_ATTRIBUTION_FIELDS',
    is_string($src['dashboard_js'])
    &&
    strpos(
        $src['dashboard_js'],
        'quickStockProductionType'
    ) !== false
    &&
    strpos(
        $src['dashboard_js'],
        'quickStockCycleId'
    ) !== false
    &&
    strpos(
        $src['dashboard_js'],
        'dashboardConfig.activeCycles'
    ) !== false
);

$check(
    'DASHBOARD_JS_HAS_NO_STOCK_MUTATION_POST',
    is_string($src['dashboard_js'])
    &&
    strpos(
        $src['dashboard_js'],
        "api/update_stock.php"
    ) === false
    &&
    strpos(
        $src['dashboard_js'],
        "X-CSRF-Token"
    ) === false
);


$check(
    'DASHBOARD_JS_HAS_NO_RECEIPT_UNIT_COST_RUNTIME',
    is_string($src['dashboard_js'])
    &&
    strpos(
        $src['dashboard_js'],
        'quickStockUnitCost'
    ) === false
);

/*
 * =========================================================
 * EXISTING AUTHORITATIVE WRITERS MUST REMAIN
 * =========================================================
 */

$check(
    'DAILY_FEED_STILL_USES_CANONICAL_WRITER',
    is_string($src['daily_feed'])
    &&
    strpos(
        $src['daily_feed'],
        'stock_apply_movement('
    ) !== false
    &&
    strpos(
        $src['daily_feed'],
        'daily_feed_sync_authoritative_cycle_guard('
    ) !== false
);

$check(
    'MANUAL_FEED_STILL_USES_CANONICAL_WRITER',
    is_string($src['manual_feed'])
    &&
    strpos(
        $src['manual_feed'],
        'stock_apply_movement('
    ) !== false
);


/*
 * =========================================================
 * NO BUSINESS-DATA MUTATION IN VERIFIER
 * =========================================================
 */

$self =
    file_get_contents(__FILE__);

$selfExecutableSource = '';

if (is_string($self)) {
    foreach (
        token_get_all($self)
        as $token
    ) {
        if (is_array($token)) {
            if (
                in_array(
                    $token[0],
                    [
                        T_CONSTANT_ENCAPSED_STRING,
                        T_ENCAPSED_AND_WHITESPACE,
                        T_COMMENT,
                        T_DOC_COMMENT,
                    ],
                    true
                )
            ) {
                continue;
            }

            $selfExecutableSource .=
                $token[1];

            continue;
        }

        $selfExecutableSource .=
            $token;
    }
}

$check(
    'VERIFIER_HAS_NO_DATABASE_CONNECTION',
    is_string($self)
    &&
    preg_match(
        '/\bnew\s+\\\\?PDO\s*\(/i',
        $selfExecutableSource
    ) !== 1
);

$check(
    'VERIFIER_HAS_NO_BUSINESS_MUTATION_SQL',
    is_string($self)
    &&
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE\s+[a-z_]|DELETE\s+FROM)\b/i',
        $selfExecutableSource
    ) !== 1
);

$failed = [];

foreach ($checks as $name => $passed) {
    echo
        $name
        . '='
        . ($passed ? 'PASS' : 'FAIL')
        . PHP_EOL;

    if (!$passed) {
        $failed[] = $name;
    }
}

echo
    'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . count($failed)
    . PHP_EOL;

if ($failed) {
    echo
        'FAILED='
        . implode(',', $failed)
        . PHP_EOL;
}

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

echo
    'RESULT='
    . ($failed ? 'FAIL' : 'PASS')
    . PHP_EOL;

exit(
    $failed
        ? 1
        : 0
);
