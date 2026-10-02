<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$service = file_get_contents($root . '/includes/pdf/PdfReportService.php');
$expenseReport = file_get_contents($root . '/management/expense_report_pdf.php');
$failures = 0;

$check = static function (bool $ok, string $label) use (&$failures): void {
    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }
    echo "FAIL: {$label}\n";
    $failures++;
};

$check(
    str_contains($service, "set('isRemoteEnabled', false)"),
    'PDF service keeps remote resources disabled'
);

$check(
    str_contains($service, "set('chroot', \$this->root)"),
    'PDF service keeps filesystem access chrooted to application root'
);

$check(
    str_contains($service, "preg_replace('/[^A-Za-z0-9._-]+/', '-', \$filename)"),
    'PDF download filename remains sanitized'
);

$check(
    !str_contains($service, "echo 'PDF generation unavailable: ' . \$e->getMessage()"),
    'PDF failure no longer exposes raw exception messages'
);

$check(
    str_contains($service, 'PDF generation is temporarily unavailable. Please try again later.'),
    'PDF failure uses generic user-facing message'
);

$check(
    str_contains($service, "error_log(")
    && str_contains($service, "get_class(\$e)"),
    'PDF failure keeps non-sensitive operator signal'
);

$check(
    str_contains($expenseReport, 'requireLogin()')
    && str_contains($expenseReport, 'requireCurrentFarmId()')
    && str_contains($expenseReport, 'WHERE e.farm_id=?'),
    'reviewed expense PDF remains authenticated and tenant scoped'
);

$check(
    substr_count($expenseReport, 'htmlspecialchars(') >= 5,
    'reviewed expense PDF continues escaping dynamic text values'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "PDF_SECURITY_CONTRACT=PASS\n";
    exit(0);
}

echo "PDF_SECURITY_CONTRACT=FAIL\n";
exit(1);
