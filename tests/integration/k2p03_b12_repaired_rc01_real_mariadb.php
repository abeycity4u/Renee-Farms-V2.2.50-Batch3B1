<?php
declare(strict_types=1);

/**
 * B12 repaired RC01, Layer stale EDIT/EDIT, independent MariaDB connections.
 * SOURCE-ONLY DRAFT: not an executable authorization or certification.
 *
 * The hard-disabled fuse MUST NOT be changed without a reviewed commit and
 * fresh explicit approval naming the exact isolated DB, fixture operations,
 * worker execution, teardown and resulting commit SHA. No workflow invokes it.
 *
 * RC01 deliberately tests only the actual shared CAS authority. RC02 will
 * separately exercise the complete save pipeline and downstream writers.
 * Preserve the original historical k2p03_b12_real_mariadb.php unmodified.
 */
const B12_RC01_EXECUTION_ENABLED = false;
const B12_RC01_DATABASE = 'renee_b12_disposable_synthetic';
const B12_RC01_SOCKET = '/run/mysqld/mysqld.sock';
const B12_RC01_RECORD_DATE = '2026-09-21';
const B12_RC01_SOURCE_BLOBS = [
    'lib/daily_record_revision_authority.php' => '5fbb90903b0cc801be45c684f6c3db85c660979a',
    'lib/daily_record_revision_cutover_gate.php' => '6e88c913ad5e5b371a9d0c92ce08870214ac1268',
    'lib/daily_record_revision_schema_plan.php' => 'a0ddbec542e891d484b48d1adbdac17442eaadf8',
    'database_schema.sql' => 'e77f287d62c5812a3d55dc23b5b20f5d167db389',
];

function rc01_assert(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException('RC01 assertion failed: ' . $label);
    }
}

function rc01_source_gate(): void
{
    $root = dirname(__DIR__, 2);
    foreach (B12_RC01_SOURCE_BLOBS as $relative => $expected) {
        $content = @file_get_contents($root . '/' . $relative);
        rc01_assert(is_string($content), 'source file readable');
        $actual = sha1('blob ' . strlen($content) . "\0" . $content);
        rc01_assert(hash_equals($expected, $actual), 'pinned source blob');
    }
    require_once $root . '/lib/daily_record_revision_cutover_gate.php';
    rc01_assert(daily_record_revision_cutover_enabled() === false, 'B12 gate is OFF');
    require_once $root . '/lib/daily_record_revision_authority.php';
}

function rc01_connection(): PDO
{
    // The future separately approved executor must attest the *Docker engine*
    // and DB-container identity before exposing this restricted socket.
    rc01_assert(getenv('B12_RC01_ISOLATED_MAC_ATTESTED') === 'YES',
        'independent Docker isolation attested');
    rc01_assert(getenv('B12_RC01_DB_NAME') === B12_RC01_DATABASE,
        'disposable DB identity requested');
    rc01_assert(@filetype(B12_RC01_SOCKET) === 'socket',
        'isolated Unix socket exists');
    $password = getenv('B12_RC01_DB_PASSWORD');
    rc01_assert(is_string($password) && $password !== '', 'credential present');
    $pdo = new PDO(
        'mysql:unix_socket=' . B12_RC01_SOCKET . ';dbname=' . B12_RC01_DATABASE . ';charset=utf8mb4',
        'root', $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false,
         PDO::ATTR_TIMEOUT => 5]
    );
    unset($password);
    rc01_assert($pdo->query('SELECT DATABASE()')->fetchColumn() === B12_RC01_DATABASE,
        'exact database identity');
    rc01_assert(str_starts_with((string)$pdo->query('SELECT VERSION()')->fetchColumn(), '10.11.'),
        'reviewed MariaDB major/minor');
    return $pdo;
}

