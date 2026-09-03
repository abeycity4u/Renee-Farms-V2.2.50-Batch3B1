<?php
$root = dirname(__DIR__);
$file = $root . '/inventory.php';
$src = file_get_contents($file);
$checks = [
    'Update Stock unit cost wrapper exists' => 'id="unitCostWrapper"',
    'Update Stock unit cost label exists' => 'Unit Cost (₦) for Received Stock',
    'Update Stock unit cost input exists' => 'name="unit_cost"',
    'Receipt backend reads unit cost' => "\$_POST['unit_cost']",
    'Receipt backend rejects missing unit cost' => 'Enter the actual unit cost for received stock',
    'Canonical movement receives incoming unit cost' => "\$type === 'received' ? \$incomingUnitCost : null",
    'JS hides cost for used stock' => "costWrapper.style.display = 'none'",
    'JS shows cost for received stock' => "costWrapper.style.display = 'block'",
    'JS requires cost for received stock' => 'costInput.required = true',
];
$failed = false;
foreach ($checks as $label => $needle) {
    if (strpos($src, $needle) === false) {
        fwrite(STDERR, "FAIL: $label\n");
        $failed = true;
    } else {
        echo "PASS: $label\n";
    }
}
if ($failed) exit(1);
echo "PASS: V2.2.48 Update Stock Unit Cost regression verifier\n";
