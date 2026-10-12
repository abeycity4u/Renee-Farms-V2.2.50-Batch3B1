<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 B08: repeated correction/replay of one real saved
 * Daily Record's mortality, canonical version/reversal source ownership.
 * All writes are confined to one guarded ephemeral GitHub MariaDB schema.
 */
require_once __DIR__ . '/k2p03_ci_isolation.php';

$stage='ISOLATION';
$pdo=null;

function b08_rows(PDO $pdo, int $farmId, string $table): array
{
    $allow=[
        'layer_daily_records','production_population_baselines',
        'production_population_movements','sales_records',
        'sale_population_effects','stock_transactions',
    ];
    if(!in_array($table,$allow,true)) {
        throw new RuntimeException('B08 snapshot table not allowlisted.');
    }
    $q=$pdo->prepare('SELECT * FROM '.$table.' WHERE farm_id=? ORDER BY id');
    $q->execute([$farmId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function b08_active(PDO $pdo, int $farmId, int $cycleId): array
{
    $sql="SELECT m.id,m.source_id,m.source_version,m.movement_date,
                 m.movement_type,m.quantity_delta
          FROM production_population_movements m
          WHERE m.farm_id=? AND m.cycle_id=?
            AND m.movement_type='mortality'
            AND m.source_type='daily_layer_record'
            AND m.reversal_of_id IS NULL
            AND NOT EXISTS (
              SELECT 1 FROM production_population_movements r
              WHERE r.farm_id=m.farm_id AND r.cycle_id=m.cycle_id
                AND r.reversal_of_id=m.id
            )
          ORDER BY m.id";
    $q=$pdo->prepare($sql);
    $q->execute([$farmId,$cycleId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function b08_movement_counts(PDO $pdo, int $farmId, int $sourceId): array
{
    $q=$pdo->prepare(
       "SELECT id,source_version,quantity_delta,reversal_of_id
        FROM production_population_movements
        WHERE farm_id=?
          AND ((source_type='daily_layer_record' AND source_id=?)
               OR (source_type='reversal' AND source_id IN (
                   SELECT id FROM production_population_movements
                   WHERE farm_id=? AND source_type='daily_layer_record'
                     AND source_id=?
               )))
        ORDER BY id"
    );
    $q->execute([$farmId,$sourceId,$farmId,$sourceId]);
    $originals=[];$reversals=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if($r['reversal_of_id']===null) $originals[]=$r;
        else $reversals[]=$r;
    }
    return [$originals,$reversals];
}

function b08_assert_state(
    PDO $pdo, int $farmId, int $cycleId, int $sourceId,
    int $mortality, int $version, int $secondOpening, int $thirdOpening,
    int $finalPopulation, int $originalCount, int $reversalCount
): void {
    $source=production_population_projection_active_snapshot(
        $pdo,$farmId,'daily_layer_record',$sourceId
    );
    if($mortality===0) {
        k2p03_ci_check($source===null,
            'no active Day One mortality when source is zero');
    } else {
        k2p03_ci_check(is_array($source),
            'exact active Day One mortality source exists');
        k2p03_ci_equal($version,(int)$source['source_version'],
            'active source version equals expected');
        k2p03_ci_equal(-$mortality,(int)$source['quantity_delta'],
            'only active source physical reduction equals mortality');
    }
    $moves=b08_active($pdo,$farmId,$cycleId);
    k2p03_ci_equal($mortality===0 ? 2 : 3,count($moves),
        'exactly one mortality movement per nonzero source');
    $first=array_values(array_filter(
        $moves,static fn(array $r):bool=>(int)$r['source_id']===$sourceId
    ));
    k2p03_ci_equal($mortality===0 ? 0 : 1,count($first),
        'target source has zero-or-one active physical deduction');
    $rows=b08_rows($pdo,$farmId,'layer_daily_records');
    k2p03_ci_equal(3,count($rows),'same three real saved Layer records');
    k2p03_ci_equal($mortality,(int)$rows[0]['mortality'],
        'target durable Daily Record mortality is authoritative');
    k2p03_ci_equal($secondOpening,(int)$rows[1]['opening_stock'],
        'day two opening follows current active mortality');
    k2p03_ci_equal($thirdOpening,(int)$rows[2]['opening_stock'],
        'day three opening follows own preserved day two mortality');
    k2p03_ci_equal(1,(int)$rows[1]['mortality'],
        'day two mortality source remains unchanged');
    k2p03_ci_equal(1,(int)$rows[2]['mortality'],
        'day three mortality source remains unchanged');
    $physical=production_population_state(
        $pdo,$farmId,$cycleId,'2026-09-23'
    );
    k2p03_ci_equal($finalPopulation,(int)$physical['quantity'],
        'canonical physical balance has no double-reduction');
    [$orig,$rev]=b08_movement_counts($pdo,$farmId,$sourceId);
    k2p03_ci_equal($originalCount,count($orig),
        'target original version history count');
    k2p03_ci_equal($reversalCount,count($rev),
        'target compensating reversal count');
    k2p03_ci_equal(array_values(range(1,$originalCount)),
        array_map(static fn(array $r):int=>(int)$r['source_version'],$orig),
        'original mortality source versions are monotone contiguous');
    $originalIds=array_map(static fn(array $r):int=>(int)$r['id'],$orig);
    $reversedIds=array_map(static fn(array $r):int=>(int)$r['reversal_of_id'],$rev);
    k2p03_ci_equal(count(array_unique($reversedIds)),count($reversedIds),
        'no original mortality movement is reversed more than once');
    k2p03_ci_equal(array_slice($originalIds,0,$reversalCount),
        $reversedIds,'every reversal belongs to its original source version');
}

function b08_correct(
    PDO $pdo,int $farmId,int $cycleId,int $sourceId,
    int $mortality,string $date
): array {
    // Route-equivalent shared writer order; this helper implements no
    // mortality, opening or projection business policy of its own.
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare(
            'UPDATE layer_daily_records SET mortality=?
             WHERE id=? AND farm_id=? AND cycle_id=?'
        );
        $q->execute([$mortality,$sourceId,$farmId,$cycleId]);
        daily_population_continuity_apply(
            $pdo,$farmId,$cycleId,'layer',$date,100,$mortality,null,$sourceId
        );
        $result=daily_population_sync_mortality(
            $pdo,$farmId,'daily_layer_record',
            $sourceId,$cycleId,$date,$mortality,null
        );
        $pdo->commit();
        return $result;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b08','B08','renee_k2p03_ci_b08'
    );
    $root=dirname(__DIR__,2);
    k2p03_ci_equal(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        k2p03_ci_blob($root.'/lib/daily_population_continuity.php'),
        'frozen canonical shared continuity blob'
    );
    k2p03_ci_equal(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        k2p03_ci_blob($root.'/database_schema.sql'),
        'frozen 69-table MariaDB schema blob'
    );
    $stage='FRESH_DISPOSABLE_DATABASE';
    $pdo=k2p03_ci_connection('renee_k2p03_ci_b08');
    require_once $root.'/lib/daily_population_continuity.php';
    require_once $root.'/lib/daily_population_sync.php';

    $stage='DURABLE_INITIAL_SOURCE';
    $pdo->exec(
        "INSERT INTO farms(name,slug)
         VALUES ('K2P03 B08 source replay synthetic farm','k2p03-b08-synthetic')"
    );
    $farmId=(int)$pdo->lastInsertId();
    k2p03_ci_check($farmId>0,'new isolated synthetic farm');
    $pdo->prepare(
        "INSERT INTO production_cycles
           (farm_id,cycle_code,farm_type,production_type,status,
            start_date,opening_headcount)
         VALUES (?,'b08-layer','poultry','layer','active','2026-09-21',100)"
    )->execute([$farmId]);
    $cycleId=(int)$pdo->lastInsertId();
    k2p03_ci_check($cycleId>0,'one synthetic canonical Layer cycle');
    $pdo->prepare(
        "INSERT INTO production_population_baselines
           (farm_id,cycle_id,baseline_date,baseline_quantity,baseline_source)
         VALUES (?,?,'2026-09-21',100,'cycle_opening')"
    )->execute([$farmId,$cycleId]);

    $ins=$pdo->prepare(
        'INSERT INTO layer_daily_records
         (farm_id,cycle_id,record_date,opening_stock,mortality,
          egg_production,crates_count,laying_rate,birds_age)
         VALUES (?,?,?,?,?,10,0,?,25)'
    );
    $seed=[
        ['2026-09-21',100,2,10.00],
        ['2026-09-22',98,1,10.20],
        ['2026-09-23',97,1,10.31],
    ];
    $ids=[];
    foreach($seed as $data) {
        $ins->execute([$farmId,$cycleId,...$data]);
        $id=(int)$pdo->lastInsertId();
        $ids[]=$id;
        $result=daily_population_sync_mortality(
            $pdo,$farmId,'daily_layer_record',
            $id,$cycleId,$data[0],$data[2],null
        );
        k2p03_ci_equal('created',$result['status'],
            'real initial mortality projection created '.$data[0]);
    }
    k2p03_ci_equal(3,count(array_unique($ids)),
        'three durable saved Daily Record source identities');
    b08_assert_state($pdo,$farmId,$cycleId,$ids[0],2,1,98,97,96,1,0);

    $stage='REPEATED_CORRECTIONS_AND_IDENTICAL_REPLAYS';
    $steps=[
        [5,'corrected',2,95,94,93,2,1],
        [5,'unchanged',2,95,94,93,2,1],
        [3,'corrected',3,97,96,95,3,2],
        [3,'unchanged',3,97,96,95,3,2],
        [0,'removed',0,100,99,98,3,3],
        [0,'unchanged_empty',0,100,99,98,3,3],
        [4,'created',4,96,95,94,4,3],
        [4,'unchanged',4,96,95,94,4,3],
    ];
    foreach($steps as $i=>$step) {
        [$quantity,$status,$version,$opening2,$opening3,$population,
          $originals,$reversals]=$step;
        $beforeRows=b08_rows($pdo,$farmId,'production_population_movements');
        $beforeAll=b08_rows($pdo,$farmId,'layer_daily_records');
        $got=b08_correct(
            $pdo,$farmId,$cycleId,$ids[0],$quantity,'2026-09-21'
        );
        k2p03_ci_equal($status,$got['status'],
            'shared source projection status on replay step '.($i+1));
        b08_assert_state(
            $pdo,$farmId,$cycleId,$ids[0],
            $quantity,$version,$opening2,$opening3,$population,
            $originals,$reversals
        );
        if($status==='unchanged'||$status==='unchanged_empty') {
            k2p03_ci_equal(
                $beforeRows,
                b08_rows($pdo,$farmId,'production_population_movements'),
                'identical replay added no version/reversal/physical movement '.($i+1)
            );
            k2p03_ci_equal(
                $beforeAll,
                b08_rows($pdo,$farmId,'layer_daily_records'),
                'identical replay rewrote no Daily Record fact '.($i+1)
            );
        }
    }

    $stage='HISTORICAL_SOURCE_INTEGRITY';
    $movementRows=b08_rows($pdo,$farmId,'production_population_movements');
    k2p03_ci_equal(9,count($movementRows),
        'two untouched later sources plus four target versions and three reversals');
    k2p03_ci_equal(3,count(array_filter(
        $movementRows,
        static fn(array $m):bool=>$m['reversal_of_id']!==null
    )),'exactly three append-only compensating reversal rows');
    $finalRows=b08_rows($pdo,$farmId,'layer_daily_records');
    k2p03_ci_equal(4,(int)$finalRows[0]['mortality'],
        'final durable original target fact is mortality four');

    $stage='UNCOMMITTED_CORRECTION_ROLLBACK';
    $before=[
        'records'=>b08_rows($pdo,$farmId,'layer_daily_records'),
        'baselines'=>b08_rows($pdo,$farmId,'production_population_baselines'),
        'movements'=>b08_rows($pdo,$farmId,'production_population_movements'),
    ];
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE layer_daily_records SET mortality=7
             WHERE id=? AND farm_id=? AND cycle_id=?'
        )->execute([$ids[0],$farmId,$cycleId]);
        daily_population_continuity_apply(
            $pdo,$farmId,$cycleId,'layer','2026-09-21',100,7,null,$ids[0]
        );
        $pending=daily_population_sync_mortality(
            $pdo,$farmId,'daily_layer_record',
            $ids[0],$cycleId,'2026-09-21',7,null
        );
        k2p03_ci_equal('corrected',$pending['status'],
            'provisional fifth source version projected inside transaction');
        k2p03_ci_equal(5,(int)$pending['source_version'],
            'provisional next version is five before caller rollback');
    } finally {
        if($pdo->inTransaction()) $pdo->rollBack();
    }
    foreach([
        'records'=>'layer_daily_records',
        'baselines'=>'production_population_baselines',
        'movements'=>'production_population_movements',
    ] as $label=>$table) {
        k2p03_ci_equal($before[$label],b08_rows($pdo,$farmId,$table),
            'caller rollback preserves '.$table);
    }
    b08_assert_state($pdo,$farmId,$cycleId,$ids[0],4,4,96,95,94,4,3);
    $redo=b08_correct($pdo,$farmId,$cycleId,$ids[0],4,'2026-09-21');
    k2p03_ci_equal('unchanged',$redo['status'],
        'post-rollback replay of authoritative mortality remains unchanged');
    k2p03_ci_equal(
        $before['movements'],
        b08_rows($pdo,$farmId,'production_population_movements'),
        'post-rollback replay has no additional physical reductions'
    );
    echo 'K2_P03_B08_REAL_MARIADB=PASS'.PHP_EOL;
} catch(Throwable $e) {
    if($pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'FAILURE_STAGE='.$stage.PHP_EOL);
    fwrite(STDERR,'FAILURE_CLASS='.get_class($e).PHP_EOL);
    fwrite(STDERR,'K2_P03_B08_REAL_MARIADB=FAIL'.PHP_EOL);
    exit(1);
}
