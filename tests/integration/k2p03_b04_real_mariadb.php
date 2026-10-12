<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 B04 — real-MariaDB legacy Daily Record fallback.
 * Only a disposable synthetic GitHub-hosted schema may execute this file.
 * Never include deployment config.php or init.php.
 */

require_once __DIR__ . '/k2p03_ci_isolation.php';

$stage = 'ENVIRONMENT';
$pdo = null;

function b04_daily_row(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, farm_id, cycle_id, record_date, opening_stock,
                mortality, egg_production, crates_count, laying_rate, birds_age,
                feed_consumption_bags, water_consumption_liters
           FROM layer_daily_records WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('B04 synthetic daily row missing.');
    }
    return $row;
}

function b04_count(PDO $pdo, string $table): int
{
    if (!in_array($table, [
        'layer_daily_records', 'production_population_baselines',
        'production_population_movements',
    ], true)) {
        throw new RuntimeException('Unapproved B04 table.');
    }
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

function b04_rate(float $expected, $actual, string $label): void
{
    k2p03_ci_check(abs((float)$actual - $expected) < 0.005, $label);
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b04', 'B04', 'renee_k2p03_ci_b04'
    );
    $root = dirname(__DIR__, 2);
    k2p03_ci_equal(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        k2p03_ci_blob($root . '/lib/daily_population_continuity.php'),
        'canonical continuity source blob unchanged'
    );
    k2p03_ci_equal(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        k2p03_ci_blob($root . '/database_schema.sql'),
        'fresh schema source blob unchanged'
    );

    $stage = 'ISOLATED_DATABASE';
    $pdo = k2p03_ci_connection('renee_k2p03_ci_b04');
    require_once $root . '/lib/daily_population_continuity.php';
    require_once $root . '/lib/daily_population_sync.php';

    $stage = 'SYNTHETIC_LEGACY_SEED';
    $pdo->exec(
        "INSERT INTO farms (name, slug)
         VALUES ('K2P03 B04 synthetic legacy Layer', 'k2p03-b04-synthetic')"
    );
    $farmId = (int)$pdo->lastInsertId();
    k2p03_ci_check($farmId > 0, 'new synthetic farm ID');

    $createCycle = $pdo->prepare(
        "INSERT INTO production_cycles
           (farm_id, cycle_code, farm_type, production_type,
            status, start_date, opening_headcount)
         VALUES (?, 'k2p03-b04-layer', 'poultry', 'layer',
                 'active', '2026-09-21', 100)"
    );
    $createCycle->execute([$farmId]);
    $cycleId = (int)$pdo->lastInsertId();
    k2p03_ci_check($cycleId > 0, 'legacy cycle persisted without baseline');

    $insert = $pdo->prepare(
        'INSERT INTO layer_daily_records
           (farm_id, cycle_id, record_date, opening_stock, mortality,
            egg_production, crates_count, laying_rate, birds_age)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 25)'
    );
    $dates = [
        ['2026-09-21', 100, 2, 80, 2, 80.00],
        ['2026-09-22', 98, 1, 80, 2, 81.63],
        ['2026-09-23', 97, 2, 78, 2, 80.41],
    ];
    $ids = [];
    foreach ($dates as $row) {
        $insert->execute([$farmId, $cycleId, ...$row]);
        $ids[] = (int)$pdo->lastInsertId();
    }
    k2p03_ci_check(count($ids) === 3 && min($ids) > 0 && count(array_unique($ids)) === 3,
        'three distinct saved synthetic source IDs');
    $sourceId = $ids[0];
    $before = [];
    foreach ($ids as $id) {
        $before[$id] = b04_daily_row($pdo, $id);
    }
    k2p03_ci_equal(3, b04_count($pdo, 'layer_daily_records'), 'three original legacy dates');
    k2p03_ci_equal(0, b04_count($pdo, 'production_population_baselines'),
        'no V3 baseline present');
    k2p03_ci_equal(0, b04_count($pdo, 'production_population_movements'),
        'no original canonical movements');
    k2p03_ci_equal(null, production_population_state($pdo, $farmId, $cycleId),
        'legacy canonical state absent');
    k2p03_ci_equal(null, daily_population_continuity_expected_opening(
        $pdo, $farmId, $cycleId, 'layer', '2026-09-21'
    ), 'legacy expected opening is not canonical');
    k2p03_ci_equal([], daily_population_boundary_snapshots(
        $pdo, $farmId, $cycleId, ['2026-09-21', '2026-09-22']
    ), 'no canonical snapshots for legacy dates');

    $stage = 'NO_SOURCE_ID_PREVIEW';
    // Omit the optional source ID deliberately: it is NULL by default.
    $preview = daily_population_continuity_preview(
        $pdo, $farmId, $cycleId, 'layer', '2026-09-21', 100, 5
    );
    k2p03_ci_equal('legacy', $preview['tracking_status'],
        'preview without source ID selects legacy chain');
    k2p03_ci_equal('daily_record_chain', $preview['source'],
        'legacy chain is authoritative');
    k2p03_ci_equal(95, (int)$preview['target_closing_stock'],
        'corrected target legacy closing');
    k2p03_ci_equal(2, $preview['affected_count'],
        'exactly two later legacy records selected');
    k2p03_ci_equal([$ids[1], $ids[2]], array_column($preview['changes'], 'id'),
        'legacy preview selects real saved downstream IDs');
    k2p03_ci_equal(95, (int)$preview['changes'][0]['new_opening_stock'],
        'day two planned legacy opening');
    k2p03_ci_equal(94, (int)$preview['changes'][1]['new_opening_stock'],
        'day three planned legacy opening');
    k2p03_ci_equal(92, (int)$preview['projected_final_closing_stock'],
        'legacy final close follows unchanged later mortality');
    k2p03_ci_equal([], $preview['target_movement_totals'],
        'legacy preview creates no canonical movement summary');
    foreach ($before as $id => $row) {
        k2p03_ci_equal($row, b04_daily_row($pdo, $id),
            'read-only preview preserved original row ' . $id);
    }
    k2p03_ci_equal(0, b04_count($pdo, 'production_population_movements'),
        'preview wrote no canonical movements');

    $savedIdPlan = daily_population_continuity_preview(
        $pdo, $farmId, $cycleId, 'layer',
        '2026-09-21', 100, 5, null, false, $sourceId
    );
    k2p03_ci_equal('legacy', $savedIdPlan['tracking_status'],
        'without baseline even saved source ID remains legacy');
    k2p03_ci_equal(
        array_column($preview['changes'], 'new_opening_stock'),
        array_column($savedIdPlan['changes'], 'new_opening_stock'),
        'saved-ID no-baseline chain matches omitted-source preview');

    $stage = 'TRANSACTION_GUARD';
    $noTransactionBlocked = false;
    try {
        daily_population_continuity_apply(
            $pdo, $farmId, $cycleId, 'layer', '2026-09-21', 100, 5
        );
    } catch (DailyPopulationContinuityException $e) {
        $noTransactionBlocked = true;
    }
    k2p03_ci_check($noTransactionBlocked, 'legacy apply rejects missing caller transaction');

    $stage = 'CALLER_OWNED_APPLY';
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE layer_daily_records SET mortality = 5
             WHERE id = ? AND farm_id = ? AND cycle_id = ? AND mortality = 2'
        );
        $update->execute([$sourceId, $farmId, $cycleId]);
        k2p03_ci_equal(1, $update->rowCount(), 'caller updates its own target source');

        $result = daily_population_continuity_apply(
            $pdo, $farmId, $cycleId, 'layer', '2026-09-21', 100, 5
        );
        k2p03_ci_equal('legacy', $result['tracking_status'],
            'locked continuity remains on legacy path');
        k2p03_ci_equal(2, $result['applied_count'], 'both later openings rebased');
        k2p03_ci_equal(95, (int)b04_daily_row($pdo, $ids[1])['opening_stock'],
            'persisted legacy day two opening corrected');
        k2p03_ci_equal(94, (int)b04_daily_row($pdo, $ids[2])['opening_stock'],
            'persisted legacy day three opening corrected');
        k2p03_ci_equal(1, (int)b04_daily_row($pdo, $ids[1])['mortality'],
            'day two mortality preserved');
        k2p03_ci_equal(2, (int)b04_daily_row($pdo, $ids[2])['mortality'],
            'day three mortality preserved');
        k2p03_ci_equal(80, (int)b04_daily_row($pdo, $ids[1])['egg_production'],
            'day two egg production preserved');
        k2p03_ci_equal(78, (int)b04_daily_row($pdo, $ids[2])['egg_production'],
            'day three egg production preserved');
        b04_rate(84.21, b04_daily_row($pdo, $ids[1])['laying_rate'],
            'derived day two laying rate recalculated');
        b04_rate(82.98, b04_daily_row($pdo, $ids[2])['laying_rate'],
            'derived day three laying rate recalculated');
        k2p03_ci_equal(
            $before[$ids[1]]['feed_consumption_bags'],
            b04_daily_row($pdo, $ids[1])['feed_consumption_bags'],
            'later feed fact preserved');

        $projection = daily_population_sync_mortality(
            $pdo, $farmId, 'daily_layer_record',
            $sourceId, $cycleId, '2026-09-21', 5, null
        );
        k2p03_ci_equal('legacy_untracked', $projection['status'],
            'shared mortality writer leaves no-baseline cycle legacy');
        k2p03_ci_equal(null, $projection['movement_id'],
            'mortality writer created no canonical movement ID');
        k2p03_ci_equal(0, b04_count($pdo, 'production_population_movements'),
            'no canonical movement written inside transaction');
        k2p03_ci_equal(0, b04_count($pdo, 'production_population_baselines'),
            'no baseline invented by legacy correction');
        k2p03_ci_equal(null, production_population_state($pdo, $farmId, $cycleId),
            'no new canonical population state');
        k2p03_ci_equal(3, b04_count($pdo, 'layer_daily_records'),
            'legacy correction creates or deletes no Daily Records');
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    $stage = 'ROLLBACK';
    foreach ($before as $id => $row) {
        k2p03_ci_equal($row, b04_daily_row($pdo, $id),
            'rollback fully restores original Daily Record ' . $id);
    }
    k2p03_ci_equal(0, b04_count($pdo, 'production_population_baselines'),
        'zero canonical baselines after rollback');
    k2p03_ci_equal(0, b04_count($pdo, 'production_population_movements'),
        'zero canonical movements after rollback');
    k2p03_ci_equal(3, b04_count($pdo, 'layer_daily_records'),
        'all original legacy records retained');

    echo 'K2_P03_B04_REAL_MARIADB=PASS' . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAILURE_STAGE=' . $stage . PHP_EOL);
    fwrite(STDERR, 'FAILURE_CLASS=' . get_class($error) . PHP_EOL);
    fwrite(STDERR, 'K2_P03_B04_REAL_MARIADB=FAIL' . PHP_EOL);
    exit(1);
}
