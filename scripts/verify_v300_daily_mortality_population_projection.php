<?php
/**
 * Focused V3.0 verifier: Daily Record mortality projects centrally into the
 * population ledger without duplicating tagged ruminant lifecycle ownership.
 *
 * Read-only. No DB connection, schema write, network call, or provider action.
 */
$root = dirname(__DIR__);
$helperPath = $root.'/lib/daily_population_sync.php';
$projectionPath = $root.'/lib/production_population_projection.php';
$layerPath = $root.'/poultry/layers_daily_record.php';
$broilerPath = $root.'/poultry/broiler_daily_record.php';
$ruminantPath = $root.'/ruminant/ruminant_daily_record.php';
$lifecyclePath = $root.'/lib/ruminant_lifecycle_service.php';

$checks = 0;
$failures = 0;

function verify_daily_population_check(bool $ok, string $label): void
{
    global $checks, $failures;
    $checks++;
    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
}

foreach (
    [$helperPath,$projectionPath,$layerPath,$broilerPath,$ruminantPath,$lifecyclePath]
    as $path
) {
    verify_daily_population_check(
        is_file($path) && is_readable($path),
        'Required source is readable: '.basename($path)
    );
}

$helper = file_get_contents($helperPath);
$layer = file_get_contents($layerPath);
$broiler = file_get_contents($broilerPath);
$ruminant = file_get_contents($ruminantPath);

require_once $helperPath;

foreach (
    ['daily_layer_record','daily_broiler_record','daily_ruminant_record']
    as $sourceType
) {
    verify_daily_population_check(
        daily_population_mortality_source_type($sourceType) === $sourceType,
        "{$sourceType} is an accepted Daily Record mortality source"
    );
}

$invalidRejected = false;
try {
    daily_population_mortality_source_type('ruminant_exit');
} catch (InvalidArgumentException $e) {
    $invalidRejected = true;
}
verify_daily_population_check(
    $invalidRejected,
    'Non-Daily Record source types fail closed'
);

verify_daily_population_check(
    str_contains($helper, 'production_population_projection_sync')
    && str_contains($helper, 'production_population_projection_active_snapshot'),
    'Shared helper owns synchronization and removal through the canonical projection service'
);

verify_daily_population_check(
    !str_contains($helper, 'INSERT INTO production_population_movements')
    && !str_contains($helper, 'UPDATE production_population_movements')
    && !str_contains($helper, 'DELETE FROM production_population_movements'),
    'Shared helper does not duplicate population-ledger SQL'
);

verify_daily_population_check(
    str_contains($helper, "'movement_type' => 'mortality'")
    && preg_match('/\$mortality\s*>\s*0/', $helper) === 1
    && str_contains($helper, ': null;'),
    'Positive mortality projects mortality while zero removes the active projection'
);

$routes = [
    'Layer' => [$layer, 'daily_layer_record'],
    'Broiler' => [$broiler, 'daily_broiler_record'],
    'Ruminant' => [$ruminant, 'daily_ruminant_record'],
];

foreach ($routes as $label => [$source, $sourceType]) {
    verify_daily_population_check(
        str_contains($source, "lib/daily_population_sync.php"),
        "{$label} Daily Record loads the shared mortality population helper"
    );
}

foreach ($routes as $label => [$source, $sourceType]) {
    verify_daily_population_check(
        preg_match(
            "/daily_population_sync_mortality\\([^;]+['\\\"]".preg_quote($sourceType,'/')."['\\\"]/s",
            $source
        ) === 1,
        "{$label} Daily Record uses its durable source namespace"
    );
}

foreach ($routes as $label => [$source, $sourceType]) {
    $idPos = strpos($source, '$dailyRecordId =');
    $syncPos = strpos($source, 'daily_population_sync_mortality(');
    $commitPos = $syncPos !== false ? strpos($source, '$pdo->commit()', $syncPos) : false;

    verify_daily_population_check(
        $idPos !== false
        && $syncPos !== false
        && $commitPos !== false
        && $idPos < $syncPos
        && $syncPos < $commitPos,
        "{$label} mortality synchronization uses the durable row id inside the save transaction"
    );
}

verify_daily_population_check(
    !str_contains($layer, 'production_population_movements')
    && !str_contains($broiler, 'production_population_movements')
    && !str_contains($ruminant, 'production_population_movements'),
    'Daily Record routes do not duplicate population-ledger SQL'
);

$deleteStart = strpos($ruminant, "isset(\$_POST['delete_record'])");
$deleteEnd = $deleteStart !== false
    ? strpos($ruminant, '// Get all records for the month', $deleteStart)
    : false;
$deleteBody = ($deleteStart !== false && $deleteEnd !== false)
    ? substr($ruminant, $deleteStart, $deleteEnd - $deleteStart)
    : '';

$removePos = strpos($deleteBody, 'daily_population_remove_mortality(');
$rowDeletePos = strpos($deleteBody, 'DELETE FROM ruminant_daily_records');

verify_daily_population_check(
    $removePos !== false
    && $rowDeletePos !== false
    && $removePos < $rowDeletePos,
    'Ruminant delete removes its population projection before deleting the durable Daily Record'
);

verify_daily_population_check(
    str_contains($deleteBody, "'daily_ruminant_record'"),
    'Ruminant delete removes the correct durable source namespace'
);

verify_daily_population_check(
    preg_match('/\$mortality\s*>\s*0\s*&&\s*\$tagNo\s*!==\s*[\'"]{2}/', $ruminant) === 1
    && str_contains($ruminant, 'Animal Registry')
    && str_contains($ruminant, 'unregistered or group losses only'),
    'Tagged ruminant mortality is rejected from Daily Record ownership'
);

verify_daily_population_check(
    str_contains($ruminant, 'Mortality (Unregistered / Group)')
    && str_contains($ruminant, 'population is not deducted twice'),
    'Ruminant Daily Record explains the tagged-versus-group mortality boundary'
);

echo "CHECKS={$checks}\n";
echo "FAILURES={$failures}\n";

if ($failures > 0) {
    echo "V3.0 DAILY MORTALITY POPULATION PROJECTION: FAILED\n";
    exit(1);
}

echo "V3.0 DAILY MORTALITY POPULATION PROJECTION: PASSED\n";
