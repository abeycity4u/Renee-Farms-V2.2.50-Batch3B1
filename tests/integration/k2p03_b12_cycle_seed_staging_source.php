<?php
declare(strict_types=1);

/**
 * B12 cycle seed cutover inventory — read-only source verifier.
 * Does not bootstrap application, access DB or start CI.
 */
$root = dirname(__DIR__, 2);
$checks = 0;
function b12_seed_stage_check(bool $ok, string $description): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $description);
    $checks++;
}
function b12_seed_stage_source(string $root, string $path): string
{
    $data = file_get_contents($root . '/' . $path);
    if (!is_string($data)) throw new RuntimeException('Source unavailable: ' . $path);
    return $data;
}

try {
    $gate = b12_seed_stage_source($root, 'lib/daily_record_revision_cutover_gate.php');
    b12_seed_stage_check(
        preg_match('/function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false;\s*\}/', $gate) === 1,
        'cutover remains hard-disabled'
    );
    b12_seed_stage_check(
        !str_contains($gate, 'function_exists(') &&
        !preg_match('/getenv\(|\$_(?:GET|POST|REQUEST|SESSION)\b|defined\(/', $gate),
        'no dynamic runtime override of cutover gate'
    );

    $route = b12_seed_stage_source($root, 'management/production_cycles.php');
    $cycleCreation = strpos($route, 'production_cycle_create_v3(');
    $revisionSeed = strpos($route, 'daily_record_revision_seed_cycle_entry(');
    $legacySeed = strpos($route, 'INSERT INTO layer_daily_records');
    $onboarding = strpos($route, 'poultry_cycle_onboarding_record_initial(');
    $commit = strpos($route, '$pdo->commit();', $revisionSeed ?: 0);
    b12_seed_stage_check(
        $cycleCreation !== false && $revisionSeed !== false &&
        $legacySeed !== false && $onboarding !== false && $commit !== false &&
        $cycleCreation < $revisionSeed &&
        $revisionSeed < $legacySeed &&
        $legacySeed < $onboarding &&
        $onboarding < $commit,
        'revision seed shares canonical creation and onboarding transaction'
    );
    b12_seed_stage_check(
        str_contains($route, 'if (daily_record_revision_cutover_enabled()) {') &&
        str_contains($route, "} elseif (\$farmType === 'poultry' && \$productionType === 'layer') {"),
        'only gated revision seed replaces original first-row path'
    );
    foreach (['layer', 'broiler', 'ruminant'] as $type) {
        b12_seed_stage_check(
            substr_count($route, 'INSERT INTO ' . $type . '_daily_records') === 1,
            $type . ' legacy seed remains unchanged and singular'
        );
    }
    b12_seed_stage_check(
        str_contains($route, "require_once(__DIR__ . '/../lib/daily_record_revision_cutover_gate.php');"),
        'Create Cycle route imports shared hard-off fuse'
    );
    b12_seed_stage_check(
        str_contains($route, '$pdo->beginTransaction();') &&
        str_contains($route, 'if ($pdo->inTransaction()) { $pdo->rollBack(); }'),
        'caller owns transaction and catches setup errors'
    );

    $seed = b12_seed_stage_source($root, 'lib/daily_record_revision_cycle_seed.php');
    b12_seed_stage_check(
        str_contains($seed, 'daily_record_revision_require_transaction($pdo);') &&
        str_contains($seed, 'production_cycle_get($pdo, $farmId, $cycleId, true)'),
        'new helper requires same transaction and locked canonical cycle'
    );
    b12_seed_stage_check(
        str_contains($seed, "record_revision';") &&
        str_contains($seed, '$values[] = 1;'),
        'new helper initializes revision to exactly one'
    );
    b12_seed_stage_check(
        str_contains($seed, 'LIMIT 1 FOR UPDATE') &&
        str_contains($seed, 'daily_record_revision_validate_scope(') &&
        str_contains($seed, 'daily_record_revision_definition($type)'),
        'central authority, cycle identity and duplicate scope checked'
    );
    b12_seed_stage_check(
        !str_contains($seed, '->beginTransaction(') &&
        !str_contains($seed, '->commit(') &&
        !str_contains($seed, '->rollBack('),
        'new seed helper never owns transaction'
    );
    b12_seed_stage_check(
        preg_match('~\$fields\[\x27feed_consumption_bags\x27\]\s*=\s*0\s*;~', $seed) === 1 &&
        preg_match('~\$fields\[\x27feed_consumption_kg\x27\]\s*=\s*0\s*;~', $seed) === 1 &&
        preg_match('~\$fields\[\x27egg_production\x27\]\s*=\s*0\s*;~', $seed) === 1 &&
        preg_match('~\$fields\[\x27birds_age\x27\]\s*=\s*\$startAgeDays\s*;~', $seed) === 1 &&
        preg_match('~\x27mortality\x27\s*=>\s*0\b~', $seed) === 1,
        'all three animal types retain starting facts'
    );

    $canonical = b12_seed_stage_source($root, 'lib/production_cycle_service.php');
    b12_seed_stage_check(
        str_contains($canonical, 'function production_cycle_create_v3(') &&
        !str_contains($canonical, 'INSERT INTO layer_daily_records') &&
        !str_contains($canonical, 'INSERT INTO broiler_daily_records') &&
        !str_contains($canonical, 'INSERT INTO ruminant_daily_records'),
        'canonical production-cycle service remains independent of Daily Record seeds'
    );

    echo "B12_CYCLE_SEED_STAGING_SOURCE=PASS\nPASS_COUNT={$checks}\n";
} catch (Throwable $error) {
    fwrite(STDERR, "B12_CYCLE_SEED_STAGING_SOURCE=FAIL " .
        $error->getMessage() . "\n");
    exit(1);
}
