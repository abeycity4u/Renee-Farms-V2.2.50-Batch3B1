<?php
$root = dirname(__DIR__);
$failures = [];

function verify_true($condition, $message)
{
    global $failures;

    if ($condition) {
        echo "PASS | {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL | {$message}\n";
}

function verify_source($root, $path)
{
    $content = file_get_contents($root . '/' . $path);

    if ($content === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    return $content;
}

require_once $root . '/lib/transaction_actor_display.php';
require_once $root . '/lib/manual_feed_transactions.php';

echo "=== SHARED HELPERS ===\n";

verify_true(
    transaction_recorded_by_label(
        'Farm A',
        'John Ade',
        'poultry_manager'
    ) === 'Farm A — John Ade (Poultry Manager)',
    'Named Poultry Manager attribution is farm + person + role'
);

verify_true(
    transaction_recorded_by_label(
        'Farm A',
        'Farm A poultry',
        'poultry_manager'
    ) === 'Farm A — Poultry Manager',
    'Synthetic legacy farm/user name falls back to canonical role'
);

verify_true(
    transaction_recorded_by_label(
        'Farm A',
        null,
        'ruminant_manager'
    ) === 'Farm A — Ruminant Manager',
    'Missing person name falls back to farm + role'
);

verify_true(
    transaction_recorded_by_label(
        'Farm A',
        null,
        null
    ) === 'Farm A — System',
    'Unknown actor falls back to farm + System'
);

verify_true(
    manual_feed_transaction_action_state([
        'source_type' => 'manual_feed',
        'transaction_type' => 'used',
    ]) === 'manual_editable',
    'Current manual feed usage is editable'
);

verify_true(
    manual_feed_transaction_action_state([
        'source_type' => 'inventory_api',
        'transaction_type' => 'received',
    ]) === 'inventory',
    'Inventory receipt is controlled by Inventory'
);

verify_true(
    manual_feed_transaction_action_state([
        'source_type' => 'daily_layer',
        'transaction_type' => 'used',
    ]) === 'daily_record',
    'Daily Record movement is controlled by Daily Record'
);

verify_true(
    manual_feed_transaction_action_state([
        'source_type' => 'manual_feed',
        'transaction_type' => 'used',
        'is_reversed' => 1,
    ]) === 'reversed',
    'Reversed original is immutable'
);

verify_true(
    manual_feed_transaction_action_state([
        'source_type' => 'manual_feed_reversal',
        'transaction_type' => 'received',
        'reversal_of_id' => 10,
    ]) === 'restoration',
    'Restoration row is immutable'
);

echo "\n=== FARMS & TENANTS ===\n";

$farms = verify_source($root, 'management/farms.php');

verify_true(
    strpos($farms, '<title>Farms &amp; Tenants</title>') !== false,
    'Farms page title uses Farms & Tenants'
);

verify_true(
    strpos(
        $farms,
        '<h1 class="h3">Farms &amp; Tenants</h1>'
    ) !== false,
    'Farms page heading uses Farms & Tenants'
);

verify_true(
    strpos($farms, 'Platform farms') === false,
    'Legacy Platform farms wording removed'
);

echo "\n=== FEED LEDGERS ===\n";

$pages = [
    'poultry/layer_feeds.php',
    'poultry/broiler_feeds.php',
    'ruminant/ruminant_feeds_record.php',
];

foreach ($pages as $path) {
    $page = verify_source($root, $path);

    verify_true(
        strpos(
            $page,
            "require_once(__DIR__ . '/../lib/transaction_actor_display.php');"
        ) !== false,
        "{$path}: shared actor formatter loaded"
    );

    verify_true(
        strpos(
            $page,
            'u.user_type AS recorded_user_type'
        ) !== false,
        "{$path}: actor role loaded"
    );

    verify_true(
        strpos(
            $page,
            'transaction_recorded_by_label('
        ) !== false,
        "{$path}: Recorded By uses shared formatter"
    );

    verify_true(
        strpos(
            $page,
            'manual_feed_transaction_origin_label($trans)'
        ) !== false,
        "{$path}: Origin uses shared classifier"
    );

    verify_true(
        substr_count(
            $page,
            "<?php if (\$isOwner && \$ledgerView === 'operational'): ?>"
        ) === 2,
        "{$path}: Full Audit has no action column/cells"
    );

    verify_true(
        strpos(
            $page,
            'Full Audit is read-only'
        ) !== false,
        "{$path}: Full Audit is labelled read-only"
    );

    verify_true(
        strpos(
            $page,
            'Managed by Inventory'
        ) !== false,
        "{$path}: Inventory-origin guidance exists"
    );

    verify_true(
        strpos(
            $page,
            'Managed by Daily Record'
        ) !== false,
        "{$path}: Daily Record guidance exists"
    );

    verify_true(
        strpos(
            $page,
            'data-confirm-title="Reverse feed transaction?"'
        ) !== false &&
        strpos(
            $page,
            'data-confirm-button="Reverse Transaction"'
        ) !== false,
        "{$path}: manual correction is labelled Reverse"
    );

    verify_true(
        strpos(
            $page,
            'Transaction reversed and stock restored successfully.'
        ) !== false,
        "{$path}: reversal success copy is accurate"
    );

    verify_true(
        strpos(
            $page,
            "\$newType !== 'used'"
        ) !== false,
        "{$path}: edit backend accepts manual usage only"
    );

    verify_true(
        strpos(
            $page,
            'Received feed stock is managed from Inventory'
        ) !== false,
        "{$path}: edit modal explains received-stock ownership"
    );
}

echo "\n=== BROWSER EDIT HANDLERS ===\n";

$jsFiles = [
    'assets/js/layer-feeds.js',
    'assets/js/broiler-feeds.js',
    'assets/js/ruminant-feeds-record.js',
];

foreach ($jsFiles as $path) {
    $js = verify_source($root, $path);

    verify_true(
        strpos(
            $js,
            "$('#feedsTable').on('click', '.edit-transaction', function() {"
        ) !== false,
        "{$path}: delegated DataTables-safe Edit handler exists"
    );

    verify_true(
        strpos(
            $js,
            "$('.edit-transaction').on('click', function() {"
        ) === false,
        "{$path}: old direct Edit binding removed"
    );

    verify_true(
        strpos(
            $js,
            "$('#editTransactionType').val(button.data('type'))"
        ) === false,
        "{$path}: JS no longer tries to change fixed Used type"
    );
}

echo "\nCHECKS FAILED: " . count($failures) . "\n";

if ($failures) {
    exit(1);
}

echo "V2.3 feed audit controls verifier: PASSED\n";
