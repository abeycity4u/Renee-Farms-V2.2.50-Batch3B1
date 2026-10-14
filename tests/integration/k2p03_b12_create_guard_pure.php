<?php
declare(strict_types=1);
final class DailyRecordRevisionConflict extends RuntimeException {}
$GLOBALS['b12_scope_rows'] = ['status'=>'absent','record_id'=>null];
$GLOBALS['b12_probe_called'] = 0;
$GLOBALS['b12_probe_lock_flags'] = [];
function daily_record_revision_reader_scope_plan(string $t,int $f,?int $c,string $d,?string $a):array {
    if (!in_array($t,['layer','broiler','ruminant'],true) || $f<1 ||
        !DateTimeImmutable::createFromFormat('!Y-m-d',$d) ||
        ($t==='ruminant' && !in_array($a,['goat','sheep','cattle','other'],true))) {
        throw new InvalidArgumentException('Invalid scope');
    }
    return ['sql'=>'scope'];
}
function daily_record_revision_reader_check_scope(PDO $pdo,string $t,int $f,?int $c,string $d,?string $a,bool $forUpdate=false):array {
    $GLOBALS['b12_probe_called']++;
    $GLOBALS['b12_probe_lock_flags'][]=$forUpdate;
    if (!$forUpdate || !$pdo->inTransaction()) {
        throw new LogicException('CREATE must use a transactional current locking read.');
    }
    return $GLOBALS['b12_scope_rows'];
}
require_once dirname(__DIR__,2).'/lib/daily_record_revision_create_guard.php';
final class B12GuardStmt extends PDOStatement {
    public function __construct(private mixed $result,private array &$trace,private string $sql) {}
    public function execute(?array $params=null):bool { $this->trace[]=['execute',$this->sql,$params];return true; }
    public function fetchColumn(int $column=0):mixed {return $this->result;}
}
final class B12GuardPDO extends PDO {
    public array $trace=[];
    public bool $transaction=true;
    public mixed $farmRow='7';
    public mixed $cycleRow='42';
    public function __construct() {}
    public function inTransaction():bool{return $this->transaction;}
    public function prepare(string $query,array $options=[]):PDOStatement|false{
        $this->trace[]=['prepare',$query];
        return new B12GuardStmt(str_contains($query,'FROM farms')?$this->farmRow:$this->cycleRow,$this->trace,$query);
    }
}
$passed=0;
function c(bool $v,string $s):void {global $passed;if(!$v)throw new RuntimeException('FAIL '.$s);$passed++;}
function rejected(callable $fn,string $class):void{
    try{$fn();}catch(Throwable $e){c($e instanceof $class,get_class($e));return;}
    c(false,'expected exception');
}
$p=new B12GuardPDO();
$r=daily_record_revision_create_lock_scope($p,'layer',7,42,'2026-10-13',null);
c($r['status']==='locked_absent','success');
c(count($p->trace)===4,'two locks with execute');
c(str_contains($p->trace[0][1],'FROM farms'),'farm first');
c(str_contains($p->trace[2][1],'FROM production_cycles'),'cycle second');
c($GLOBALS['b12_probe_called']===1,'probe after locks');
c($GLOBALS['b12_probe_lock_flags'] === [true],
    'CREATE scope probe explicitly demands current locking read');
$p=new B12GuardPDO();daily_record_revision_create_lock_scope($p,'ruminant',7,null,'2026-10-13','goat');
c(count($p->trace)===2,'null cycle skips parent');
c($GLOBALS['b12_probe_lock_flags'] === [true,true],
    'legacy NULL-cycle CREATE also demands current locking read');
foreach ([['unique',90],['ambiguous',null]] as [$status,$id]) {
    $GLOBALS['b12_scope_rows']=['status'=>$status,'record_id'=>$id];
    rejected(fn()=>daily_record_revision_create_lock_scope(new B12GuardPDO(),'layer',7,42,'2026-10-13',null),DailyRecordRevisionConflict::class);
}
$GLOBALS['b12_scope_rows']=['status'=>'absent','record_id'=>null];
$p=new B12GuardPDO();$p->transaction=false;
rejected(fn()=>daily_record_revision_create_lock_scope($p,'layer',7,42,'2026-10-13',null),LogicException::class);
c(count($p->trace)===0,'no lock outside tx');
$p=new B12GuardPDO();$p->farmRow=false;
rejected(fn()=>daily_record_revision_create_lock_scope($p,'layer',7,42,'2026-10-13',null),RuntimeException::class);
$p=new B12GuardPDO();$p->cycleRow=false;
rejected(fn()=>daily_record_revision_create_lock_scope($p,'layer',7,42,'2026-10-13',null),RuntimeException::class);
rejected(fn()=>daily_record_revision_create_lock_scope(new B12GuardPDO(),'unknown',7,42,'2026-10-13',null),InvalidArgumentException::class);
rejected(fn()=>daily_record_revision_create_lock_scope(new B12GuardPDO(),'layer',0,42,'2026-10-13',null),InvalidArgumentException::class);
echo "B12_CREATE_GUARD_PURE=PASS\nPASS_COUNT=$passed\n";
