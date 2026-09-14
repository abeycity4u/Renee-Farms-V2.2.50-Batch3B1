<?php
/**
 * V3.0 focused static/pure-contract verifier:
 * durable-source population projection synchronizer.
 *
 * No database connection is opened and no migration is executed.
 */

$root = dirname(__DIR__);
$servicePath = $root . '/lib/production_population_projection.php';
$populationPath = $root . '/lib/production_population.php';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$message}\n";
};

$check(
    is_file($servicePath) && is_readable($servicePath),
    'population projection service exists and is readable'
);

$check(
    is_file($populationPath) && is_readable($populationPath),
    'canonical population dependency exists and is readable'
);

$source = is_readable($servicePath)
    ? file_get_contents($servicePath)
    : false;

if ($source === false) {
    echo "\nChecks: {$checks}\n";
    echo "Failures: {$failures}\n";
    echo "V3.0 POPULATION PROJECTION SERVICE: FAILED\n";
    exit(1);
}

require_once $servicePath;

$check(
    class_exists('ProductionPopulationProjectionException')
    && function_exists('production_population_projection_sync')
    && function_exists('production_population_projection_desired'),
    'shared projection synchronizer surface is loaded'
);

$check(
    function_exists('production_population_record_movement')
    && function_exists('production_population_reverse_movement')
    && function_exists('production_population_lock_baseline'),
    'projection service self-loads canonical population authority'
);

$expectedSources = [
    'daily_layer_record',
    'daily_broiler_record',
    'daily_ruminant_record',
    'sale',
    'ruminant_exit',
    'transfer',
    'poultry_acquisition',
];

$check(
    production_population_projection_source_types() === $expectedSources,
    'projection service accepts only durable source-owned integrations'
);

$check(
    !in_array(
        'adjustment',
        production_population_projection_source_types(),
        true
    ),
    'request-owned manual adjustments stay outside durable source projection'
);

$check(
    production_population_projection_desired(null) === null,
    'null desired projection represents no physical population effect'
);

$desired = production_population_projection_desired([
    'movement_type' => 'mortality',
    'movement_date' => '2026-09-14',
    'quantity' => 5,
    'notes' => 'Daily mortality',
]);

$check(
    $desired['movement_type'] === 'mortality'
    && $desired['movement_date'] === '2026-09-14'
    && $desired['quantity'] === 5,
    'desired projection normalization preserves canonical dated quantity'
);

$invalidRejected = false;
try {
    production_population_projection_normalize_source_type('daily_record');
} catch (InvalidArgumentException $e) {
    $invalidRejected = true;
}
$check(
    $invalidRejected,
    'ambiguous generic daily_record source is rejected'
);

$compact = preg_replace('/\s+/', ' ', $source);

$check(
    strpos($source, "require_once __DIR__ . '/production_population.php';")
        !== false,
    'projection service owns its population dependency'
);

$check(
    strpos($compact, 'production_population_lock_cycle(') !== false
    && strpos($compact, 'production_population_lock_baseline(') !== false,
    'projection synchronization uses canonical cycle and baseline locks'
);

$check(
    strpos($source, "'status' => 'legacy_untracked'") !== false,
    'pre-cutover cycles remain on legacy behavior without V3 writes'
);

$check(
    strpos($source, "'before_baseline_untracked'") !== false,
    'pre-baseline historical sources are not silently backfilled'
);

$check(
    strpos($compact, 'production_population_projection_matches(') !== false
    && strpos($source, "'status' => 'unchanged'") !== false,
    'unchanged source projections are idempotent no-ops'
);

$check(
    strpos($compact, 'production_population_reverse_movement(') !== false
    && strpos($compact, 'production_population_record_movement(') !== false,
    'source corrections compose canonical reversal then append-only movement'
);

$check(
    strpos($compact, '$nextVersion = $latestVersion + 1;') !== false,
    'corrected source facts advance immutable source_version'
);

$check(
    strpos($compact, 'NOT EXISTS ( SELECT 1 FROM production_population_movements r')
        !== false
    && strpos($compact, 'r.reversal_of_id = m.id') !== false,
    'current projection excludes movements already compensated by reversal'
);

$check(
    strpos($compact, 'LIMIT 2 FOR UPDATE') !== false
    && strpos($compact, 'if (count($rows) > 1)') !== false
    && strpos(
        $source,
        'Population projection integrity check failed: this source has multiple active movements in one cycle.'
    ) !== false,
    'projection synchronizer fails closed on multiple active source movements'
);

$check(
    strpos($compact, '$startedTransaction = !$pdo->inTransaction();') !== false
    && strpos($compact, 'if ($startedTransaction) { $pdo->beginTransaction(); }')
        !== false,
    'projection service preserves caller-owned transaction boundaries'
);

$check(
    stripos($source, 'UPDATE production_population_movements') === false
    && stripos($source, 'DELETE FROM production_population_movements') === false,
    'projection service never rewrites or deletes population history'
);

$check(
    stripos($source, 'CREATE TABLE') === false
    && stripos($source, 'ALTER TABLE') === false
    && stripos($source, 'curl_') === false
    && stripos($source, 'file_get_contents("http') === false,
    'projection service performs no schema network or provider work'
);

$check(
    stripos($source, 'new PDO') === false,
    'projection service never opens its own database connection'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V3.0 POPULATION PROJECTION SERVICE: FAILED\n";
    exit(1);
}

echo "V3.0 POPULATION PROJECTION SERVICE: PASSED\n";
