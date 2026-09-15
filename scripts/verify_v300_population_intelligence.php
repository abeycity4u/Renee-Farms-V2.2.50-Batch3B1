<?php

$servicePath =
    __DIR__
    . '/../lib/production_population_intelligence.php';

$routePath =
    __DIR__
    . '/../management/production_cycles.php';

$populationPath =
    __DIR__
    . '/../lib/production_population.php';

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
    is_file($servicePath)
    && is_readable($servicePath),
    'shared population intelligence service exists and is readable'
);

$check(
    is_file($routePath)
    && is_readable($routePath),
    'Production Cycles route exists and is readable'
);

$service = is_readable($servicePath)
    ? (string)file_get_contents($servicePath)
    : '';

$route = is_readable($routePath)
    ? (string)file_get_contents($routePath)
    : '';

$population = is_readable($populationPath)
    ? (string)file_get_contents($populationPath)
    : '';

$check(
    strpos(
        $service,
        "require_once __DIR__ . '/production_population.php';"
    ) !== false,
    'population intelligence composes the canonical population authority'
);

$check(
    substr_count(
        $service,
        'function production_population_intelligence_legacy_snapshot('
    ) === 1,
    'one shared legacy snapshot fallback is defined'
);

$check(
    substr_count(
        $service,
        'function production_population_intelligence_cycle_snapshot('
    ) === 1,
    'one shared cycle population snapshot contract is defined'
);

$check(
    strpos(
        $service,
        "'layer' => 'layer_daily_records'"
    ) !== false
    && strpos(
        $service,
        "'broiler' => 'broiler_daily_records'"
    ) !== false,
    'legacy poultry fallback uses only hard-coded Daily Record sources'
);

$check(
    substr_count(
        $service,
        'WHERE farm_id = ?'
    ) >= 3
    && substr_count(
        $service,
        'cycle_id = ?'
    ) >= 3,
    'legacy fallback remains farm and cycle scoped'
);

$check(
    strpos(
        $service,
        'SELECT MAX(record_date)'
    ) !== false,
    'ruminant fallback selects one explicit latest legacy date'
);

$check(
    strpos(
        $service,
        'SUM(opening_stock - mortality)'
    ) !== false,
    'ruminant legacy fallback preserves latest-date herd aggregation'
);

$check(
    substr_count(
        $service,
        'record_date <= ?'
    ) === 2,
    'legacy fallback supports explicit as-of reads for poultry and ruminants'
);

$check(
    substr_count(
        $service,
        'production_population_state('
    ) === 2,
    'canonical cycle reads delegate to the shared population ledger'
);

$check(
    strpos(
        $service,
        "'tracking_status' =>\n                            'canonical'"
    ) !== false
    && strpos(
        $service,
        "'source' =>\n                            'v3_population_ledger'"
    ) !== false,
    'canonical snapshots are explicitly identified as ledger-backed'
);

$check(
    strpos(
        $service,
        "'legacy_untracked'"
    ) !== false
    && strpos(
        $service,
        "'before_baseline_untracked'"
    ) !== false
    && strpos(
        $service,
        "'legacy_daily_records'"
    ) !== false,
    'legacy and before-baseline fallback states remain explicit'
);

$check(
    strpos(
        $service,
        'production_population_baselines'
    ) === false
    && strpos(
        $service,
        'production_population_movements'
    ) === false,
    'intelligence service never bypasses canonical population tables directly'
);

$check(
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i',
        $service
    ) !== 1,
    'population intelligence is read-only'
);

$check(
    strpos(
        $route,
        "require_once(__DIR__ . '/../lib/production_population_intelligence.php');"
    ) !== false,
    'Production Cycles loads the shared population intelligence service'
);

$check(
    strpos(
        $route,
        'function getCycleCurrentStock('
    ) === false
    && strpos(
        $route,
        'getCycleCurrentStock('
    ) === false,
    'Production Cycles no longer owns a legacy current-stock helper'
);

$check(
    substr_count(
        $route,
        'production_population_intelligence_cycle_snapshot('
    ) === 1,
    'Production Cycles delegates current population exactly once per active cycle'
);

$check(
    strpos(
        $route,
        'production_population_state('
    ) === false,
    'Production Cycles no longer duplicates canonical population read policy'
);

$check(
    strpos(
        $route,
        "\$cycle['current_stock'] =\n                (int)\$cycle['population_snapshot']['quantity'];"
    ) !== false,
    'headline current stock comes from the shared snapshot result'
);

$check(
    strpos(
        $route,
        "\$summary['total_current_stock'] +=\n                (int)\$cycle['current_stock'];"
    ) !== false,
    'active-cycle total consumes the shared population result'
);

$check(
    strpos(
        $route,
        "\$cycle['population_snapshot']['tracking_status']\n                    === 'canonical'"
    ) !== false,
    'cutover tracking classification uses shared population status'
);

$check(
    strpos(
        $route,
        '$populationBaselineTableExists'
    ) !== false
    && strpos(
        $route,
        "production_population_intelligence_cycle_snapshot(\n                    \$pdo,\n                    \$tenantFarmId,"
    ) !== false,
    'route passes its existing canonical-availability context into the shared read contract'
);

$check(
    strpos(
        $route,
        "\$cycle['population_state'] =\n                \$cycle['population_snapshot']['canonical_state'];"
    ) !== false,
    'existing population-state compatibility remains available to the page'
);

$check(
    substr_count(
        $population,
        'function production_population_current_states('
    ) === 1,
    'canonical population service exposes one bulk current-state reader'
);

$check(
    strpos(
        $population,
        'GROUP BY cycle_id'
    ) !== false
    && strpos(
        $population,
        'b.cycle_id IN ({$placeholders})'
    ) !== false,
    'bulk canonical reader resolves baselines and movement totals with bounded grouped queries'
);

$check(
    substr_count(
        $service,
        'function production_population_intelligence_legacy_current_snapshots('
    ) === 1,
    'intelligence service exposes one bounded bulk legacy fallback'
);

$check(
    substr_count(
        $service,
        'function production_population_intelligence_active_cycle_snapshots('
    ) === 1,
    'intelligence service exposes one bulk active-cycle snapshot contract'
);

$check(
    substr_count(
        $service,
        'production_population_current_states('
    ) === 1,
    'bulk active-cycle intelligence delegates canonical state once per farm scope'
);

$check(
    strpos(
        $service,
        "AND status = 'active'"
    ) !== false
    && strpos(
        $service,
        "'poultry',"
    ) !== false
    && strpos(
        $service,
        "'ruminant'"
    ) !== false,
    'bulk intelligence reads active livestock cycles only within the requested farm scope'
);

$check(
    strpos(
        $service,
        'ORDER BY' . PHP_EOL
        . '                            d2.record_date DESC,'
    ) !== false
    && strpos(
        $service,
        'GROUP BY d.cycle_id'
    ) !== false,
    'bulk legacy fallback preserves latest-row poultry and latest-date ruminant semantics'
);

$check(
    strpos(
        $service,
        'production_population_baselines'
    ) === false
    && strpos(
        $service,
        'production_population_movements'
    ) === false,
    'bulk intelligence still never bypasses canonical population-table ownership'
);

echo PHP_EOL;
echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;

if ($failures === 0) {
    echo 'V3.0 POPULATION INTELLIGENCE READ CONTRACT: PASSED'
        . PHP_EOL;
}

echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
