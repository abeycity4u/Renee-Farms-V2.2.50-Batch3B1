<?php
declare(strict_types=1);

/**
 * B12 source-only staged route safety inventory.
 * Offline: reads source text only. No database, bootstrapping, HTTP or CI.
 *
 * THIS IS NOT RUNTIME, CONCURRENCY OR MIGRATION CERTIFICATION.
 */
$root = dirname(__DIR__, 2);
$passed = 0;
function b12_stage_assert(bool $ok, string $name): void
{
    global $passed;
    if (!$ok) throw new RuntimeException('FAIL ' . $name);
    $passed++;
}
function b12_stage_read(string $root, string $path): string
{
    $source = file_get_contents($root . '/' . $path);
    if (!is_string($source)) {
        throw new RuntimeException('Required B12 staged source is absent: ' . $path);
    }
    return $source;
}
try {
    $gate = b12_stage_read($root, 'lib/daily_record_revision_cutover_gate.php');
    b12_stage_assert(
        preg_match(
            '/function daily_record_revision_cutover_enabled\(\): bool\s*\{\s*return false;\s*\}/D',
            $gate
        ) === 1,
        'cutover remains literal hard-off'
    );
    b12_stage_assert(
        !preg_match('/(getenv\s*\(|\$_(GET|POST|REQUEST|SESSION)|constant\s*\(|defined\s*\()/i', $gate),
        'fuse cannot be enabled by environment or HTTP'
    );
    foreach ([
        'layer' => 'poultry/layers_daily_record.php',
        'broiler' => 'poultry/broiler_daily_record.php',
        'ruminant' => 'ruminant/ruminant_daily_record.php',
    ] as $type => $path) {
        $code = b12_stage_read($root, $path);
        b12_stage_assert(
            substr_count($code, "require_once(__DIR__ . '/../lib/daily_record_revision_cutover_gate.php');") === 1,
            $type . ' imports one cutover fuse'
        );
        b12_stage_assert(
            substr_count($code, 'if (daily_record_revision_cutover_enabled()) {') === 1,
            $type . ' has exactly one guarded future save'
        );
        b12_stage_assert(
            substr_count($code, 'daily_record_revision_route_dispatch_json(') === 1,
            $type . ' dispatches only behind gate'
        );
        $start = strpos($code, 'if (daily_record_revision_cutover_enabled()) {');
        $dispatch = strpos($code, 'daily_record_revision_route_dispatch_json(', $start);
        $nextLegacy = strpos($code, 'SELECT id FROM ' . ($type==='ruminant'
            ? 'ruminant' : $type) . '_daily_records WHERE farm_id');
        b12_stage_assert(
            $dispatch !== false && $nextLegacy !== false && $dispatch < $nextLegacy,
            $type . ' staged CAS before legacy upsert check'
        );
        foreach ([
            "'opening_stock' => \$openingStock",
            "'mortality' => \$mortality",
            "'feed_consumption' => \$feedConsumption",
            "'feed_item_id' => \$feedItemId",
            "'water_consumption' => \$waterConsumption",
        ] as $fragment) {
            b12_stage_assert(str_contains($code, $fragment),
                $type . ' preserves validated core fact for shared pipeline');
        }
        b12_stage_assert(
            str_contains($code, 'daily_population_continuity_apply(') &&
            str_contains($code, 'sync_daily_feed_usage(') &&
            str_contains($code, 'daily_population_sync_mortality('),
            $type . ' legacy side effects retained during staging'
        );
    }
    $runtime = b12_stage_read($root, 'includes/permission_runtime.php');
    b12_stage_assert(
        str_contains($runtime, 'function permission_runtime_daily_save_permission(') &&
        str_contains($runtime, 'if (daily_record_revision_cutover_enabled()) {') &&
        str_contains($runtime, 'daily_record_revision_parse_write_intent(') &&
        str_contains($runtime, 'return permission_runtime_existing_daily_record('),
        'permission split retains legacy lookup and future exact intent'
    );
    foreach (['layer','broiler','ruminant'] as $type) {
        b12_stage_assert(
            str_contains($runtime, "permission_runtime_daily_save_permission(\$pdo, '$type')"),
            $type . ' permission route delegates to shared choice'
        );
    }
    $adapter = b12_stage_read($root, 'lib/daily_record_revision_route_adapter.php');
    b12_stage_assert(
        str_contains($adapter, 'daily_record_revision_save_pipeline(') &&
        str_contains($adapter, 'daily_record_revision_route_revalidate_locked(') &&
        str_contains($adapter, 'daily_record_revision_route_transaction('),
        'one transaction/permission/continuity adapter'
    );
    b12_stage_assert(
        str_contains($adapter, 'http_response_code($status);') &&
        str_contains($adapter, "'code'=>'record_conflict'") &&
        str_contains($adapter, 'Cache-Control: no-store'),
        'future conflict body is non-cached JSON'
    );
    b12_stage_assert(
        !str_contains($adapter, 'header(\'Location:') &&
        !str_contains($adapter, 'Location: ') &&
        !str_contains($adapter, '$_SESSION[\'farm_id\']'),
        'future AJAX path does not redirect or trust session POST farm'
    );
    echo "B12_STAGED_ROUTES_PURE=PASS\nPASS_COUNT={$passed}\n";
} catch (Throwable $error) {
    fwrite(STDERR, "B12_STAGED_ROUTES_PURE=FAIL " .
        $error->getMessage() . "\n");
    exit(1);
}
