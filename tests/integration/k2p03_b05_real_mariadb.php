<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 B05 — synthetic Layer eggs + canonical continuity +
 * historical automatic pooled egg-sale revenue conservation.
 *
 * Explicit B05-only GitHub-hosted disposable MariaDB; never config.php/init.php.
 * No browser session, notifications, protected fixtures, deployed DB or AWS.
 */

require_once __DIR__ . '/k2p03_ci_isolation.php';

$stage = 'GUARDS';
$pdo = null;

function b05_daily(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, farm_id, cycle_id, record_date, opening_stock, mortality,
                egg_production, crates_count, laying_rate, feed_consumption_bags
         FROM layer_daily_records WHERE id=?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Missing B05 synthetic Daily Record.');
    }
    return $row;
}

function b05_sale(PDO $pdo, int $farmId, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, farm_id, public_reference, sale_date, cycle_id,
                quantity, unit_of_measure, unit_price, total_amount, product_type
         FROM sales_records WHERE farm_id=? AND id=?'
    );
    $stmt->execute([$farmId, $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Missing B05 synthetic pooled sale.');
    }
    return $row;
}

function b05_allocations(PDO $pdo, int $farmId, int $saleId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, farm_id, sale_id, cycle_id, allocation_percent,
                allocated_quantity, allocation_unit, allocated_amount,
                allocation_basis
           FROM sales_allocations
          WHERE farm_id=? AND sale_id=? ORDER BY cycle_id'
    );
    $stmt->execute([$farmId, $saleId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function b05_money($actual): int
{
    return (int)round(((float)$actual) * 100);
}

function b05_near(float $expected, $actual, string $label, float $tolerance = 0.00011): void
{
    k2p03_ci_check(abs((float)$actual - $expected) <= $tolerance, $label);
}

function b05_count(PDO $pdo, string $table): int
{
    if (!in_array($table, [
        'layer_daily_records', 'production_population_baselines',
        'production_population_movements', 'sales_records',
        'sales_allocations', 'sales_allocation_revisions',
    ], true)) {
        throw new RuntimeException('B05 count table is not allowlisted.');
    }
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

/**
 * Check two-cycle pooled automatic allocation from persisted source sale,
 * not by duplicating the source revenue or treating money as a physical exit.
 */
function b05_assert_sale_conserved(PDO $pdo, int $farmId, int $saleId, array $cycles): void
{
    $sale = b05_sale($pdo, $farmId, $saleId);
    $allocations = b05_allocations($pdo, $farmId, $saleId);
    k2p03_ci_equal(2, count($allocations), 'one attribution row per cycle for sale ' . $saleId);
    k2p03_ci_equal(null, $sale['cycle_id'], 'pooled sale parent has no direct cycle ' . $saleId);
    k2p03_ci_equal('eggs', strtolower((string)$sale['product_type']),
        'sale source is Layer eggs ' . $saleId);

    $actualCycles = array_map(static fn(array $r): int => (int)$r['cycle_id'], $allocations);
    $expectedCycles = $cycles;
    sort($actualCycles);
    sort($expectedCycles);
    k2p03_ci_equal($expectedCycles, $actualCycles,
        'both eligible cycles share the sale without duplication ' . $saleId);

    $quantity = 0.0;
    $amountCents = 0;
    foreach ($allocations as $r) {
        k2p03_ci_equal('layer_unsold_egg_pool', $r['allocation_basis'],
            'automatic egg pool is the sole allocation policy ' . $saleId);
        k2p03_ci_equal('crate', $r['allocation_unit'],
            'allocated egg quantity uses canonical crate unit ' . $saleId);
        k2p03_ci_check((float)$r['allocated_quantity'] >= 0.0,
            'egg quantity is nonnegative ' . $saleId);
        $quantity += (float)$r['allocated_quantity'];
        $amountCents += b05_money($r['allocated_amount']);
    }
    b05_near((float)$sale['quantity'], $quantity,
        'allocated physical crate quantity conserved ' . $saleId);
    k2p03_ci_equal(b05_money($sale['total_amount']), $amountCents,
        'allocated revenue cents equal the immutable source sale ' . $saleId);
    $status = sales_allocation_status(
        $pdo, $farmId, $saleId, (float)$sale['total_amount']
    );
    k2p03_ci_equal('allocated', $status['status'],
        'sale allocation reporting sees fully allocated pool ' . $saleId);
    k2p03_ci_equal(0, b05_money($status['unallocated_amount']),
        'there is no missing revenue pool ' . $saleId);
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b05', 'B05', 'renee_k2p03_ci_b05'
    );
    $root = dirname(__DIR__, 2);
    k2p03_ci_equal(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        k2p03_ci_blob($root . '/lib/daily_population_continuity.php'),
        'corrected shared continuity source pinned'
    );
    k2p03_ci_equal(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        k2p03_ci_blob($root . '/database_schema.sql'),
        '69-table source schema pinned'
    );

    $stage = 'DISPOSABLE_REAL_DATABASE';
    $pdo = k2p03_ci_connection('renee_k2p03_ci_b05');
    require_once $root . '/lib/daily_population_continuity.php';
    require_once $root . '/lib/daily_population_sync.php';
    require_once $root . '/lib/sales_allocation.php';

    $stage = 'SYNTHETIC_TWO_CYCLE_SEED';
    $pdo->exec(
        "INSERT INTO farms(name,slug)
         VALUES('K2P03 B05 synthetic Layer eggs','k2p03-b05-synthetic')"
    );
    $farmId = (int)$pdo->lastInsertId();
    k2p03_ci_check($farmId > 0, 'synthetic B05 farm persisted');

    $cycleInsert = $pdo->prepare(
        "INSERT INTO production_cycles
          (farm_id,cycle_code,farm_type,production_type,status,start_date,opening_headcount)
          VALUES(? ,?,'poultry','layer','active','2026-09-21',100)"
    );
    $cycleInsert->execute([$farmId, 'b05-layer-a']);
    $cycleA = (int)$pdo->lastInsertId();
    $cycleInsert->execute([$farmId, 'b05-layer-b']);
    $cycleB = (int)$pdo->lastInsertId();
    k2p03_ci_check($cycleA > 0 && $cycleB > 0 && $cycleA !== $cycleB,
        'distinct canonical Layer production cycles');

    $baseline = $pdo->prepare(
        "INSERT INTO production_population_baselines
          (farm_id,cycle_id,baseline_date,baseline_quantity,baseline_source)
         VALUES(?,?,'2026-09-21',100,'cycle_opening')"
    );
    foreach ([$cycleA, $cycleB] as $cycleId) {
        $baseline->execute([$farmId, $cycleId]);
    }
    k2p03_ci_equal(2, b05_count($pdo, 'production_population_baselines'),
        'both eligible Layer cycles have canonical baselines');

    $insertDaily = $pdo->prepare(
        'INSERT INTO layer_daily_records
          (farm_id,cycle_id,record_date,opening_stock,mortality,
           egg_production,crates_count,laying_rate,birds_age,feed_consumption_bags)
         VALUES (?,?,?,?,?,?,?,?,25,0)'
    );
    $fixture = [
        'a' => [
            ['2026-09-21', 100, 2, 60, 2, 60.00],
            ['2026-09-22',  98, 1, 90, 3, 91.84],
            ['2026-09-23',  97, 2, 60, 2, 61.86],
        ],
        'b' => [
            ['2026-09-21', 100, 1, 90, 3, 90.00],
            ['2026-09-22',  99, 1, 90, 3, 90.91],
            ['2026-09-23',  98, 1, 90, 3, 91.84],
        ],
    ];
    $dailyIds = ['a'=>[], 'b'=>[]];
    foreach (['a'=>$cycleA, 'b'=>$cycleB] as $side=>$cycleId) {
        foreach ($fixture[$side] as [$date,$opening,$mortality,$eggs,$crates,$rate]) {
            $insertDaily->execute([
                $farmId,$cycleId,$date,$opening,$mortality,$eggs,$crates,$rate,
            ]);
            $id = (int)$pdo->lastInsertId();
            $dailyIds[$side][] = $id;
            $projection = daily_population_sync_mortality(
                $pdo,$farmId,'daily_layer_record',$id,$cycleId,$date,$mortality,null
            );
            k2p03_ci_equal('created', $projection['status'],
                'actual saved source mortality projected ' . $side . ' ' . $date);
        }
    }
    k2p03_ci_equal(6, b05_count($pdo,'layer_daily_records'),
        'six durable Layer records seeded');
    k2p03_ci_equal(6, b05_count($pdo,'production_population_movements'),
        'six immutable original mortality projections seeded');

    $stage = 'HISTORICAL_POOLED_SALES';
    $saleInsert = $pdo->prepare(
        "INSERT INTO sales_records
           (public_reference,farm_id,sale_date,farm_type,production_type,
            attribution_scope,cycle_id,product_type,quantity,unit_of_measure,unit_price)
         VALUES (?, ?, ?,'poultry','layer','production_type',NULL,'eggs',?,'crate',10000.00)"
    );
    $saleInsert->execute(['B05-EGG-001', $farmId, '2026-09-22', 4]);
    $sale1 = (int)$pdo->lastInsertId();
    $saleInsert->execute(['B05-EGG-002', $farmId, '2026-09-23', 8]);
    $sale2 = (int)$pdo->lastInsertId();
    k2p03_ci_check($sale1 > 0 && $sale2 > 0 && $sale1 !== $sale2,
        'two independently persisted historical pooled egg sales');
    k2p03_ci_equal(4000000, b05_money(b05_sale($pdo,$farmId,$sale1)['total_amount']),
        'first immutable sale source equals 40000 currency units');
    k2p03_ci_equal(8000000, b05_money(b05_sale($pdo,$farmId,$sale2)['total_amount']),
        'second immutable sale source equals 80000 currency units');

    $pdo->beginTransaction();
    try {
        $initialRebuild = sales_rebuild_layer_egg_allocations(
            $pdo, $farmId, '2026-09-21'
        );
        k2p03_ci_equal(
            ['rebuilt'=>2,'allocated'=>2,'unallocated'=>0],
            $initialRebuild,
            'existing shared allocator creates two historical pooled sales'
        );
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    $cycles=[$cycleA,$cycleB];
    b05_assert_sale_conserved($pdo,$farmId,$sale1,$cycles);
    b05_assert_sale_conserved($pdo,$farmId,$sale2,$cycles);
    k2p03_ci_equal(2, b05_count($pdo,'sales_records'),
        'allocation rows do not duplicate source sale records');
    k2p03_ci_equal(4, b05_count($pdo,'sales_allocations'),
        'two cycles per historic sale are attributed once');
    k2p03_ci_equal(0, b05_count($pdo,'sales_allocation_revisions'),
        'automatic egg attribution creates no manual shared-revenue revisions');

    $allocBefore=[
        $sale1=>b05_allocations($pdo,$farmId,$sale1),
        $sale2=>b05_allocations($pdo,$farmId,$sale2),
    ];
    $saleBefore=[
        $sale1=>b05_sale($pdo,$farmId,$sale1),
        $sale2=>b05_sale($pdo,$farmId,$sale2),
    ];
    $recordsBefore=[];
    foreach ($dailyIds as $ids) {
        foreach ($ids as $id) {
            $recordsBefore[$id]=b05_daily($pdo,$id);
        }
    }
    $stockBefore=layer_egg_pool_totals(
        layer_egg_pool_before_sale($pdo,$farmId,'2026-09-24',1)
    );
    b05_near(16.0,$stockBefore['produced_crates'],
        'derived pooled production remains 16 crates from six source records');
    b05_near(12.0,$stockBefore['sold_crates'],
        'pooled historical sales consume exactly 12 crates');
    b05_near(4.0,$stockBefore['available_crates'],
        'derived unsold egg inventory is four crates');

    $stage='EGG_EXCEEDS_CORRECTED_OPENING_REJECTION';
    $invalidRejected=false;
    try {
        daily_population_continuity_preview(
            $pdo,$farmId,$cycleA,'layer','2026-09-21',
            100,11,null,false,$dailyIds['a'][0]
        );
    } catch (DailyPopulationContinuityException $e) {
        $invalidRejected=str_contains(
            strtolower($e->getMessage()),'egg production'
        );
    }
    k2p03_ci_check($invalidRejected,
        'future 90 eggs cannot exceed corrected day-two opening 89');
    foreach ($recordsBefore as $id=>$snapshot) {
        k2p03_ci_equal($snapshot,b05_daily($pdo,$id),
            'rejected preview left source records unchanged ' . $id);
    }
    k2p03_ci_equal($allocBefore[$sale1],b05_allocations($pdo,$farmId,$sale1),
        'rejected correction leaves earlier egg revenue allocation unchanged');
    k2p03_ci_equal($allocBefore[$sale2],b05_allocations($pdo,$farmId,$sale2),
        'rejected correction leaves later egg revenue allocation unchanged');
    k2p03_ci_equal(6,b05_count($pdo,'production_population_movements'),
        'rejected preview created no mortality projection');

    $stage='VALID_CANONICAL_LAYER_EGGS_PREVIEW';
    $sourceId=$dailyIds['a'][0];
    $plan=daily_population_continuity_preview(
        $pdo,$farmId,$cycleA,'layer','2026-09-21',
        100,10,null,false,$sourceId
    );
    k2p03_ci_equal('canonical',$plan['tracking_status'],
        'saved source ID keeps V3 canonical population authority');
    k2p03_ci_equal(2,$plan['affected_count'],
        'two later records require canonical opening rebasing');
    k2p03_ci_equal([$dailyIds['a'][1],$dailyIds['a'][2]],
        array_column($plan['changes'],'id'),
        'only selected Layer cycle downstream record IDs are changed');
    k2p03_ci_equal(90,(int)$plan['changes'][0]['new_opening_stock'],
        'corrected day-two opening exactly accommodates 90 eggs');
    k2p03_ci_equal(89,(int)$plan['changes'][1]['new_opening_stock'],
        'corrected day-three opening reflects later mortality');
    k2p03_ci_equal(87,(int)$plan['projected_final_closing_stock'],
        'canonical closing after 10, 1, 2 mortalities');
    b05_near(100.00,$plan['changes'][0]['new_laying_rate'],
        'day-two laying rate preview reaches exactly 100 percent',0.005);
    b05_near(67.42,$plan['changes'][1]['new_laying_rate'],
        'day-three laying rate preview recalculates from 60 eggs',0.005);
    k2p03_ci_equal($allocBefore[$sale1],b05_allocations($pdo,$farmId,$sale1),
        'canonical preview reads but never mutates historical allocation');

    $stage='SINGLE_CALLER_TRANSACTION';
    $pdo->beginTransaction();
    try {
        $update=$pdo->prepare(
            'UPDATE layer_daily_records SET mortality=10
             WHERE farm_id=? AND cycle_id=? AND id=? AND mortality=2'
        );
        $update->execute([$farmId,$cycleA,$sourceId]);
        k2p03_ci_equal(1,$update->rowCount(),
            'caller owns exactly one target mortality edit');

        $applied=daily_population_continuity_apply(
            $pdo,$farmId,$cycleA,'layer','2026-09-21',
            100,10,null,$sourceId
        );
        k2p03_ci_equal('canonical',$applied['tracking_status'],
            'locked apply uses canonical state rather than legacy');
        k2p03_ci_equal(2,$applied['applied_count'],
            'only two later selected-cycle openings changed');
        k2p03_ci_equal(90,(int)b05_daily($pdo,$dailyIds['a'][1])['opening_stock'],
            'persisted day-two opening corrected');
        k2p03_ci_equal(89,(int)b05_daily($pdo,$dailyIds['a'][2])['opening_stock'],
            'persisted day-three opening corrected');
        b05_near(100.00,b05_daily($pdo,$dailyIds['a'][1])['laying_rate'],
            'persisted day-two laying rate updated',0.005);
        b05_near(67.42,b05_daily($pdo,$dailyIds['a'][2])['laying_rate'],
            'persisted day-three laying rate updated',0.005);
        k2p03_ci_equal(90,(int)b05_daily($pdo,$dailyIds['a'][1])['egg_production'],
            'day-two eggs unchanged and equal opening');
        k2p03_ci_equal(60,(int)b05_daily($pdo,$dailyIds['a'][2])['egg_production'],
            'day-three eggs remain durable source');
        foreach ($dailyIds['b'] as $id) {
            k2p03_ci_equal($recordsBefore[$id],b05_daily($pdo,$id),
                'independent Layer cycle unchanged during correction ' . $id);
        }

        $proj=daily_population_sync_mortality(
            $pdo,$farmId,'daily_layer_record',$sourceId,
            $cycleA,'2026-09-21',10,null
        );
        k2p03_ci_equal(2,(int)$proj['source_version'],
            'canonical mortality source advances to version two');
        k2p03_ci_check((int)$proj['reversal_id']>0,
            'old mortality movement compensated by reversal');

        $rebuilt=sales_rebuild_layer_egg_allocations(
            $pdo,$farmId,'2026-09-21'
        );
        k2p03_ci_equal(
            ['rebuilt'=>2,'allocated'=>2,'unallocated'=>0],
            $rebuilt,
            'shared service chronologically rebuilt both historical egg sales'
        );
        b05_assert_sale_conserved($pdo,$farmId,$sale1,$cycles);
        b05_assert_sale_conserved($pdo,$farmId,$sale2,$cycles);

        foreach ([$sale1,$sale2] as $saleId) {
            $new=b05_allocations($pdo,$farmId,$saleId);
            k2p03_ci_equal(count($allocBefore[$saleId]),count($new),
                'rebuilt allocation does not duplicate cycle attribution ' . $saleId);
            foreach ($new as $i=>$r) {
                k2p03_ci_equal(
                    (int)$allocBefore[$saleId][$i]['cycle_id'],
                    (int)$r['cycle_id'],
                    'allocation stays on original physical cycle ' . $saleId);
                b05_near(
                    (float)$allocBefore[$saleId][$i]['allocated_quantity'],
                    $r['allocated_quantity'],
                    'unchanged egg facts conserve allocated crates ' . $saleId);
                k2p03_ci_equal(
                    b05_money($allocBefore[$saleId][$i]['allocated_amount']),
                    b05_money($r['allocated_amount']),
                    'unchanged egg facts conserve revenue per cycle ' . $saleId);
            }
            k2p03_ci_equal($saleBefore[$saleId],b05_sale($pdo,$farmId,$saleId),
                'rebuild never rewrites immutable sale revenue source ' . $saleId);
        }
        k2p03_ci_equal(4,b05_count($pdo,'sales_allocations'),
            'historical rebuild produces exactly four allocation rows');
        k2p03_ci_equal(0,b05_count($pdo,'sales_allocation_revisions'),
            'automatic Layer allocation never creates manual revision records');
        k2p03_ci_equal(2,b05_count($pdo,'sales_records'),
            'sales revenue remains two immutable source records');

        $stockAfter=layer_egg_pool_totals(
            layer_egg_pool_before_sale($pdo,$farmId,'2026-09-24',1)
        );
        k2p03_ci_equal($stockBefore,$stockAfter,
            'egg quantity pool stays balanced after historical mortality correction');
        k2p03_ci_equal(8,b05_count($pdo,'production_population_movements'),
            'only one compensating reversal and one version-two movement added');
        $finalA=production_population_state($pdo,$farmId,$cycleA,'2026-09-23');
        $finalB=production_population_state($pdo,$farmId,$cycleB,'2026-09-23');
        k2p03_ci_equal(87,(int)$finalA['closing_quantity'],
            'corrected Layer cycle canonical close 87');
        k2p03_ci_equal(97,(int)$finalB['closing_quantity'],
            'independent Layer cycle canonical close 97');
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    $stage='FULL_RECONCILIATION_AFTER_ROLLBACK';
    foreach ($recordsBefore as $id=>$snapshot) {
        k2p03_ci_equal($snapshot,b05_daily($pdo,$id),
            'all six original operational Daily Records restored ' . $id);
    }
    foreach ([$sale1,$sale2] as $saleId) {
        k2p03_ci_equal($allocBefore[$saleId],b05_allocations($pdo,$farmId,$saleId),
            'original automatic attribution rows restored ' . $saleId);
        k2p03_ci_equal($saleBefore[$saleId],b05_sale($pdo,$farmId,$saleId),
            'original immutable sale source unchanged ' . $saleId);
    }
    k2p03_ci_equal(6,b05_count($pdo,'production_population_movements'),
        'mortality ledger returns to exactly six original sources');
    k2p03_ci_equal(2,b05_count($pdo,'sales_records'),
        'source sales retained without duplication');
    k2p03_ci_equal(4,b05_count($pdo,'sales_allocations'),
        'original egg allocation row count restored');
    k2p03_ci_equal(0,b05_count($pdo,'sales_allocation_revisions'),
        'no manual revenue revisions created after rollback');
    k2p03_ci_equal($stockBefore,layer_egg_pool_totals(
        layer_egg_pool_before_sale($pdo,$farmId,'2026-09-24',1)
    ),'all egg-pool stock totals restored after rollback');
    k2p03_ci_equal(98,(int)b05_daily($pdo,$dailyIds['a'][1])['opening_stock'],
        'original first later opening restored');
    k2p03_ci_equal(97,(int)b05_daily($pdo,$dailyIds['a'][2])['opening_stock'],
        'original second later opening restored');

    echo 'K2_P03_B05_REAL_MARIADB=PASS' . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // No credentials, DSN, production metadata or stack trace in the job log.
    fwrite(STDERR,'FAILURE_STAGE='.$stage.PHP_EOL);
    fwrite(STDERR,'FAILURE_CLASS='.get_class($e).PHP_EOL);
    fwrite(STDERR,'K2_P03_B05_REAL_MARIADB=FAIL'.PHP_EOL);
    exit(1);
}
