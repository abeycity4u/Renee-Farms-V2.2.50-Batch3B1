<?php
declare(strict_types=1);

/**
 * B12 — offline, sequential two-actor stale-token simulation.
 *
 * Exercises the ACTUAL shared revision lock/CAS helpers against one
 * in-memory PDO test double shared by two simulated actors. NO real PDO
 * connection, DB credentials, application bootstrap, server, migrations,
 * browser, GitHub Actions or background workers are used.
 *
 * This cannot prove MariaDB locking, true concurrent transactions,
 * rollback of business ledgers, permissions, CSRF, or B12 certification.
 * Those require the separately approved real two-connection fixture.
 */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_authority.php';
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_cutover_gate.php';

final class B12ActorMemory
{
    /** @var array<string,array<string,mixed>> */
    public array $rows = [];
    /** @var array<int,array{actor:string,kind:string,table:string}> */
    public array $mutations = [];
    public bool $forceCasMiss = false;

    public function seed(string $table): void
    {
        $this->rows[$table] = [
            'id'=>19, 'farm_id'=>7, 'cycle_id'=>11,
            'record_date'=>'2026-10-13', 'record_revision'=>4,
            'animal_type'=>'goat', 'mortality'=>2,
        ];
        $this->mutations = [];
        $this->forceCasMiss = false;
    }
}

final class B12ActorConnection extends PDO
{
    public bool $active = false;
    public function __construct(
        public B12ActorMemory $memory,
        public string $actor
    ) {}
    public function inTransaction(): bool { return $this->active; }
    public function beginTransaction(): bool
    {
        if ($this->active) throw new LogicException('Nested synthetic transaction.');
        $this->active = true;
        return true;
    }
    public function commit(): bool
    {
        if (!$this->active) throw new LogicException('No transaction.');
        $this->active = false;
        return true;
    }
    public function rollBack(): bool
    {
        if (!$this->active) throw new LogicException('No transaction.');
        $this->active = false;
        return true;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new B12ActorStatement($this, $query);
    }
}

final class B12ActorStatement extends PDOStatement
{
    private mixed $found = false;
    private int $changed = 0;
    public function __construct(
        private B12ActorConnection $connection,
        private string $sql
    ) {}
    public function execute(?array $params = null): bool
    {
        if (!$this->connection->inTransaction()) {
            throw new LogicException('CAS test query outside synthetic transaction.');
        }
        $params ??= [];
        $sql = preg_replace('/\s+/', ' ', trim($this->sql));
        if (!is_string($sql)) throw new LogicException('Unparseable test SQL.');
        $this->found = false;
        $this->changed = 0;
        $memory = $this->connection->memory;
        $tablePattern = '(layer_daily_records|broiler_daily_records|ruminant_daily_records)';

        if (preg_match('/^SELECT .+ FROM ' . $tablePattern . ' WHERE id = \? AND farm_id = \? LIMIT 1 FOR UPDATE$/D', $sql, $match) === 1) {
            if (count($params) !== 2) throw new LogicException('Unscoped source lock.');
            $record = $memory->rows[$match[1]] ?? null;
            if (is_array($record) &&
                (int)$record['id'] === (int)$params[0] &&
                (int)$record['farm_id'] === (int)$params[1]) {
                $this->found = $record;
            }
            return true;
        }

        if (preg_match('/^UPDATE ' . $tablePattern . ' SET /D', $sql, $match) === 1) {
            if (!str_contains($sql, 'mortality = ?') ||
                !str_contains($sql, 'record_revision = record_revision + 1') ||
                !str_contains($sql, 'WHERE id = ? AND farm_id = ? AND record_revision = ?') ||
                count($params) !== 4) {
                throw new LogicException('Mutation lacks original-row CAS contract.');
            }
            [$mortality, $id, $farm, $revision] = $params;
            $record = $memory->rows[$match[1]] ?? null;
            if (!$memory->forceCasMiss && is_array($record) &&
                (int)$record['id'] === (int)$id &&
                (int)$record['farm_id'] === (int)$farm &&
                (int)$record['record_revision'] === (int)$revision) {
                $memory->rows[$match[1]]['mortality'] = $mortality;
                ++$memory->rows[$match[1]]['record_revision'];
                $this->changed = 1;
                $memory->mutations[] = [
                    'actor'=>$this->connection->actor,
                    'kind'=>'update',
                    'table'=>$match[1],
                ];
            }
            return true;
        }

        if (preg_match('/^DELETE FROM ' . $tablePattern . ' WHERE id = \? AND farm_id = \? AND record_revision = \?$/D', $sql, $match) === 1) {
            if (count($params) !== 3) throw new LogicException('Unscoped CAS deletion.');
            [$id, $farm, $revision] = $params;
            $record = $memory->rows[$match[1]] ?? null;
            if (is_array($record) &&
                (int)$record['id'] === (int)$id &&
                (int)$record['farm_id'] === (int)$farm &&
                (int)$record['record_revision'] === (int)$revision) {
                unset($memory->rows[$match[1]]);
                $this->changed = 1;
                $memory->mutations[] = [
                    'actor'=>$this->connection->actor,
                    'kind'=>'delete',
                    'table'=>$match[1],
                ];
            }
            return true;
        }

        throw new LogicException('Unexpected SQL in offline CAS simulation.');
    }
    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->found;
    }
    public function rowCount(): int { return $this->changed; }
}

