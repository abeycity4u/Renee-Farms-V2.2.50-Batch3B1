<?php
declare(strict_types=1);

/**
 * C137 monthly reporting numerical regression.
 *
 * Pure-data fixture:
 * - no DB connection
 * - no writes
 * - canonical profitability outputs are simulated
 * - call count is observational, not an assertion
 */

final class C137FixturePDO extends PDO
{
    public function __construct()
    {
        // Intentionally no database connection.
    }
}

$GLOBALS['c137_calls'] = [];

function getProfitabilitySummary(
    PDO $pdo,
    int $farmId,
    string $startDate,
    string $endDate,
    ?string $farmType = null,
    ?int $cycleId = null,
    ?string $productionType = null
): array {
    $GLOBALS['c137_calls'][] = [
        'farm_id' => $farmId,
        'start' => $startDate,
        'end' => $endDate,
        'farm_type' => $farmType,
    ];

    if (
        $startDate === '2024-01-01'
        && $endDate === '2024-12-31'
    ) {
        return [
            'revenue' => 200.0,
            'cost_of_goods_sold' => 28.0,
            'general_sale_inventory_cogs' => 10.0,
            'slaughter_output_cogs' => 15.0,
            'feed_consumption_cost' => 5.0,
            'non_feed_expenses' => 5.0,
            'total_operating_cost' => 10.0,
            'total_recognized_cost' => 38.0,
            'profit' => 162.0,
            'expense_breakdown' => [
                'feeds' => 5.0,
                'salary' => 3.0,
            ],
            'inventory_operating_consumption_breakdown' => [
                'supplement' => 2.0,
            ],
        ];
    }

    $month = (int)substr($startDate, 5, 2);

    return [
        'revenue' => 100.0 * $month,
        'cost_of_goods_sold' => 10.0 * $month,
        'feed_consumption_cost' => 5.0 * $month,
        'non_feed_expenses' => 7.0 * $month,
        'total_operating_cost' => 12.0 * $month,
        'total_recognized_cost' => 22.0 * $month,
        'profit' => 78.0 * $month,
        'expense_breakdown' => [],
        'inventory_operating_consumption_breakdown' => [],
    ];
}

require_once dirname(__DIR__) . '/lib/farm_intelligence.php';

function c137_assert(string $name, bool $condition): void
{
    echo $name . '=' . ($condition ? 'PASS' : 'FAIL') . PHP_EOL;

    if (!$condition) {
        throw new RuntimeException($name);
    }
}

function c137_equal(float $a, float $b): bool
{
    return abs($a - $b) < 0.000001;
}

$pdo = new C137FixturePDO();

$months = farm_intelligence_monthly_series(
    $pdo,
    52,
    2024,
    'all'
);

c137_assert('MONTH_COUNT', count($months) === 12);

$monthlyValid = true;

foreach ($months as $index => $row) {
    $m = $index + 1;

    $monthlyValid = $monthlyValid
        && $row['month'] === sprintf('2024-%02d', $m)
        && $row['farm_type'] === 'all'
        && c137_equal((float)$row['total_sales'], 100 * $m)
        && c137_equal((float)$row['cost_of_goods_sold'], 10 * $m)
        && c137_equal((float)$row['gross_profit'], 90 * $m)
        && c137_equal((float)$row['feed_consumed'], 5 * $m)
        && c137_equal((float)$row['other_operating_cost'], 7 * $m)
        && c137_equal((float)$row['operating_expenses'], 12 * $m)
        && c137_equal((float)$row['total_expenses'], 22 * $m)
        && c137_equal((float)$row['net_profit'], 78 * $m)
        && c137_equal((float)$row['margin_percent'], 78.0);
}

c137_assert('MONTHLY_NUMERICAL_CONTRACT', $monthlyValid);

$breakdown = farm_intelligence_expense_breakdown(
    $pdo,
    52,
    '2024-01-01',
    '2024-12-31',
    'all'
);

$actual = array_column(
    $breakdown,
    'total_amount',
    'category'
);

$expected = [
    'Cost of Goods Sold · General Inventory' => 10.0,
    'Cost of Goods Sold · Slaughter Output' => 15.0,
    'Cost of Goods Sold' => 3.0,
    'Feed Consumed' => 5.0,
    'Salary' => 3.0,
    'Inventory Used · supplement' => 2.0,
];

$breakdownValid = count($actual) === count($expected);

foreach ($expected as $category => $amount) {
    $breakdownValid = $breakdownValid
        && array_key_exists($category, $actual)
        && c137_equal((float)$actual[$category], $amount);
}

c137_assert(
    'EXPENSE_BREAKDOWN_NUMERICAL_CONTRACT',
    $breakdownValid
);

c137_assert(
    'FEED_PURCHASE_NOT_DOUBLE_COUNTED',
    !array_key_exists('Feeds', $actual)
    && c137_equal((float)$actual['Feed Consumed'], 5.0)
);

c137_assert(
    'COGS_COMPONENT_RECONCILIATION',
    c137_equal(
        (float)$actual['Cost of Goods Sold · General Inventory']
        + (float)$actual['Cost of Goods Sold · Slaughter Output']
        + (float)$actual['Cost of Goods Sold'],
        28.0
    )
);

$scoped = count($GLOBALS['c137_calls']) > 0;
$leapBoundary = false;

foreach ($GLOBALS['c137_calls'] as $call) {
    $scoped = $scoped
        && $call['farm_id'] === 52
        && $call['farm_type'] === 'all'
        && $call['start'] >= '2024-01-01'
        && $call['end'] <= '2024-12-31'
        && $call['start'] <= $call['end'];

    if (
        $call['start'] === '2024-02-01'
        && $call['end'] === '2024-02-29'
    ) {
        $leapBoundary = true;
    }
}

c137_assert('FARM_AND_DATE_SCOPE', $scoped);
c137_assert('FEBRUARY_LEAP_YEAR_BOUNDARY', $leapBoundary);

echo 'OBSERVED_CANONICAL_CALLS='
    . count($GLOBALS['c137_calls'])
    . PHP_EOL;

echo "RESULT=PASS\n";
