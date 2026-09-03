<?php
$root = dirname(__DIR__);
$checks = [
    'inventory transaction-date field' => [
        $root . '/inventory.php',
        'name="transaction_date"'
    ],
    'inventory transaction-date validation' => [
        $root . '/inventory.php',
        'Please provide a valid transaction date that is not in the future.'
    ],
    'inventory financial helper' => [
        $root . '/lib/inventory_financial.php',
        'function inventory_financial_receipts('
    ],
    'layer inventory purchases' => [
        $root . '/poultry/layer_expenses.php',
        'Inventory Purchases'
    ],
    'broiler inventory purchases' => [
        $root . '/poultry/broiler_expenses.php',
        'Inventory Purchases'
    ],
    'ruminant inventory purchases' => [
        $root . '/ruminant/ruminant_expenses.php',
        'Inventory Purchases'
    ],
];

$failed = 0;
foreach ($checks as $label => [$file, $needle]) {
    $contents = is_file($file) ? file_get_contents($file) : false;
    $ok = $contents !== false && strpos($contents, $needle) !== false;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}

if ($failed > 0) {
    fwrite(STDERR, "Verification failed: {$failed} check(s).\n");
    exit(1);
}
echo "V2.2.48 Foundation Batch 1 static verification passed.\n";
