<?php

declare(strict_types=1);

/**
 * V3.0.1 — append-only stock receipt cost-adjustment verifier.
 * Source-only. No database connection and no business mutation.
 */

$root = dirname(__DIR__);

$paths = [
    'migration' =>
        $root . '/migrations/073_stock_receipt_cost_adjustments.sql',

    'helper' =>
        $root . '/lib/stock_receipt_cost_adjustments.php',

    'costing' =>
        $root . '/lib/stock_costing.php',

    'service' =>
        $root . '/lib/stock_service.php',

    'history_api' =>
        $root . '/api/get_stock_history.php',
];

$sources = [];

foreach ($paths as $key => $path) {
    $sources[$key] =
        is_file($path)
        && is_readable($path)
            ? (string)file_get_contents($path)
            : '';
}

$checks = 0;
$failures = 0;

$check =
    static function (
        string $label,
        bool $ok
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo ($ok ? 'PASS: ' : 'FAIL: ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };

$check(
    'Required receipt-cost adjustment sources are readable',
    !in_array('', $sources, true)
);

$check(
    'Migration creates append-only receipt-cost adjustment table',
    str_contains(
        $sources['migration'],
        'CREATE TABLE IF NOT EXISTS stock_receipt_cost_adjustments'
    )
);

$check(
    'Adjustment row retains farm and original stock transaction provenance',
    str_contains(
        $sources['migration'],
        'stock_transaction_id INT NOT NULL'
    )
    && str_contains(
        $sources['migration'],
        'FOREIGN KEY (stock_transaction_id)'
    )
    && str_contains(
        $sources['migration'],
        'REFERENCES stock_transactions(id)'
    )
);

$check(
    'Adjustment source identity is idempotent per farm',
    str_contains(
        $sources['migration'],
        'uniq_stock_receipt_cost_adjustment_source'
    )
);

$check(
    'Migration does not rewrite posted stock ledger',
    !preg_match(
        '/UPDATE\s+stock_transactions/i',
        $sources['migration']
    )
    && !preg_match(
        '/DELETE\s+FROM\s+stock_transactions/i',
        $sources['migration']
    )
);

$check(
    'Migration does not rewrite slaughter output or population data',
    !preg_match(
        '/UPDATE\s+ruminant_slaughter_outputs/i',
        $sources['migration']
    )
    && !preg_match(
        '/UPDATE\s+production_population_movements/i',
        $sources['migration']
    )
);

$check(
    'Central helper owns adjustment SQL projection',
    str_contains(
        $sources['helper'],
        'function stock_receipt_cost_adjustment_total_sql('
    )
    && str_contains(
        $sources['helper'],
        'function stock_receipt_effective_total_cost_sql('
    )
);

$check(
    'Weighted cost replay includes append-only receipt adjustment',
    str_contains(
        $sources['costing'],
        'stock_receipt_cost_adjustment_total_sql('
    )
    && str_contains(
        $sources['costing'],
        "receipt_cost_adjustment_total"
    )
);

$check(
    'Canonical writer exposes receipt-cost adjustment service',
    str_contains(
        $sources['service'],
        'function stock_post_receipt_cost_adjustment('
    )
);

$check(
    'Adjustment service accepts only active received stock movement',
    str_contains(
        $sources['service'],
        "transaction_type'] !== 'received'"
    )
    && str_contains(
        $sources['service'],
        "is_reversed']"
    )
    && str_contains(
        $sources['service'],
        "reversal_of_id']"
    )
);

$check(
    'Adjustment service requires durable source identity',
    str_contains(
        $sources['service'],
        'sourceType'
    )
    && str_contains(
        $sources['service'],
        'sourceId'
    )
    && str_contains(
        $sources['service'],
        'stock_receipt_cost_adjustments'
    )
);

$check(
    'Adjustment service prevents negative effective receipt value',
    str_contains(
        $sources['service'],
        'would make the effective receipt value negative'
    )
);

$check(
    'Adjustment service recalculates current unit-cost cache centrally',
    str_contains(
        $sources['service'],
        'stock_recalculate_current_unit_cost('
    )
);

$check(
    'Stock History API exposes raw adjustment and effective monetary total',
    str_contains(
        $sources['history_api'],
        'receipt_cost_adjustment_total'
    )
    && str_contains(
        $sources['history_api'],
        'effective_total_cost'
    )
);

$check(
    'Migration records checkpoint 073',
    str_contains(
        $sources['migration'],
        '073_stock_receipt_cost_adjustments.sql'
    )
);

echo PHP_EOL;
echo 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . (
        $failures === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
