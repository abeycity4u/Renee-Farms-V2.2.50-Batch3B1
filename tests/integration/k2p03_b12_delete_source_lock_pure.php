<?php
declare(strict_types=1);

/**
 * B12 step 1: source-first deletion lock for all three Daily Record families.
 * Source/pure test only: this does not certify a real MariaDB concurrency race,
 * client expected revisions, schema migration or 150 VU readiness.
 */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_authority.php';

final class B12DeleteLockStmt extends PDOStatement
{
    public function __construct(
        private mixed $row,
        private array &$trace,
        private string $sql
    ) {}
    public function execute(?array $params = null): bool
    {
        $this->trace[] = ['execute', $this->sql, $params];
        return true;
    }
    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->row;
    }
}
final class B12DeleteLockPDO extends PDO
{
    public array $trace = [];
    public bool $transaction = true;
    public mixed $row = [
        'id' => '77',
        'record_date' => '2026-10-08',
        'cycle_id' => '45',
        'animal_type' => 'goat',
    ];
    public function __construct() {}
    public function inTransaction(): bool { return $this->transaction; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->trace[] = ['prepare', $query];
        return new B12DeleteLockStmt($this->row, $this->trace, $query);
    }
}

$passed = 0;
function check_b12_delete_lock(bool $condition, string $label): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $passed++;
}
function reject_b12_delete_lock(callable $callback, string $exception): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        check_b12_delete_lock($error instanceof $exception, $exception);
        return;
    }
    check_b12_delete_lock(false, 'exception required');
}

foreach (['layer' => 'layer_daily_records',
          'broiler' => 'broiler_daily_records',
          'ruminant' => 'ruminant_daily_records'] as $type => $table) {
    $pdo = new B12DeleteLockPDO();
    $locked = daily_record_revision_lock_source_for_delete(
        $pdo, $type, 3, 77
    );
    check_b12_delete_lock($locked['id'] === '77', $type . ' row returned');
    check_b12_delete_lock(count($pdo->trace) === 2, $type . ' prepare and execute');
    $sql = $pdo->trace[0][1];
    check_b12_delete_lock(str_contains($sql, 'FROM ' . $table), $type . ' table');
    check_b12_delete_lock(
        str_contains($sql, 'WHERE id = ? AND farm_id = ? LIMIT 1 FOR UPDATE'),
        $type . ' tenant and exact row lock'
    );
    check_b12_delete_lock(
        $pdo->trace[1][2] === [77, 3],
        $type . ' bound tenant and row'
    );
    check_b12_delete_lock(
        !str_contains($sql, 'record_revision'),
        $type . ' migration-independent lock'
    );
}

$pdo = new B12DeleteLockPDO();
$pdo->transaction = false;
reject_b12_delete_lock(
    fn() => daily_record_revision_lock_source_for_delete($pdo, 'layer', 3, 77),
    LogicException::class
);
check_b12_delete_lock(count($pdo->trace) === 0, 'no SQL outside transaction');

foreach ([0, -1] as $badId) {
    reject_b12_delete_lock(
        fn() => daily_record_revision_lock_source_for_delete(
            new B12DeleteLockPDO(), 'layer', 3, $badId
        ),
        InvalidArgumentException::class
    );
}
reject_b12_delete_lock(
    fn() => daily_record_revision_lock_source_for_delete(
        new B12DeleteLockPDO(), 'unsupported', 3, 77
    ),
    InvalidArgumentException::class
);
$pdo = new B12DeleteLockPDO();
$pdo->row = false;
reject_b12_delete_lock(
    fn() => daily_record_revision_lock_source_for_delete($pdo, 'broiler', 3, 77),
    DailyRecordRevisionConflict::class
);
$pdo = new B12DeleteLockPDO();
$pdo->row['id'] = '78';
reject_b12_delete_lock(
    fn() => daily_record_revision_lock_source_for_delete($pdo, 'layer', 3, 77),
    DailyRecordRevisionConflict::class
);
$pdo = new B12DeleteLockPDO();
$pdo->row['animal_type'] = 'unsupported';
reject_b12_delete_lock(
    fn() => daily_record_revision_lock_source_for_delete($pdo, 'ruminant', 3, 77),
    DailyRecordRevisionConflict::class
);

$root = dirname(__DIR__, 2);
foreach ([
    'api/delete_record.php',
    'lib/ruminant_daily_record_deletion.php',
] as $route) {
    $source = file_get_contents($root . '/' . $route);
    check_b12_delete_lock(is_string($source), $route . ' readable');
    $lockAt = strpos($source, 'daily_record_revision_lock_source_for_delete(');
    $feedAt = strpos($source, 'delete_daily_feed_usage(');
    $mortalityAt = strpos($source, 'daily_population_remove_mortality(');
    check_b12_delete_lock(
        $lockAt !== false && $feedAt !== false && $mortalityAt !== false
        && $lockAt < $feedAt && $feedAt < $mortalityAt,
        $route . ' locks before feed and mortality reversal'
    );
}

echo "B12_DELETE_SOURCE_LOCK_PURE=PASS\nPASS_COUNT=$passed\n";
