<?php

$root = dirname(__DIR__);

$checks = 0;
$failures = 0;

$check = static function (
    bool $condition,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

$helperPath =
    $root . '/includes/pdf/FeedTransactionReport.php';

$helper = is_file($helperPath)
    ? (string) file_get_contents($helperPath)
    : '';

$servicePath =
    $root . '/includes/pdf/PdfReportService.php';

$service = is_file($servicePath)
    ? (string) file_get_contents($servicePath)
    : '';

$check(
    $helper !== '',
    'shared Feed transaction PDF renderer exists'
);

$check(
    str_contains(
        $helper,
        'function stream_feed_transaction_report_pdf('
    ),
    'shared Feed PDF renderer exposes one canonical function'
);

$check(
    str_contains(
        $helper,
        '$ledgerView === \'audit\''
    ),
    'Feed PDF renderer distinguishes audit and operational view'
);

$check(
    str_contains(
        $helper,
        'Full Audit'
    )
    && str_contains(
        $helper,
        'Operational View'
    ),
    'Feed PDF identifies the selected ledger view'
);

$check(
    str_contains(
        $helper,
        'manual_feed_transaction_origin_label('
    ),
    'Feed PDF preserves transaction origin'
);

$check(
    str_contains(
        $helper,
        'transaction_recorded_by_label('
    ),
    'Feed PDF preserves Recorded By attribution'
);

$check(
    str_contains(
        $helper,
        'display_previous_stock'
    )
    && str_contains(
        $helper,
        'display_new_stock'
    ),
    'Feed PDF preserves stock movement history'
);

$check(
    str_contains(
        $helper,
        'new PdfReportService()'
    ),
    'Feed PDF uses centralized tenant-aware PDF service'
);

$check(
    str_contains(
        $service,
        'pdf_report_tenant_brand_name'
    ),
    'central PDF tenant branding remains active'
);

$feedPages = [
    'poultry/layer_feeds.php',
    'poultry/broiler_feeds.php',
    'ruminant/ruminant_feeds_record.php',
];

foreach ($feedPages as $relative) {
    $path = $root . '/' . $relative;

    $content = is_file($path)
        ? (string) file_get_contents($path)
        : '';

    $check(
        str_contains(
            $content,
            'FeedTransactionReport.php'
        ),
        $relative . ' uses shared Feed PDF renderer'
    );

    $check(
        str_contains(
            $content,
            'stream_feed_transaction_report_pdf('
        ),
        $relative . ' streams selected transaction view to PDF'
    );

    $check(
        str_contains(
            $content,
            '$displayTransactions'
        )
        && str_contains(
            $content,
            '$ledgerView'
        ),
        $relative . ' PDF uses current operational/audit collection'
    );

    $check(
        str_contains(
            $content,
            '$feedPdfUrl = pdf_report_current_url();'
        )
        && str_contains(
            $content,
            'PDF Report'
        ),
        $relative . ' exposes PDF Report button preserving query state'
    );
}

$expensePages = [
    'poultry/layer_expenses.php',
    'poultry/broiler_expenses.php',
    'ruminant/ruminant_expenses.php',
];

foreach ($expensePages as $relative) {
    $path = $root . '/' . $relative;

    $content = is_file($path)
        ? (string) file_get_contents($path)
        : '';

    $check(
        str_contains(
            $content,
            'class="btn btn-light"'
        )
        && str_contains(
            $content,
            'PDF Report'
        ),
        $relative . ' uses high-contrast daylight PDF button'
    );

    $check(
        !str_contains(
            $content,
            'class="btn btn-outline-primary" href="<?php echo htmlspecialchars($pdfReportUrl); ?>"'
        ),
        $relative . ' no longer uses faded outline PDF button'
    );
}

echo "\n{$checks} checks, {$failures} failure(s).\n";

if ($failures > 0) {
    exit(1);
}

echo "PASS: V2.3 Feed PDF history and Expense PDF daylight button contracts are centralized and intact.\n";
