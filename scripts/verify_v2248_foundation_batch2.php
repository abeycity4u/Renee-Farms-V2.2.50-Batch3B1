<?php
$root = dirname(__DIR__);
$checks = [
    'financial classification migration' => [$root . '/migrations/025_inventory_financial_classification.sql', 'financial_classification'],
    'stock movement snapshots financial classification' => [$root . '/lib/stock_service.php', 'financial_classification,'],
    'inventory category financial classification source' => [$root . '/inventory.php', 'name="category_financial_type"'],
    'inventory update inherits item usage classification' => [$root . '/inventory.php', "(string)\$movementItem['feed_category']"],
    'API update inherits item usage classification' => [$root . '/api/update_stock.php', "(string)\$item['feed_category']"],
    'combined spending helper' => [$root . '/lib/inventory_financial.php', 'function inventory_financial_combined_spending_totals('],
    'layer total spending' => [$root . '/poultry/layer_expenses.php', 'TOTAL SPENDING:'],
    'broiler total spending' => [$root . '/poultry/broiler_expenses.php', 'TOTAL BROILER SPENDING:'],
    'ruminant total spending' => [$root . '/ruminant/ruminant_expenses.php', 'TOTAL RUMINANT SPENDING:'],
    'layer new-feed blocked server-side' => [$root . '/poultry/layer_expenses.php', "\$allowedCategories = ['salary', 'logistic', 'fuel', 'misc'];"],
    'broiler new-feed blocked server-side' => [$root . '/poultry/broiler_expenses.php', "\$allowedCategories = ['salary', 'logistic', 'fuel', 'misc'];"],
    'ruminant new-feed blocked server-side' => [$root . '/ruminant/ruminant_expenses.php', "\$allowedCategories = ['salary', 'logistic', 'fuel', 'misc'];"],
    'legacy feed edit protection' => [$root . '/api/update_expense.php', '$isLegacyFeedEdit'],
];

$failed = 0;
foreach ($checks as $label => [$file, $needle]) {
    $contents = is_file($file) ? file_get_contents($file) : false;
    $ok = $contents !== false && strpos($contents, $needle) !== false;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}

foreach (['poultry/layer_expenses.php','poultry/broiler_expenses.php','ruminant/ruminant_expenses.php'] as $relative) {
    $contents = file_get_contents($root . '/' . $relative);
    $pos = strpos($contents, 'id="addExpenseModal"');
    $tail = $pos === false ? '' : substr($contents, $pos);
    $ok = $pos !== false && strpos($tail, 'option value="feeds"') === false && strpos($tail, 'option value="medication"') === false;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $relative . ' Add Expense hides Feed/Medication' . PHP_EOL;
    if (!$ok) $failed++;
}

if ($failed > 0) {
    fwrite(STDERR, "Verification failed: {$failed} check(s).\n");
    exit(1);
}
echo "V2.2.48 Foundation Batch 2 static verification passed.\n";
