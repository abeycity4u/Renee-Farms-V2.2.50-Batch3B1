<?php

$root = dirname(__DIR__);
$servicePath = $root . '/includes/pdf/PdfReportService.php';
$configPath = $root . '/config.php';

$checks = 0;
$failures = 0;

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

$service = is_file($servicePath)
    ? (string) file_get_contents($servicePath)
    : '';

$config = is_file($configPath)
    ? (string) file_get_contents($configPath)
    : '';

$check(
    $service !== '',
    'central PDF service is available'
);

$check(
    str_contains($config, 'function farmBrandName(): string'),
    'canonical tenant farm-name resolver is available'
);

$check(
    str_contains($service, 'function pdf_report_tenant_brand_name(): string'),
    'PDF service has one canonical tenant-brand resolver'
);

$check(
    str_contains($service, "function_exists('farmBrandName')"),
    'PDF tenant branding delegates to canonical farmBrandName()'
);

$check(
    str_contains($service, '$farmName = pdf_report_tenant_brand_name();'),
    'every rendered PDF resolves the current tenant brand centrally'
);

$check(
    str_contains($service, "\$farmName . ' - ' . \$title"),
    'PDF document title combines tenant farm name with report title'
);

$check(
    str_contains($service, "\$fontBold = \$fontMetrics->getFont('DejaVu Sans', 'bold');"),
    'PDF service prepares a bold tenant header font'
);

$check(
    preg_match(
        '/page_text\\s*\\(\\s*28\\s*,\\s*15\\s*,\\s*\\$farmName\\s*,\\s*\\$fontBold/s',
        $service
    ) === 1,
    'tenant farm name is rendered at top-left on every PDF page'
);

$check(
    str_contains($service, "\$farmName . ' • ' . \$title"),
    'tenant farm name is rendered in the bottom-left report footer'
);

$check(
    str_contains($service, "'Page {PAGE_NUM} of {PAGE_COUNT}'"),
    'page numbering remains in the PDF footer'
);

$check(
    !str_contains($service, "'Renee Farms • ' . \$title"),
    'central PDF footer no longer hard-codes Renee Farms as tenant brand'
);

$check(
    !str_contains($service, "'Renee Farms Report'"),
    'generic PDF report defaults no longer hard-code Renee Farms'
);

$check(
    !str_contains($service, 'renee-farms-report.pdf'),
    'generic PDF fallback filename is tenant-neutral'
);

$pdfCallers = [
    'billing/receipt.php',
    'management/debt_history_pdf.php',
    'management/expense_report_pdf.php',
    'management/expenses.php',
    'management/poultry_ruminant_report.php',
    'management/reports.php',
    'management/sales_records.php',
    'management/sales_report_pdf.php',
    'poultry/broiler_expenses.php',
    'poultry/layer_expenses.php',
    'ruminant/ruminant_expenses.php',
];

foreach ($pdfCallers as $relative) {
    $path = $root . '/' . $relative;
    $content = is_file($path)
        ? (string) file_get_contents($path)
        : '';

    $usesService = (
        str_contains($content, 'PdfReportService.php')
        && (
            str_contains($content, 'pdf_report_finish(')
            || str_contains($content, '->streamHtml(')
        )
    );

    $check(
        $usesService,
        $relative . ' remains routed through centralized PDF branding'
    );
}

echo "\n{$checks} checks, {$failures} failure(s).\n";

if ($failures > 0) {
    exit(1);
}

echo "PASS: V2.3 tenant PDF branding is centralized across all current official PDF routes.\n";
