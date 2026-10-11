<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 — B02 real-MariaDB Broiler mixed exits.
 *
 * Real shared-service path: Sale physical-effect synchronizer, Slaughter
 * canonical projection, paired Transfer writer, Broiler continuity planner
 * and transactional apply, and mortality projection versioning.
 *
 * The Slaughter batch is a synthetic durable source row; this does not execute
 * the separate poultry-slaughter costing, inventory or output workflow.
 */
require_once __DIR__ . '/k2p03_ci_isolation.php';

$stage = 'ENVIRONMENT';
$pdo = null;

function b02_row(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, opening_stock, mortality, record_date
           FROM broiler_daily_records WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Synthetic Broiler Daily Record missing.');
    }
    return $row;
}

function b02_snapshot(PDO $pdo, int $farmId, int $cycleId, array $dates): array
{
    return daily_population_boundary_snapshots(
        $pdo, $farmId, $cycleId, $dates
    );
}

function b02_active_count(
    PDO $pdo,
    int $farmId,
    int $cycleId,
    string $movementType
): int {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
           FROM production_population_movements m
          WHERE m.farm_id=? AND m.cycle_id=? AND m.movement_type=?
            AND m.reversal_of_id IS NULL
            AND NOT EXISTS (
                SELECT 1 FROM production_population_movements r
                 WHERE r.farm_id=m.farm_id
                   AND r.cycle_id=m.cycle_id
                   AND r.reversal_of_id=m.id
            )"
    );
    $stmt->execute([$farmId, $cycleId, $movementType]);
    return (int)$stmt->fetchColumn();
}

