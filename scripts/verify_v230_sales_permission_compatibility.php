<?php
/**
 * V2.3 Sales delegated-permission compatibility verifier.
 * Static/read-only: no database connection and no farm data mutation.
 */

$root = dirname(__DIR__);
$sales = file_get_contents($root . '/management/sales_records.php') ?: '';
$runtime = file_get_contents($root . '/includes/permission_runtime.php') ?: '';
$failures = 0;

$check = static function (string $label, bool $ok) use (&$failures): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$check('legacy combined canRecordSales gate is removed', !str_contains($sales, '$canRecordSales'));
$check('Add Sale capability uses Sales Records — Add Sale',
    str_contains($sales, "\$canAddSales = \$privilegedSalesActions || hasPermission(getUserType(), 'sales_add');")
    && str_contains($sales, 'if (!$canAddSales)'));
$check('Record Payment capability uses Sales Receivables — Record Payment',
    str_contains($sales, "\$canRecordPayments = \$privilegedSalesActions || hasPermission(getUserType(), 'sales_payment');")
    && str_contains($sales, 'if (!$canRecordPayments)'));
$check('Receivable ledger Edit uses Sales Receivables — Edit',
    str_contains($sales, "\$canEditLedger = \$privilegedSalesActions || hasPermission(getUserType(), 'sales_receivables_edit');")
    && str_contains($sales, 'if (!$canEditLedger)'));
$check('Receivable ledger Delete uses Sales Receivables — Delete',
    str_contains($sales, "\$canDeleteLedger = \$privilegedSalesActions || hasPermission(getUserType(), 'sales_receivables_delete');")
    && str_contains($sales, 'if (!$canDeleteLedger)'));
$check('Add Sale button follows the exact Add permission capability',
    str_contains($sales, '<?php if ($canAddSales): ?>')
    && str_contains($sales, 'data-bs-target="#addSaleModal"'));
$check('Record Payment button follows the exact payment permission capability',
    str_contains($sales, '<?php if ($canRecordPayments): ?>')
    && str_contains($sales, 'data-bs-target="#addPaymentModal"'));
$check('ledger action UI separates Edit and Delete capabilities',
    str_contains($sales, '<?php if ($canEditLedger): ?>')
    && str_contains($sales, '<?php if ($canDeleteLedger): ?>'));
$check('central runtime still independently guards Add Sale',
    str_contains($runtime, "isset(\$_POST['add_sale'])")
    && str_contains($runtime, "permission_runtime_has('sales_add')"));
$check('central runtime still independently guards Record Payment',
    str_contains($runtime, "isset(\$_POST['record_payment'])")
    && str_contains($runtime, "permission_runtime_has('sales_payment')"));

if ($failures === 0) {
    echo PHP_EOL . "10 checks, 0 failure(s)." . PHP_EOL;
    echo "PASS: Sales delegated action compatibility is aligned with the V2.3 permission matrix." . PHP_EOL;
} else {
    echo PHP_EOL . "10 checks, {$failures} failure(s)." . PHP_EOL;
}

exit($failures === 0 ? 0 : 1);
