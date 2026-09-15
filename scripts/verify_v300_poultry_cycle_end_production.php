<?php
/**
 * V3.0 — Poultry End Production contract verifier.
 *
 * Static/pure verifier:
 * - no database connection;
 * - no database write;
 * - proves Manage Cycle is a thin End Production route;
 * - proves orchestration delegates to shared lifecycle and V3 cycle services;
 * - proves normal lifecycle rules remain intact outside cycle closing.
 */

$root = dirname(__DIR__);

$managePath =
    $root . '/management/poultry_cycle.php';

$completionPath =
    $root . '/lib/poultry_cycle_completion.php';

$lifecyclePath =
    $root . '/lib/poultry_cycle_lifecycle.php';

$cycleServicePath =
    $root . '/lib/production_cycle_service.php';

$legacyPath =
    $root . '/management/production_cycles.php';

$manage = is_file($managePath)
    ? (string)file_get_contents($managePath)
    : '';

$completion = is_file($completionPath)
    ? (string)file_get_contents($completionPath)
    : '';

$lifecycle = is_file($lifecyclePath)
    ? (string)file_get_contents($lifecyclePath)
    : '';

$cycleService = is_file($cycleServicePath)
    ? (string)file_get_contents($cycleServicePath)
    : '';

$legacy = is_file($legacyPath)
    ? (string)file_get_contents($legacyPath)
    : '';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $label
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
    $manage !== '',
    'Manage Cycle page is readable'
);

$check(
    $completion !== '',
    'shared poultry completion orchestrator exists'
);

$check(
    $lifecycle !== '',
    'canonical poultry lifecycle service is readable'
);

$check(
    $cycleService !== '',
    'canonical production cycle service is readable'
);

$check(
    $legacy !== '',
    'Production Cycles fallback page is readable'
);

$check(
    strpos(
        $manage,
        "require_once(__DIR__ . '/../lib/poultry_cycle_completion.php');"
    ) !== false,
    'Manage Cycle loads the shared completion orchestrator'
);

$check(
    strpos(
        $manage,
        '$canEndProduction ='
    ) !== false
    && strpos(
        $manage,
        "isPlatformOwner()"
    ) !== false
    && strpos(
        $manage,
        "hasRole('farm_admin')"
    ) !== false,
    'End Production uses the Owner/Farm Admin mutation capability'
);

$check(
    strpos(
        $manage,
        "if (\$action !== 'end_production')"
    ) !== false
    && strpos(
        $manage,
        'value="end_production"'
    ) !== false,
    'Manage Cycle accepts End Production as its only supported action'
);

$obsoleteActions = [
    'record_poultry_acquisition',
    'void_poultry_acquisition',
    'set_initial_poultry_phase',
    'transition_poultry_phase',
    'end_poultry_phase',
    'approve_production_entry_basis',
];

$obsoleteAbsent = true;

foreach ($obsoleteActions as $action) {
    if (strpos($manage, $action) !== false) {
        $obsoleteAbsent = false;
        break;
    }
}

$check(
    $obsoleteAbsent,
    'Manage Cycle no longer owns acquisition, lifecycle-transition, or economic-basis mutations'
);

$check(
    substr_count(
        $manage,
        'poultry_cycle_end_production('
    ) === 1,
    'Manage Cycle delegates End Production exactly once'
);

$check(
    strpos(
        $manage,
        'production_cycle_close_v3('
    ) === false
    && strpos(
        $manage,
        'poultry_lifecycle_end_current_phase('
    ) === false,
    'thin Manage Cycle does not bypass the completion orchestrator'
);

$manageOwnsSql =
    strpos($manage, '->prepare(') !== false
    || strpos($manage, '->query(') !== false
    || stripos($manage, 'INSERT INTO') !== false
    || stripos($manage, 'DELETE FROM') !== false;

$check(
    !$manageOwnsSql,
    'Manage Cycle owns no direct SQL persistence or legacy population query'
);

$check(
    strpos(
        $manage,
        'Population cutover is required'
    ) !== false
    && strpos(
        $manage,
        'Population cutover required'
    ) !== false
    && strpos(
        $manage,
        'Legacy Daily Record estimate'
    ) === false,
    'untracked legacy population is never silently inferred on Manage Cycle'
);

$check(
    strpos(
        $manage,
        'Ending production does not remove birds'
    ) !== false
    && strpos(
        $manage,
        'Sales, mortality, cull, slaughter and'
    ) !== false,
    'UI separates cycle completion from physical population exits'
);

