<?php
/**
 * V2.3 focused performance/debt verifier.
 * Static/read-only: no database connection and no farm data mutation.
 */

$root = dirname(__DIR__);
$dashboard = file_get_contents($root . '/dashboard.php') ?: '';
$snapshot = file_get_contents($root . '/includes/dashboard_livestock_snapshot.php') ?: '';
$followup = file_get_contents($root . '/lib/investigation_followup.php') ?: '';
$failures = 0;

$check = static function (string $label, bool $ok) use (&$failures): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$ok) $failures++;
};

$check('dashboard loads the constant-query livestock snapshot helper',
    str_contains($dashboard, "require_once(__DIR__ . '/includes/dashboard_livestock_snapshot.php');"));
$check('dashboard ticker uses one shared snapshot call',
    substr_count($dashboard, 'dashboard_livestock_snapshot($pdo, $tenantFarmId, $farmAccess)') === 1);
$check('legacy Poultry per-cycle ticker loop is removed from dashboard',
    !str_contains($dashboard, '$poultryCycles')
    && !str_contains($dashboard, '$latestLayerStmt')
    && !str_contains($dashboard, '$latestBroilerStmt'));
$check('legacy Ruminant per-cycle ticker loop is removed from dashboard',
    !str_contains($dashboard, '$ruminantCycles')
    && !str_contains($dashboard, '$latestCycleDateStmt')
    && !str_contains($dashboard, '$sumCycleStockStmt'));
$check('snapshot preserves explicit Poultry Layer and Broiler sources',
    str_contains($snapshot, "'layer' => ['table' => 'layer_daily_records', 'label' => 'Layer']")
    && str_contains($snapshot, "'broiler' => ['table' => 'broiler_daily_records', 'label' => 'Broiler']"));
$check('Poultry snapshot keeps latest record_date then id semantics',
    str_contains($snapshot, 'ORDER BY d2.record_date DESC, d2.id DESC')
    && str_contains($snapshot, 'LIMIT 1'));
$check('Ruminant snapshot keeps latest-date-per-cycle semantics',
    str_contains($snapshot, 'SELECT MAX(d2.record_date)')
    && str_contains($snapshot, 'GROUP BY pc.id, pc.production_type'));
$check('Ruminant snapshot preserves sum of all records on latest cycle date',
    str_contains($snapshot, 'COALESCE(SUM(d.opening_stock - d.mortality), 0) AS cycle_stock')
    && str_contains($snapshot, 'COUNT(d.id) AS record_count'));
$check('snapshot DB execute sites are constant, not cycle-count dependent',
    substr_count($snapshot, '->execute(') === 2
    && !str_contains($snapshot, 'foreach ($poultryCycles')
    && !str_contains($snapshot, 'foreach ($ruminantCycles'));
$check('unknown Ruminant production types still map to Other',
    str_contains($snapshot, "if (!array_key_exists(\$cycleType, \$totals)) \$cycleType = 'other';"));
$check('investigation follow-up reads all matching review rows in one query',
    str_contains($followup, 'WHERE f.farm_id=? AND f.as_of_date<=?')
    && str_contains($followup, '$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);'));
$check('investigation source-evidence lookups are batched with IN lists',
    str_contains($followup, 'cycle_id IN ({$ph})')
    && str_contains($followup, 'animal_id IN ({$ph})'));

if ($failures === 0) {
    echo PHP_EOL . "12 checks, 0 failure(s)." . PHP_EOL;
    echo "PASS: confirmed dashboard livestock N+1 debt is removed and investigation follow-up remains batch-loaded." . PHP_EOL;
    echo "NOTE: historical weight/age scans remain a separate scaling observation; no schema/index change was made in this pass." . PHP_EOL;
} else {
    echo PHP_EOL . "12 checks, {$failures} failure(s)." . PHP_EOL;
}

exit($failures === 0 ? 0 : 1);
