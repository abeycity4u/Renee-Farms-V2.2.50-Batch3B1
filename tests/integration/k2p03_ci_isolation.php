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
    if (!in_array($database, ['renee_k2p03_ci_b02', 'renee_k2p03_ci_b03'], true)) {
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
        'production_population_movements', 'broiler_daily_records',
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
