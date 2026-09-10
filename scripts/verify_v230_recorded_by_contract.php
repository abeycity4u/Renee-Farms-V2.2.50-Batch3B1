<?php
$root = dirname(__DIR__);
$failures = [];

function rb_check($condition, $message)
{
    global $failures;

    if ($condition) {
        echo "PASS | {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FAIL | {$message}\n";
}

function rb_source($root, $path)
{
    $content = file_get_contents(
        $root . '/' . $path
    );

    if ($content === false) {
        throw new RuntimeException(
            "Unable to read {$path}"
        );
    }

    return $content;
}

require_once $root .
    '/lib/transaction_actor_display.php';

require_once $root .
    '/lib/manual_feed_transactions.php';


echo "=== CANONICAL ACTOR FORMAT ===\n";

rb_check(
    transaction_recorded_by_label(
        'Farm A LLC',
        'Farm A',
        'farm_admin'
    ) === 'Farm A LLC — Farm Admin',
    'LLC legal suffix does not duplicate synthetic Farm Admin name'
);

rb_check(
    transaction_recorded_by_label(
        'Renee Farms Limited',
        'Renee Farms poultry',
        'poultry_manager'
    ) === 'Renee Farms Limited — Poultry Manager',
    'Limited suffix and synthetic poultry account normalize correctly'
);

rb_check(
    transaction_recorded_by_label(
        'Farm A LLC',
        'John Ade',
        'farm_admin'
    ) ===
        'Farm A LLC — John Ade (Farm Admin)',
    'Real person name remains visible with canonical role'
);

rb_check(
    transaction_recorded_by_label(
        'Farm A LLC',
        null,
        'ruminant_manager'
    ) ===
        'Farm A LLC — Ruminant Manager',
    'Missing full name falls back to farm + role'
);


echo "\n=== FEED ORIGIN OWNERSHIP ===\n";

rb_check(
    manual_feed_transaction_action_state([
        'source_type' => 'inventory_manual',
        'transaction_type' => 'used',
    ]) === 'inventory',
    'inventory_manual Used transaction is Inventory-owned'
);

rb_check(
    manual_feed_transaction_origin_label([
        'source_type' => 'inventory_manual',
        'transaction_type' => 'used',
    ]) === 'Inventory',
    'inventory_manual displays Origin as Inventory'
);

rb_check(
    manual_feed_transaction_action_state([
        'source_type' => 'manual_feed',
        'transaction_type' => 'used',
    ]) === 'manual_editable',
    'genuine manual Feed Record usage remains editable'
);


echo "\n=== RECORDED BY CONSUMERS ===\n";

$consumerFiles = [
    'dashboard.php',
    'management/expenses.php',
    'poultry/layer_expenses.php',
    'poultry/broiler_expenses.php',
    'ruminant/ruminant_expenses.php',
    'management/expense_report_pdf.php',
    'poultry/health.php',
    'management/sales_records.php',
    'management/sales_report_pdf.php',
    'management/debt_history_pdf.php',
    'ruminant/animal_view.php',
    'includes/platform_owner_subscription_history.php',
    'management/investigation.php',
    'management/ruminant_investigation.php',
    'poultry/layer_feeds.php',
    'poultry/broiler_feeds.php',
    'ruminant/ruminant_feeds_record.php',
];

foreach ($consumerFiles as $path) {
    $source = rb_source(
        $root,
        $path
    );

    rb_check(
        strpos(
            $source,
            'transaction_recorded_by_label'
        ) !== false,
        "{$path}: uses canonical Recorded By formatter"
    );
}


echo "\n=== ACTOR ROLE QUERIES ===\n";

$expenseFiles = [
    'management/expenses.php',
    'poultry/layer_expenses.php',
    'poultry/broiler_expenses.php',
    'ruminant/ruminant_expenses.php',
    'management/expense_report_pdf.php',
];

foreach ($expenseFiles as $path) {
    $source = rb_source(
        $root,
        $path
    );

    rb_check(
        strpos(
            $source,
            'recorded_by_user_type'
        ) !== false,
        "{$path}: loads actor role with expense history"
    );
}

$dashboard = rb_source(
    $root,
    'dashboard.php'
);

rb_check(
    strpos(
        $dashboard,
        'u.user_type AS seller_user_type'
    ) !== false,
    'Dashboard Recent Sales loads actor role'
);

rb_check(
    strpos(
        $dashboard,
        "app_html(\$sale['seller'])"
    ) === false &&
    strpos(
        $dashboard,
        'transaction_recorded_by_label'
    ) !== false,
    'Dashboard Recent Sales uses canonical actor formatter'
);

$healthLib = rb_source(
    $root,
    'lib/poultry_health.php'
);

rb_check(
    strpos(
        $healthLib,
        'u.user_type AS recorded_by_user_type'
    ) !== false,
    'Poultry Health query loads actor role'
);

$investigationLib = rb_source(
    $root,
    'lib/investigation_followup.php'
);

rb_check(
    substr_count(
        $investigationLib,
        'recorded_by_user_type'
    ) >= 4,
    'Investigation queries load Recorded By roles'
);


echo "\n=== STOCK HISTORY ===\n";

$stockApi = rb_source(
    $root,
    'api/get_stock_history.php'
);

rb_check(
    strpos(
        $stockApi,
        "['recorded_by_label']"
    ) !== false,
    'Stock History API supplies canonical Recorded By label'
);

$stockJs = rb_source(
    $root,
    'assets/js/stock-history.js'
);

rb_check(
    strpos(
        $stockJs,
        'tx.recorded_by_label'
    ) !== false &&
    strpos(
        $stockJs,
        'tx.full_name ?'
    ) === false,
    'Stock History browser renders canonical actor label'
);


echo "\nCHECKS FAILED: " .
    count($failures) .
    "\n";

if ($failures) {
    exit(1);
}

echo
    "V2.3 platform Recorded By contract verifier: PASSED\n";
