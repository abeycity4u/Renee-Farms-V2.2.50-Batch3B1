<?php

$helperPath =
    __DIR__
    . '/../includes/dashboard_livestock_snapshot.php';

$dashboardPath =
    __DIR__
    . '/../dashboard.php';

$intelligencePath =
    __DIR__
    . '/../lib/production_population_intelligence.php';

$checks = 0;
$failures = 0;

$check = function (
    bool $passed,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    if ($passed) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }

    $failures++;
    echo 'FAIL: ' . $label . PHP_EOL;
};

$check(
    is_file($helperPath)
    && is_readable($helperPath),
    'Dashboard livestock adapter exists and is readable'
);

$check(
    is_file($dashboardPath)
    && is_readable($dashboardPath),
    'Dashboard route exists and is readable'
);

$check(
    is_file($intelligencePath)
    && is_readable($intelligencePath),
    'shared population intelligence service exists and is readable'
);

$helper = is_readable($helperPath)
    ? (string)file_get_contents($helperPath)
    : '';

$dashboard = is_readable($dashboardPath)
    ? (string)file_get_contents($dashboardPath)
    : '';

$intelligence = is_readable($intelligencePath)
    ? (string)file_get_contents($intelligencePath)
    : '';

$check(
    substr_count(
        $helper,
        "require_once __DIR__ . '/../lib/production_population_intelligence.php';"
    ) === 1,
    'Dashboard adapter loads the shared population-intelligence contract once'
);

$check(
    substr_count(
        $helper,
        'function dashboard_livestock_snapshot('
    ) === 1,
    'one Dashboard livestock adapter is defined'
);

$check(
    substr_count(
        $helper,
        'production_population_intelligence_active_cycle_snapshots('
    ) === 1,
    'Dashboard adapter delegates active population exactly once'
);

$check(
    strpos(
        $helper,
        'production_cycles'
    ) === false,
    'Dashboard adapter no longer reads production cycles directly'
);

$check(
    preg_match(
        '/(?:layer_daily_records|broiler_daily_records|ruminant_daily_records)/',
        $helper
    ) !== 1,
    'Dashboard adapter no longer owns Daily Record population SQL'
);

$check(
    preg_match(
        '/->(?:prepare|query|execute)\s*\(/',
        $helper
    ) !== 1,
    'Dashboard adapter owns no direct database query execution'
);

$check(
    strpos(
        $helper,
        "'canonical_cycles'"
    ) !== false
    && strpos(
        $helper,
        "'legacy_estimate_cycles'"
    ) !== false
    && strpos(
        $helper,
        "'untracked_without_snapshot_cycles'"
    ) !== false
    && strpos(
        $helper,
        "'read_error'"
    ) !== false,
    'Dashboard adapter exposes explicit tracking metadata'
);

$check(
    strpos(
        $helper,
        "\$trackingStatus === 'canonical'"
    ) !== false,
    'canonical tracked cycles are classified explicitly'
);

$check(
    strpos(
        $helper,
        "=== 'legacy_untracked'"
    ) !== false,
    'legacy fallback cycles are classified explicitly'
);

$check(
    strpos(
        $helper,
        'if (!$hasSnapshot)'
    ) !== false,
    'cycles without a usable snapshot never invent a stock value'
);

$check(
    strpos(
        $helper,
        "'quantity'"
    ) !== false
    && strpos(
        $helper,
        '$poultryTotals[$label] +='
    ) !== false
    && strpos(
        $helper,
        '$ruminantTotals[$label] +='
    ) !== false,
    'Dashboard totals aggregate shared snapshot quantity only'
);

$check(
    strpos(
        $helper,
        "'layer' => 'Layer'"
    ) !== false
    && strpos(
        $helper,
        "'broiler' => 'Broiler'"
    ) !== false,
    'Layer and Broiler ticker categories remain stable'
);

$check(
    strpos(
        $helper,
        "'cattle' => 'Cattle'"
    ) !== false
    && strpos(
        $helper,
        "'goat' => 'Goat'"
    ) !== false
    && strpos(
        $helper,
        "'sheep' => 'Sheep'"
    ) !== false
    && strpos(
        $helper,
        "'other' => 'Other'"
    ) !== false,
    'ruminant ticker categories remain stable'
);

$check(
    substr_count(
        $dashboard,
        "require_once(__DIR__ . '/includes/dashboard_livestock_snapshot.php');"
    ) === 1,
    'Dashboard still loads its thin livestock adapter once'
);

$check(
    substr_count(
        $dashboard,
        'dashboard_livestock_snapshot('
    ) === 1,
    'Dashboard invokes the livestock adapter once'
);

$check(
    strpos(
        $dashboard,
        "\$poultryCurrentStock = \$livestockSnapshot['poultry'];"
    ) !== false
    && strpos(
        $dashboard,
        "\$ruminantCurrentStock = \$livestockSnapshot['ruminant'];"
    ) !== false,
    'existing poultry and ruminant ticker arrays remain compatible'
);

$check(
    strpos(
        $dashboard,
        "\$livestockTracking = \$livestockSnapshot['tracking'];"
    ) !== false,
    'Dashboard consumes shared tracking metadata'
);

$check(
    strpos(
        $dashboard,
        'Poultry Active Cycle Stock'
    ) !== false
    && strpos(
        $dashboard,
        'Ruminant Active Cycle Stock'
    ) !== false,
    'active-cycle ticker labels remain unchanged'
);

$check(
    strpos(
        $dashboard,
        'Some active-cycle figures use the latest Daily Record estimate until population tracking is confirmed.'
    ) !== false,
    'legacy fallback is disclosed instead of silently presented as canonical truth'
);

$check(
    strpos(
        $dashboard,
        'Some active cycles do not yet have a population snapshot.'
    ) !== false,
    'missing untracked population snapshots are disclosed'
);

$check(
    strpos(
        $dashboard,
        'Population snapshot is temporarily unavailable.'
    ) !== false,
    'Dashboard has a safe read-failure message'
);

$check(
    strpos(
        $dashboard,
        'production_population_intelligence_active_cycle_snapshots('
    ) === false,
    'Dashboard route stays thin and does not bypass its adapter'
);

$check(
    strpos(
        $dashboard,
        'SELECT * FROM layer_daily_records'
    ) !== false
    && strpos(
        $dashboard,
        'SELECT * FROM broiler_daily_records'
    ) !== false
    && strpos(
        $dashboard,
        'SELECT * FROM ruminant_daily_records'
    ) !== false,
    'historical Latest Production Daily Record reads remain intact'
);

$check(
    substr_count(
        $intelligence,
        'function production_population_intelligence_active_cycle_snapshots('
    ) === 1,
    'Dashboard dependency exposes one shared bulk active-cycle contract'
);

echo PHP_EOL;
echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;

if ($failures === 0) {
    echo 'V3.0 DASHBOARD POPULATION INTELLIGENCE: PASSED'
        . PHP_EOL;
}

echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
