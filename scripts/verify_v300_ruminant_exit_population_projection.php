<?php
/**
 * Focused V3.0 verifier: tagged ruminant lifecycle exits own their population
 * projection centrally through the lifecycle service.
 *
 * Read-only. No DB connection, schema write, network call, or provider action.
 */
$root = dirname(__DIR__);
$servicePath = $root.'/lib/ruminant_lifecycle_service.php';
$membershipPath = $root.'/lib/ruminant_cycle_membership.php';
$projectionPath = $root.'/lib/production_population_projection.php';
$populationPath = $root.'/lib/production_population.php';
$salesPath = $root.'/management/sales_records.php';

$checks = 0;
$failures = 0;

function verify_check(bool $ok, string $label): void
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

foreach ([$servicePath,$membershipPath,$projectionPath,$populationPath,$salesPath] as $path) {
    verify_check(is_file($path) && is_readable($path), 'Required source is readable: '.basename($path));
}

$service = file_get_contents($servicePath);
$membership = file_get_contents($membershipPath);
$sales = file_get_contents($salesPath);

require_once $servicePath;

verify_check(
    in_array('ruminant_exit', production_population_projection_source_types(), true),
    'ruminant_exit is a canonical durable population source'
);

$expectedMap = [
    'manual_dead' => 'mortality',
    'manual_culled' => 'cull',
    'manual_slaughtered' => 'slaughter',
    'culled_slaughtered' => 'cull',
    'sold_live' => 'sale',
    'manual_transferred' => null,
];
foreach ($expectedMap as $outcome => $movementType) {
    verify_check(
        ruminant_lifecycle_population_movement_type($outcome) === $movementType,
        "{$outcome} maps to ".($movementType ?? 'no standalone population movement')
    );
}

$unknownRejected = false;
try {
    ruminant_lifecycle_population_movement_type('future_unknown_exit');
} catch (RuntimeException $e) {
    $unknownRejected = true;
}
verify_check($unknownRejected, 'Unknown lifecycle outcomes fail closed');

verify_check(
    str_contains($membership, 'function ruminant_cycle_membership_at_date_locked')
    && str_contains($membership, "start_date<=?")
    && str_contains($membership, "(end_date IS NULL OR end_date>=?)"),
    'Shared membership service owns exact-date cycle resolution'
);
verify_check(
    preg_match('/LIMIT\s+2\s+FOR\s+UPDATE/i', $membership) === 1
    && preg_match('/count\s*\(\s*\$rows\s*\)\s*>\s*1/', $membership) === 1,
    'Overlapping exit-date memberships fail closed under lock'
);
verify_check(
    str_contains($service, 'ruminant_cycle_membership_at_date_locked')
    && !str_contains($service, 'function ruminant_lifecycle_population_membership_at_date_locked'),
    'Lifecycle integration delegates exact-date membership authority to the shared membership service'
);
verify_check(
    str_contains($service, "production_population_projection_active_snapshot")
    && str_contains($service, "'ruminant_exit'"),
    'Existing projection supplies cycle fallback when an edit removes membership context'
);
verify_check(
    preg_match('/[\'"]movement_date[\'"]\s*=>\s*\$exitDate/', $service) === 1
    && preg_match('/[\'"]quantity[\'"]\s*=>\s*1/', $service) === 1,
    'Tagged exit projects exactly one animal on the durable exit date'
);

$applyStart = strpos($service, 'function ruminant_lifecycle_apply_exit_boundary');
$reverseStart = strpos($service, 'function ruminant_lifecycle_reverse_exit_boundary');
$repairStart = strpos($service, 'function ruminant_lifecycle_repair_open_membership');
$applyBody = ($applyStart !== false && $reverseStart !== false)
    ? substr($service, $applyStart, $reverseStart - $applyStart)
    : '';
$reverseBody = ($reverseStart !== false && $repairStart !== false)
    ? substr($service, $reverseStart, $repairStart - $reverseStart)
    : '';

verify_check(
    str_contains($applyBody, 'ruminant_lifecycle_sync_exit_population'),
    'Shared apply boundary synchronizes the tagged exit population projection'
);
verify_check(
    str_contains($reverseBody, 'ruminant_lifecycle_remove_exit_population'),
    'Shared reverse boundary removes the tagged exit population projection'
);
$removePos = strpos($reverseBody, 'ruminant_lifecycle_remove_exit_population');
$restorePos = strpos($reverseBody, 'UPDATE ruminant_animal_cycle_memberships');
verify_check(
    $removePos !== false && $restorePos !== false && $removePos < $restorePos,
    'Projection removal happens before membership boundary restoration'
);
verify_check(
    !str_contains($service, 'INSERT INTO production_population_movements')
    && !str_contains($service, 'UPDATE production_population_movements')
    && !str_contains($service, 'DELETE FROM production_population_movements'),
    'Lifecycle service does not duplicate population-ledger SQL'
);
verify_check(
    !str_contains($sales, 'production_population_projection_sync')
    && !str_contains($sales, 'production_population_movements'),
    'Sales route does not duplicate tagged-ruminant population ownership'
);
verify_check(
    !str_contains($service, 'new PDO'),
    'Lifecycle integration reuses caller PDO/transaction'
);

echo "CHECKS={$checks}\n";
echo "FAILURES={$failures}\n";

if ($failures > 0) {
    echo "V3.0 RUMINANT EXIT POPULATION PROJECTION: FAILED\n";
    exit(1);
}

echo "V3.0 RUMINANT EXIT POPULATION PROJECTION: PASSED\n";
