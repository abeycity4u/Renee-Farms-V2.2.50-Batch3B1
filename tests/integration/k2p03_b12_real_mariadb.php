<?php
declare(strict_types=1);

/**
 * C137 GC24 K2-P03 B12 — two REAL independently connected, competing Layer
 * correction processes. Synthetic GitHub-hosted disposable MariaDB ONLY.
 *
 * SOURCE-ONLY until a separately approved, one-shot B12 workflow invocation.
 * Uses source-route-equivalent target UPDATE (no invented optimistic guard)
 * then the ORIGINAL shared continuity -> feed -> mortality -> egg allocation.
 * A stale second edit silently overwriting the first MUST FAIL this test.
 */
require_once __DIR__ . '/k2p03_ci_isolation.php';

const B12_BRANCH = 'c137-gc24-k2p03-ci-mariadb-b12';
const B12_DB = 'renee_k2p03_ci_b12';
const B12_DATE = '2026-09-21';
const B12_TABLES = [
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

function b12_env(): void
{
    k2p03_ci_require_environment(B12_BRANCH, 'B12', B12_DB);
    $root = dirname(__DIR__, 2);
    foreach ([
        'database_schema.sql' => 'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        'poultry/layers_daily_record.php' => '87b7c6ace9bd577ad2303ea4aa50312a780412d2',
        'lib/daily_population_continuity.php' => '9503771b47c6c2fe26bb3237da69a17874e724a3',
        'lib/daily_population_boundary.php' => '38b346a177788a439355cd18205ab16c47065310',
        'lib/production_population.php' => 'fff1b3e663bb0845c34ccf734a52f6c92eaae706',
        'lib/production_population_projection.php' => 'c4cdae4c193b624a7469915e0451d41fef1b2f4b',
        'lib/daily_population_sync.php' => 'ff4b045000910af222c5203347419c7a7cb66e6a',
        'lib/daily_feed_sync.php' => '752707749fea0583249ec600bb5d624d416b8a47',
        'lib/stock_consumption_source_resolver.php' => 'd9eeaeb53225f3ac1797d5065cc15bb773558200',
        'lib/stock_service.php' => 'b9e7b021020ffaa92fa201800572451f8762d520',
        'lib/sales_allocation.php' => '1a701fff059189b6a9ad3fe1a190188be958a96b',
    ] as $path => $sha) {
        if (k2p03_ci_blob($root . '/' . $path) !== $sha) {
            throw new RuntimeException('Pinned B12 source mismatch (fail closed).');
        }
    }
}

function b12_services(): void
{
    $root = dirname(__DIR__, 2);
    require_once $root . '/lib/daily_population_continuity.php';
    require_once $root . '/lib/daily_population_sync.php';
    require_once $root . '/lib/daily_feed_sync.php';
    require_once $root . '/lib/sales_allocation.php';
}

function b12_snapshot(PDO $pdo, int $farm, string $table): array
{
    if (!in_array($table, B12_TABLES, true)) {
        throw new RuntimeException('B12 snapshot table not allowlisted.');
    }
    $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE farm_id=? ORDER BY id');
    $stmt->execute([$farm]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function b12_row(PDO $pdo, int $farm, string $table, int $id): array
{
    if (!in_array($table, ['layer_daily_records', 'stock_items'], true)) {
        throw new RuntimeException('B12 row table not allowlisted.');
    }
    $s = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE farm_id=? AND id=?');
    $s->execute([$farm, $id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Synthetic scoped row missing.');
    }
    return $row;
}

function b12_signal(string $dir, string $name): void
{
    if (!preg_match('/^[A-Z_]+$/D', $name)) {
        throw new RuntimeException('Invalid B12 synchronization signal.');
    }
    if (file_put_contents($dir . '/' . $name, "1\n", LOCK_EX) === false) {
        throw new RuntimeException('B12 synchronization write failed.');
    }
}

function b12_wait(string $dir, string $name, float $seconds = 18.0): void
{
    $until = microtime(true) + $seconds;
    while (!is_file($dir . '/' . $name)) {
        if (is_file($dir . '/ABORT')) {
            throw new RuntimeException('B12 bounded synchronization aborted.');
        }
        if (microtime(true) >= $until) {
            throw new RuntimeException('B12 synchronization timed out.');
        }
        usleep(20000);
    }
}

function b12_result(string $dir, string $role, array $result): void
{
    $path = $dir . '/' . $role . '_RESULT';
    $temp = $path . '.tmp';
    $json = json_encode($result, JSON_THROW_ON_ERROR);
    if (file_put_contents($temp, $json, LOCK_EX) === false || !rename($temp, $path)) {
        throw new RuntimeException('B12 atomic worker result write failed.');
    }
}

/**
 * Worker start is a new OS PHP process and a NEW PDO/MySQL connection.
 * Both workers read the target BEFORE the initial write gate. B takes the
 * target UPDATE only after A has acquired its uncommitted exclusive row lock.
 */
function b12_worker(array $args): void
{
    $role = $args[1] ?? '';
    $dir = $args[2] ?? '';
    $farm = (int)($args[3] ?? 0);
    $cycle = (int)($args[4] ?? 0);
    $target = (int)($args[5] ?? 0);
    $feed = (int)($args[6] ?? 0);
    $pdo = null;
    $waitMs = 0;
    $conn = 0;
    $staleMortality = null;
    $workerFailed = false;
    try {
        if (!in_array($role, ['A', 'B'], true)
            || !is_dir($dir) || $farm <= 0 || $cycle <= 0
            || $target <= 0 || $feed <= 0
            || !str_starts_with(basename($dir), 'k2p03_b12_')) {
            throw new RuntimeException('Invalid isolated B12 worker arguments.');
        }
        b12_env();
        $pdo = k2p03_ci_connection(B12_DB);
        b12_services();
        $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
        $conn = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        $isolation = (string)$pdo->query('SELECT @@tx_isolation')->fetchColumn();
        $prior = b12_row($pdo, $farm, 'layer_daily_records', $target);
        $staleMortality = (int)$prior['mortality'];
        k2p03_ci_equal(2, $staleMortality, $role . ' read same initial target mortality');
        k2p03_ci_equal('1.00', (string)$prior['feed_consumption_bags'],
            $role . ' read same initial target feed');
        k2p03_ci_equal($cycle, (int)$prior['cycle_id'],
            $role . ' stayed inside synthetic production cycle');
        b12_signal($dir, $role . '_READY');
        b12_wait($dir, 'GO');

        $mortality = $role === 'A' ? 4 : 3;
        $feedBags = $role === 'A' ? 2.0 : 3.0;
        if ($role === 'B') {
            b12_wait($dir, 'A_LOCKED');
        }
        $pdo->beginTransaction();
        if ($role === 'B') {
            b12_signal($dir, 'B_ATTEMPT');
        }
        $start = hrtime(true);
        /*
         * Mirrors the affected route's target UPDATE predicate and its
         * absence of a stale-value / expected-version condition. ONLY
         * mortality and feed columns differ from the synthetic base row.
         * No ad hoc CAS guard is added to make this test pass.
         */
        $update = $pdo->prepare(
            'UPDATE layer_daily_records
             SET mortality=?, feed_consumption_bags=?, feed_item_id=?
             WHERE farm_id=? AND record_date=? AND cycle_id=?'
        );
        $update->execute([$mortality, $feedBags, $feed, $farm, B12_DATE, $cycle]);
        $waitMs = (int)round((hrtime(true) - $start) / 1_000_000);
        if ($role === 'A') {
            b12_signal($dir, 'A_LOCKED');
            b12_wait($dir, 'RELEASE');
        }
        $plan = daily_population_continuity_apply(
            $pdo, $farm, $cycle, 'layer', B12_DATE, 30, $mortality, null, $target
        );
        k2p03_ci_equal('canonical', $plan['tracking_status'],
            $role . ' genuine canonical continuity writer');
        sync_daily_feed_usage(
            $pdo, $farm, $target, $feed, $feedBags, $cycle,
            B12_DATE, 'poultry', 'layer', 'daily_layer_record'
        );
        daily_population_sync_mortality(
            $pdo, $farm, 'daily_layer_record', $target,
            $cycle, B12_DATE, $mortality, null
        );
        sales_rebuild_layer_egg_allocations($pdo, $farm, B12_DATE);
        $pdo->commit();
        b12_result($dir, $role, [
            'status' => 'committed',
            'connection_id' => $conn,
            'isolation' => $isolation,
            'stale_mortality' => $staleMortality,
            'desired_mortality' => $mortality,
            'desired_feed' => $feedBags,
            'wait_ms' => $waitMs,
        ]);
        return;
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // ONLY a known explicit stale-row optimistic conflict is acceptable.
        // Do NOT reclassify deadlocks, timeouts, arbitrary failures as PASS.
        $conflict = $e instanceof DailyPopulationContinuityException
            && str_contains($e->getMessage(), 'Retry the correction.');
        if ($role === 'A' || $role === 'B') {
            try {
                b12_result($dir, $role, [
                    'status' => $conflict ? 'conflict' : 'error',
                    'category' => $conflict ? 'OPTIMISTIC_CONFLICT' : get_class($e),
                    'connection_id' => $conn,
                    'stale_mortality' => $staleMortality,
                    'wait_ms' => $waitMs,
                ]);
            } catch (Throwable $ignored) {
                // The controller fails on missing results; no false PASS.
            }
        }
        if (!$conflict) {
            fwrite(STDERR, 'B12_WORKER_FAILURE_ROLE=' . $role . PHP_EOL);
            fwrite(STDERR, 'B12_WORKER_FAILURE_CLASS=' . get_class($e) . PHP_EOL);
            $workerFailed = true;
        }
    } finally {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    if ($workerFailed) exit(1);
}

function b12_seed(PDO $pdo): array
{
    // Every SQL INSERT below seeds synthetic facts; mutations under test use
    // ORIGINAL shared production writers, never a second business policy.
    $pdo->exec(
        "INSERT INTO farms(name,slug)
         VALUES('K2P03 B12 concurrent correction','k2p03-b12-synthetic')"
    );
    $farm = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO production_cycles
         (farm_id,cycle_code,farm_type,production_type,status,start_date,opening_headcount)
         VALUES (?,'b12-layer','poultry','layer','active','2026-09-21',30)"
    )->execute([$farm]);
    $cycle = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO production_population_baselines
         (farm_id,cycle_id,baseline_date,baseline_quantity,baseline_source)
         VALUES (?,?,'2026-09-21',30,'cycle_opening')"
    )->execute([$farm, $cycle]);

    $pdo->beginTransaction();
    try {
        production_population_record_movement(
            $pdo, $farm, $cycle, 'adjustment_in', '2026-09-22',
            20, 'adjustment', null, 1, 'b1200000000000000000000000000001',
            'Synthetic later intake', null
        );
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    $insert = $pdo->prepare(
        'INSERT INTO layer_daily_records
         (farm_id,cycle_id,record_date,opening_stock,mortality,
          feed_consumption_bags,egg_production,crates_count,laying_rate,birds_age)
         VALUES (?,?,?,?,?,1,?,?,?,25)'
    );
    $days = [
        ['2026-09-21', 30, 2, 15, 1, 50.00],
        ['2026-09-22', 28, 20, 8, 1, 28.57],
        ['2026-09-23', 28, 1, 8, 1, 28.57],
    ];
    $ids = [];
    foreach ($days as $day) {
        $insert->execute([$farm, $cycle, ...$day]);
        $id = (int)$pdo->lastInsertId();
        $ids[] = $id;
        daily_population_sync_mortality(
            $pdo, $farm, 'daily_layer_record', $id,
            $cycle, $day[0], $day[2], null
        );
    }

    $pdo->prepare(
        "INSERT INTO inventory_categories
         (farm_id,category_name,farm_type,unit,financial_type,inventory_role)
         VALUES(?,'B12 synthetic layer feed','poultry','bag','feed','operational')"
    )->execute([$farm]);
    $category = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO stock_items
         (farm_id,item_name,category_id,current_stock,unit_cost,unit,farm_type,
          feed_category,default_production_type,financial_classification)
         VALUES(?,'B12 feed',?,0,0,'bag','poultry','layer','layer','feed')"
    )->execute([$farm,$category]);
    $feed = (int)$pdo->lastInsertId();

    $pdo->beginTransaction();
    try {
        stock_apply_movement(
            $pdo,$farm,$feed,'received',20.0,B12_DATE,
            'Synthetic feed receipt',null,'poultry','layer',null,'inventory_api',null
        );
        foreach ($ids as $i => $id) {
            sync_daily_feed_usage(
                $pdo,$farm,$id,$feed,1.0,$cycle,$days[$i][0],
                'poultry','layer','daily_layer_record'
            );
        }
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    $pdo->prepare(
        "INSERT INTO sales_records
         (public_reference,farm_id,sale_date,farm_type,production_type,
          attribution_scope,cycle_id,product_type,quantity,unit_of_measure,unit_price)
         VALUES('B12-EGGS-001',?,'2026-09-22','poultry','layer',
                'production_type',NULL,'eggs',1,'crate',1000.00)"
    )->execute([$farm]);
    $pdo->beginTransaction();
    try {
        sales_rebuild_layer_egg_allocations($pdo, $farm, B12_DATE);
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    // Independent synthetic tenant must remain completely unaffected.
    $pdo->exec(
        "INSERT INTO farms(name,slug)
         VALUES('K2P03 B12 other tenant','k2p03-b12-other')"
    );
    $otherFarm = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO production_cycles
         (farm_id,cycle_code,farm_type,production_type,status,start_date,opening_headcount)
         VALUES(?,'b12-other','poultry','layer','active','2026-09-21',12)"
    )->execute([$otherFarm]);
    $otherCycle = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO production_population_baselines
         (farm_id,cycle_id,baseline_date,baseline_quantity,baseline_source)
         VALUES (?,?,'2026-09-21',12,'cycle_opening')"
    )->execute([$otherFarm,$otherCycle]);
    $pdo->prepare(
        "INSERT INTO layer_daily_records
         (farm_id,cycle_id,record_date,opening_stock,mortality,birds_age)
         VALUES(?,?,'2026-09-21',12,0,25)"
    )->execute([$otherFarm,$otherCycle]);

    return [$farm,$cycle,$ids,$feed,$otherFarm];
}

function b12_cleanup(?string $dir, array $processes): void
{
    if ($dir !== null && is_dir($dir)) {
        b12_signal($dir, 'ABORT');
    }
    foreach ($processes as $proc) {
        if (!is_resource($proc)) continue;
        $status = proc_get_status($proc);
        if ($status['running']) {
            proc_terminate($proc, 15);
            usleep(180000);
            $status = proc_get_status($proc);
            if ($status['running']) proc_terminate($proc, 9);
        }
        proc_close($proc);
    }
    if ($dir !== null && is_dir($dir)
        && str_starts_with(basename($dir), 'k2p03_b12_')) {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $dir . '/' . $name;
            if (is_file($path) || is_link($path)) unlink($path);
        }
        rmdir($dir);
    }
}

function b12_controller(): void
{
    $stage = 'ENVIRONMENT';
    $pdo = null;
    $dir = null;
    $processes = [];
    $failed = false;
    try {
        b12_env();
        $pdo = k2p03_ci_connection(B12_DB);
        b12_services();
        $isolation = (string)$pdo->query('SELECT @@tx_isolation')->fetchColumn();
        k2p03_ci_check($isolation !== '', 'real MariaDB isolation level observable');
        $stage = 'SYNTHETIC_FIXTURE';
        [$farm,$cycle,$ids,$feed,$otherFarm] = b12_seed($pdo);
        k2p03_ci_equal(3, count($ids), 'three distinct Layer dates seeded');
        k2p03_ci_equal(27,
            (int)production_population_state($pdo,$farm,$cycle,'2026-09-23')['quantity'],
            'initial durable canonical flock is 27');
        k2p03_ci_equal('17.00',
            (string)b12_row($pdo,$farm,'stock_items',$feed)['current_stock'],
            'initial original feed balance is 17 bags');
        $otherBefore = [];
        $sourceBefore = [];
        foreach (B12_TABLES as $table) {
            $otherBefore[$table] = b12_snapshot($pdo,$otherFarm,$table);
            $sourceBefore[$table] = b12_snapshot($pdo,$farm,$table);
        }
        k2p03_ci_equal(1, count($sourceBefore['sales_allocations']),
            'one pooled egg allocation before contention');
        $stage = 'SPAWN_INDEPENDENT_WORKERS';
        $dir = sys_get_temp_dir() . '/k2p03_b12_' . bin2hex(random_bytes(12));
        if (!mkdir($dir, 0700) || !is_dir($dir)) {
            throw new RuntimeException('Failed to establish private temporary barrier.');
        }
        foreach (['A','B'] as $role) {
            $out = $dir . '/' . $role . '_OUT';
            $err = $dir . '/' . $role . '_ERR';
            $descriptors = [
                0 => ['pipe','r'],
                1 => ['file',$out,'w'],
                2 => ['file',$err,'w'],
            ];
            $proc = proc_open(
                [PHP_BINARY,__FILE__,$role,$dir,(string)$farm,(string)$cycle,
                    (string)$ids[0],(string)$feed],
                $descriptors,$pipes
            );
            if (!is_resource($proc)) {
                throw new RuntimeException('Independent B12 worker could not launch.');
            }
            fclose($pipes[0]);
            $processes[$role] = $proc;
        }
        b12_wait($dir, 'A_READY');
        b12_wait($dir, 'B_READY');
        b12_signal($dir, 'GO');
        b12_wait($dir, 'A_LOCKED');
        b12_wait($dir, 'B_ATTEMPT');
        // Keep A's target row locked while B enters its competing UPDATE.
        usleep(700000);
        k2p03_ci_check(!is_file($dir.'/B_RESULT'),
            'second updater does not finish while first row lock is held');
        b12_signal($dir, 'RELEASE');
        $stage = 'BOUNDED_WORKER_RESULTS';
        b12_wait($dir, 'A_RESULT');
        b12_wait($dir, 'B_RESULT');
        $resultA = json_decode(
            (string)file_get_contents($dir.'/A_RESULT'),true,512,JSON_THROW_ON_ERROR
        );
        $resultB = json_decode(
            (string)file_get_contents($dir.'/B_RESULT'),true,512,JSON_THROW_ON_ERROR
        );
        k2p03_ci_check(is_array($resultA)&&is_array($resultB),
            'both independent workers returned structured outcomes');
        k2p03_ci_check(
            (int)($resultA['connection_id'] ?? 0) > 0
            && (int)($resultB['connection_id'] ?? 0) > 0
            && $resultA['connection_id'] !== $resultB['connection_id'],
            'two distinct actual MariaDB connection IDs were used'
        );
        k2p03_ci_equal(2, (int)($resultA['stale_mortality'] ?? -1),
            'first editor loaded original mortality');
        k2p03_ci_equal(2, (int)($resultB['stale_mortality'] ?? -1),
            'second editor also loaded stale original mortality');
        k2p03_ci_check((int)($resultB['wait_ms'] ?? 0)>=300,
            'second UPDATE genuinely waited during first uncommitted lock');
        k2p03_ci_equal($isolation,(string)($resultA['isolation'] ?? ''),
            'first worker MariaDB isolation matches controller');
        k2p03_ci_equal($isolation,(string)($resultB['isolation'] ?? ''),
            'second worker MariaDB isolation matches controller');

        $stage = 'DURABLE_RECONCILIATION';
        foreach (B12_TABLES as $table) {
            k2p03_ci_equal($otherBefore[$table],
                b12_snapshot($pdo,$otherFarm,$table),
                'other synthetic farm untouched: '.$table);
        }
        k2p03_ci_equal($sourceBefore['sales_records'],
            b12_snapshot($pdo,$farm,'sales_records'),
            'original pooled egg sale source is immutable');
        k2p03_ci_equal(0,count(b12_snapshot($pdo,$farm,'sales_allocation_revisions')),
            'automatic sale attribution cannot create manual revision');
        k2p03_ci_equal(1,count(b12_snapshot($pdo,$farm,'sales_allocations')),
            'one conserved pooled Layer egg allocation');

        $statuses = [
            (string)($resultA['status'] ?? ''),
            (string)($resultB['status'] ?? ''),
        ];
        $committed = count(array_filter($statuses,
            static fn(string $status): bool => $status === 'committed'));
        $conflicts = count(array_filter($statuses,
            static fn(string $status): bool => $status === 'conflict'));
        $target = b12_row($pdo,$farm,'layer_daily_records',$ids[0]);
        $active = $pdo->prepare(
            "SELECT source_version,quantity_delta FROM production_population_movements m
             WHERE farm_id=? AND source_type='daily_layer_record' AND source_id=?
               AND reversal_of_id IS NULL AND NOT EXISTS
                 (SELECT 1 FROM production_population_movements r
                  WHERE r.farm_id=m.farm_id AND r.cycle_id=m.cycle_id
                    AND r.reversal_of_id=m.id)"
        );
        $active->execute([$farm,$ids[0]]);
        $activeMovements=$active->fetchAll(PDO::FETCH_ASSOC);
        k2p03_ci_equal(1,count($activeMovements),
            'exactly one effective mortality source movement');
        $feedTx = $pdo->prepare(
            "SELECT quantity FROM stock_transactions WHERE farm_id=?
             AND source_type='daily_layer_record' AND source_id=?
             AND transaction_type='used' AND is_reversed=0"
        );
        $feedTx->execute([$farm,$ids[0]]);
        $liveFeed=$feedTx->fetchAll(PDO::FETCH_ASSOC);
        k2p03_ci_equal(1,count($liveFeed),
            'exactly one active source-owned feed USED movement');
        k2p03_ci_check(!$pdo->inTransaction(),
            'controller has no open MariaDB transaction');

        /*
         * Important: a second successful write from the same PRE-edit state
         * is NOT success, even if ledgers happen to reconcile. No silent
         * overwrite is the B12 requirement; never mask it as last-write-wins.
         */
        $stage = 'NO_SILENT_OVERWRITE';
        if ($committed !== 1 || $conflicts !== 1) {
            fwrite(STDERR,'B12_CONCURRENCY_OUTCOME='.
                ($committed===2 ? 'TWO_STALE_COMMITS' : 'NOT_ONE_COMMIT_ONE_CONFLICT').PHP_EOL);
            throw new RuntimeException('B12 no-silent-overwrite invariant failed.');
        }
        $winner = $statuses[0] === 'committed' ? $resultA : $resultB;
        $mortality = (int)($winner['desired_mortality'] ?? -1);
        $bags = (float)($winner['desired_feed'] ?? -1);
        k2p03_ci_equal($mortality,(int)$target['mortality'],
            'authoritative target matches sole successful editor');
        k2p03_ci_equal(number_format($bags,2,'.',''),
            (string)$target['feed_consumption_bags'],
            'authoritative target feed matches sole successful editor');
        k2p03_ci_equal(2,(int)$activeMovements[0]['source_version'],
            'sole commit produced exactly one new mortality source version');
        k2p03_ci_equal(-$mortality,(int)$activeMovements[0]['quantity_delta'],
            'mortality movement matches sole winning editor');
        k2p03_ci_equal(number_format($bags,2,'.',''),(string)$liveFeed[0]['quantity'],
            'source-owned USED movement matches sole winning editor');
        k2p03_ci_equal(number_format(18-$bags,2,'.',''),
            (string)b12_row($pdo,$farm,'stock_items',$feed)['current_stock'],
            'stock receipt minus 2 other daily uses and winning edit conserved');
        k2p03_ci_equal(29-$mortality,(int)production_population_state(
            $pdo,$farm,$cycle,'2026-09-23')['quantity'],
            'physical flock reconciled to winner-only mortality');
        foreach ([$ids[1],$ids[2]] as $later) {
            k2p03_ci_equal(30-$mortality,
                (int)b12_row($pdo,$farm,'layer_daily_records',$later)['opening_stock'],
                'downstream opening is based on the sole successful correction');
        }
        echo 'K2_P03_B12_REAL_MARIADB=PASS'.PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR,'FAILURE_STAGE='.$stage.PHP_EOL);
        fwrite(STDERR,'FAILURE_CLASS='.get_class($e).PHP_EOL);
        fwrite(STDERR,'K2_P03_B12_REAL_MARIADB=FAIL'.PHP_EOL);
        $failed = true;
    } finally {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        b12_cleanup($dir, $processes);
        echo 'B12_LOCAL_WORKER_CLEANUP=COMPLETE'.PHP_EOL;
    }
    if ($failed) exit(1);
}

$role = $argv[1] ?? '';
if ($role === 'A' || $role === 'B') {
    b12_worker($argv);
} elseif ($role === '--controller') {
    b12_controller();
} else {
    fwrite(STDERR,"B12 explicit controller/worker mode is required.\n");
    exit(1);
}