$passes = 0;
function b12_actor_check(bool $result, string $label): void
{
    global $passes;
    if (!$result) throw new RuntimeException('FAILED: ' . $label);
    ++$passes;
}
function b12_actor_conflict(callable $action, string $label): void
{
    try {
        $action();
    } catch (DailyRecordRevisionConflict $error) {
        b12_actor_check(true, $label);
        return;
    }
    throw new RuntimeException('Expected B12 revision conflict: ' . $label);
}
function b12_actor_write(
    PDO $pdo, string $type, int $expected, int $mortality,
    int $farm = 7, ?int $cycle = 11, ?string $species = null
): int {
    return daily_record_revision_write(
        $pdo, $type, $farm, 19, $cycle, '2026-10-13',
        $species, $expected, ['mortality'=>$mortality]
    );
}
function b12_actor_delete(PDO $pdo, string $type, int $expected, ?string $species): void
{
    daily_record_revision_lock_and_compare(
        $pdo, $type, 7, 19, 11, '2026-10-13', $species, $expected
    );
    daily_record_revision_delete_locked(
        $pdo, $type, 7, 19, 11, '2026-10-13', $species, $expected
    );
}

try {
    b12_actor_check(daily_record_revision_cutover_enabled() === false,
        'current B12 gate stays hard-OFF');

    foreach ([
        'layer'=>'layer_daily_records',
        'broiler'=>'broiler_daily_records',
        'ruminant'=>'ruminant_daily_records',
    ] as $type => $table) {
        $species = $type === 'ruminant' ? 'goat' : null;
        $shared = new B12ActorMemory();
        $shared->seed($table);
        $actorA = new B12ActorConnection($shared, 'A');
        $actorB = new B12ActorConnection($shared, 'B');

        // Two editors intentionally load the SAME immutable original token.
        $originalA = (int)$shared->rows[$table]['record_revision'];
        $originalB = (int)$shared->rows[$table]['record_revision'];
        b12_actor_check($originalA === 4 && $originalB === 4 && $actorA !== $actorB,
            $type . ' simulated actors have independent handles and same original token');

        $actorA->beginTransaction();
        $new = b12_actor_write($actorA, $type, $originalA, 4, 7, 11, $species);
        $actorA->commit();
        b12_actor_check($new === 5 && $shared->rows[$table]['record_revision'] === 5 &&
            $shared->rows[$table]['mortality'] === 4,
            $type . ' first editor increments authoritative revision once');

        $actorB->beginTransaction();
        b12_actor_conflict(
            fn() => b12_actor_write($actorB, $type, $originalB, 3, 7, 11, $species),
            $type . ' second original stale token is explicitly rejected'
        );
        $actorB->rollBack();
        b12_actor_check(
            count($shared->mutations) === 1 &&
            $shared->mutations[0] === ['actor'=>'A','kind'=>'update','table'=>$table] &&
            $shared->rows[$table]['mortality'] === 4,
            $type . ' simulated stale editor has zero source writes'
        );

        $actorB->beginTransaction();
        b12_actor_conflict(
            fn() => b12_actor_write($actorB, $type, 5, 3, 8, 11, $species),
            $type . ' cross-tenant request cannot take source lock'
        );
        b12_actor_conflict(
            fn() => b12_actor_write($actorB, $type, 5, 3, 7, null, $species),
            $type . ' changed cycle identity rejects EDIT'
        );
        if ($type === 'ruminant') {
            b12_actor_conflict(
                fn() => b12_actor_write($actorB, $type, 5, 3, 7, 11, 'sheep'),
                'Ruminant wrong species rejects EDIT'
            );
        }
        $actorB->rollBack();
        b12_actor_check(count($shared->mutations) === 1,
            $type . ' unauthorized or mismatched scope has zero writes');

        // The fake can force rowCount 0 AFTER matching source lock; CAS must fail.
        $actorB->beginTransaction();
        $shared->forceCasMiss = true;
        b12_actor_conflict(
            fn() => b12_actor_write($actorB, $type, 5, 3, 7, 11, $species),
            $type . ' zero-row CAS is not reported as success'
        );
        $shared->forceCasMiss = false;
        $actorB->rollBack();
        b12_actor_check($shared->rows[$table]['record_revision'] === 5 &&
            count($shared->mutations) === 1,
            $type . ' failed CAS leaves prior simulated source unchanged');

        // A later deliberate edit is allowed only with a NEW loaded revision.
        $actorB->beginTransaction();
        $second = b12_actor_write($actorB, $type, 5, 3, 7, 11, $species);
        $actorB->commit();
        b12_actor_check($second === 6 &&
            $shared->rows[$table]['record_revision'] === 6 &&
            $shared->rows[$table]['mortality'] === 3 &&
            count($shared->mutations) === 2,
            $type . ' explicit fresh revision supports a new reviewed edit');

        $actorA->beginTransaction();
        b12_actor_delete($actorA, $type, 6, $species);
        $actorA->commit();
        b12_actor_check(!isset($shared->rows[$table]) &&
            count($shared->mutations) === 3 &&
            $shared->mutations[2]['kind'] === 'delete',
            $type . ' source deletion uses original revision contract');

        $actorB->beginTransaction();
        b12_actor_conflict(
            fn() => b12_actor_delete($actorB, $type, 6, $species),
            $type . ' stale second delete cannot remove another record'
        );
        $actorB->rollBack();
        b12_actor_check(count($shared->mutations) === 3,
            $type . ' duplicate delete produces no second source mutation');
    }

    echo "B12_TWO_ACTOR_REVISION_PURE=PASS\nPASS_COUNT={$passes}\n";
    echo "REAL_MARIADB_CONNECTIONS=0\nAUTHENTICATED_BROWSER=NO\n";
    echo "B12_RUNTIME_CERTIFIED=NO\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'B12_TWO_ACTOR_REVISION_PURE=FAIL ' .
        get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
