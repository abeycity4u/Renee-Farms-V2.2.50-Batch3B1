<?php
declare(strict_types=1);

/**
 * B12 WS05B source-only farm -> cycle parent locking contract.
 * No app config, real PDO driver, DB, network or migrations.
 */
final class DailyRecordRevisionConflict extends RuntimeException {}
require_once dirname(__DIR__,2).'/lib/daily_record_revision_parent_lock.php';

final class B12ParentStatement extends PDOStatement {
    public function __construct(private B12ParentPDO $db,private string $sql) {}
    public function execute(?array $params=null):bool {
        $this->db->trace[]=[$this->sql,$params];
        return true;
    }
    public function fetchColumn(int $column=0):mixed {
        if (str_contains($this->sql,'FROM farms')) return $this->db->farmRow;
        if (str_contains($this->sql,'FROM production_cycles')) return $this->db->cycleRow;
        throw new LogicException('Unexpected parent lock query.');
    }
}
final class B12ParentPDO extends PDO {
    public bool $active=true;
    public mixed $farmRow='7';
    public mixed $cycleRow='11';
    public array $trace=[];
    public function __construct() {}
    public function inTransaction():bool { return $this->active; }
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        return new B12ParentStatement($this,$query);
    }
}
$passes=0;
function ws05b_check(bool $ok,string $message):void {
    global $passes;
    if (!$ok) throw new RuntimeException('FAIL '.$message);
    $passes++;
}
function ws05b_reject(callable $call,string $exception,string $name):void {
    try { $call(); }
    catch(Throwable $error) {
        ws05b_check($error instanceof $exception,$name);
        return;
    }
    throw new RuntimeException('Expected exception '.$name);
}
try {
    $pdo=new B12ParentPDO();
    daily_record_revision_lock_parents($pdo,7,11);
    ws05b_check(count($pdo->trace)===2,'farm and cycle locks both execute');
    ws05b_check(str_contains($pdo->trace[0][0],
        'FROM farms WHERE id = ? LIMIT 1 FOR UPDATE') &&
        $pdo->trace[0][1]===[7],
        'first farm lock is transaction-scoped and tenant-bound');
    ws05b_check(str_contains($pdo->trace[1][0],
        'FROM production_cycles WHERE id = ? AND farm_id = ? LIMIT 1 FOR UPDATE') &&
        $pdo->trace[1][1]===[11,7],
        'second cycle lock is tenant-qualified and current');
    ws05b_check(count(array_filter($pdo->trace,
        static fn(array $row):bool=>str_contains($row[0],'FOR UPDATE')))===2,
        'both parent locks use locking reads');

    $pdo=new B12ParentPDO();
    daily_record_revision_lock_parents($pdo,7,null);
    ws05b_check(count($pdo->trace)===1 &&
        str_contains($pdo->trace[0][0],'FROM farms'),
        'explicit legacy NULL-cycle DELETE locks farm only');

    foreach ([[0,11],[-1,11],[7,0],[7,-1]] as [$farm,$cycle]) {
        $pdo=new B12ParentPDO();
        ws05b_reject(fn()=>daily_record_revision_lock_parents($pdo,$farm,$cycle),
            InvalidArgumentException::class,'invalid parent identity rejected');
        ws05b_check($pdo->trace===[],'invalid identity performs no query');
    }
    $pdo=new B12ParentPDO();$pdo->active=false;
    ws05b_reject(fn()=>daily_record_revision_lock_parents($pdo,7,11),
        LogicException::class,'transaction ownership required');
    ws05b_check($pdo->trace===[],'no SQL without caller transaction');

    $pdo=new B12ParentPDO();$pdo->farmRow=false;
    ws05b_reject(fn()=>daily_record_revision_lock_parents($pdo,7,11),
        DailyRecordRevisionConflict::class,'unknown farm fails closed');
    ws05b_check(count($pdo->trace)===1,
        'unknown farm never locks a cycle');
    $pdo=new B12ParentPDO();$pdo->cycleRow=false;
    ws05b_reject(fn()=>daily_record_revision_lock_parents($pdo,7,11),
        DailyRecordRevisionConflict::class,'cycle outside farm fails closed');
    ws05b_check(count($pdo->trace)===2,
        'cycle rejection occurs after farm guard');

    echo "B12_PARENT_LOCK_PURE=PASS\nPASS_COUNT={$passes}\n";
} catch(Throwable $error) {
    fwrite(STDERR,'B12_PARENT_LOCK_PURE=FAIL '.get_class($error).': '.
        $error->getMessage()."\n");
    exit(1);
}
