<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 B03 — ruminant canonical species/cycle isolation.
 * Synthetic-only; see docs/C137_GC24_K2_P03_CI_B03.md.
 *
 * Production V3 cycles are species-specific. One deliberate invalid
 * cross-species sentinel inside the Goat cycle verifies the defensive SQL
 * filter, NOT a supported mixed-species product configuration.
 *
 * No config.php/init.php, no remote connections, no app route execution.
 */

require_once __DIR__ . '/k2p03_ci_isolation.php';

$stage = 'ENVIRONMENT';
$pdo = null;

function b03_row(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, farm_id, cycle_id, record_date, animal_type,
                opening_stock, mortality, feed_consumption_kg,
                water_consumption_liters
           FROM ruminant_daily_records WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Synthetic ruminant record missing.');
    }
    return $row;
}

function b03_count(PDO $pdo, string $table): int
{
    if (!in_array($table, [
        'production_population_movements',
        'ruminant_daily_records',
    ], true)) {
        throw new RuntimeException('Non-allowlisted CI table.');
    }
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

function b03_active_projection(PDO $pdo, int $farmId, int $sourceId): ?array
{
    return production_population_projection_active_snapshot(
        $pdo, $farmId, 'daily_ruminant_record', $sourceId
    );
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b03',
        'B03',
        'renee_k2p03_ci_b03'
    );
    $root = dirname(__DIR__, 2);
    k2p03_ci_equal(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        k2p03_ci_blob($root . '/lib/daily_population_continuity.php'),
        'original corrected continuity blob unchanged'
    );
    k2p03_ci_equal(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        k2p03_ci_blob($root . '/database_schema.sql'),
        'original 69-table schema blob unchanged'
    );

    $stage = 'FRESH_DATABASE';
    $pdo = k2p03_ci_connection('renee_k2p03_ci_b03');
    require_once $root . '/lib/daily_population_continuity.php';
    require_once $root . '/lib/daily_population_sync.php';

    $stage = 'SPECIES_CYCLE_SEED';
    $pdo->exec(
        "INSERT INTO farms (name, slug)
         VALUES ('K2P03 B03 synthetic ruminants', 'ci-b03-only')"
    );
    $farmId = (int)$pdo->lastInsertId();
    k2p03_ci_check($farmId > 0, 'synthetic farm persisted');

    $cycleStmt = $pdo->prepare(
        "INSERT INTO production_cycles
           (farm_id, cycle_code, farm_type, production_type,
            status, start_date, opening_headcount)
         VALUES (?, ?, 'ruminant', ?, 'active', '2026-09-21', ?)"
    );
    $cycleStmt->execute([$farmId, 'b03-goat', 'goat', 60]);
    $goatCycleId = (int)$pdo->lastInsertId();
    $cycleStmt->execute([$farmId, 'b03-cattle', 'cattle', 30]);
    $cattleCycleId = (int)$pdo->lastInsertId();
    k2p03_ci_check(
        $goatCycleId > 0 && $cattleCycleId > 0 && $goatCycleId !== $cattleCycleId,
        'distinct species-specific canonical cycle IDs'
    );

    $baselineStmt = $pdo->prepare(
        "INSERT INTO production_population_baselines
           (farm_id, cycle_id, baseline_date, baseline_quantity, baseline_source)
         VALUES (?, ?, '2026-09-21', ?, 'cycle_opening')"
    );
    $baselineStmt->execute([$farmId, $goatCycleId, 60]);
    $baselineStmt->execute([$farmId, $cattleCycleId, 30]);

    $insert = $pdo->prepare(
        'INSERT INTO ruminant_daily_records
           (farm_id, cycle_id, record_date, animal_type,
            opening_stock, mortality, feed_consumption_kg,
            water_consumption_liters)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $dates = ['2026-09-21', '2026-09-22', '2026-09-23'];
    $seed = [
        'goat' => [
            [$dates[0], 60, 2, 2.50, 8.0],
            [$dates[1], 58, 1, 2.75, 9.0],
            [$dates[2], 57, 2, 2.25, 8.5],
        ],
        'cattle' => [
            [$dates[0], 30, 1, 10.0, 40.0],
            [$dates[1], 29, 1, 10.5, 42.0],
            [$dates[2], 28, 1, 9.5, 43.0],
        ],
    ];

    $ids = ['goat' => [], 'cattle' => []];
    foreach (['goat' => $goatCycleId, 'cattle' => $cattleCycleId] as $species => $cycleId) {
        foreach ($seed[$species] as [$date, $opening, $mortality, $feed, $water]) {
            $insert->execute([
                $farmId, $cycleId, $date, $species,
                $opening, $mortality, $feed, $water,
            ]);
            $id = (int)$pdo->lastInsertId();
            $ids[$species][] = $id;
            $projection = daily_population_sync_mortality(
                $pdo, $farmId, 'daily_ruminant_record',
                $id, $cycleId, $date, $mortality, null
            );
            k2p03_ci_equal(
                'created',
                $projection['status'],
                $species . ' ' . $date . ' durable mortality source synced'
            );
        }
    }

    k2p03_ci_equal(6, b03_count($pdo, 'ruminant_daily_records'), 'six species-valid Daily Records');
    k2p03_ci_equal(6, b03_count($pdo, 'production_population_movements'), 'six durable mortality movements');

    // Production-cycle validity is enforced by expected_opening(), which
    // rejects a Cattle Daily Record against the Goat production cycle.
    k2p03_ci_equal(
        60,
        daily_population_continuity_expected_opening(
            $pdo, $farmId, $goatCycleId, 'ruminant', $dates[0], 'goat'
        )['opening_stock'],
        'Goat opening matches its own V3 baseline'
    );
    k2p03_ci_equal(
        30,
        daily_population_continuity_expected_opening(
            $pdo, $farmId, $cattleCycleId, 'ruminant', $dates[0], 'cattle'
        )['opening_stock'],
        'Cattle opening matches its own V3 baseline'
    );
    $wrongSpeciesBlocked = false;
    try {
        daily_population_continuity_expected_opening(
            $pdo, $farmId, $goatCycleId, 'ruminant', $dates[0], 'cattle'
        );
    } catch (DailyPopulationContinuityException $e) {
        $wrongSpeciesBlocked = true;
    }
    k2p03_ci_check($wrongSpeciesBlocked, 'wrong-species canonical cycle rejected by production guard');

    // This intentionally invalid source row in the Goat cycle must never be
    // included in the Goat continuity plan. It has no mortality projection;
    // it does not represent a supported Cattle-in-Goat-cycle production setup.
    $insert->execute([
        $farmId, $goatCycleId, $dates[1], 'cattle', 777, 0, 3.5, 14.0,
    ]);
    $sentinelId = (int)$pdo->lastInsertId();
    $sentinelBefore = b03_row($pdo, $sentinelId);
    k2p03_ci_equal(
        7,
        b03_count($pdo, 'ruminant_daily_records'),
        'deliberate mismatched-species sentinel exists'
    );

    $cattleBefore = [];
    foreach ($ids['cattle'] as $id) {
        $cattleBefore[$id] = b03_row($pdo, $id);
    }

    $stage = 'GOAT_SPECIES_PREVIEW';
    $sourceId = $ids['goat'][0];
    $preview = daily_population_continuity_preview(
        $pdo, $farmId, $goatCycleId, 'ruminant',
        $dates[0], 60, 5, 'goat', false, $sourceId
    );
    k2p03_ci_equal('canonical', $preview['tracking_status'], 'saved Goat source selects V3 canonical plan');
    k2p03_ci_equal('goat', $preview['animal_type'], 'Goat plan retains animal type');
    k2p03_ci_equal(2, $preview['affected_count'], 'only later Goat rows selected');
    k2p03_ci_equal(
        [$ids['goat'][1], $ids['goat'][2]],
        array_column($preview['changes'], 'id'),
        'planned changed IDs are exactly the two Goat rows'
    );
    k2p03_ci_equal(55, (int)$preview['changes'][0]['new_opening_stock'], 'Goat day-two corrected opening');
    k2p03_ci_equal(54, (int)$preview['changes'][1]['new_opening_stock'], 'Goat day-three corrected opening');
    k2p03_ci_equal(52, (int)$preview['projected_final_closing_stock'], 'Goat correction final canonical closing');
    k2p03_ci_equal(58, (int)b03_row($pdo, $ids['goat'][1])['opening_stock'], 'preview writes no Goat records');
    k2p03_ci_equal($sentinelBefore, b03_row($pdo, $sentinelId), 'preview leaves mismatched-species sentinel untouched');
    foreach ($cattleBefore as $id => $row) {
        k2p03_ci_equal($row, b03_row($pdo, $id), 'preview leaves original Cattle cycle row ' . $id . ' untouched');
    }

    $stage = 'TRANSACTIONAL_SPECIES_APPLY';
    $pdo->beginTransaction();
    try {
        $target = $pdo->prepare(
            'UPDATE ruminant_daily_records SET mortality=5
              WHERE id=? AND farm_id=? AND cycle_id=?
                AND LOWER(animal_type)=? AND mortality=2'
        );
        $target->execute([$sourceId, $farmId, $goatCycleId, 'goat']);
        k2p03_ci_equal(1, $target->rowCount(), 'caller updates only Goat target mortality');

        $applied = daily_population_continuity_apply(
            $pdo, $farmId, $goatCycleId, 'ruminant',
            $dates[0], 60, 5, 'goat', $sourceId
        );
        k2p03_ci_equal('canonical', $applied['tracking_status'], 'locked Goat continuity remains canonical');
        k2p03_ci_equal(2, $applied['applied_count'], 'two later Goat openings updated');
        k2p03_ci_equal(55, (int)b03_row($pdo, $ids['goat'][1])['opening_stock'], 'Goat day-two row rebased');
        k2p03_ci_equal(54, (int)b03_row($pdo, $ids['goat'][2])['opening_stock'], 'Goat day-three row rebased');
        k2p03_ci_equal(1, (int)b03_row($pdo, $ids['goat'][1])['mortality'], 'Goat day-two mortality unchanged');
        k2p03_ci_equal(2, (int)b03_row($pdo, $ids['goat'][2])['mortality'], 'Goat day-three mortality unchanged');
        k2p03_ci_equal(
            '2.75',
            (string)b03_row($pdo, $ids['goat'][1])['feed_consumption_kg'],
            'Goat feed fact unchanged'
        );

        $sync = daily_population_sync_mortality(
            $pdo, $farmId, 'daily_ruminant_record',
            $sourceId, $goatCycleId, $dates[0], 5, null
        );
        k2p03_ci_equal('corrected', $sync['status'], 'Goat mortality source projection corrected');
        k2p03_ci_equal(2, $sync['source_version'], 'Goat mortality source-version increments once');
        k2p03_ci_check((int)$sync['reversal_id'] > 0, 'old Goat mortality compensated');

        $goat = daily_population_boundary_snapshots($pdo, $farmId, $goatCycleId, $dates);
        $cattle = daily_population_boundary_snapshots($pdo, $farmId, $cattleCycleId, $dates);
        k2p03_ci_equal(55, (int)$goat[$dates[1]]['opening_quantity'], 'canonical Goat day-two opening matches stored row');
        k2p03_ci_equal(54, (int)$goat[$dates[2]]['opening_quantity'], 'canonical Goat day-three opening matches stored row');
        k2p03_ci_equal(52, (int)$goat[$dates[2]]['closing_quantity'], 'Goat canonical final population 52');
        k2p03_ci_equal(29, (int)$cattle[$dates[1]]['opening_quantity'], 'Cattle canonical day-two opening independent');
        k2p03_ci_equal(27, (int)$cattle[$dates[2]]['closing_quantity'], 'Cattle canonical final population unchanged at 27');
        k2p03_ci_equal(8, b03_count($pdo, 'production_population_movements'), 'only two expected Goat projection history rows appended');

        foreach ($cattleBefore as $id => $row) {
            k2p03_ci_equal($row, b03_row($pdo, $id), 'Cattle Daily Record unchanged in correction transaction ' . $id);
            $cattleMovement = b03_active_projection($pdo, $farmId, $id);
            k2p03_ci_check(is_array($cattleMovement), 'Cattle mortality source stays active ' . $id);
            k2p03_ci_equal(1, (int)$cattleMovement['source_version'], 'Cattle mortality source version still one ' . $id);
        }
        k2p03_ci_equal(
            $sentinelBefore,
            b03_row($pdo, $sentinelId),
            'wrong-species sentinel in same cycle remains unchanged'
        );
        k2p03_ci_equal(7, b03_count($pdo, 'ruminant_daily_records'), 'no cross-species Daily Record deleted or created');
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    $stage = 'ROLLBACK_RECONCILIATION';
    k2p03_ci_equal(2, (int)b03_row($pdo, $sourceId)['mortality'], 'Goat source mortality restored by rollback');
    k2p03_ci_equal(58, (int)b03_row($pdo, $ids['goat'][1])['opening_stock'], 'Goat day-two opening rolled back');
    k2p03_ci_equal(57, (int)b03_row($pdo, $ids['goat'][2])['opening_stock'], 'Goat day-three opening rolled back');
    k2p03_ci_equal(6, b03_count($pdo, 'production_population_movements'), 'Goat reversal/source-version rows rolled back');
    $restored = b03_active_projection($pdo, $farmId, $sourceId);
    k2p03_ci_check(is_array($restored), 'original Goat mortality source remains active');
    k2p03_ci_equal(1, (int)$restored['source_version'], 'Goat source version one restored');
    k2p03_ci_equal(-2, (int)$restored['quantity_delta'], 'original Goat mortality quantity restored');
    k2p03_ci_equal($sentinelBefore, b03_row($pdo, $sentinelId), 'sentinel unchanged after rollback');
    foreach ($cattleBefore as $id => $row) {
        k2p03_ci_equal($row, b03_row($pdo, $id), 'Cattle untouched after rollback ' . $id);
    }
    k2p03_ci_equal(55, (int)production_population_state($pdo, $farmId, $goatCycleId)['quantity'], 'original Goat final balance 55');
    k2p03_ci_equal(27, (int)production_population_state($pdo, $farmId, $cattleCycleId)['quantity'], 'original Cattle final balance 27');
    echo 'K2_P03_B03_REAL_MARIADB=PASS' . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // No SQL exception, DSN, credentials, tenant data, or stack traces printed.
    fwrite(STDERR, 'FAILURE_STAGE=' . $stage . PHP_EOL);
    fwrite(STDERR, 'FAILURE_CLASS=' . get_class($e) . PHP_EOL);
    fwrite(STDERR, 'K2_P03_B03_REAL_MARIADB=FAIL' . PHP_EOL);
    exit(1);
}
