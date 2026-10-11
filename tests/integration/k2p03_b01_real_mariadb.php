<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 — real-MariaDB B01 canonical Layer continuity.
 *
 * This is NOT a production verifier or a D12 fixture importer. Run only in the
 * manual, GitHub-hosted CI job against its freshly created disposable schema.
 * Never include config.php/init.php: they can connect to a deployed database.
 */

$stage = 'GUARDS';
$pdo = null;

function b01_check(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException('B01 assertion failed: ' . $label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}

function b01_equals($expected, $actual, string $label): void
{
    b01_check($expected === $actual, $label);
}

function b01_blob(string $file): string
{
    $data = file_get_contents($file);
    if ($data === false) {
        throw new RuntimeException('Required source is not readable.');
    }
    return sha1('blob ' . strlen($data) . "\0" . $data);
}

function b01_daily_row(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(
        'SELECT id, opening_stock, mortality, egg_production, laying_rate
           FROM layer_daily_records WHERE id = ?'
    );
    $statement->execute([$id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Synthetic Daily Record was not found.');
    }
    return $row;
}

function b01_rate(float $expected, $actual, string $label): void
{
    b01_check(abs((float)$actual - $expected) < 0.005, $label);
}

try {
    // Deliberately fail closed outside the exact, new GitHub-hosted CI target.
    foreach ([
        'GITHUB_ACTIONS' => 'true',
        'RUNNER_ENVIRONMENT' => 'github-hosted',
        'GITHUB_REPOSITORY' => 'abeycity4u/Renee-Farms-V2.2.50-Batch3B1',
        'GITHUB_REF' => 'refs/heads/c137-gc24-k2p03-ci-mariadb-b01',
        'CI_K2P03_B01_ALLOWED' => 'YES',
        'CI_DB_HOST' => '127.0.0.1',
        'CI_DB_PORT' => '3306',
        'CI_DB_NAME' => 'renee_k2p03_ci_b01',
        'CI_DB_USER' => 'ci_k2p03',
    ] as $key => $expected) {
        if (getenv($key) !== $expected) {
            throw new RuntimeException('Execution authority guard failed: ' . $key);
        }
    }

    $password = getenv('CI_DB_PASSWORD');
    if (!is_string($password) || $password !== 'ci-only-nonsecret-b01') {
        throw new RuntimeException('Disposable CI credential guard failed.');
    }

    $root = dirname(__DIR__, 2);
    $continuity = $root . '/lib/daily_population_continuity.php';
    $schema = $root . '/database_schema.sql';

    b01_equals(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        b01_blob($continuity),
        'pinned corrected continuity source'
    );
    b01_equals(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        b01_blob($schema),
        'pinned fresh-install schema'
    );

    $stage = 'DB_IDENTITY';
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=renee_k2p03_ci_b01;charset=utf8mb4',
        'ci_k2p03',
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    unset($password);

    b01_equals('mysql', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'real MySQL PDO driver');
    b01_equals('renee_k2p03_ci_b01', $pdo->query('SELECT DATABASE()')->fetchColumn(), 'isolated schema identity');
    b01_check(
        stripos((string)$pdo->query('SELECT VERSION()')->fetchColumn(), 'MariaDB') !== false,
        'real MariaDB server'
    );

    $tables = (int)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
    )->fetchColumn();
    b01_equals(69, $tables, '69-table fresh schema installed');

    $engineQuery = $pdo->prepare(
        'SELECT engine FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = ?'
    );
    foreach ([
        'farms', 'production_cycles', 'production_population_baselines',
        'production_population_movements', 'layer_daily_records',
    ] as $table) {
        $engineQuery->execute([$table]);
        b01_equals('InnoDB', $engineQuery->fetchColumn(), $table . ' transaction engine');
    }

    require_once $continuity;

    $stage = 'SYNTHETIC_SEED';
    // These are newly inserted synthetic rows in the throwaway CI database.
    $pdo->exec(
        "INSERT INTO farms (name, slug)
         VALUES ('K2 P03 CI synthetic', 'k2p03-b01-synthetic')"
    );
    $farmId = (int)$pdo->lastInsertId();

    $createCycle = $pdo->prepare(
        "INSERT INTO production_cycles
           (farm_id, cycle_code, farm_type, production_type,
            status, start_date, opening_headcount)
         VALUES (?, 'k2p03-b01-layer', 'poultry', 'layer',
                 'active', '2026-09-21', 100)"
    );
    $createCycle->execute([$farmId]);
    $cycleId = (int)$pdo->lastInsertId();

    $createBaseline = $pdo->prepare(
        "INSERT INTO production_population_baselines
           (farm_id, cycle_id, baseline_date, baseline_quantity, baseline_source)
         VALUES (?, ?, '2026-09-21', 100, 'production_entry')"
    );
    $createBaseline->execute([$farmId, $cycleId]);

    $createDaily = $pdo->prepare(
        'INSERT INTO layer_daily_records
           (farm_id, cycle_id, record_date, opening_stock, mortality,
            egg_production, crates_count, laying_rate, birds_age)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 25)'
    );

    $daily = [
        ['2026-09-21', 100, 2, 80, 2, 80.00],
        ['2026-09-22', 98, 1, 80, 2, 81.63],
        ['2026-09-23', 97, 2, 78, 2, 80.41],
    ];
    $dailyIds = [];
    foreach ($daily as $row) {
        $createDaily->execute([$farmId, $cycleId, ...$row]);
        $dailyIds[] = (int)$pdo->lastInsertId();
    }

    $createMovement = $pdo->prepare(
        "INSERT INTO production_population_movements
           (farm_id, cycle_id, movement_date, movement_type,
            quantity_delta, source_type, source_id, source_version)
         VALUES (?, ?, ?, 'mortality', ?, 'daily_layer_record', ?, 1)"
    );

    foreach ($daily as $index => $row) {
        $createMovement->execute([
            $farmId, $cycleId, $row[0], -$row[2], $dailyIds[$index],
        ]);
    }

    $sourceId = $dailyIds[0];
    b01_check($farmId > 0 && $cycleId > 0 && $sourceId > 0, 'real persisted synthetic source IDs');
    b01_equals(2, (int)b01_daily_row($pdo, $sourceId)['mortality'], 'original target mortality persisted');
    b01_equals(
        3,
        (int)$pdo->query('SELECT COUNT(*) FROM production_population_movements')->fetchColumn(),
        'original canonical mortality projection seeded'
    );

    $stage = 'CANONICAL_PREVIEW';
    $plan = daily_population_continuity_preview(
        $pdo, $farmId, $cycleId, 'layer',
        '2026-09-21', 100, 5, null, false, $sourceId
    );
    b01_equals('canonical', $plan['tracking_status'], 'canonical V3 rather than legacy path');
    b01_equals(2, $plan['affected_count'], 'both later Layer dates need rebasing');
    b01_equals(95, (int)$plan['changes'][0]['new_opening_stock'], 'day two preview opening');
    b01_equals(94, (int)$plan['changes'][1]['new_opening_stock'], 'day three preview opening');
    b01_equals(92, (int)$plan['projected_final_closing_stock'], 'canonical final closing after pending mortality');
    b01_equals(98, (int)b01_daily_row($pdo, $dailyIds[1])['opening_stock'], 'preview performed no SQL update');

    $stage = 'CALLER_OWNED_TRANSACTION';
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE layer_daily_records SET mortality = 5
             WHERE id = ? AND farm_id = ? AND cycle_id = ? AND mortality = 2'
        );
        $update->execute([$sourceId, $farmId, $cycleId]);
        b01_equals(1, $update->rowCount(), 'caller saved target mortality before continuity');

        $applied = daily_population_continuity_apply(
            $pdo, $farmId, $cycleId, 'layer',
            '2026-09-21', 100, 5, null, $sourceId
        );
        b01_equals('canonical', $applied['tracking_status'], 'locked canonical transaction path');
        b01_equals(2, $applied['applied_count'], 'two later Layer rows updated');

        $dayTwo = b01_daily_row($pdo, $dailyIds[1]);
        $dayThree = b01_daily_row($pdo, $dailyIds[2]);

        b01_equals(95, (int)$dayTwo['opening_stock'], 'day two opening rebased');
        b01_equals(94, (int)$dayThree['opening_stock'], 'day three opening rebased');
        b01_equals(1, (int)$dayTwo['mortality'], 'day two original mortality unchanged');
        b01_equals(2, (int)$dayThree['mortality'], 'day three original mortality unchanged');
        b01_equals(80, (int)$dayTwo['egg_production'], 'day two egg fact unchanged');
        b01_equals(78, (int)$dayThree['egg_production'], 'day three egg fact unchanged');
        b01_rate(84.21, $dayTwo['laying_rate'], 'day two laying rate recalculated');
        b01_rate(82.98, $dayThree['laying_rate'], 'day three laying rate recalculated');
        b01_equals(5, (int)b01_daily_row($pdo, $sourceId)['mortality'], 'target mortality remains caller owned');
        b01_equals(
            3,
            (int)$pdo->query('SELECT COUNT(*) FROM production_population_movements')->fetchColumn(),
            'continuity service does not write canonical projections'
        );
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    $stage = 'ROLLBACK_RECONCILIATION';
    b01_equals(2, (int)b01_daily_row($pdo, $sourceId)['mortality'], 'target update rolled back');
    b01_equals(98, (int)b01_daily_row($pdo, $dailyIds[1])['opening_stock'], 'day two update rolled back');
    b01_equals(97, (int)b01_daily_row($pdo, $dailyIds[2])['opening_stock'], 'day three update rolled back');
    b01_rate(81.63, b01_daily_row($pdo, $dailyIds[1])['laying_rate'], 'day two derived rate rolled back');
    b01_rate(80.41, b01_daily_row($pdo, $dailyIds[2])['laying_rate'], 'day three derived rate rolled back');

    echo 'K2_P03_B01_REAL_MARIADB=PASS' . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Never echo PDO exception messages, environment variables or DSNs.
    fwrite(STDERR, 'FAILURE_STAGE=' . $stage . PHP_EOL);
    fwrite(STDERR, 'FAILURE_CLASS=' . get_class($error) . PHP_EOL);
    fwrite(STDERR, 'K2_P03_B01_REAL_MARIADB=FAIL' . PHP_EOL);
    exit(1);
}
