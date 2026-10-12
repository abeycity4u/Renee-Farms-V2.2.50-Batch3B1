<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 B07 — negative later canonical physical balance.
 * Only synthetic GitHub-hosted MariaDB, no config.php, no external network.
 *
 * Later mortality/eggs remain valid at corrected openings; a separate
 * durable, source-linked live-bird SALE makes corrected canonical stock
 * negative on a future day, distinguishing B07 from B05/B06.
 */
require_once __DIR__ . '/k2p03_ci_isolation.php';

$stage='GUARDS';
$pdo=null;

function b07_rows(PDO $pdo, int $farmId, string $table): array
{
    if (!in_array($table, [
        'layer_daily_records',
        'production_population_baselines',
        'production_population_movements',
        'sales_records',
        'sale_population_effects',
        'sales_allocations',
    ], true)) {
        throw new RuntimeException('B07 snapshot table is not allowlisted.');
    }
    $stmt=$pdo->prepare('SELECT * FROM ' . $table . ' WHERE farm_id=? ORDER BY id');
    $stmt->execute([$farmId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function b07_assert_unchanged(PDO $pdo, int $farmId, array $snapshots, string $label): void
{
    foreach($snapshots as $table=>$expected) {
        k2p03_ci_equal(
            $expected,b07_rows($pdo,$farmId,$table),
            $label . ': ' . $table . ' unchanged'
        );
    }
}

function b07_row(PDO $pdo, int $farmId, int $id): array
{
    $stmt=$pdo->prepare(
        'SELECT id,farm_id,cycle_id,record_date,opening_stock,mortality,
                egg_production,laying_rate
           FROM layer_daily_records
          WHERE id=? AND farm_id=?'
    );
    $stmt->execute([$id,$farmId]);
    $r=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($r)) {
        throw new RuntimeException('B07 original synthetic Daily Record missing.');
    }
    return $r;
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b07','B07','renee_k2p03_ci_b07'
    );
    $root=dirname(__DIR__,2);
    k2p03_ci_equal(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        k2p03_ci_blob($root.'/lib/daily_population_continuity.php'),
        'B07 existing canonical continuity is source pinned'
    );
    k2p03_ci_equal(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        k2p03_ci_blob($root.'/database_schema.sql'),
        'B07 69-table schema is source pinned'
    );

    $stage='DISPOSABLE_MARIADB';
    $pdo=k2p03_ci_connection('renee_k2p03_ci_b07');
    require_once $root.'/lib/daily_population_continuity.php';
    require_once $root.'/lib/daily_population_sync.php';
    require_once $root.'/lib/sale_population_effects.php';

    $stage='SYNTHETIC_CANONICAL_LAYER_SOURCE';
    $pdo->exec(
        "INSERT INTO farms(name,slug)
         VALUES('B07 negative historical boundary fixture','k2p03-b07-synthetic')"
    );
    $farmId=(int)$pdo->lastInsertId();
    k2p03_ci_check($farmId>0,'new synthetic B07 farm exists');

    $cycleInsert=$pdo->prepare(
        "INSERT INTO production_cycles
           (farm_id,cycle_code,farm_type,production_type,status,start_date,opening_headcount)
         VALUES (?,'b07-layer','poultry','layer','active','2026-09-21',30)"
    );
    $cycleInsert->execute([$farmId]);
    $cycleId=(int)$pdo->lastInsertId();
    k2p03_ci_check($cycleId>0,'synthetic B07 Layer cycle exists');

    $pdo->prepare(
        "INSERT INTO production_population_baselines
           (farm_id,cycle_id,baseline_date,baseline_quantity,baseline_source)
         VALUES (?,?,'2026-09-21',30,'cycle_opening')"
    )->execute([$farmId,$cycleId]);
    k2p03_ci_equal(
        30,(int)production_population_state(
            $pdo,$farmId,$cycleId,'2026-09-21'
        )['quantity'],'source-backed V3 baseline 30'
    );

    $stmt=$pdo->prepare(
        'INSERT INTO layer_daily_records
          (farm_id,cycle_id,record_date,opening_stock,mortality,
           egg_production,crates_count,laying_rate,birds_age)
         VALUES (?,?,?,?,?,?,0,?,25)'
    );
    $daily=[
        ['2026-09-21',30,2,5,16.67],
        ['2026-09-22',28,3,5,17.86],
        ['2026-09-23',25,1,5,20.00],
    ];
    $ids=[];
    foreach($daily as $row) {
        $stmt->execute([$farmId,$cycleId,...$row]);
        $id=(int)$pdo->lastInsertId();
        $ids[]=$id;
        $p=daily_population_sync_mortality(
            $pdo,$farmId,'daily_layer_record',
            $id,$cycleId,$row[0],$row[2],null
        );
        k2p03_ci_equal('created',$p['status'],
            'actual original Daily Record mortality source projected '.$row[0]);
    }
    k2p03_ci_equal(3,count(array_unique($ids)),
        'three distinct durable original source IDs');

    $stage='REAL_SALE_POPULATION_EFFECT';
    // A real persisted financial sale, with a separate explicitly owned
    // population effect. Never infer physical exits from sale product text.
    $pdo->prepare(
        "INSERT INTO sales_records
           (public_reference,farm_id,sale_date,farm_type,production_type,
            attribution_scope,cycle_id,product_type,quantity,unit_of_measure,unit_price)
         VALUES ('B07-LIVE-SALE-001',?,'2026-09-23','poultry','layer',
                 'cycle',?,'live_birds',20,'bird',1000.00)"
    )->execute([$farmId,$cycleId]);
    $saleId=(int)$pdo->lastInsertId();
    k2p03_ci_check($saleId>0,'durable financial sale exists as one source');

    // The shared Sales population effect service owns both the durable
    // sale_population_effects record and the canonical -20 sale movement.
    $saleSync=sale_population_effect_sync(
        $pdo,$farmId,$saleId,
        [['cycle_id'=>$cycleId,'population_quantity'=>20]],null
    );
    k2p03_ci_equal('synchronized',$saleSync['status'],
        'existing shared sale service projects physical live-bird exit');
    k2p03_ci_equal(1,$saleSync['effect_count'],
        'one source-owned physical sale effect');
    $effect=b07_rows($pdo,$farmId,'sale_population_effects');
    k2p03_ci_equal(1,count($effect),
        'sale effect is an independently durable canonical source');
    k2p03_ci_equal(20,(int)$effect[0]['population_quantity'],
        'sale effect headcount is 20, not inferred from money');
    k2p03_ci_equal(1,(int)$effect[0]['is_active'],
        'physical sale effect is currently active');

    $current=production_population_state($pdo,$farmId,$cycleId,'2026-09-23');
    k2p03_ci_equal(4,(int)$current['quantity'],
        'original cycle closes at four live birds after mortality and sale');
    $origBoundary=daily_population_boundary_snapshots(
        $pdo,$farmId,$cycleId,
        ['2026-09-21','2026-09-22','2026-09-23']
    );
    k2p03_ci_equal(28,(int)$origBoundary['2026-09-22']['opening_quantity'],
        'original second-day opening is 28');
    k2p03_ci_equal(25,(int)$origBoundary['2026-09-23']['opening_quantity'],
        'original sale-date opening is 25');
    k2p03_ci_equal(4,(int)$origBoundary['2026-09-23']['closing_quantity'],
        'original sale-date closing remains positive');

    $snapshotTables=[
        'layer_daily_records',
        'production_population_baselines',
        'production_population_movements',
        'sales_records',
        'sale_population_effects',
        'sales_allocations',
    ];
    $before=[];
    foreach($snapshotTables as $table) {
        $before[$table]=b07_rows($pdo,$farmId,$table);
    }
    k2p03_ci_equal(3,count($before['layer_daily_records']),
        'three durable source facts before rejected correction');
    k2p03_ci_equal(4,count($before['production_population_movements']),
        'three mortality projections and one source-owned sale movement');
    k2p03_ci_equal(1,count($before['sale_population_effects']),
        'only one durable sale-population effect before rejection');

    $stage='NEGATIVE_CANONICAL_BOUNDARY_PREVIEW';
    /*
     * Valid later individual mortality: on day two 3 <= corrected 20,
     * on day three 1 <= corrected 17; five eggs also fit both openings.
     * But 30-10-3-1-20 = -4 once the later physical sale is included.
     * The shared canonical boundary must fail before returning any plan.
     */
    k2p03_ci_check(3<=20&&1<=17&&5<=17,
        'later mortality and eggs are valid relative to corrected openings');
    $boundaryFailed=false;
    try {
        daily_population_boundary_snapshots(
            $pdo,$farmId,$cycleId,
            ['2026-09-21','2026-09-22','2026-09-23'],
            [
                'source_type'=>'daily_layer_record',
                'source_id'=>$ids[0],
                'record_date'=>'2026-09-21',
                'mortality'=>10,
                'for_update'=>false,
            ]
        );
    } catch(DailyPopulationBoundaryException $e) {
        $boundaryFailed=str_contains(
            strtolower($e->getMessage()),'negative on 2026-09-23'
        );
    }
    k2p03_ci_check($boundaryFailed,
        'canonical boundary rejects future negative closing at sale date');

    $previewFailed=false;
    try {
        daily_population_continuity_preview(
            $pdo,$farmId,$cycleId,'layer','2026-09-21',
            30,10,null,false,$ids[0]
        );
    } catch(DailyPopulationBoundaryException $e) {
        $previewFailed=str_contains(
            strtolower($e->getMessage()),'negative on 2026-09-23'
        );
    }
    k2p03_ci_check($previewFailed,
        'shared daily continuity preview propagates negative boundary rejection');
    b07_assert_unchanged($pdo,$farmId,$before,
        'advisory negative preview has no side effects');

    $stage='DIRECT_CANONICAL_WRITER_GUARD';
    // This is the shared population writer's own later-date balance guard,
    // independent of the continuity preview and of mortality>opening.
    $negativeWriterRejected=false;
    $pdo->beginTransaction();
    try {
        $baseline=production_population_lock_baseline(
            $pdo,$farmId,$cycleId
        );
        k2p03_ci_check(is_array($baseline),
            'canonical baseline is locked for read-only delta guard');
        production_population_project_delta_locked(
            $pdo,$baseline,'2026-09-21',-8
        );
    } catch(ProductionPopulationException $e) {
        $negativeWriterRejected=str_contains(
            strtolower($e->getMessage()),'later recorded population balance negative'
        );
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    k2p03_ci_check($negativeWriterRejected,
        'canonical movement projection forbids the negative later balance');
    b07_assert_unchanged($pdo,$farmId,$before,
        'writer negative-balance guard leaves durable sources intact');

    $stage='LOCKED_CONTINUITY_TRANSACTION_ROLLBACK';
    $lockedRejected=false;
    $pdo->beginTransaction();
    try {
        $update=$pdo->prepare(
            'UPDATE layer_daily_records SET mortality=10
             WHERE farm_id=? AND cycle_id=? AND id=? AND mortality=2'
        );
        $update->execute([$farmId,$cycleId,$ids[0]]);
        k2p03_ci_equal(1,$update->rowCount(),
            'caller transaction temporarily updates historical source');
        k2p03_ci_equal(10,(int)b07_row($pdo,$farmId,$ids[0])['mortality'],
            'uncommitted new mortality is visible within transaction');
        daily_population_continuity_apply(
            $pdo,$farmId,$cycleId,'layer','2026-09-21',
            30,10,null,$ids[0]
        );
        // An unsafe implementation returning here makes the assertion fail
        // after the caller-owned transaction is rolled back.
    } catch(DailyPopulationBoundaryException $e) {
        $lockedRejected=str_contains(
            strtolower($e->getMessage()),'negative on 2026-09-23'
        );
        k2p03_ci_check($lockedRejected,
            'locked shared continuity stops historical negative correction');
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    k2p03_ci_check($lockedRejected,
        'no partially committed Daily Record after one caller rollback');
    b07_assert_unchanged($pdo,$farmId,$before,
        'caller rollback preserves source, sale and canonical movements');

    $stage='DIRECT_SOURCE_PROJECTION_ROLLBACK';
    /*
     * Even a direct mortality source synchronization cannot permanently
     * force a later negative population through the canonical writer.
     * Its intermediate reversal must remain caller-transactional.
     */
    $syncRejected=false;
    $pdo->beginTransaction();
    try {
        $target=$pdo->prepare(
            'UPDATE layer_daily_records SET mortality=10
             WHERE farm_id=? AND cycle_id=? AND id=? AND mortality=2'
        );
        $target->execute([$farmId,$cycleId,$ids[0]]);
        k2p03_ci_equal(1,$target->rowCount(),
            'direct-source transactional target update is provisional');
        daily_population_sync_mortality(
            $pdo,$farmId,'daily_layer_record',
            $ids[0],$cycleId,'2026-09-21',10,null
        );
    } catch(ProductionPopulationException $e) {
        $syncRejected=str_contains(
            strtolower($e->getMessage()),'later recorded population balance negative'
        );
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    k2p03_ci_check($syncRejected,
        'direct canonical mortality projection rejects negative later history');
    b07_assert_unchanged($pdo,$farmId,$before,
        'direct-source failed projection reversal is rolled back');

    $stage='POST_ROLLBACK_FINAL_RECONCILIATION';
    k2p03_ci_equal(2,(int)b07_row($pdo,$farmId,$ids[0])['mortality'],
        'original day-one mortality remains two after all failures');
    k2p03_ci_equal(28,(int)b07_row($pdo,$farmId,$ids[1])['opening_stock'],
        'original day-two opening retained');
    k2p03_ci_equal(25,(int)b07_row($pdo,$farmId,$ids[2])['opening_stock'],
        'original sale-date opening retained');
    k2p03_ci_equal(4,(int)production_population_state(
        $pdo,$farmId,$cycleId,'2026-09-23'
    )['quantity'],'original final canonical population remains positive four');
    k2p03_ci_equal(4,count(b07_rows(
        $pdo,$farmId,'production_population_movements'
    )),'no extra population movement, reversal or version persisted');
    k2p03_ci_equal(1,count(b07_rows(
        $pdo,$farmId,'sale_population_effects'
    )),'source-owned live-bird sale effect persisted unchanged');
    b07_assert_unchanged($pdo,$farmId,$before,
        'final all-farm-table source and movement reconciliation');

    echo 'K2_P03_B07_REAL_MARIADB=PASS'.PHP_EOL;
} catch(Throwable $e) {
    if($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // No credentials, DSN, or protected identifying data in CI logs.
    fwrite(STDERR,'FAILURE_STAGE='.$stage.PHP_EOL);
    fwrite(STDERR,'FAILURE_CLASS='.get_class($e).PHP_EOL);
    fwrite(STDERR,'K2_P03_B07_REAL_MARIADB=FAIL'.PHP_EOL);
    exit(1);
}
