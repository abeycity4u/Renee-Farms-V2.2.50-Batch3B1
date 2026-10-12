<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 B06 — invalid future mortality.
 *
 * Real-MariaDB, synthetic-only, GitHub-hosted disposable B06 schema.
 * This exercises the route's shared writer order and the one caller-owned
 * transaction boundary; never load config.php or execute a browser route.
 */
require_once __DIR__ . '/k2p03_ci_isolation.php';

$stage = 'ENVIRONMENT';
$pdo = null;

function b06_snapshot(PDO $pdo, int $farmId, string $table): array
{
    $allowed = [
        'layer_daily_records',
        'production_population_baselines',
        'production_population_movements',
        'stock_items',
        'stock_transactions',
        'stock_consumption_allocations',
        'sales_records',
        'sales_allocations',
        'sales_allocation_revisions',
    ];
    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException('B06 snapshot table is not allowlisted.');
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . $table . ' WHERE farm_id=? ORDER BY id'
    );
    $stmt->execute([$farmId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function b06_one(PDO $pdo, int $farmId, string $table, int $id): array
{
    if (!in_array($table, ['layer_daily_records', 'stock_items'], true)) {
        throw new RuntimeException('B06 row table is not allowlisted.');
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . $table . ' WHERE farm_id=? AND id=?'
    );
    $stmt->execute([$farmId, $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($result)) {
        throw new RuntimeException('B06 synthetic source row is missing.');
    }
    return $result;
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b06', 'B06', 'renee_k2p03_ci_b06'
    );
    $root = dirname(__DIR__, 2);
    k2p03_ci_equal(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        k2p03_ci_blob($root . '/lib/daily_population_continuity.php'),
        'pinned corrected population continuity blob unchanged'
    );
    k2p03_ci_equal(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        k2p03_ci_blob($root . '/database_schema.sql'),
        'pinned 69-table fresh schema blob unchanged'
    );

    $stage = 'DISPOSABLE_DATABASE';
    $pdo = k2p03_ci_connection('renee_k2p03_ci_b06');
    require_once $root . '/lib/daily_population_continuity.php';
    require_once $root . '/lib/daily_population_sync.php';
    require_once $root . '/lib/daily_feed_sync.php';
    require_once $root . '/lib/sales_allocation.php';

    $stage = 'SYNTHETIC_CANONICAL_FIXTURE';
    $pdo->exec(
        "INSERT INTO farms(name, slug)
         VALUES ('K2P03 B06 future mortality', 'k2p03-b06-synthetic')"
    );
    $farmId = (int)$pdo->lastInsertId();
    k2p03_ci_check($farmId > 0, 'one synthetic B06 farm persisted');

    $pdo->prepare(
        "INSERT INTO production_cycles
           (farm_id,cycle_code,farm_type,production_type,status,
            start_date,opening_headcount)
         VALUES (?,'b06-layer','poultry','layer','active','2026-09-21',30)"
    )->execute([$farmId]);
    $cycleId = (int)$pdo->lastInsertId();
    k2p03_ci_check($cycleId > 0, 'one canonical Layer cycle persisted');

    $pdo->prepare(
        "INSERT INTO production_population_baselines
           (farm_id,cycle_id,baseline_date,baseline_quantity,baseline_source)
         VALUES (?,?,'2026-09-21',30,'cycle_opening')"
    )->execute([$farmId,$cycleId]);
    k2p03_ci_equal(
        30,
        (int)production_population_state($pdo,$farmId,$cycleId,'2026-09-21')['quantity'],
        'baseline is 30 birds before original mortality'
    );

    /*
     * Same-day adjustment is deliberate, not a missing mortality:
     * day-two opening 28, intake +20, mortality 20, closing 28.
     * Proposed day-one correction 2 -> 12 makes day-two opening 18,
     * intake +20, mortality 20, closing 18 (nonnegative).
     * Therefore B06 isolates the explicit mortality > OPENING guard;
     * B07 separately tests negative canonical physical balances.
     */
    $pdo->beginTransaction();
    try {
        $additionId = production_population_record_movement(
            $pdo,$farmId,$cycleId,'adjustment_in','2026-09-22',
            20,'adjustment',null,1,'b0600000000000000000000000000001',
            'Synthetic same-day intake isolates later mortality guard',null
        );
        k2p03_ci_check($additionId > 0,
            'durable synthetic day-two population addition recorded');
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    $insertDaily=$pdo->prepare(
        'INSERT INTO layer_daily_records
           (farm_id,cycle_id,record_date,opening_stock,mortality,
            feed_consumption_bags,egg_production,crates_count,
            laying_rate,birds_age)
         VALUES (?,?,?,?,?,1,?,?,?,25)'
    );
    $seed=[
        ['2026-09-21',30,2,15,1,50.00],
        ['2026-09-22',28,20,8,1,28.57],
        ['2026-09-23',28,1,8,1,28.57],
    ];
    $ids=[];
    foreach($seed as $row) {
        $insertDaily->execute([$farmId,$cycleId,...$row]);
        $id=(int)$pdo->lastInsertId();
        $ids[]=$id;
        $status=daily_population_sync_mortality(
            $pdo,$farmId,'daily_layer_record',$id,
            $cycleId,$row[0],$row[2],null
        );
        k2p03_ci_equal('created',$status['status'],
            'durable original mortality source projected '.$row[0]);
    }
    k2p03_ci_check(count($ids)===3&&count(array_unique($ids))===3,
        'three unique saved Layer Daily Record source IDs');

    $baseState = production_population_state(
        $pdo,$farmId,$cycleId,'2026-09-23'
    );
    k2p03_ci_equal(27,(int)$baseState['quantity'],
        'original day-three canonical balance remains 27');
    k2p03_ci_equal(
        4,count(b06_snapshot($pdo,$farmId,'production_population_movements')),
        'three mortality events and one physical addition remain durable'
    );

    $stage='SYNTHETIC_DURABLE_FEED';
    $pdo->prepare(
        "INSERT INTO inventory_categories
           (farm_id,category_name,farm_type,unit,financial_type,inventory_role)
         VALUES (?,'B06 synthetic Layer feed','poultry','bag','feed','operational')"
    )->execute([$farmId]);
    $categoryId=(int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO stock_items
           (farm_id,item_name,category_id,current_stock,unit_cost,unit,
            farm_type,feed_category,default_production_type,financial_classification)
         VALUES (?,'B06 Layer feed',?,0,0,'bag','poultry','layer','layer','feed')"
    )->execute([$farmId,$categoryId]);
    $feedId=(int)$pdo->lastInsertId();
    k2p03_ci_check($feedId>0,'one synthetic Layer feed stock item persisted');

    $pdo->beginTransaction();
    try {
        $receipt=stock_apply_movement(
            $pdo,$farmId,$feedId,'received',20.0,'2026-09-21',
            'Synthetic feed inventory receipt',null,'poultry','layer',
            null,'inventory_api',null
        );
        k2p03_ci_check($receipt>0,
            'real shared stock writer created synthetic feed receipt');
        foreach($ids as $i=>$id) {
            sync_daily_feed_usage(
                $pdo,$farmId,$id,$feedId,1.0,$cycleId,$seed[$i][0],
                'poultry','layer','daily_layer_record'
            );
        }
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    k2p03_ci_equal(
        4,count(b06_snapshot($pdo,$farmId,'stock_transactions')),
        'one receipt plus three source-linked feed USED transactions'
    );
    k2p03_ci_equal(
        '17.00',
        (string)b06_one($pdo,$farmId,'stock_items',$feedId)['current_stock'],
        'three bags consumed from original 20-bag stock balance'
    );
    foreach ($ids as $id) {
        $stmt=$pdo->prepare(
            "SELECT COUNT(*) FROM stock_transactions
             WHERE farm_id=? AND source_type='daily_layer_record'
               AND source_id=? AND transaction_type='used' AND is_reversed=0"
        );
        $stmt->execute([$farmId,$id]);
        k2p03_ci_equal(1,(int)$stmt->fetchColumn(),
            'each saved Daily Record owns one active feed consumption '.$id);
    }

    $stage='SYNTHETIC_DURABLE_EGG_SALE';
    $pdo->prepare(
        "INSERT INTO sales_records
           (public_reference,farm_id,sale_date,farm_type,production_type,
            attribution_scope,cycle_id,product_type,quantity,unit_of_measure,unit_price)
         VALUES ('B06-EGGS-001',?,'2026-09-22','poultry','layer',
                 'production_type',NULL,'eggs',1,'crate',1000.00)"
    )->execute([$farmId]);
    $saleId=(int)$pdo->lastInsertId();
    $pdo->beginTransaction();
    try {
        $rebuild=sales_rebuild_layer_egg_allocations(
            $pdo,$farmId,'2026-09-21'
        );
        k2p03_ci_equal(
            ['rebuilt'=>1,'allocated'=>1,'unallocated'=>0],
            $rebuild,'real automatic egg allocator attributes synthetic historical sale'
        );
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    k2p03_ci_equal(
        1,count(b06_snapshot($pdo,$farmId,'sales_allocations')),
        'one original pooled egg-sale allocation exists'
    );
    k2p03_ci_equal(
        0,count(b06_snapshot($pdo,$farmId,'sales_allocation_revisions')),
        'automatic egg revenue created no manual allocation history'
    );

    $tables=[
        'layer_daily_records',
        'production_population_baselines',
        'production_population_movements',
        'stock_items',
        'stock_transactions',
        'stock_consumption_allocations',
        'sales_records',
        'sales_allocations',
        'sales_allocation_revisions',
    ];
    $before=[];
    foreach ($tables as $table) {
        $before[$table]=b06_snapshot($pdo,$farmId,$table);
    }
    k2p03_ci_equal(3,count($before['layer_daily_records']),
        'three durable Layer source facts before attempted correction');
    k2p03_ci_equal(1,count($before['sales_records']),
        'one immutable original Layer egg-sale revenue source');
    k2p03_ci_equal(4,count($before['production_population_movements']),
        'four existing canonical physical population movements');

    $stage='PREVIEW_EXPLICIT_LATER_MORTALITY_GUARD';
    $snapshots=daily_population_boundary_snapshots(
        $pdo,$farmId,$cycleId,['2026-09-21','2026-09-22','2026-09-23'],
        [
            'source_type'=>'daily_layer_record',
            'source_id'=>$ids[0],
            'record_date'=>'2026-09-21',
            'mortality'=>12,
            'for_update'=>false,
        ]
    );
    k2p03_ci_equal(18,(int)$snapshots['2026-09-22']['opening_quantity'],
        'corrected day-two canonical opening becomes 18');
    k2p03_ci_equal(18,(int)$snapshots['2026-09-22']['closing_quantity'],
        'same-day addition keeps projected canonical close nonnegative');
    k2p03_ci_equal(18,(int)$snapshots['2026-09-23']['opening_quantity'],
        'projected third-day opening remains 18');

    $previewRejected=false;
    try {
        daily_population_continuity_preview(
            $pdo,$farmId,$cycleId,'layer',
            '2026-09-21',30,12,null,false,$ids[0]
        );
    } catch (DailyPopulationContinuityException $e) {
        $previewRejected=str_contains(
            strtolower($e->getMessage()),
            'existing mortality'
        );
    }
    k2p03_ci_check($previewRejected,
        'explicit later mortality 20 exceeds corrected opening 18');
    foreach ($tables as $table) {
        k2p03_ci_equal($before[$table],b06_snapshot($pdo,$farmId,$table),
            'rejected advisory preview has zero side effects on '.$table);
    }

    /*
     * Model the exact route ordering, not an invented duplicated writer:
     * saved target -> locked continuity -> feed -> mortality -> egg allocations.
     * Locked continuity MUST throw before later writers execute. The route's
     * one catch/rollback must undo the saved target and keep all ledgers intact.
     * B11 separately covers forced failures after intermediate writers.
     */
    $stage='ATOMIC_ROUTE_ORDER_REJECTION';
    $caught=false;
    $pdo->beginTransaction();
    try {
        $target=$pdo->prepare(
            'UPDATE layer_daily_records
             SET mortality=12,feed_consumption_bags=2
             WHERE farm_id=? AND cycle_id=? AND id=? AND mortality=2'
        );
        $target->execute([$farmId,$cycleId,$ids[0]]);
        k2p03_ci_equal(1,$target->rowCount(),
            'caller temporarily saved revised mortality and feed fact');
        k2p03_ci_equal(12,(int)b06_one(
            $pdo,$farmId,'layer_daily_records',$ids[0]
        )['mortality'],'caller-owned transaction sees pending target');

        daily_population_continuity_apply(
            $pdo,$farmId,$cycleId,'layer',
            '2026-09-21',30,12,null,$ids[0]
        );

        // If an unsafe continuity implementation ever returns, do NOT invoke
        // subsequent writers, and FAIL this test after rollback.
    } catch (DailyPopulationContinuityException $e) {
        $caught=str_contains(
            strtolower($e->getMessage()),
            'existing mortality'
        );
        k2p03_ci_check($caught,
            'locked continuity rejects invalid later mortality before feed/mortality/egg writers');
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    k2p03_ci_check($caught,
        'caller owned exactly one rollback for invalid saved correction');

    $stage='POST_ROLLBACK_ALL_LEDGER_RECONCILIATION';
    foreach ($tables as $table) {
        k2p03_ci_equal($before[$table],b06_snapshot($pdo,$farmId,$table),
            'entire caller-owned rollback restores '.$table);
    }
    k2p03_ci_equal(2,(int)b06_one(
        $pdo,$farmId,'layer_daily_records',$ids[0]
    )['mortality'],'original first-day mortality restored');
    k2p03_ci_equal(
        '1.00',
        (string)b06_one($pdo,$farmId,'layer_daily_records',$ids[0])['feed_consumption_bags'],
        'original Daily Record feed fact restored'
    );
    k2p03_ci_equal('17.00',
        (string)b06_one($pdo,$farmId,'stock_items',$feedId)['current_stock'],
        'original physical feed stock preserved');
    k2p03_ci_equal(27,(int)production_population_state(
        $pdo,$farmId,$cycleId,'2026-09-23'
    )['quantity'],'original physical canonical population balance preserved');
    k2p03_ci_equal(1,count(b06_snapshot($pdo,$farmId,'sales_allocations')),
        'automatic Layer egg-sale attribution still exists exactly once');
    echo 'K2_P03_B06_REAL_MARIADB=PASS' . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR,'FAILURE_STAGE='.$stage.PHP_EOL);
    fwrite(STDERR,'FAILURE_CLASS='.get_class($e).PHP_EOL);
    fwrite(STDERR,'K2_P03_B06_REAL_MARIADB=FAIL'.PHP_EOL);
    exit(1);
}
