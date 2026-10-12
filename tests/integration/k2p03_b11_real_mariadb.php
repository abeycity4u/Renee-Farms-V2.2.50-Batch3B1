<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 B11 — controlled mid-writer rollback atomicity.
 *
 * Real-MariaDB, synthetic-only, GitHub-hosted disposable B11 schema.
 * Exercise original continuity, feed, mortality projection and pooled egg-sale
 * writers under one caller-owned transaction with deliberate mid-writer errors.
 * Never load config.php or execute a browser route.
 */
require_once __DIR__ . '/k2p03_ci_isolation.php';

$stage = 'ENVIRONMENT';
$pdo = null;

function b11_snapshot(PDO $pdo, int $farmId, string $table): array
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
        throw new RuntimeException('B11 snapshot table is not allowlisted.');
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . $table . ' WHERE farm_id=? ORDER BY id'
    );
    $stmt->execute([$farmId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function b11_one(PDO $pdo, int $farmId, string $table, int $id): array
{
    if (!in_array($table, ['layer_daily_records', 'stock_items'], true)) {
        throw new RuntimeException('B11 row table is not allowlisted.');
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . $table . ' WHERE farm_id=? AND id=?'
    );
    $stmt->execute([$farmId, $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($result)) {
        throw new RuntimeException('B11 synthetic source row is missing.');
    }
    return $result;
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b11', 'B11', 'renee_k2p03_ci_b11'
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
    $pdo = k2p03_ci_connection('renee_k2p03_ci_b11');
    require_once $root . '/lib/daily_population_continuity.php';
    require_once $root . '/lib/daily_population_sync.php';
    require_once $root . '/lib/daily_feed_sync.php';
    require_once $root . '/lib/sales_allocation.php';

    $stage = 'SYNTHETIC_CANONICAL_FIXTURE';
    $pdo->exec(
        "INSERT INTO farms(name, slug)
         VALUES ('K2P03 B11 mid-writer rollback', 'k2p03-b11-synthetic')"
    );
    $farmId = (int)$pdo->lastInsertId();
    k2p03_ci_check($farmId > 0, 'one synthetic B11 farm persisted');

    $pdo->prepare(
        "INSERT INTO production_cycles
           (farm_id,cycle_code,farm_type,production_type,status,
            start_date,opening_headcount)
         VALUES (?,'b11-layer','poultry','layer','active','2026-09-21',30)"
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
     * Three real original mortality projections and one canonical addition.
     * Correcting day-one mortality 2 -> 4 gives valid later openings 26,
     * with the day-two +20 adjustment balancing its 20-bird mortality.
     * The correction intentionally reaches ALL downstream writers before
     * a controlled test-only exception and caller-owned rollback.
     */
    $pdo->beginTransaction();
    try {
        $additionId = production_population_record_movement(
            $pdo,$farmId,$cycleId,'adjustment_in','2026-09-22',
            20,'adjustment',null,1,'b1100000000000000000000000000001',
            'Synthetic same-day intake keeps the revised later mortality valid',null
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
        4,count(b11_snapshot($pdo,$farmId,'production_population_movements')),
        'three mortality events and one physical addition remain durable'
    );

    $stage='SYNTHETIC_DURABLE_FEED';
    $pdo->prepare(
        "INSERT INTO inventory_categories
           (farm_id,category_name,farm_type,unit,financial_type,inventory_role)
         VALUES (?,'B11 synthetic Layer feed','poultry','bag','feed','operational')"
    )->execute([$farmId]);
    $categoryId=(int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO stock_items
           (farm_id,item_name,category_id,current_stock,unit_cost,unit,
            farm_type,feed_category,default_production_type,financial_classification)
         VALUES (?,'B11 Layer feed',?,0,0,'bag','poultry','layer','layer','feed')"
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
        4,count(b11_snapshot($pdo,$farmId,'stock_transactions')),
        'one receipt plus three source-linked feed USED transactions'
    );
    k2p03_ci_equal(
        '17.00',
        (string)b11_one($pdo,$farmId,'stock_items',$feedId)['current_stock'],
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
         VALUES ('B11-EGGS-001',?,'2026-09-22','poultry','layer',
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
        1,count(b11_snapshot($pdo,$farmId,'sales_allocations')),
        'one original pooled egg-sale allocation exists'
    );
    k2p03_ci_equal(
        0,count(b11_snapshot($pdo,$farmId,'sales_allocation_revisions')),
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
        $before[$table]=b11_snapshot($pdo,$farmId,$table);
    }
    k2p03_ci_equal(3,count($before['layer_daily_records']),
        'three durable Layer source facts before attempted correction');
    k2p03_ci_equal(1,count($before['sales_records']),
        'one immutable original Layer egg-sale revenue source');
    k2p03_ci_equal(4,count($before['production_population_movements']),
        'four existing canonical physical population movements');


    $stage='VALID_CANONICAL_CORRECTION_PREFLIGHT';
    $sourceId=$ids[0];
    $plan=daily_population_continuity_preview(
        $pdo,$farmId,$cycleId,'layer',
        '2026-09-21',30,4,null,false,$sourceId
    );
    k2p03_ci_equal('canonical',$plan['tracking_status'],
        'real saved source uses canonical population tracking');
    k2p03_ci_equal(2,(int)$plan['affected_count'],
        'valid four-bird mortality correction rebases both later Layer rows');
    k2p03_ci_equal(
        [26,26],
        array_map('intval',array_column($plan['changes'],'new_opening_stock')),
        'two canonical later openings project 26 birds before writer edits'
    );
    foreach($tables as $table) {
        k2p03_ci_equal($before[$table],b11_snapshot($pdo,$farmId,$table),
            'read-only canonical preview never changes '.$table);
    }

    /*
     * The test does NOT patch production helpers or implement a second
     * application writer. The original route's shared write ORDER is:
     *
     * target Daily Record -> continuity -> feed -> mortality -> egg sale.
     *
     * Each controlled error occurs AFTER one of four intermediate writers
     * has run against a real disposable MariaDB caller-owned transaction.
     * Catch only the exact test injection token, then roll back.
     */
    $checkpoints=[
        'AFTER_CONTINUITY',
        'AFTER_FEED',
        'AFTER_MORTALITY',
        'AFTER_EGG_REBUILD',
    ];
    foreach($checkpoints as $point) {
        $stage='INJECTED_MID_WRITER_'.$point;
        $token='B11_SYNTHETIC_INJECT_'.$point;
        $caught=false;
        $pdo->beginTransaction();
        try {
            $update=$pdo->prepare(
                'UPDATE layer_daily_records
                 SET mortality=4, feed_consumption_bags=2
                 WHERE farm_id=? AND cycle_id=? AND id=?
                   AND mortality=2 AND feed_consumption_bags=1'
            );
            $update->execute([$farmId,$cycleId,$sourceId]);
            k2p03_ci_equal(1,$update->rowCount(),
                $point.' caller edits same original target source within transaction');

            $continuity=daily_population_continuity_apply(
                $pdo,$farmId,$cycleId,'layer',
                '2026-09-21',30,4,null,$sourceId
            );
            k2p03_ci_equal('canonical',$continuity['tracking_status'],
                $point.' genuine locked canonical writer selected');
            k2p03_ci_equal(2,(int)$continuity['applied_count'],
                $point.' genuine continuity writer rebased two later rows');
            k2p03_ci_equal(26,(int)b11_one(
                $pdo,$farmId,'layer_daily_records',$ids[1]
            )['opening_stock'],$point.' uncommitted later opening changed');
            k2p03_ci_check(
                $before['layer_daily_records']!==
                    b11_snapshot($pdo,$farmId,'layer_daily_records'),
                $point.' real continuity write visibly differs inside transaction'
            );

            if($point==='AFTER_CONTINUITY') {
                throw new RuntimeException($token);
            }

            sync_daily_feed_usage(
                $pdo,$farmId,$sourceId,$feedId,2.0,$cycleId,
                '2026-09-21','poultry','layer','daily_layer_record'
            );
            k2p03_ci_equal('16.00',(string)b11_one(
                $pdo,$farmId,'stock_items',$feedId
            )['current_stock'],$point.' original real feed writer temporarily consumes extra bag');
            k2p03_ci_check(
                $before['stock_transactions']!==
                    b11_snapshot($pdo,$farmId,'stock_transactions'),
                $point.' feed reversal/new USED ledger changed inside transaction'
            );
            if($point==='AFTER_FEED') {
                throw new RuntimeException($token);
            }

            $projected=daily_population_sync_mortality(
                $pdo,$farmId,'daily_layer_record',$sourceId,
                $cycleId,'2026-09-21',4,null
            );
            k2p03_ci_equal(2,(int)$projected['source_version'],
                $point.' genuine mortality source projection version increments');
            k2p03_ci_check((int)$projected['reversal_id']>0,
                $point.' original mortality projection gets compensated');
            k2p03_ci_equal(25,(int)production_population_state(
                $pdo,$farmId,$cycleId,'2026-09-23'
            )['quantity'],$point.' projected physical closing changes inside transaction');
            k2p03_ci_check(
                $before['production_population_movements']!==
                    b11_snapshot($pdo,$farmId,'production_population_movements'),
                $point.' real mortality movement ledger changed inside transaction'
            );
            if($point==='AFTER_MORTALITY') {
                throw new RuntimeException($token);
            }

            $rebuilt=sales_rebuild_layer_egg_allocations(
                $pdo,$farmId,'2026-09-21'
            );
            k2p03_ci_equal(
                ['rebuilt'=>1,'allocated'=>1,'unallocated'=>0],
                $rebuilt,$point.' original automatic egg-sale writer rebuilt pooled revenue'
            );
            k2p03_ci_equal(1,count(b11_snapshot(
                $pdo,$farmId,'sales_allocations'
            )),$point.' pooled egg sale has exactly one cycle allocation');
            k2p03_ci_check(
                $before['sales_allocations']!==
                    b11_snapshot($pdo,$farmId,'sales_allocations'),
                $point.' real automatic allocator deleted/recreated its rows inside transaction'
            );
            k2p03_ci_equal($before['sales_records'],
                b11_snapshot($pdo,$farmId,'sales_records'),
                $point.' original egg-sale source revenue never rewritten');
            // The test-only exception is AFTER the actual sale writer returns.
            throw new RuntimeException($token);
        } catch(RuntimeException $e) {
            if($e->getMessage()!==$token) {
                throw $e; // Never classify a real writer error as injection PASS.
            }
            $caught=true;
            k2p03_ci_check($pdo->inTransaction(),
                $point.' controlled error caught with caller transaction active');
        } finally {
            if($pdo->inTransaction()) {
                $pdo->rollBack(); // Exactly one caller-owned rollback.
            }
        }
        k2p03_ci_check($caught&&!$pdo->inTransaction(),
            $point.' injected failure ended with single caller-owned rollback');
        foreach($tables as $table) {
            k2p03_ci_equal(
                $before[$table],b11_snapshot($pdo,$farmId,$table),
                $point.' exact pre/post durable ledger equality: '.$table
            );
        }
        k2p03_ci_equal(2,(int)b11_one(
            $pdo,$farmId,'layer_daily_records',$sourceId
        )['mortality'],$point.' original target mortality remains durable');
        k2p03_ci_equal('1.00',(string)b11_one(
            $pdo,$farmId,'layer_daily_records',$sourceId
        )['feed_consumption_bags'],$point.' original target feed remains durable');
        k2p03_ci_equal('17.00',(string)b11_one(
            $pdo,$farmId,'stock_items',$feedId
        )['current_stock'],$point.' original physical stock remains durable');
        k2p03_ci_equal(27,(int)production_population_state(
            $pdo,$farmId,$cycleId,'2026-09-23'
        )['quantity'],$point.' original canonical physical population remains durable');
    }

    $stage='FINAL_RECONCILIATION';
    k2p03_ci_check(!$pdo->inTransaction(),
        'all four mid-writer exceptions left no caller transaction open');
    foreach($tables as $table) {
        k2p03_ci_equal(
            $before[$table],b11_snapshot($pdo,$farmId,$table),
            'final all-checkpoint rollback immutable ledger: '.$table
        );
    }
    k2p03_ci_equal(1,count(b11_snapshot(
        $pdo,$farmId,'sales_allocations'
    )),'original pooled Layer egg sale attribution still exists exactly once');
    k2p03_ci_equal(0,count(b11_snapshot(
        $pdo,$farmId,'sales_allocation_revisions'
    )),'no manual sale allocation revision was written');
    echo 'K2_P03_B11_REAL_MARIADB=PASS'.PHP_EOL;
} catch(Throwable $e) {
    if($pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'FAILURE_STAGE='.$stage.PHP_EOL);
    fwrite(STDERR,'FAILURE_CLASS='.get_class($e).PHP_EOL);
    fwrite(STDERR,'K2_P03_B11_REAL_MARIADB=FAIL'.PHP_EOL);
    exit(1);
}
