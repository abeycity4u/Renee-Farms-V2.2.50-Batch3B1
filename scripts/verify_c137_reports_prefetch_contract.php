<?php
declare(strict_types=1);

// Fixture verification is a command-line operation only.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

/*
 * C137 Reports prefetch contract verifier.
 *
 * Run only against the designated isolated fixture using a
 * root-protected, read-only credential JSON supplied as argv[1].
 *
 * No production credentials, writes, migrations or load tests.
 */

final class C137ContractPDO extends PDO
{
    public array $sql = [];

    private function record(string $query): void
    {
        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $query)) {
            throw new RuntimeException('NON_SELECT_SQL_BLOCKED');
        }

        $this->sql[] = $query;
    }

    public function prepare(
        string $query,
        array $options = []
    ): PDOStatement|false {
        $this->record($query);
        return parent::prepare($query, $options);
    }

    public function query(
        string $query,
        ?int $fetchMode = null,
        mixed ...$fetchModeArgs
    ): PDOStatement|false {
        $this->record($query);

        return $fetchMode === null
            ? parent::query($query)
            : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        if (!in_array(
            strtoupper(trim($statement)),
            ['START TRANSACTION READ ONLY', 'ROLLBACK'],
            true
        )) {
            throw new RuntimeException('NON_READONLY_EXEC_BLOCKED');
        }

        return parent::exec($statement);
    }
}

function verify(string $label, bool $passed): void
{
    echo $label . '=' . ($passed ? 'PASS' : 'FAIL') . "\n";

    if (!$passed) {
        throw new RuntimeException($label);
    }
}

function sourceQueries(array $statements): int
{
    $count = 0;

    foreach ($statements as $sql) {
        if (
            preg_match('/\bFROM\s+stock_transactions\s+t\b/i', $sql)
            &&
            preg_match('/\bJOIN\s+stock_items\s+s\b/i', $sql)
        ) {
            $count++;
        }
    }

    return $count;
}

function connectFixture(
    string $dsn,
    string $username,
    string $password
): C137ContractPDO {
    $pdo = new C137ContractPDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false,
        ]
    );

    $identity = $pdo->query(
        'SELECT DATABASE() AS db_name, '
        . 'CURRENT_USER() AS db_user'
    )->fetch(PDO::FETCH_ASSOC);

    verify(
        'READONLY_DATABASE_IDENTITY',
        ($identity['db_name'] ?? '') === 'renee_c137_fixture'
        &&
        ($identity['db_user'] ?? '')
            === 'c137_fixture_reader@172.31.40.149'
    );

    $pdo->exec('START TRANSACTION READ ONLY');

    return $pdo;
}

$pdo = null;
$second = null;
$firstTx = false;
$secondTx = false;
$success = false;

