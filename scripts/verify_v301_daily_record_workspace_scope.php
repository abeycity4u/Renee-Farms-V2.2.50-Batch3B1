<?php

declare(strict_types=1);

/**
 * V3.0.1 — Daily Record workspace scope verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$paths = [
    'helper' => $root . '/lib/daily_record_workspace_scope.php',
    'layer' => $root . '/poultry/layers_daily_record.php',
    'broiler' => $root . '/poultry/broiler_daily_record.php',
    'ruminant' => $root . '/ruminant/ruminant_daily_record.php',
];

$sources = [];

foreach ($paths as $key => $path) {
    $sources[$key] = is_file($path)
        ? (string)file_get_contents($path)
        : '';
}

$checks = 0;
$failures = 0;

$check = static function (
    string $label,
    bool $ok
) use (&$checks, &$failures): void {
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    'Daily Record workspace scope sources are readable',
    !in_array('', $sources, true)
);

if ($sources['helper'] !== '') {
    require_once $paths['helper'];
}

$allScope = daily_record_workspace_scope(true, 0);
$cycleScope = daily_record_workspace_scope(true, 55);
$legacyScope = daily_record_workspace_scope(false, 0);

$check(
    'Shared scope labels All mode as active population plus legacy records',
    ($allScope['selector_all_label'] ?? '')
        === 'All Active Cycles / Legacy Records'
    && ($allScope['current_stock_label'] ?? '')
        === 'All Active Cycles'
    && ($allScope['activity_label'] ?? '')
        === 'This Month'
);

$check(
    'Shared scope labels selected-cycle Current Stock correctly',
    ($cycleScope['current_stock_label'] ?? '')
        === 'Current Cycle'
);

$check(
    'Shared scope preserves legacy-only Current Stock wording',
    ($legacyScope['current_stock_label'] ?? '')
        === 'Latest Closing'
);

$check(
    'Shared distinct-day helper collapses multiple cycle rows on one date',
    daily_record_workspace_distinct_days([
        ['record_date' => '2026-09-01'],
        ['record_date' => '2026-09-01'],
        ['record_date' => '2026-09-02'],
    ]) === 2
);

foreach (['layer', 'broiler', 'ruminant'] as $type) {
    $source = $sources[$type];

    $check(
        ucfirst($type)
            . ' loads shared workspace scope helper',
        str_contains(
            $source,
            "daily_record_workspace_scope.php"
        )
        && str_contains(
            $source,
            'daily_record_workspace_scope('
        )
    );

    $check(
        ucfirst($type)
            . ' Current Stock subtitle uses shared scope label',
        str_contains(
            $source,
            "$workspaceScope['current_stock_label']"
        )
    );

    $check(
        ucfirst($type)
            . ' all-record selector uses shared scope label',
        str_contains(
            $source,
            "$workspaceScope['selector_all_label']"
        )
        && !str_contains(
            $source,
            '>All / Legacy records<'
        )
    );

    $check(
        ucfirst($type)
            . ' activity cards expose displayed-month scope',
        str_contains(
            $source,
            "$workspaceScope['activity_label']"
        )
    );

    $check(
        ucfirst($type)
            . ' record query remains scoped to displayed month',
        str_contains(
            $source,
            "DATE_FORMAT(record_date, '%Y-%m') = ?"
        )
    );
}

$check(
    'Broiler no longer replaces monthly cards with all-time farm totals',
    str_contains(
        $sources['broiler'],
        '$summaryTotals = $monthlyTotals;'
    )
    && !str_contains(
        $sources['broiler'],
        '$summaryStmt = $pdo->query('
    )
);

$check(
    'Ruminant no longer replaces monthly cards with all-time farm totals',
    str_contains(
        $sources['ruminant'],
        '$summaryTotals = $monthlyTotals;'
    )
    && !str_contains(
        $sources['ruminant'],
        '$summaryStmt = $pdo->query('
    )
);

$check(
    'Broiler Days Recorded counts distinct displayed-month dates',
    str_contains(
        $sources['broiler'],
        'daily_record_workspace_distinct_days($records)'
    )
    && str_contains(
        $sources['broiler'],
        '<?php echo $daysRecorded; ?>'
    )
);

$check(
    'Population authority remains outside workspace-scope helper',
    !str_contains(
        $sources['helper'],
        'production_population_movements'
    )
    && !str_contains(
        $sources['helper'],
        'production_population_baselines'
    )
    && !str_contains(
        $sources['helper'],
        'SELECT '
    )
);

echo PHP_EOL . 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . ($failures === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
