<?php
declare(strict_types=1);

/**
 * B12 offline continuity writer contract; overrides the canonical preview
 * ONLY in this disposable PHP process. No config, DB, HTTP or CI call.
 * Real MariaDB concurrent-writer certification is still required.
 */
$GLOBALS['b12_locked_continuity_plan'] = [];
$GLOBALS['b12_preview_calls'] = [];

function daily_population_continuity_preview(
    PDO $pdo, int $farmId, int $cycleId, string $recordType,
    string $recordDate, int $openingStock, int $mortality,
    ?string $animalType = null, bool $forUpdate = false, ?int $sourceId = null
): array {
    $GLOBALS['b12_preview_calls'][] = [
        $farmId, $cycleId, $recordType, $recordDate,
        $openingStock, $mortality, $animalType, $forUpdate, $sourceId,
    ];
    return $GLOBALS['b12_locked_continuity_plan'];
}
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_continuity_apply.php';

class B12ContinuityRevisionStatement extends PDOStatement
{
    public function __construct(private B12ContinuityRevisionPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $this->pdo->executed[] = [$this->sql, $params];
        return $this->pdo->executeOk;
    }
    public function rowCount(): int { return $this->pdo->affected; }
}
class B12ContinuityRevisionPDO extends PDO
{
    public bool $active = true;
    public bool $executeOk = true;
    public int $affected = 1;
    public array $executed = [];
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function prepare(string $sql, array $options = []): PDOStatement|false
    {
        return new B12ContinuityRevisionStatement($this, $sql);
    }
}
$passes = 0;
function b12_revision_check(bool $good, string $label): void
{
    global $passes;
    if (!$good) throw new RuntimeException('FAIL: ' . $label);
    $passes++;
}
function b12_revision_reject(callable $operation, string $exception, string $label): void
{
    try { $operation(); }
    catch (Throwable $e) {
        b12_revision_check($e instanceof $exception, $label . ' [' . get_class($e) . ']');
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $label);
}
function b12_revision_change(int $id, string $date, int $old=100, int $new=95): array
{
    return [
        'id'=>$id, 'record_date'=>$date,
        'old_opening_stock'=>$old, 'new_opening_stock'=>$new,
        'new_laying_rate'=>75.53,
    ];
}
function b12_revision_plan(string $type, array $changes): array
{
    return [
        'record_type'=>$type, 'farm_id'=>7, 'cycle_id'=>11,
        'record_date'=>'2026-10-13',
        'animal_type'=>$type==='ruminant'?'goat':null,
        'affected_count'=>count($changes), 'changes'=>$changes,
        'tracking_status'=>'canonical',
        'source'=>'v3_population_ledger',
    ];
}
function b12_revision_apply(PDO $pdo, string $type, ?string $species=null): array
{
    return daily_record_revision_continuity_apply(
        $pdo, 7, 11, $type, '2026-10-13', 120, 2, $species, 19
    );
}
try {
    foreach (['layer', 'broiler', 'ruminant'] as $type) {
        $one = b12_revision_change(20, '2026-10-14');
        $two = b12_revision_change(21, '2026-10-15', 95, 94);
        $GLOBALS['b12_locked_continuity_plan'] = b12_revision_plan($type, [$one, $two]);
        $GLOBALS['b12_preview_calls'] = [];
        $pdo = new B12ContinuityRevisionPDO();
        $result = b12_revision_apply($pdo, $type, $type==='ruminant' ? ' Goat ' : null);
        b12_revision_check($result['applied_count']===2 && $result['affected_count']===2,
            $type . ' continuity rows applied');
        b12_revision_check(count($GLOBALS['b12_preview_calls'])===1 &&
            $GLOBALS['b12_preview_calls'][0][7]===true &&
            $GLOBALS['b12_preview_calls'][0][8]===19,
            $type . ' preview under locked-source identity');
        b12_revision_check(count($pdo->executed)===2, $type . ' exact two changed-row UPDATEs');
        b12_revision_check(str_contains($pdo->executed[0][0],
            'record_revision = record_revision + 1'), $type . ' revision bump');
        b12_revision_check(str_contains($pdo->executed[0][0],
            'record_date = ? AND opening_stock = ?'), $type . ' old opening guarded');
        b12_revision_check(str_contains($pdo->executed[0][0],
            'UPDATE ' . ($type==='ruminant'?'ruminant':$type) . '_daily_records'),
            $type . ' correct authoritative table');
        b12_revision_check(
            str_contains($pdo->executed[0][0], 'LOWER(animal_type) = ?') === ($type==='ruminant'),
            $type . ' preserves species isolation');
        b12_revision_check(
            str_contains($pdo->executed[0][0], 'laying_rate = ?') === ($type==='layer'),
            $type . ' Layer-only rate recalculation');
        b12_revision_check(
            $GLOBALS['b12_preview_calls'][0][6]===($type==='ruminant'?'goat':null),
            $type . ' canonical species input');
    }

    $GLOBALS['b12_locked_continuity_plan'] = b12_revision_plan('layer', []);
    $pdo = new B12ContinuityRevisionPDO();
    $none = b12_revision_apply($pdo, 'layer');
    b12_revision_check($none['applied_count']===0 && $pdo->executed===[],
        'unchanged later rows never bump revision');

    $badPlan = b12_revision_plan('layer', [
        b12_revision_change(20,'2026-10-14'),
        b12_revision_change(21,'2026-10-15'),
    ]);
    foreach ([
        ['affected_count', 1],
        ['farm_id', 8],
        ['cycle_id', 12],
        ['record_type', 'broiler'],
        ['animal_type', 'goat'],
    ] as [$key, $bad]) {
        $GLOBALS['b12_locked_continuity_plan'] = array_replace($badPlan, [$key=>$bad]);
        $pdo = new B12ContinuityRevisionPDO();
        b12_revision_reject(fn()=>b12_revision_apply($pdo,'layer'),
            DailyRecordRevisionConflict::class, 'plan mismatch ' . $key);
        b12_revision_check($pdo->executed===[], 'mismatch stops before UPDATE');
    }
    foreach ([
        [b12_revision_change(20,'2026-10-14'),b12_revision_change(21,'2026-10-13')],
        [b12_revision_change(20,'2026-10-14'),b12_revision_change(20,'2026-10-15')],
        [b12_revision_change(20,'2026-02-30')],
        [b12_revision_change(20,'2026-10-14',100,100)],
        [b12_revision_change(20,'2026-10-14',100,0)],
    ] as $changes) {
        $GLOBALS['b12_locked_continuity_plan'] = b12_revision_plan('layer', $changes);
        $pdo = new B12ContinuityRevisionPDO();
        b12_revision_reject(fn()=>b12_revision_apply($pdo,'layer'),
            DailyRecordRevisionConflict::class, 'invalid manifest');
        b12_revision_check($pdo->executed===[], 'invalid manifest has no UPDATE');
    }
    $GLOBALS['b12_locked_continuity_plan'] = b12_revision_plan('layer', [
        b12_revision_change(20,'2026-10-14'),
    ]);
    $pdo = new B12ContinuityRevisionPDO();
    $pdo->affected = 0;
    b12_revision_reject(fn()=>b12_revision_apply($pdo,'layer'),
        DailyRecordRevisionConflict::class, 'changed under lock detects zero affected rows');

    $pdo = new B12ContinuityRevisionPDO();
    $pdo->active = false;
    b12_revision_reject(fn()=>b12_revision_apply($pdo,'layer'),
        LogicException::class, 'transaction required');
    b12_revision_check($pdo->executed===[], 'no UPDATE without transaction');

    b12_revision_reject(
        fn()=>daily_record_revision_continuity_apply(
            new B12ContinuityRevisionPDO(),7,11,'layer','2026-10-13',120,2,null,0
        ),
        InvalidArgumentException::class, 'source identity required');

    echo "B12_CONTINUITY_APPLY_PURE=PASS\nPASS_COUNT={$passes}\n";
} catch (Throwable $error) {
    fwrite(STDERR,'B12_CONTINUITY_APPLY_PURE=FAIL ' . get_class($error) .
        ': ' . $error->getMessage() . "\n");
    exit(1);
}
