<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    (string)file_get_contents(
        $root . '/includes/pdf/PdfReportService.php'
    );

$poultry =
    (string)file_get_contents(
        $root . '/poultry/expenses.php'
    );

$checks = [];
$failed = [];

$check = static function (
    string $name,
    bool $ok
) use (
    &$checks,
    &$failed
): void {
    $checks[$name] = $ok;

    if (!$ok) {
        $failed[] = $name;
    }

    echo $name
        . '='
        . ($ok ? 'PASS' : 'FAIL')
        . PHP_EOL;
};


$check(
    'CENTRAL_PDF_SERVICE_OWNS_FIX',
    strpos(
        $service,
        '.card.h-100'
    ) !== false
);


$check(
    'PDF_FULL_HEIGHT_IS_NEUTRALIZED',
    strpos(
        $service,
        'height: auto !important;'
    ) !== false
    &&
    strpos(
        $service,
        'min-height: 0 !important;'
    ) !== false
);


$check(
    'POULTRY_REPORT_REALLY_USES_H100_CARDS',
    strpos(
        $poultry,
        'card h-100'
    ) !== false
);


$check(
    'FIX_IS_NOT_POULTRY_PAGE_SPECIFIC',
    strpos(
        $poultry,
        '.card.h-100'
    ) === false
);


$check(
    'CENTRAL_PDF_RENDERER_PRESERVED',
    strpos(
        $service,
        'final class PdfReportService'
    ) !== false
    &&
    strpos(
        $service,
        'pdf_report_finish('
    ) !== false
);


echo 'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo 'FAILED_COUNT='
    . count($failed)
    . PHP_EOL;

echo 'RESULT='
    . (
        $failed === []
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failed === []
        ? 0
        : 1
);
