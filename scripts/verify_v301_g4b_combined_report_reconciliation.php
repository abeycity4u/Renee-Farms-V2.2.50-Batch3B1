<?php

/**
 * V3.0.1 G4B Combined Operational Report Reconciliation.
 *
 * Source-only verifier.
 * No database connection.
 * No database writes.
 */

$root =
    dirname(__DIR__);

$reportPath =
    $root
    . '/management/poultry_ruminant_report.php';

$financialPath =
    $root
    . '/includes/financial.php';

if (
    !is_file($reportPath)
    ||
    !is_file($financialPath)
) {
    echo "RESULT=FAIL\n";
    echo "FAIL=MISSING_SOURCE\n";
    echo "DATABASE_CONNECTION=NONE\n";
    echo "DATABASE_WRITE=NONE\n";
    exit(1);
}

$report =
    file_get_contents(
        $reportPath
    );

$financial =
    file_get_contents(
        $financialPath
    );

$checks = [];
$failures = [];

$check =
    static function (
        string $name,
        bool $passed
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks[$name] =
            $passed;

        if (!$passed) {
            $failures[] =
                $name;
        }
    };

$check(
    'REPORT_DECLARED_OPERATIONAL_SOURCE_REPORT',
    strpos(
        $report,
        'Operational source report.'
    ) !== false
    &&
    strpos(
        $report,
        'Recorded Spending shows cash/expense source attribution'
    ) !== false
);

$check(
    'REPORT_DISTINGUISHES_PROFITABILITY',
    strpos(
        $report,
        'not Profitability operating cost.'
    ) !== false
    &&
    strpos(
        $report,
        'Use Profitability and Shared Cost Allocation'
    ) !== false
);

$check(
    'GENERAL_SALES_NOT_MERGED_INTO_MODULES',
    strpos(
        $report,
        "farm_type IN ('poultry', 'general')"
    ) === false
    &&
    strpos(
        $report,
        "farm_type IN ('ruminant', 'general')"
    ) === false
    &&
    strpos(
        $report,
        "\$salesSummary[\$farmType] ="
    ) === false
);

$check(
    'MODULE_SALES_FILTERS_ARE_EXPLICIT',
    strpos(
        $report,
        "farm_type = 'poultry'"
    ) !== false
    &&
    strpos(
        $report,
        "farm_type = 'ruminant'"
    ) !== false
);

$check(
    'SHARED_EXPENSE_PARENT_SHOWN_ONCE',
    strpos(
        $report,
        "\$expenseSummary['shared'] +="
    ) !== false
    &&
    strpos(
        $report,
        'Farm-wide Shared Spending'
    ) !== false
);

$check(
    'SHARED_EXPENSE_NOT_DUPLICATED_TO_MODULES',
    strpos(
        $report,
        "\$expenseSummary['poultry'] += (float)\$row['total_expenses'];"
    ) === false
    &&
    strpos(
        $report,
        "\$expenseSummary['ruminant'] += (float)\$row['total_expenses'];"
    ) === false
);

$check(
    'NO_AUTOMATIC_CROSS_MODULE_SPLIT',
    strpos(
        strtolower($report),
        '50/50'
    ) === false
    &&
    strpos(
        strtolower($report),
        'split equally'
    ) === false
    &&
    strpos(
        $report,
        'no Poultry/Ruminant split is inferred.'
    ) !== false
);

$check(
    'RECORDED_SPENDING_LABELS_USED',
    strpos(
        $report,
        'Poultry Recorded Spending'
    ) !== false
    &&
    strpos(
        $report,
        'Ruminant Recorded Spending'
    ) !== false
);

$check(
    'SHARED_STOCK_HAS_SEPARATE_SUMMARY',
    strpos(
        $report,
        '$sharedStockSummary'
    ) !== false
    &&
    strpos(
        $report,
        'Shared Farm Inventory'
    ) !== false
);

$check(
    'SHARED_STOCK_NOT_DUPLICATED_TO_MODULES',
    strpos(
        $report,
        "\$stockSummary['poultry']['items'] += (int)\$row['items'];"
    ) === false
    &&
    strpos(
        $report,
        "\$stockSummary['ruminant']['items'] += (int)\$row['items'];"
    ) === false
    &&
    strpos(
        $report,
        "\$stockSummary['poultry']['stock_value'] += (float)\$row['stock_value'];"
    ) === false
    &&
    strpos(
        $report,
        "\$stockSummary['ruminant']['stock_value'] += (float)\$row['stock_value'];"
    ) === false
);

$check(
    'STOCK_QUERY_TENANT_PARAMETERIZED',
    strpos(
        $report,
        'WHERE farm_id=?'
    ) !== false
    &&
    strpos(
        $report,
        '$stockStmt->execute(['
    ) !== false
);

$check(
    'INVENTORY_PRESENTATION_EXPLAINS_NON_ADDITIVE_CONTEXT',
    strpos(
        $report,
        'Inventory columns are availability context, not additive production totals.'
    ) !== false
);

$check(
    'REPORT_REMAINS_OPERATIONAL_NOT_SECOND_PROFIT_ENGINE',
    strpos(
        $report,
        'getProfitabilitySummary('
    ) === false
    &&
    strpos(
        $report,
        'farm_intelligence_summary('
    ) === false
);

$check(
    'CANONICAL_FEED_ECONOMICS_UNCHANGED',
    strpos(
        $financial,
        'Feed purchases are cash-flow records, not an additional operating cost'
    ) !== false
);

$check(
    'REPORT_HAS_NO_MALFORMED_PHP_OPEN_ECHO',
    strpos(
        $report,
        '<?phpecho'
    ) === false
);

$check(
    'REPORT_HAS_NO_MALFORMED_ECHO_NUMBER_FORMAT',
    strpos(
        $report,
        '<?php echonumber'
    ) === false
);

foreach (
    $checks
    as $name => $passed
) {
    echo $name
        . '='
        . (
            $passed
                ? 'PASS'
                : 'FAIL'
        )
        . "\n";
}

echo 'CHECK_COUNT='
    . count($checks)
    . "\n";

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

if ($failures) {
    echo 'FAILED='
        . implode(
            ',',
            $failures
        )
        . "\n";

    echo "RESULT=FAIL\n";
    exit(1);
}

echo "RESULT=PASS\n";