function b02_balance(PDO $pdo, int $farmId, int $cycleId): int
{
    $state = production_population_state($pdo, $farmId, $cycleId);
    if (!is_array($state)) {
        throw new RuntimeException('Synthetic canonical state not tracked.');
    }
    return (int)$state['quantity'];
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b02', 'B02', 'renee_k2p03_ci_b02'
    );

    $root = dirname(__DIR__, 2);
    k2p03_ci_equal(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        k2p03_ci_blob($root . '/lib/daily_population_continuity.php'),
        'pinned corrected continuity PHP'
    );
    k2p03_ci_equal(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        k2p03_ci_blob($root . '/database_schema.sql'),
        'pinned fresh-install schema'
    );

    $stage = 'FRESH_SCHEMA';
    $pdo = k2p03_ci_connection('renee_k2p03_ci_b02');

    require_once $root . '/lib/daily_population_continuity.php';
    require_once $root . '/lib/production_population_transfer.php';
    require_once $root . '/lib/sale_population_effects.php';

    $stage = 'SYNTHETIC_SOURCES';
    $pdo->exec(
        "INSERT INTO farms (name, slug)
         VALUES ('K2 P03 B02 synthetic', 'k2p03-b02-synthetic')"
    );
    $farmId = (int)$pdo->lastInsertId();

    $cycle = $pdo->prepare(
        "INSERT INTO production_cycles
           (farm_id, cycle_code, farm_type, production_type,
            status, start_date, opening_headcount)
         VALUES (?, ?, 'poultry', 'broiler', 'active', '2026-09-21', ?)"
    );
    $cycle->execute([$farmId, 'ci-b02-donor', 200]);
    $donor = (int)$pdo->lastInsertId();
    $cycle->execute([$farmId, 'ci-b02-recipient', 25]);
    $recipient = (int)$pdo->lastInsertId();

    $baseline = $pdo->prepare(
        "INSERT INTO production_population_baselines
           (farm_id, cycle_id, baseline_date, baseline_quantity, baseline_source)
         VALUES (?, ?, '2026-09-21', ?, 'cycle_opening')"
    );
    $baseline->execute([$farmId, $donor, 200]);
    $baseline->execute([$farmId, $recipient, 25]);

    $daily = $pdo->prepare(
        "INSERT INTO broiler_daily_records
           (farm_id, cycle_id, record_date, opening_stock, mortality, birds_age)
         VALUES (?, ?, ?, ?, ?, 8)"
    );
    $dates = ['2026-09-21', '2026-09-22', '2026-09-23'];
    $dailySeed = [
        [$dates[0], 200, 2],
        [$dates[1], 198, 1],
        [$dates[2], 197, 2],
    ];
    $dailyIds = [];
    foreach ($dailySeed as [$date, $opening, $mortality]) {
        $daily->execute([$farmId, $donor, $date, $opening, $mortality]);
        $sourceId = (int)$pdo->lastInsertId();
        $dailyIds[] = $sourceId;
        production_population_record_movement(
            $pdo, $farmId, $donor, 'mortality', $date,
            $mortality, 'daily_broiler_record', $sourceId,
            1, null, null, null
        );
    }
    k2p03_ci_check(count(array_unique($dailyIds)) === 3, 'three persisted unique Broiler source IDs');

    // Sales record is a financial source; its *explicit* effect owns headcount.
    $sale = $pdo->prepare(
        "INSERT INTO sales_records
           (public_reference, farm_id, sale_date, farm_type,
            production_type, attribution_scope, cycle_id,
            product_type, quantity, unit_of_measure, unit_price)
         VALUES ('CI-B02-SALE-001', ?, '2026-09-22', 'poultry',
                 'broiler', 'cycle', ?, 'Live Broilers', 30, 'birds', 1000)"
    );
    $sale->execute([$farmId, $donor]);
    $saleId = (int)$pdo->lastInsertId();

    $saleResult = sale_population_effect_sync(
        $pdo, $farmId, $saleId,
        [['cycle_id' => $donor, 'population_quantity' => 30]], null
    );
    k2p03_ci_equal(1, $saleResult['effect_count'], 'one explicitly recorded physical Sale effect');

    $saleEffectStmt = $pdo->prepare(
        'SELECT id, population_quantity FROM sale_population_effects
         WHERE farm_id=? AND sale_id=? AND cycle_id=? AND is_active=1'
    );
    $saleEffectStmt->execute([$farmId, $saleId, $donor]);
    $saleEffect = $saleEffectStmt->fetch(PDO::FETCH_ASSOC);
    k2p03_ci_check(is_array($saleEffect), 'Sale effect has a durable source row');
    k2p03_ci_equal(30, (int)$saleEffect['population_quantity'], 'Sale removal quantity');

    // Slaughter's durable-source economics are irrelevant to B02; freeze a
    // minimal synthetic source row without calling the costing writer.
    $slaughter = $pdo->prepare(
        "INSERT INTO poultry_slaughter_batches
           (farm_id, cycle_id, batch_code, request_token, slaughter_date,
            bird_count, population_before, capital_pool_available_before,
            operating_pool_available_before, capital_basis_transferred,
            embedded_operating_basis_transferred, full_cost_basis_amount,
            cost_basis_method, cost_basis_snapshot_at,
            cost_basis_provenance_fingerprint, cost_basis_provenance_json)
         VALUES (?, ?, 'ci-b02-slaughter', ?, '2026-09-22',
                 20, 168, 0, 0, 0, 0, 0, 'synthetic-ci-only', NOW(), ?, '{}')"
    );
    $slaughter->execute([$farmId, $donor, str_repeat('b', 64), str_repeat('a', 64)]);
    $slaughterId = (int)$pdo->lastInsertId();
    k2p03_ci_check($slaughterId > 0, 'Slaughter durable batch ID inserted');

    $slaughterResult = production_population_projection_sync(
        $pdo, $farmId, $donor, 'poultry_slaughter', $slaughterId,
        [
            'movement_type' => 'slaughter',
            'movement_date' => $dates[1],
            'quantity' => 20,
            'notes' => 'CI synthetic slaughter physical exit only',
        ],
        null
    );
    k2p03_ci_equal('created', $slaughterResult['status'], 'Slaughter physical projection created');

    // Production transfer service creates its own durable paired leg IDs and
    // distinct signed canonical OUT / IN movements atomically.
    $transfer = production_population_transfer_record(
        $pdo, $farmId, $donor, $recipient, $dates[2],
        15, 'CI synthetic paired Broiler transfer', null, str_repeat('c', 64)
    );
    k2p03_ci_check(is_array($transfer), 'paired Transfer service returned a snapshot');
    k2p03_ci_equal(40, b02_balance($pdo, $farmId, $recipient), 'recipient gains 15 birds exactly once');
    k2p03_ci_equal(1, b02_active_count($pdo, $farmId, $donor, 'sale'), 'one active Sale exit');
    k2p03_ci_equal(1, b02_active_count($pdo, $farmId, $donor, 'slaughter'), 'one active Slaughter exit');
    k2p03_ci_equal(1, b02_active_count($pdo, $farmId, $donor, 'transfer_out'), 'one active Transfer OUT');
    k2p03_ci_equal(1, b02_active_count($pdo, $farmId, $recipient, 'transfer_in'), 'one active Transfer IN');

    $stage = 'PRE_CORRECTION_RECONCILIATION';
    $before = b02_snapshot($pdo, $farmId, $donor, $dates);
    k2p03_ci_equal(200, (int)$before[$dates[0]]['opening_quantity'], 'initial day one opening');
    k2p03_ci_equal(198, (int)$before[$dates[0]]['closing_quantity'], 'initial day one closing');
    k2p03_ci_equal(198, (int)$before[$dates[1]]['opening_quantity'], 'Sale/Slaughter day opening');
    k2p03_ci_equal(147, (int)$before[$dates[1]]['closing_quantity'], 'Sale/Slaughter day canonical closing');
    k2p03_ci_equal(147, (int)$before[$dates[2]]['opening_quantity'], 'Transfer day canonical opening');
    k2p03_ci_equal(130, (int)$before[$dates[2]]['closing_quantity'], 'Transfer day canonical closing');
    k2p03_ci_equal(130, b02_balance($pdo, $farmId, $donor), 'initial donor ledger reconciliation');

    $stage = 'CANONICAL_MIXED_EXIT_PREVIEW';
    $targetSourceId = $dailyIds[0];
    $preview = daily_population_continuity_preview(
        $pdo, $farmId, $donor, 'broiler', $dates[0],
        200, 5, null, false, $targetSourceId
    );
    k2p03_ci_equal('canonical', $preview['tracking_status'], 'real canonical rather than legacy planning');
    k2p03_ci_equal(2, $preview['affected_count'], 'later Broiler dates both require rebasing');
    k2p03_ci_equal(195, (int)$preview['changes'][0]['new_opening_stock'], 'corrected day two opening');
    k2p03_ci_equal(144, (int)$preview['changes'][1]['new_opening_stock'], 'corrected day three opening includes mixed exits');
    k2p03_ci_equal(127, (int)$preview['projected_final_closing_stock'], 'mixed exits and mortality counted once');
    k2p03_ci_equal(198, (int)b02_row($pdo, $dailyIds[1])['opening_stock'], 'preview wrote no Broiler row');

    $stage = 'CALLER_OWNED_TRANSACTION';
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE broiler_daily_records SET mortality=5
             WHERE id=? AND farm_id=? AND cycle_id=? AND mortality=2'
        );
        $update->execute([$targetSourceId, $farmId, $donor]);
        k2p03_ci_equal(1, $update->rowCount(), 'caller writes target mortality');

        $applied = daily_population_continuity_apply(
            $pdo, $farmId, $donor, 'broiler', $dates[0],
            200, 5, null, $targetSourceId
        );
        k2p03_ci_equal('canonical', $applied['tracking_status'], 'locked canonical Broiler continuity path');
        k2p03_ci_equal(2, $applied['applied_count'], 'both Broiler downstream updates applied');
        k2p03_ci_equal(195, (int)b02_row($pdo, $dailyIds[1])['opening_stock'], 'saved day two opening');
        k2p03_ci_equal(144, (int)b02_row($pdo, $dailyIds[2])['opening_stock'], 'saved day three opening');
        k2p03_ci_equal(1, (int)b02_row($pdo, $dailyIds[1])['mortality'], 'later day two mortality unchanged');
        k2p03_ci_equal(2, (int)b02_row($pdo, $dailyIds[2])['mortality'], 'later day three mortality unchanged');

        $mortalityResult = production_population_projection_sync(
            $pdo, $farmId, $donor, 'daily_broiler_record', $targetSourceId,
            [
                'movement_type' => 'mortality',
                'movement_date' => $dates[0],
                'quantity' => 5,
            ],
            null,
            'CI B02 synthetic historical mortality correction'
        );
        k2p03_ci_equal('corrected', $mortalityResult['status'], 'canonical mortality projection corrected');
        k2p03_ci_equal(2, $mortalityResult['source_version'], 'durable source receives second version');
        k2p03_ci_check((int)$mortalityResult['reversal_id'] > 0, 'old mortality movement compensated');

        $after = b02_snapshot($pdo, $farmId, $donor, $dates);
        k2p03_ci_equal(195, (int)$after[$dates[0]]['closing_quantity'], 'corrected day one closing');
        k2p03_ci_equal(195, (int)$after[$dates[1]]['opening_quantity'], 'corrected Sale/Slaughter day opening');
        k2p03_ci_equal(144, (int)$after[$dates[1]]['closing_quantity'], 'corrected Sale/Slaughter day closing');
        k2p03_ci_equal(144, (int)$after[$dates[2]]['opening_quantity'], 'corrected Transfer day opening');
        k2p03_ci_equal(127, (int)$after[$dates[2]]['closing_quantity'], 'corrected Transfer day closing');
        k2p03_ci_equal(127, b02_balance($pdo, $farmId, $donor), 'corrected donor ledger equals final closing');
        k2p03_ci_equal(40, b02_balance($pdo, $farmId, $recipient), 'other cycle still receives transfer once');
        k2p03_ci_equal(1, b02_active_count($pdo, $farmId, $donor, 'sale'), 'Sale movement not duplicated');
        k2p03_ci_equal(1, b02_active_count($pdo, $farmId, $donor, 'slaughter'), 'Slaughter movement not duplicated');
        k2p03_ci_equal(1, b02_active_count($pdo, $farmId, $donor, 'transfer_out'), 'Transfer OUT not duplicated');
        k2p03_ci_equal(1, b02_active_count($pdo, $farmId, $recipient, 'transfer_in'), 'Transfer IN not duplicated');
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    $stage = 'ROLLBACK_AND_SOURCE_RECONCILIATION';
    k2p03_ci_equal(2, (int)b02_row($pdo, $dailyIds[0])['mortality'], 'target mortality rolled back');
    k2p03_ci_equal(198, (int)b02_row($pdo, $dailyIds[1])['opening_stock'], 'day two opening rolled back');
    k2p03_ci_equal(197, (int)b02_row($pdo, $dailyIds[2])['opening_stock'], 'day three opening rolled back');
    k2p03_ci_equal(130, b02_balance($pdo, $farmId, $donor), 'original donor ledger restored');
    k2p03_ci_equal(40, b02_balance($pdo, $farmId, $recipient), 'recipient transfer still balanced');
    k2p03_ci_equal(
        1,
        b02_active_count($pdo, $farmId, $donor, 'mortality')
        + b02_active_count($pdo, $farmId, $recipient, 'mortality') >= 1 ? 1 : 0,
        'mortality movement remains present'
    );

    echo 'K2_P03_B02_REAL_MARIADB=PASS' . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAILURE_STAGE=' . $stage . PHP_EOL);
    fwrite(STDERR, 'FAILURE_CLASS=' . get_class($error) . PHP_EOL);
    fwrite(STDERR, 'K2_P03_B02_REAL_MARIADB=FAIL' . PHP_EOL);
    exit(1);
}