$completionOwnsSql =
    strpos($completion, '->prepare(') !== false
    || strpos($completion, '->query(') !== false
    || stripos($completion, 'INSERT INTO') !== false
    || stripos($completion, 'UPDATE production_') !== false
    || stripos($completion, 'DELETE FROM') !== false;

$check(
    !$completionOwnsSql,
    'completion orchestrator owns no lifecycle, population, or cycle SQL'
);

$check(
    strpos(
        $completion,
        '$pdo->inTransaction()'
    ) !== false
    && strpos(
        $completion,
        '$pdo->beginTransaction()'
    ) !== false
    && strpos(
        $completion,
        '$pdo->commit()'
    ) !== false
    && strpos(
        $completion,
        '$pdo->rollBack()'
    ) !== false,
    'completion orchestrator is atomic while preserving caller transactions'
);

$check(
    substr_count(
        $completion,
        'production_cycle_close_v3('
    ) === 1,
    'completion delegates canonical cycle closure exactly once'
);

$check(
    substr_count(
        $completion,
        'poultry_lifecycle_end_current_phase('
    ) === 1,
    'completion delegates lifecycle ending exactly once'
);

$check(
    strpos(
        $completion,
        'poultry_lifecycle_current_phase('
    ) !== false,
    'completion checks whether a biological stage is actually open'
);

$cycleClosingCallPattern =
    '/poultry_lifecycle_end_current_phase\s*\('
    . '[\s\S]*?'
    . '\$userId\s*,\s*true\s*\);/';

$check(
    preg_match(
        $cycleClosingCallPattern,
        $completion
    ) === 1,
    'End Production explicitly invokes lifecycle ending in cycle-closing context'
);

$check(
    strpos(
        $lifecycle,
        'bool $cycleClosing = false'
    ) !== false
    && strpos(
        $lifecycle,
        '!$cycleClosing'
    ) !== false
    && strpos(
        $lifecycle,
        "'mode' => \$cycleClosing ? 'cycle_closing' : 'normal'"
    ) !== false,
    'normal lifecycle behavior remains guarded while cycle-closing mode is explicit and audited'
);

$check(
    strpos(
        $lifecycle,
        'This phase has a defined next biological phase. Record a lifecycle transition instead of ending it directly.'
    ) !== false,
    'ordinary lifecycle ending still refuses to skip a defined next biological stage'
);

$check(
    strpos(
        $cycleService,
        'function production_cycle_close_v3'
    ) !== false
    && strpos(
        $cycleService,
        'production_population_state('
    ) !== false
    && strpos(
        $cycleService,
        'movement_date > ?'
    ) !== false
    && strpos(
        $cycleService,
        'closing_headcount = ?'
    ) !== false,
    'canonical V3 close service still owns population eligibility, later-movement protection, and closing headcount'
);

$check(
    strpos(
        $legacy,
        "if (\$action === 'close_cycle')"
    ) === false
    && strpos(
        $legacy,
        'value="close_cycle"'
    ) === false
    && strpos(
        $legacy,
        "if (\$action === 'confirm_population_cutover')"
    ) !== false
    && strpos(
        $legacy,
        "if (\$action === 'record_poultry_acquisition')"
    ) === false
    && strpos(
        $legacy,
        "if (\$action === 'set_initial_poultry_phase')"
    ) === false
    && strpos(
        $legacy,
        "if (\$action === 'transition_poultry_phase')"
    ) !== false,
    'legacy cycle-close and duplicate initial-onboarding paths are retired while controlled cutover and lifecycle transition remain available'
);

$check(
    strpos(
        $manage,
        'Record Flock Entry'
    ) === false
    && strpos(
        $manage,
        'Correct an Erroneous Entry'
    ) === false
    && strpos(
        $manage,
        'Set Initial Biological Stage'
    ) === false
    && strpos(
        $manage,
        'Record Transition'
    ) === false
    && strpos(
        $manage,
        'Production-Entry Economic Basis'
    ) === false,
    'rejected operational workspace controls are absent from Manage Cycle'
);

$check(
    strpos(
        $manage,
        'This production cycle has ended.'
    ) !== false
    && strpos(
        $manage,
        'Closing live population:'
    ) !== false,
    'closed cycle renders completion date/headcount context instead of another mutation'
);

echo PHP_EOL;
echo "Checks: {$checks}" . PHP_EOL;
echo "Failures: {$failures}" . PHP_EOL;

if ($failures > 0) {
    echo "V3.0 POULTRY END PRODUCTION: FAILED" . PHP_EOL;
    echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
    echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
    exit(1);
}

echo "V3.0 POULTRY END PRODUCTION: PASSED" . PHP_EOL;
echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