try {
    $credential = $argv[1] ?? '';

    verify(
        'FIXTURE_CREDENTIAL_PATH',
        $credential === '/root/.c137-fixture/readonly.json'
    );

    $stat = lstat($credential);

    verify(
        'CREDENTIAL_FILE_SECURITY',
        is_array($stat)
        && is_file($credential)
        && !is_link($credential)
        && $stat['uid'] === 0
        && ($stat['mode'] & 0777) === 0600
    );

    $config = json_decode(
        file_get_contents($credential),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $dsn =
        'mysql:host=172.31.35.250;port=3306;'
        . 'dbname=renee_c137_fixture;charset=utf8mb4';

    $username = $config['username']
        ?? $config['user']
        ?? '';

    verify(
        'FIXTURE_CONFIGURATION',
        ($config['dsn'] ?? '') === $dsn
        && $username === 'c137_fixture_reader'
        && is_string($config['password'] ?? null)
    );

    require_once __DIR__ . '/../lib/farm_intelligence.php';

    $cache = stock_consumption_economics_source_request_cache();

    verify(
        'WEAKMAP_CACHE_CONTRACT',
        $cache instanceof WeakMap && count($cache) === 0
    );

    $pdo = connectFixture(
        $dsn, $username, $config['password']
    );

    $firstTx = true;

    $farms = $pdo->query(
        "SELECT id, slug FROM farms WHERE slug IN "
        . "('c137-fixture-alpha','c137-fixture-beta')"
    )->fetchAll(PDO::FETCH_ASSOC);

    $farmIds = [];

    foreach ($farms as $farm) {
        $farmIds[$farm['slug']] = (int)$farm['id'];
    }

    $alpha = $farmIds['c137-fixture-alpha'] ?? 0;
    $beta = $farmIds['c137-fixture-beta'] ?? 0;

    verify(
        'TWO_FARM_FIXTURE',
        count($farmIds) === 2
        && $alpha > 0
        && $beta > 0
        && $alpha !== $beta
    );

    foreach ([
        'sales_records' => 9,
        'farm_expenses' => 12,
        'stock_transactions' => 8,
        'stock_consumption_allocations' => 1,
    ] as $table => $expected) {
        $actual = (int)$pdo->query(
            "SELECT COUNT(*) FROM `{$table}`"
        )->fetchColumn();

        verify(
            strtoupper($table) . '_CHECKPOINT',
            $actual === $expected
        );
    }

    // Profile the canonical Reports service chain.
    $pdo->sql = [];

    $monthly = farm_intelligence_monthly_series(
        $pdo, $alpha, 2025, 'all'
    );

    $products = farm_intelligence_top_products(
        $pdo, $alpha,
        '2025-01-01', '2025-12-31', 'all', 10
    );

    $expenses = farm_intelligence_expense_breakdown(
        $pdo, $alpha,
        '2025-01-01', '2025-12-31', 'all'
    );

    $calls = count($pdo->sql);
    $sources = sourceQueries($pdo->sql);

    echo "REPORTS_SQL_CALLS={$calls}\n";
    echo "STOCK_SOURCE_QUERIES={$sources}\n";

    verify(
        'REPORTS_SQL_REDUCTION_CONTRACT',
        $calls === 82 && $sources === 1
    );

    verify(
        'MONTHLY_ROW_COUNT',
        count($monthly) === 12
    );

    // Protect period recognition, not just annual conservation.
    $monthsByKey = [];

    foreach ($monthly as $month) {
        $monthsByKey[(string)($month['month'] ?? '')] = $month;
    }

    foreach ([
        '2025-01' => [10000.0, 7000.0],
        '2025-02' => [0.0, -6800.0],
        '2025-03' => [20800.0, 4300.0],
    ] as $monthKey => [$expectedRevenue, $expectedProfit]) {
        $entry = $monthsByKey[$monthKey] ?? null;

        verify(
            'MONTHLY_' . str_replace('-', '_', $monthKey)
                . '_ACCOUNTING',
            is_array($entry)
            && abs(
                (float)($entry['total_sales'] ?? 0)
                - $expectedRevenue
            ) < 0.001
            && abs(
                (float)($entry['net_profit'] ?? 0)
                - $expectedProfit
            ) < 0.001
        );
    }

    $revenue = 0.0;
    $profit = 0.0;
    $cost = 0.0;
    $productRevenue = 0.0;

    foreach ($monthly as $month) {
        $revenue += (float)$month['total_sales'];
        $profit += (float)$month['net_profit'];
    }

    foreach ($expenses as $expense) {
        $cost += (float)$expense['total_amount'];
    }

    foreach ($products as $product) {
        $productRevenue += (float)$product['total_revenue'];
    }

    verify(
        'ANNUAL_ACCOUNTING_CONSERVATION',
        abs($revenue - 30800.0) < 0.001
        && abs($profit - 4500.0) < 0.001
        && abs($cost - 26300.0) < 0.001
        && abs($productRevenue - 30800.0) < 0.001
    );

    // The reporting helper must clean its temporary months.
    verify(
        'REPORTING_MONTHLY_CACHE_RELEASE',
        isset(
            $cache[$pdo]->farms[$alpha]
                ['2025-01-01']['2025-12-31']
        )
        &&
        !isset(
            $cache[$pdo]->farms[$alpha]
                ['2025-02-01']['2025-02-28']
        )
    );

    $betaMonths = farm_intelligence_monthly_series(
        $pdo, $beta, 2025, 'all'
    );

    $betaZero = count($betaMonths) === 12;

    foreach ($betaMonths as $month) {
        $betaZero = $betaZero
            && abs((float)$month['total_sales']) < 0.001
            && abs((float)$month['net_profit']) < 0.001;
    }

    verify('SECOND_FARM_FINANCIAL_ISOLATION', $betaZero);

    // Invalidate only Alpha; Beta remains in the cache.
    stock_consumption_economics_forget_source_request_cache(
        $pdo, $alpha
    );

    verify(
        'FARM_SCOPED_INVALIDATION',
        !isset($cache[$pdo]->farms[$alpha])
        && isset($cache[$pdo]->farms[$beta])
    );

    $overflow =
        stock_consumption_economics_prefetch_monthly_source_rows(
            $pdo, $alpha, 2025, 1
        );

    verify(
        'OVERFLOW_FALLBACK',
        $overflow === []
        &&
        !isset(
            $cache[$pdo]->farms[$alpha]
                ['2025-02-01']['2025-02-28']
        )
    );

    $seeded =
        stock_consumption_economics_prefetch_monthly_source_rows(
            $pdo, $alpha, 2025, 2048
        );

    verify('MONTHLY_PREFETCH_SEED', count($seeded) === 12);

    stock_consumption_economics_release_prefetched_monthly_source_rows(
        $pdo, $alpha, $seeded
    );

    verify(
        'EXPLICIT_PREFETCH_RELEASE',
        !isset(
            $cache[$pdo]->farms[$alpha]
                ['2025-02-01']['2025-02-28']
        )
    );

    // Verify that weak connection ownership releases the cache.
    $firstId = spl_object_id($pdo);
    $reference = WeakReference::create($pdo);

    $pdo->exec('ROLLBACK');
    $firstTx = false;

    unset($pdo);
    $pdo = null;
    gc_collect_cycles();

    verify(
        'FIRST_CONNECTION_DESTROYED',
        $reference->get() === null
    );

    verify(
        'WEAKMAP_ENTRY_REMOVED',
        count($cache) === 0
    );

    $second = connectFixture(
        $dsn, $username, $config['password']
    );

    $secondTx = true;

    echo "PDO_OBJECT_ID_REUSED="
        . (
            spl_object_id($second) === $firstId
                ? 'YES'
                : 'NO'
        )
        . "\n";

    $second->sql = [];

    $rows = stock_consumption_economics_source_rows(
        $second, $alpha,
        '2025-01-01', '2025-12-31'
    );

    verify(
        'NEW_CONNECTION_DATABASE_READ',
        count($rows) === 2
        && sourceQueries($second->sql) === 1
    );

    $second->sql = [];

    stock_consumption_economics_source_rows(
        $second, $alpha,
        '2025-01-01', '2025-12-31'
    );

    verify(
        'SAME_CONNECTION_CACHE_HIT',
        sourceQueries($second->sql) === 0
    );

    $success = true;

} catch (Throwable $error) {
    echo "VERIFIER_FAILURE_TYPE="
        . get_class($error) . "\n";

    if ($error instanceof RuntimeException) {
        echo "FAILED_GUARD=" . $error->getMessage() . "\n";
    }

} finally {
    foreach ([
        [$second, $secondTx, 'SECOND'],
        [$pdo, $firstTx, 'FIRST'],
    ] as [$connection, $active, $label]) {
        if (!$active || !($connection instanceof PDO)) {
            continue;
        }

        try {
            $connection->exec('ROLLBACK');
            echo "READ_ONLY_{$label}_ROLLBACK=PASS\n";
        } catch (Throwable $error) {
            echo "READ_ONLY_{$label}_ROLLBACK=FAIL\n";
            $success = false;
        }
    }
}

echo "DATABASE_MODIFIED=NO\n";
echo "LOAD_TEST_STARTED=NO\n";
echo "C137_GC10_VERIFIER="
    . ($success ? 'PASS' : 'FAIL') . "\n";

exit($success ? 0 : 1);
