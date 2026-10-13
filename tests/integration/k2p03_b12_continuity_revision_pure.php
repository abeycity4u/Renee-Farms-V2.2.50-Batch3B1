<?php
declare(strict_types=1);

/**
 * Offline B12 continuity revision SQL-contract verifier. No application
 * bootstrap, database connection, migrated schema or GitHub Actions run.
 * A real locked-transaction MariaDB test is still required for certification.
 */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_continuity.php';

final class B12ContinuityStmt extends PDOStatement
{
    public function __construct(private B12ContinuityPDO $parent) {}
    public function execute(?array $params = null): bool
    {
        $this->parent->bound[] = $params;
        return $this->parent->executeOk;
    }
    public function rowCount(): int { return $this->parent->affected; }
}

final class B12ContinuityPDO extends PDO
{
    public bool $active = true;
    public bool $executeOk = true;
    public int $affected = 1;
    public array $queries = [];
    public array $bound = [];
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        return new B12ContinuityStmt($this);
    }
}

$passes = 0;
function b12_continuity_assert(bool $ok, string $label): void
{
    global $passes;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $passes++;
}
function b12_continuity_reject(callable $operation, string $expected): void
{
    try { $operation(); }
    catch (Throwable $error) {
        b12_continuity_assert($error instanceof $expected, 'expected ' . $expected);
        return;
    }
    throw new RuntimeException('Expected rejection ' . $expected);
}

try {
    $base = [
        'id' => 19,
        'record_date' => '2026-10-13',
        'old_opening_stock' => 100,
        'new_opening_stock' => 95,
    ];
    $layer = new B12ContinuityPDO();
    daily_record_revision_apply_continuity_change(
        $layer, 'layer', 7, 11, null, $base + ['new_laying_rate' => 80.52]
    );
    b12_continuity_assert(count($layer->queries) === 1, 'layer one update');
    b12_continuity_assert(str_contains($layer->queries[0], 'UPDATE layer_daily_records'), 'layer table');
    b12_continuity_assert(str_contains($layer->queries[0], 'laying_rate = ?'), 'layer rate carried');
    b12_continuity_assert(str_contains($layer->queries[0], 'record_revision = record_revision + 1'), 'layer revision bump');
    b12_continuity_assert(
        $layer->bound[0] === [95, 80.52, 19, 7, 11, '2026-10-13', 100, PHP_INT_MAX - 1],
        'layer immutable scope and source-opening guard'
    );
    $mixedCase = new B12ContinuityPDO();
    daily_record_revision_apply_continuity_change(
        $mixedCase, ' Layer ', 7, 11, null, $base + ['new_laying_rate' => 80.52]
    );
    b12_continuity_assert(str_contains($mixedCase->queries[0], 'laying_rate = ?'), 'type normalization preserves Layer rate');
    $broiler = new B12ContinuityPDO();
    daily_record_revision_apply_continuity_change($broiler, 'broiler', 7, 11, null, $base);
    b12_continuity_assert(str_contains($broiler->queries[0], 'UPDATE broiler_daily_records'), 'broiler table');
    b12_continuity_assert(!str_contains($broiler->queries[0], 'laying_rate'), 'broiler no layer rate');
    b12_continuity_assert($broiler->bound[0] === [95, 19, 7, 11, '2026-10-13', 100, PHP_INT_MAX - 1], 'broiler bound identity');
    $ruminant = new B12ContinuityPDO();
    daily_record_revision_apply_continuity_change($ruminant, 'ruminant', 7, 11, ' Goat ', $base);
    b12_continuity_assert(str_contains($ruminant->queries[0], 'UPDATE ruminant_daily_records'), 'ruminant table');
    b12_continuity_assert(str_contains($ruminant->queries[0], 'LOWER(animal_type) = ?'), 'ruminant species condition');
    b12_continuity_assert($ruminant->bound[0] === [95, 19, 7, 11, '2026-10-13', 100, 'goat', PHP_INT_MAX - 1], 'species normalization');
    b12_continuity_assert(str_contains($ruminant->queries[0], 'record_revision >= 1 AND record_revision < ?'), 'revision range guarded');
    $failed = new B12ContinuityPDO();
    $failed->affected = 0;
    b12_continuity_reject(
        fn() => daily_record_revision_apply_continuity_change($failed, 'broiler', 7, 11, null, $base),
        DailyRecordRevisionConflict::class
    );
    $inactive = new B12ContinuityPDO();
    $inactive->active = false;
    b12_continuity_reject(
        fn() => daily_record_revision_apply_continuity_change($inactive, 'layer', 7, 11, null, $base),
        LogicException::class
    );
    b12_continuity_assert($inactive->queries === [], 'no SQL outside transaction');
    foreach ([
        ['old_opening_stock' => 95],
        ['new_opening_stock' => 0],
        ['id' => 0],
        ['record_date' => '2026-02-30'],
    ] as $bad) {
        b12_continuity_reject(
            fn() => daily_record_revision_apply_continuity_change(
                new B12ContinuityPDO(), 'broiler', 7, 11, null, array_replace($base, $bad)
            ),
            InvalidArgumentException::class
        );
    }
    b12_continuity_reject(
        fn() => daily_record_revision_apply_continuity_change(new B12ContinuityPDO(), 'layer', 7, 11, null, $base),
        InvalidArgumentException::class
    );
    b12_continuity_reject(
        fn() => daily_record_revision_apply_continuity_change(new B12ContinuityPDO(), 'ruminant', 7, 11, 'unknown', $base),
        InvalidArgumentException::class
    );
    b12_continuity_reject(
        fn() => daily_record_revision_apply_continuity_change(new B12ContinuityPDO(), 'broiler', 7, 0, null, $base),
        InvalidArgumentException::class
    );
    b12_continuity_reject(
        fn() => daily_record_revision_apply_continuity_change(new B12ContinuityPDO(), 'wrong', 7, 11, null, $base),
        InvalidArgumentException::class
    );
    echo "B12_CONTINUITY_REVISION_PURE=PASS\nPASS_COUNT={$passes}\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'B12_CONTINUITY_REVISION_PURE=FAIL ' . get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
