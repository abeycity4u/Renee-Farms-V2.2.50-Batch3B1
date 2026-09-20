<?php

$root = dirname(__DIR__);

$paths = [
    'sales_page' => $root . '/management/sales_records.php',
    'main_js' => $root . '/assets/js/main.js',
    'sales_js' => $root . '/assets/js/management-sales-records.js',
    'behaviors_js' => $root . '/assets/js/app-behaviors.js',
    'delete_api' => $root . '/api/delete_sale.php',
];

foreach ($paths as $name => $path) {
    if (!is_file($path)) {
        echo "CHECK_COUNT=0\n";
        echo "FAILED=SOURCE_FILE_MISSING:$name\n";
        echo "DATABASE_CONNECTION=NONE\n";
        echo "DATABASE_WRITES=NONE\n";
        echo "RESULT=FAIL\n";
        exit(1);
    }
}

$salesPage = file_get_contents($paths['sales_page']);
$mainJs = file_get_contents($paths['main_js']);
$salesJs = file_get_contents($paths['sales_js']);
$behaviorsJs = file_get_contents($paths['behaviors_js']);
$deleteApi = file_get_contents($paths['delete_api']);

$checks = [];

$check = static function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
};

$check(
    'SALES_DELETE_BUTTON_USES_DATA_MARKER',
    strpos($salesPage, 'data-sale-delete-id=') !== false
);

$check(
    'DELEGATED_BROWSER_TRIGGER_PRESENT',
    strpos(
        $behaviorsJs,
        "event.target.closest('[data-sale-delete-id]')"
    ) !== false
    &&
    strpos(
        $behaviorsJs,
        'window.deleteSale(saleId);'
    ) !== false
);

$check(
    'SERVER_CONFIGURES_CANONICAL_DELETE_URL',
    strpos(
        $salesPage,
        'data-delete-sale-url='
    ) !== false
    &&
    strpos(
        $salesPage,
        "BASE_URL . '/api/delete_sale.php'"
    ) !== false
);

$check(
    'GLOBAL_DELETE_READS_SERVER_CONFIG',
    strpos(
        $mainJs,
        "document.getElementById('managementSalesRecordsConfig')"
    ) !== false
    &&
    strpos(
        $mainJs,
        'salesConfigElement.dataset.deleteSaleUrl'
    ) !== false
);

$check(
    'GLOBAL_DELETE_FAILS_CLOSED_WITHOUT_CONFIG',
    strpos(
        $mainJs,
        'Sale delete endpoint is not configured for this page.'
    ) !== false
);

$check(
    'GLOBAL_DELETE_CALLS_CONFIGURED_URL',
    strpos(
        $mainJs,
        'apiFetch(deleteSaleUrl,'
    ) !== false
);

$check(
    'GLOBAL_DELETE_NO_LONGER_USES_RELATIVE_ENDPOINT',
    strpos(
        $mainJs,
        "apiFetch('api/delete_sale.php'"
    ) === false
);

$check(
    'PAGE_LOCAL_DUPLICATE_DELETE_HANDLER_REMOVED',
    strpos(
        $salesJs,
        'function deleteSale('
    ) === false
);

$check(
    'CANONICAL_DELETE_API_IS_POST_CSRF_PROTECTED',
    strpos(
        $deleteApi,
        "require_http_method('POST')"
    ) !== false
    &&
    strpos(
        $deleteApi,
        'require_csrf_token()'
    ) !== false
);

$guardPos = strpos(
    $deleteApi,
    'sales_assert_manual_revenue_delete_allowed('
);

$ledgerDeletePos = strpos(
    $deleteApi,
    'DELETE FROM customer_ledger_entries'
);

$parentDeletePos = strpos(
    $deleteApi,
    'DELETE FROM sales_records'
);

$check(
    'MANUAL_REVENUE_GUARD_PRECEDES_DESTRUCTIVE_DELETE',
    $guardPos !== false
    &&
    $ledgerDeletePos !== false
    &&
    $parentDeletePos !== false
    &&
    $guardPos < $ledgerDeletePos
    &&
    $guardPos < $parentDeletePos
);

$check(
    'MANUAL_REVENUE_DELETE_CONFLICT_IS_409',
    strpos(
        $deleteApi,
        'catch(SaleRevenueAllocationLifecycleException $e)'
    ) !== false
    &&
    strpos(
        $deleteApi,
        "send_json(['success'=>false,'error'=>\$e->getMessage()],409)"
    ) !== false
);

$deleteSaleStart =
    strpos(
        $mainJs,
        'async function deleteSale(saleId) {'
    );

$deleteExpenseStart =
    strpos(
        $mainJs,
        "/**\n * Delete expense",
        $deleteSaleStart === false
            ? 0
            : $deleteSaleStart
    );

$deleteSaleBlock =
    (
        $deleteSaleStart !== false
        &&
        $deleteExpenseStart !== false
        &&
        $deleteExpenseStart > $deleteSaleStart
    )
        ? substr(
            $mainJs,
            $deleteSaleStart,
            $deleteExpenseStart - $deleteSaleStart
        )
        : '';

$check(
    'GLOBAL_DELETE_CLASSIFIES_TRANSPORT_ERRORS_ONLY',
    strpos(
        $deleteSaleBlock,
        'error instanceof TypeError'
    ) !== false
    &&
    strpos(
        $deleteSaleBlock,
        "'Network error: ' + error.message"
    ) !== false
    &&
    strpos(
        $deleteSaleBlock,
        "showAlert('danger', message);"
    ) !== false
);

$check(
    'GLOBAL_DELETE_API_CONFLICT_NOT_UNCONDITIONALLY_NETWORK_LABELED',
    strpos(
        $deleteSaleBlock,
        "showAlert('danger', 'Network error: ' + error.message);"
    ) === false
);

$failed = [];

foreach ($checks as $name => $passed) {
    echo $name . '=' . ($passed ? 'PASS' : 'FAIL') . PHP_EOL;

    if (!$passed) {
        $failed[] = $name;
    }
}

echo 'CHECK_COUNT=' . count($checks) . PHP_EOL;
echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITES=NONE\n";

if ($failed) {
    echo 'FAILED=' . implode(',', $failed) . PHP_EOL;
    echo "RESULT=FAIL\n";
    exit(1);
}

echo "RESULT=PASS\n";
exit(0);
