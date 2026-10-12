<?php
declare(strict_types=1);

/**
 * Isolated C137 K2/P03 test-only primitives.
 * NO config.php/init.php, NO remote hosts, NO migrations, NO production data.
 * The caller supplies its own exact branch and disposable schema identity.
 */

function k2p03_ci_require_environment(
    string $branch,
    string $scenario,
    string $database
): void {
    // Keep each CI scenario bound to its own branch, schema and fake credential.
    $allowed = [
        'B02' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b02',
            'database' => 'renee_k2p03_ci_b02',
            'password' => 'ci-only-nonsecret-b02',
        ],
        'B12' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b12',
            'database' => 'renee_k2p03_ci_b12',
            'password' => 'ci-only-nonsecret-b12',
        ],
        'B11' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b11',
            'database' => 'renee_k2p03_ci_b11',
            'password' => 'ci-only-nonsecret-b11',
        ],
        'B10R5' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b10-r5',
            'database' => 'renee_k2p03_ci_b10_r5',
            'password' => 'ci-only-nonsecret-b10-r5',
        ],
        'B10R4' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b10-r4',
            'database' => 'renee_k2p03_ci_b10_r4',
            'password' => 'ci-only-nonsecret-b10-r4',
        ],
        'B10R3' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b10-r3',
            'database' => 'renee_k2p03_ci_b10_r3',
            'password' => 'ci-only-nonsecret-b10-r3',
        ],
        'B10R2' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b10-r2',
            'database' => 'renee_k2p03_ci_b10_r2',
            'password' => 'ci-only-nonsecret-b10-r2',
        ],
        'B10R1' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b10-r1',
            'database' => 'renee_k2p03_ci_b10_r1',
            'password' => 'ci-only-nonsecret-b10-r1',
        ],
        'B10' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b10',
            'database' => 'renee_k2p03_ci_b10',
            'password' => 'ci-only-nonsecret-b10',
        ],
        'B09' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b09',
            'database' => 'renee_k2p03_ci_b09',
            'password' => 'ci-only-nonsecret-b09',
        ],
        'B08' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b08',
            'database' => 'renee_k2p03_ci_b08',
            'password' => 'ci-only-nonsecret-b08',
        ],
        'B07' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b07',
            'database' => 'renee_k2p03_ci_b07',
            'password' => 'ci-only-nonsecret-b07',
        ],
        'B06R1' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b06-r1',
            'database' => 'renee_k2p03_ci_b06_r1',
            'password' => 'ci-only-nonsecret-b06-r1',
        ],
        'B06' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b06',
            'database' => 'renee_k2p03_ci_b06',
            'password' => 'ci-only-nonsecret-b06',
        ],
        'B05' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b05',
            'database' => 'renee_k2p03_ci_b05',
            'password' => 'ci-only-nonsecret-b05',
        ],
        'B04' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b04',
            'database' => 'renee_k2p03_ci_b04',
            'password' => 'ci-only-nonsecret-b04',
        ],
        'B03' => [
            'branch' => 'c137-gc24-k2p03-ci-mariadb-b03',
            'database' => 'renee_k2p03_ci_b03',
            'password' => 'ci-only-nonsecret-b03',
        ],
    ];

    if (!isset($allowed[$scenario])
        || $branch !== $allowed[$scenario]['branch']
        || $database !== $allowed[$scenario]['database']
    ) {
        throw new RuntimeException('Unrecognized isolated CI scenario.');
    }

    $expected = [
        'GITHUB_ACTIONS' => 'true',
        'RUNNER_ENVIRONMENT' => 'github-hosted',
        'GITHUB_REPOSITORY' => 'abeycity4u/Renee-Farms-V2.2.50-Batch3B1',
        'GITHUB_REF' => 'refs/heads/' . $branch,
        'CI_K2P03_SCENARIO_ALLOWED' => $scenario,
        'CI_DB_HOST' => '127.0.0.1',
        'CI_DB_PORT' => '3306',
        'CI_DB_NAME' => $database,
        'CI_DB_USER' => 'ci_k2p03',
    ];

    foreach ($expected as $name => $value) {
        if (getenv($name) !== $value) {
            throw new RuntimeException('Isolated CI environment guard failed: ' . $name);
        }
    }

    if (getenv('CI_DB_PASSWORD') !== $allowed[$scenario]['password']) {
        throw new RuntimeException('Disposable synthetic CI credentials do not match.');
    }
}

function k2p03_ci_blob(string $path): string
{
    $data = file_get_contents($path);
    if ($data === false) {
        throw new RuntimeException('Pinned source is not readable.');
    }

    return sha1('blob ' . strlen($data) . "\0" . $data);
}

function k2p03_ci_check(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException('Failed CI integration assertion: ' . $label);
    }

    echo 'PASS: ' . $label . PHP_EOL;
}

function k2p03_ci_equal($expected, $actual, string $label): void
{
    k2p03_ci_check($expected === $actual, $label);
}

function k2p03_ci_connection(string $database): PDO
{
    if (!in_array($database, ['renee_k2p03_ci_b02', 'renee_k2p03_ci_b03', 'renee_k2p03_ci_b04', 'renee_k2p03_ci_b05', 'renee_k2p03_ci_b06', 'renee_k2p03_ci_b06_r1', 'renee_k2p03_ci_b07', 'renee_k2p03_ci_b08', 'renee_k2p03_ci_b09', 'renee_k2p03_ci_b10', 'renee_k2p03_ci_b10_r1', 'renee_k2p03_ci_b10_r2', 'renee_k2p03_ci_b10_r3', 'renee_k2p03_ci_b10_r4', 'renee_k2p03_ci_b10_r5', 'renee_k2p03_ci_b11', 'renee_k2p03_ci_b12'], true)) {
        throw new RuntimeException('The requested CI schema is not approved.');
    }

    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=' . $database . ';charset=utf8mb4',
        'ci_k2p03',
        (string)getenv('CI_DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    k2p03_ci_equal('mysql', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'real PDO mysql driver');
    k2p03_ci_equal($database, $pdo->query('SELECT DATABASE()')->fetchColumn(), 'disposable CI database identity');
    k2p03_ci_check(
        stripos((string)$pdo->query('SELECT VERSION()')->fetchColumn(), 'MariaDB') !== false,
        'real MariaDB server'
    );

    k2p03_ci_equal(
        69,
        (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
        )->fetchColumn(),
        '69-table pinned fresh schema'
    );

    $stmt = $pdo->prepare(
        'SELECT engine FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    foreach ([
        'farms', 'production_cycles', 'production_population_baselines',
        'production_population_movements', 'layer_daily_records', 'broiler_daily_records',
        'sales_records', 'sale_population_effects',
        'ruminant_daily_records',
        'poultry_slaughter_batches', 'production_population_transfers',
        'production_population_transfer_legs',
    ] as $table) {
        $stmt->execute([$table]);
        k2p03_ci_equal('InnoDB', $stmt->fetchColumn(), $table . ' is transactional');
    }

    return $pdo;
}
