<?php

declare(strict_types=1);

/**
 * V3.0.1 — exact stock receipt cost / current valuation precision verifier.
 * Source-only: no DB connection and no business mutation.
 */

$root = dirname(__DIR__);

$paths = [
    'migration' =>
        $root . '/migrations/072_stock_receipt_cost_precision.sql',

    'service' =>
        $root . '/lib/stock_service.php',

    'costing' =>
        $root . '/lib/stock_costing.php',

    'slaughter' =>
        $root . '/lib/ruminant_slaughter_processing.php',
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
    'Required stock-cost precision sources are readable',
    !in_array('', $sources, true)
);

$check(
    'Migration widens current Inventory unit cost to four decimals',
    str_contains(
        $sources['migration'],
        'DECIMAL(14,4)'
    )
    && str_contains(
        $sources['migration'],
        'MODIFY COLUMN unit_cost'
    )
);

$check(
    'Migration performs no historical stock transaction rewrite',
    !preg_match(
        '/UPDATE\s+stock_transactions/i',
        $sources['migration']
    )
);

$check(
    'Canonical stock writer accepts optional exact receipt total',
    str_contains(
        $sources['service'],
        '?float $incomingTotalCost = null'
    )
);

$check(
    'Exact total is restricted to receipt movements',
    str_contains(
        $sources['service'],
        "if (\$type !== 'received')"
    )
    && str_contains(
        $sources['service'],
        'An exact incoming total cost is valid only for received stock.'
    )
);

$check(
    'Current weighted valuation uses exact receipt value when supplied',
    str_contains(
        $sources['service'],
        '$incomingValue'
    )
    && str_contains(
        $sources['service'],
        '$incomingTotalCost !== null'
    )
);

$check(
    'Ledger total preserves exact sourced totals and snapshot-cost fallback',
    str_contains(
        preg_replace(
            '/\\s+/',
            ' ',
            $sources['service']
        ) ?? '',
        "if ( \\$type === 'received' && \\$incomingTotalCost !== null ) { \\$totalCost = \\$incomingTotalCost; } elseif ( \\$type === 'used' && \\$outgoingTotalCost !== null ) { \\$totalCost = \\$outgoingTotalCost; } else { \\$totalCost = round( \\$quantity * \\$snapshotUnitCost, 2 ); }"
    )
);

$check(
    'Weighted cost replay treats posted receipt total as monetary authority',
    preg_match(
        '/transaction_type[^\n]*received.*?total_cost.*?\$value\s*\+=/s',
        $sources['costing']
    ) === 1
);

$check(
    'Slaughter receipt passes exact allocated output cost to stock writer',
    preg_match(
        '/stock_apply_movement\(.*?\$unitCostSnapshot,\s*\(string\)\$batch\[\'production_type\'\],\s*\$allocatedCost\s*\)/s',
        $sources['slaughter']
    ) === 1
);

$check(
    'Migration records checkpoint 072',
    str_contains(
        $sources['migration'],
        '072_stock_receipt_cost_precision.sql'
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