function rc01_metadata_gate(PDO $pdo): void
{
    $tables = (int)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema=DATABASE() AND table_type='BASE TABLE'"
    )->fetchColumn();
    rc01_assert($tables === 69, '69 synthetic tables');
    $columns = (int)$pdo->query(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE()'
    )->fetchColumn();
    rc01_assert($columns === 886, '886 columns including three revised columns');
    $stmt = $pdo->prepare(
        "SELECT table_name,column_type,is_nullable,column_default
         FROM information_schema.columns
         WHERE table_schema=DATABASE() AND column_name='record_revision'
           AND table_name IN ('layer_daily_records','broiler_daily_records','ruminant_daily_records')"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();
    rc01_assert(count($rows) === 3, 'exactly three revision columns');
    $seen = [];
    foreach ($rows as $row) {
        rc01_assert(in_array(strtolower((string)$row['column_type']),
            ['bigint(20) unsigned','bigint unsigned'], true), 'revision type');
        rc01_assert($row['is_nullable'] === 'NO' &&
            trim((string)$row['column_default'], "'") === '1', 'revision default');
        $seen[$row['table_name']] = true;
    }
    rc01_assert(count($seen) === 3, 'one revision column per Daily Record table');
    foreach ([
        'farms', 'production_cycles', 'layer_daily_records',
        'broiler_daily_records', 'ruminant_daily_records',
        'production_population_baselines', 'production_population_movements',
        'stock_items', 'stock_transactions', 'sales_records',
    ] as $table) {
        // Identifiers are from a fixed local allowlist only.
        $n = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        rc01_assert($n === 0, $table . ' has no prior rows');
    }
    rc01_assert((int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 0,
        'no application migrations');
    $fk = $pdo->query("SELECT COUNT(*) FROM information_schema.key_column_usage k
      JOIN information_schema.referential_constraints r
        ON r.constraint_schema=k.constraint_schema
       AND r.table_name=k.table_name AND r.constraint_name=k.constraint_name
      WHERE k.table_schema=DATABASE()
        AND ((k.table_name='layer_daily_records' AND k.constraint_name='fk_layer_cycle')
          OR (k.table_name='broiler_daily_records' AND k.constraint_name='fk_broiler_cycle')
          OR (k.table_name='ruminant_daily_records' AND k.constraint_name='fk_ruminant_cycle'))
        AND k.column_name='cycle_id'
        AND k.referenced_table_name='production_cycles'
        AND k.referenced_column_name='id' AND r.delete_rule='SET NULL'")->fetchColumn();
    rc01_assert((int)$fk === 3, 'three source cycle FKs preserved');
}

function rc01_signal(string $dir, string $name): void
{
    rc01_assert(preg_match('/^[AB]_(?:READY|ATTEMPT|LOCKED|RESULT)$|^(?:GO|RELEASE|ABORT)$/D',
        $name) === 1, 'closed barrier signal');
    rc01_assert(file_put_contents($dir . '/' . $name, "1\n", LOCK_EX) !== false,
        'barrier signal created');
}

function rc01_wait(string $dir, string $name, float $seconds = 12.0): void
{
    $until = microtime(true) + $seconds;
    while (!is_file($dir . '/' . $name)) {
        if (is_file($dir . '/ABORT')) {
            throw new RuntimeException('RC01 worker abort requested');
        }
        if (microtime(true) >= $until) {
            throw new RuntimeException('RC01 bounded barrier timeout');
        }
        usleep(20000);
    }
}

function rc01_result(string $dir, string $role, array $value): void
{
    $temp = $dir . '/' . $role . '_RESULT.tmp';
    $path = $dir . '/' . $role . '_RESULT';
    rc01_assert(file_put_contents($temp,
        json_encode($value, JSON_THROW_ON_ERROR), LOCK_EX) !== false &&
        rename($temp, $path), 'atomic worker result');
}

function rc01_row(PDO $pdo, int $farm, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, farm_id, cycle_id, record_date, record_revision, mortality
         FROM layer_daily_records WHERE id=? AND farm_id=?'
    );
    $stmt->execute([$id, $farm]);
    $row = $stmt->fetch();
    rc01_assert(is_array($row), 'synthetic Layer source row available');
    return $row;
}

function rc01_worker(array $args): void
{
    [$_file, $_mode, $role, $dir, $farmString, $cycleString, $idString] = $args;
    rc01_assert(in_array($role, ['A','B'], true), 'worker role');
    rc01_assert(is_dir($dir) &&
        str_starts_with(basename($dir), 'b12_rc01_'), 'private worker barrier');
    $farm = (int)$farmString;
    $cycle = (int)$cycleString;
    $id = (int)$idString;
    rc01_assert($farm > 0 && $cycle > 0 && $id > 0, 'synthetic IDs');
    rc01_source_gate();
    $pdo = rc01_connection();
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
    $connectionId = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $initial = rc01_row($pdo, $farm, $id);
    rc01_assert((int)$initial['record_revision'] === 1 &&
        (int)$initial['mortality'] === 2 &&
        (int)$initial['cycle_id'] === $cycle, 'same original read');
    $expectedRevision = (int)$initial['record_revision'];
    rc01_signal($dir, $role . '_READY');
    rc01_wait($dir, 'GO');
    if ($role === 'B') {
        rc01_wait($dir, 'A_LOCKED');
    }
    $outcome = 'UNKNOWN';
    $start = hrtime(true);
    try {
        $pdo->beginTransaction();
        if ($role === 'B') rc01_signal($dir, 'B_ATTEMPT');
        $next = daily_record_revision_write(
            $pdo, 'layer', $farm, $id, $cycle, B12_RC01_RECORD_DATE,
            null, $expectedRevision,
            ['mortality' => $role === 'A' ? 4 : 3]
        );
        rc01_assert($next === 2, 'next revision returned by shared CAS');
        if ($role === 'A') {
            rc01_signal($dir, 'A_LOCKED');
            rc01_wait($dir, 'RELEASE');
        }
        rc01_assert($pdo->commit(), 'worker commit');
        $outcome = 'COMMITTED';
    } catch (DailyRecordRevisionConflict $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // An arbitrary revision conflict is not evidence of stale contention.
        // Only the canonical expected-revision mismatch is acceptable in RC01.
        if ($role !== 'B' || $e->getMessage() !==
            'The Daily Record has changed. Reload it before saving.') {
            rc01_result($dir, $role, [
                'status' => 'UNEXPECTED_CONFLICT',
                'connection_id' => $connectionId,
            ]);
            exit(1);
        }
        $outcome = 'STALE_CONFLICT';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        rc01_result($dir, $role, [
            'status' => 'UNEXPECTED_ERROR',
            'class' => get_class($e),
            'connection_id' => $connectionId,
        ]);
        exit(1);
    }
    rc01_result($dir, $role, [
        'status' => $outcome,
        'connection_id' => $connectionId,
        'original_revision' => $expectedRevision,
        'elapsed_ms' => (int)round((hrtime(true) - $start) / 1e6),
    ]);
}

function rc01_seed(PDO $pdo): array
{
    $pdo->beginTransaction();
    try {
        $slug = 'b12-rc01-' . bin2hex(random_bytes(8));
        $stmt = $pdo->prepare('INSERT INTO farms (name,slug) VALUES (?,?)');
        $stmt->execute(['B12 RC01 disposable synthetic farm', $slug]);
        $farm = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare(
            'INSERT INTO production_cycles
             (farm_id, cycle_code, farm_type, production_type, status,
              start_date, opening_headcount)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$farm, 'b12-rc01', 'poultry', 'layer', 'active',
            B12_RC01_RECORD_DATE, 30]);
        $cycle = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare(
            'INSERT INTO layer_daily_records
             (farm_id,cycle_id,record_date,opening_stock,mortality,birds_age)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$farm, $cycle, B12_RC01_RECORD_DATE, 30, 2, 25]);
        $id = (int)$pdo->lastInsertId();
        rc01_assert($pdo->commit(), 'synthetic fixture commit');
        return [$farm, $cycle, $id];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function rc01_cleanup(PDO $pdo, int $farm, int $cycle, int $id): void
{
    // Cleanup is restricted to the precise fixture identity.
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('DELETE FROM layer_daily_records WHERE farm_id=? AND id=?');
        $stmt->execute([$farm,$id]);
        rc01_assert($stmt->rowCount() === 1, 'remove only RC01 layer fixture');
        $stmt = $pdo->prepare('DELETE FROM production_cycles WHERE farm_id=? AND id=?');
        $stmt->execute([$farm,$cycle]);
        rc01_assert($stmt->rowCount() === 1, 'remove only RC01 cycle fixture');
        $stmt = $pdo->prepare(
            "DELETE FROM farms WHERE id=? AND name='B12 RC01 disposable synthetic farm' AND slug LIKE 'b12-rc01-%'"
        );
        $stmt->execute([$farm]);
        rc01_assert($stmt->rowCount() === 1, 'remove only RC01 farm fixture');
        rc01_assert($pdo->commit(), 'fixture teardown committed');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function rc01_controller(): void
{
    rc01_source_gate();
    $pdo = rc01_connection();
    rc01_metadata_gate($pdo);
    $dir = null;
    $processes = [];
    $fixture = null;
    $passed = false;
    try {
        $fixture = rc01_seed($pdo);
        [$farm, $cycle, $id] = $fixture;
        rc01_assert((int)rc01_row($pdo,$farm,$id)['record_revision'] === 1,
            'fresh fixture revision 1');
        $dir = sys_get_temp_dir() . '/b12_rc01_' . bin2hex(random_bytes(10));
        rc01_assert(mkdir($dir,0700) && is_dir($dir), 'private barrier initialized');
        foreach (['A','B'] as $role) {
            $descriptors = [
                0 => ['pipe','r'],
                1 => ['file',$dir.'/'.$role.'_OUT','w'],
                2 => ['file',$dir.'/'.$role.'_ERR','w'],
            ];
            $proc = proc_open([PHP_BINARY,__FILE__,'--worker',$role,$dir,
                (string)$farm,(string)$cycle,(string)$id], $descriptors,$pipes);
            rc01_assert(is_resource($proc), 'independent worker process launched');
            fclose($pipes[0]);
            $processes[$role] = $proc;
        }
        rc01_wait($dir,'A_READY');
        rc01_wait($dir,'B_READY');
        rc01_signal($dir,'GO');
        rc01_wait($dir,'A_LOCKED');
        rc01_wait($dir,'B_ATTEMPT');
        usleep(500000);
        rc01_assert(!is_file($dir.'/B_RESULT'), 'B cannot finish while A holds row lock');
        rc01_signal($dir,'RELEASE');
        rc01_wait($dir,'A_RESULT');
        rc01_wait($dir,'B_RESULT');
        $result = [];
        foreach (['A','B'] as $role) {
            $result[$role] = json_decode(
                (string)file_get_contents($dir.'/'.$role.'_RESULT'),
                true, 512, JSON_THROW_ON_ERROR
            );
            rc01_assert(is_array($result[$role]), 'structured worker result');
        }
        rc01_assert($result['A']['status'] === 'COMMITTED' &&
            $result['B']['status'] === 'STALE_CONFLICT', 'one commit, one explicit stale');
        rc01_assert((int)$result['A']['connection_id'] > 0 &&
            (int)$result['B']['connection_id'] > 0 &&
            $result['A']['connection_id'] !== $result['B']['connection_id'],
            'distinct real database connection IDs');
        rc01_assert($result['A']['original_revision'] === 1 &&
            $result['B']['original_revision'] === 1, 'both loaded the same revision');
        rc01_assert((int)$result['B']['elapsed_ms'] >= 300,
            'stale writer was blocked by first lock');
        foreach ($processes as $role => $proc) {
            $until = microtime(true) + 3.0;
            do {
                $state = proc_get_status($proc);
                if (!$state['running']) break;
                usleep(20000);
            } while (microtime(true) < $until);
            rc01_assert(!$state['running'] && (int)$state['exitcode'] === 0,
                'independent worker ' . $role . ' exited successfully');
        }
        $stored = rc01_row($pdo, $farm, $id);
        rc01_assert((int)$stored['record_revision'] === 2 &&
            (int)$stored['mortality'] === 4,
            'independent read confirms winner-only durable CAS');
        echo "RC01_TWO_CONNECTIONS=PASS\n";
        echo "RC01_EXACTLY_ONE_COMMIT=PASS\n";
        echo "RC01_STALE_CONFLICT=PASS\n";
        echo "RC01_DURABLE_REVISION=2\n";
        $passed = true;
    } finally {
        if ($dir !== null && is_dir($dir)) {
            rc01_signal($dir,'ABORT');
        }
        foreach ($processes as $proc) {
            if (!is_resource($proc)) continue;
            $status = proc_get_status($proc);
            if ($status['running']) {
                proc_terminate($proc, 15);
                usleep(250000);
                if (proc_get_status($proc)['running']) proc_terminate($proc, 9);
            }
            proc_close($proc);
        }
        if ($fixture !== null) {
            [$farm,$cycle,$id] = $fixture;
            rc01_cleanup($pdo,$farm,$cycle,$id);
        }
        if ($dir !== null && is_dir($dir) &&
            str_starts_with(basename($dir), 'b12_rc01_')) {
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $file = $dir . '/' . $entry;
                if (is_file($file) || is_link($file)) unlink($file);
            }
            rmdir($dir);
        }
    }
    rc01_assert($passed, 'RC01 completed');
    rc01_metadata_gate($pdo);
    echo "RC01_FIXTURE_TEARDOWN=PASS\n";
}

// Runtime fuse intentionally precedes any database connection or fixture.
if (!B12_RC01_EXECUTION_ENABLED) {
    fwrite(STDERR, "B12_RC01=SOURCE_ONLY_HARD_DISABLED\n");
    exit(90);
}

try {
    if (($argv[1] ?? '') === '--controller' && count($argv) === 2) {
        rc01_controller();
    } elseif (($argv[1] ?? '') === '--worker' && count($argv) === 7) {
        rc01_worker($argv);
    } else {
        throw new RuntimeException('RC01 requires a separately approved runner mode.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, "B12_RC01_RESULT=FAIL_CLOSED\n");
    fwrite(STDERR, 'B12_RC01_ERROR_CLASS=' . get_class($error) . "\n");
    exit(1);
}
