<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$hub =
    (string)file_get_contents(
        $root
        . '/poultry/expenses.php'
    );

$pdfService =
    (string)file_get_contents(
        $root
        . '/includes/pdf/PdfReportService.php'
    );

$layer =
    (string)file_get_contents(
        $root
        . '/poultry/layer_expenses.php'
    );

$broiler =
    (string)file_get_contents(
        $root
        . '/poultry/broiler_expenses.php'
    );

$checks = 0;
$failed = 0;

function hub_pdf_check(
    string $name,
    bool $ok
): void {
    global $checks, $failed;

    $checks++;

    echo
        $name
        . '='
        . (
            $ok
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$ok) {
        $failed++;
    }
}


hub_pdf_check(
    'HUB_LOADS_ONE_CENTRAL_PDF_SERVICE',
    preg_match_all(
        '/PdfReportService\.php/',
        $hub
    ) === 1
);


hub_pdf_check(
    'HUB_HAS_ONE_PDF_REQUEST_AUTHORITY',
    preg_match_all(
        '/pdf_report_is_requested\s*\(/',
        $hub
    ) === 1
);


hub_pdf_check(
    'HUB_HAS_ONE_PDF_BUFFER_BEGIN',
    preg_match_all(
        '/pdf_report_begin\s*\(/',
        $hub
    ) === 1
);


hub_pdf_check(
    'HUB_HAS_ONE_PDF_FINISH',
    preg_match_all(
        '/pdf_report_finish\s*\(/',
        $hub
    ) === 1
);


hub_pdf_check(
    'HUB_REUSES_SHARED_PDF_URL_HELPER',
    preg_match_all(
        '/pdf_report_current_url\s*\(/',
        $hub
    ) === 1
);


hub_pdf_check(
    'HUB_HAS_ONE_COMMON_PDF_BUTTON',
    preg_match_all(
        '/bi-file-earmark-pdf/',
        $hub
    ) === 1
    &&
    strpos(
        $hub,
        'PDF Report'
    ) !== false
);


hub_pdf_check(
    'PDF_EXPORT_USES_ACTIVE_TAB',
    strpos(
        $hub,
        "\$activeTab"
    ) !== false
    &&
    strpos(
        $hub,
        "'poultry-expenses-'"
    ) !== false
);


hub_pdf_check(
    'PDF_TITLE_USES_ACTIVE_WORKSPACE_VIEW',
    strpos(
        $hub,
        '$pdfViewLabel'
    ) !== false
    &&
    strpos(
        $hub,
        '$pdfReportTitle'
    ) !== false
);


hub_pdf_check(
    'WORKSPACE_TABS_ARE_EXCLUDED_FROM_PDF',
    preg_match(
        '/<ul[^>]+class=["\'][^"\']*\bnav-tabs\b[^"\']*\bno-print\b[^"\']*["\']/',
        $hub
    ) === 1
);


hub_pdf_check(
    'CENTRAL_PDF_SERVICE_OWNS_TENANT_BRANDING',
    strpos(
        $pdfService,
        'pdf_report_tenant_brand_name'
    ) !== false
    &&
    strpos(
        $pdfService,
        'farmBrandName()'
    ) !== false
);


hub_pdf_check(
    'CENTRAL_PDF_SERVICE_OWNS_RENDERER',
    strpos(
        $pdfService,
        'final class PdfReportService'
    ) !== false
    &&
    strpos(
        $pdfService,
        'streamHtml('
    ) !== false
);


hub_pdf_check(
    'HUB_RETAINS_SINGLE_ADD_AUTHORITY',
    preg_match_all(
        '/poultry_expense_entry_create\s*\(/',
        $hub
    ) === 1
);


hub_pdf_check(
    'HUB_OWNS_NO_FINANCIAL_SQL',
    preg_match(
        '/FROM\s+(?:farm_expenses|stock_transactions)\b/i',
        $hub
    ) !== 1
);


hub_pdf_check(
    'LEGACY_LAYER_PDF_UNTOUCHED_IN_E4',
    strpos(
        $layer,
        'pdf_report_finish('
    ) !== false
);


hub_pdf_check(
    'LEGACY_BROILER_PDF_UNTOUCHED_IN_E4',
    strpos(
        $broiler,
        'pdf_report_finish('
    ) !== false
);


echo
    'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . $failed
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
