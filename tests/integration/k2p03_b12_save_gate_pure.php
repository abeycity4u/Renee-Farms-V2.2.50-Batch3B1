<?php
declare(strict_types=1);

/**
 * B12 dormant save-gate offline verifier. No bootstrap, actual PDO driver,
 * database connection, production route or one-use CI invocation.
 * This is not a concurrency/browser/schema certification.
 */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_save_gate.php';

final class B12GateStatement extends PDOStatement
{
    public function __construct(private B12GatePDO $db, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $this->db->trace[] = [$this->sql, $params];
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->db->sourceRow;
    }
    public function fetchColumn(int $column = 0): mixed
    {
        if (str_contains($this->sql, 'FROM farms')) return $this->db->farmRow;
        if (str_contains($this->sql, 'FROM production_cycles')) return $this->db->cycleRow;
        return false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if (str_contains($this->sql, 'SELECT d.id FROM')) return $this->db->scopeRows;
        throw new LogicException('Unexpected test query.');
    }
}
final class B12GatePDO extends PDO
{
    public bool $active = true;
    public mixed $farmRow = '7';
    public mixed $cycleRow = '11';
    public array $scopeRows = [];
    public array $trace = [];
    public array $sourceRow = [
        'id'=>'19','farm_id'=>'7','cycle_id'=>'11','record_date'=>'2026-10-13',
        'record_revision'=>'4','animal_type'=>'goat'
    ];
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new B12GateStatement($this, $query);
    }
}
$pass = 0;
function b12_gate_ok(bool $condition, string $label): void
{
    global $pass;
    if (!$condition) throw new RuntimeException('FAILED: ' . $label);
    ++$pass;
}
function b12_gate_reject(callable $fn, string $expected, string $label): void
{
    try { $fn(); }
    catch (Throwable $error) {
        b12_gate_ok($error instanceof $expected, $label . ' (' . get_class($error) . ')');
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $label);
}
function b12_gate_tokens(string $mode): array
{
    return [
        'write_intent'=>$mode,
        'record_id'=>$mode==='edit' ? '19' : '',
        'expected_revision'=>$mode==='edit' ? '4' : '',
        'original_cycle_id'=>$mode==='edit' ? '11' : '',
        'original_record_date'=>$mode==='edit' ? '2026-10-13' : '',
        'original_animal_type'=>'',
    ];
}
try {
    $expectPermission = [
        'layer'=>['poultry','poultry_daily_layer','poultry_daily_layer_add','poultry_daily_layer_edit'],
        'broiler'=>['poultry','poultry_daily_broiler','poultry_daily_broiler_add','poultry_daily_broiler_edit'],
        'ruminant'=>['ruminant','ruminant_daily','ruminant_daily_add','ruminant_daily_edit'],
    ];
    foreach ($expectPermission as $type=>$names) {
        $c = daily_record_revision_capabilities($type);
        b12_gate_ok($c['module']===$names[0] && $c['view']===$names[1], $type . ' module/view');
        b12_gate_ok($c['add']===$names[2] && $c['edit']===$names[3], $type . ' add/edit');
        b12_gate_ok(daily_record_revision_operation_permission($type,'create')===$names[2], $type . ' CREATE permission');
        b12_gate_ok(daily_record_revision_operation_permission($type,'edit')===$names[3], $type . ' EDIT permission');
    }

    $c = daily_record_revision_save_contract('layer',7,11,'2026-10-13',null,b12_gate_tokens('edit'));
    b12_gate_ok($c['mode']==='edit' && $c['record_id']===19 && $c['expected_revision']===4, 'original edit tokens');
    b12_gate_ok($c['permission']==='poultry_daily_layer_edit', 'never infer permission from existence');
    $c = daily_record_revision_save_contract('broiler',7,11,'2026-10-13',null,b12_gate_tokens('create'));
    b12_gate_ok($c['mode']==='create' && $c['record_id']===null, 'explicit CREATE without row token');

    $tracePermission = [];
    $auth = static function (string $module,string $view,string $write) use (&$tracePermission): bool {
        $tracePermission[] = [$module,$view,$write];
        return true;
    };
    $edit = new B12GatePDO();
    $out = daily_record_revision_prepare_save($edit,'layer',7,11,'2026-10-13',null,b12_gate_tokens('edit'),$auth);
    b12_gate_ok($out['contract']['mode']==='edit' && $out['locked_row']['id']==='19', 'edit returns locked source');
    b12_gate_ok(count($edit->trace)===1 && str_contains($edit->trace[0][0],'FOR UPDATE'), 'single exact-row lock before side effects');
    b12_gate_ok($tracePermission[0]===['poultry','poultry_daily_layer','poultry_daily_layer_edit'], 'edit authorization capabilities');

    $create = new B12GatePDO();
    $out = daily_record_revision_prepare_save($create,'broiler',7,11,'2026-10-13',null,b12_gate_tokens('create'),$auth);
    b12_gate_ok($out['contract']['mode']==='create' && $out['create_scope']['status']==='locked_absent', 'create reservation');
    b12_gate_ok(count($create->trace)===3, 'tenant lock, cycle lock, then scope probe');
    b12_gate_ok(str_contains($create->trace[0][0],'FROM farms') &&
        str_contains($create->trace[1][0],'FROM production_cycles') &&
        str_contains($create->trace[2][0],'SELECT d.id FROM'), 'create lock order');
    b12_gate_ok($tracePermission[1]===['poultry','poultry_daily_broiler','poultry_daily_broiler_add'], 'create requires ADD');
    $denied = new B12GatePDO();
    b12_gate_reject(
        fn()=>daily_record_revision_prepare_save($denied,'layer',7,11,'2026-10-13',null,b12_gate_tokens('edit'),static fn():bool=>false),
        DailyRecordRevisionPermissionDenied::class,'permission denial'
    );
    b12_gate_ok($denied->trace===[], 'denial occurs before SQL');

    $stale = new B12GatePDO();
    $stale->sourceRow['record_revision']='5';
    b12_gate_reject(
        fn()=>daily_record_revision_prepare_save($stale,'layer',7,11,'2026-10-13',null,b12_gate_tokens('edit'),$auth),
        DailyRecordRevisionConflict::class,'stale revision blocked'
    );
    b12_gate_ok(count($stale->trace)===1, 'stale edit obtains source lock without writing');
    $missing = new B12GatePDO();
    $missing->sourceRow = [];
    b12_gate_reject(
        fn()=>daily_record_revision_prepare_save($missing,'layer',7,11,'2026-10-13',null,b12_gate_tokens('edit'),$auth),
        DailyRecordRevisionConflict::class,'deleted edit source denied'
    );
    $duplicate = new B12GatePDO();
    $duplicate->scopeRows = [['id'=>19]];
    b12_gate_reject(
        fn()=>daily_record_revision_prepare_save($duplicate,'layer',7,11,'2026-10-13',null,b12_gate_tokens('create'),$auth),
        DailyRecordRevisionConflict::class,'concurrent create scope not silently treated as edit'
    );

    $inactive = new B12GatePDO();
    $inactive->active=false;
    b12_gate_reject(
        fn()=>daily_record_revision_prepare_save($inactive,'layer',7,11,'2026-10-13',null,b12_gate_tokens('edit'),$auth),
        LogicException::class,'caller must own transaction'
    );
    b12_gate_ok($inactive->trace===[], 'no SQL without caller transaction');

    $rum = new B12GatePDO();
    $tokens=b12_gate_tokens('edit');
    $tokens['original_animal_type']='goat';
    $out=daily_record_revision_prepare_save($rum,'ruminant',7,11,'2026-10-13','goat',$tokens,$auth);
    b12_gate_ok($out['contract']['permission']==='ruminant_daily_edit', 'ruminant species and edit permission');

    $bad=[
        ['record_id'=>''], ['expected_revision'=>'0'],
        ['original_cycle_id'=>'12'], ['original_record_date'=>'2026-10-12'],
        ['original_animal_type'=>'goat'], ['write_intent'=>'create'],
        ['record_id'=>['19']], ['unexpected_field'=>'harmless'],
    ];
    foreach ($bad as $mutation) {
        $tokens=array_replace(b12_gate_tokens('edit'),$mutation);
        if (isset($mutation['unexpected_field'])) {
            unset($tokens['record_id']); // prove missing required token rejects
        }
        b12_gate_reject(
            fn()=>daily_record_revision_save_contract('layer',7,11,'2026-10-13',null,$tokens),
            DailyRecordRevisionConflict::class,'invalid edit form'
        );
    }
    $mixed=b12_gate_tokens('create');
    $mixed['original_record_date']='2026-10-13';
    b12_gate_reject(
        fn()=>daily_record_revision_save_contract('layer',7,11,'2026-10-13',null,$mixed),
        DailyRecordRevisionConflict::class,'create cannot reuse identity'
    );
    b12_gate_reject(
        fn()=>daily_record_revision_save_contract('ruminant',7,11,'2026-10-13','sheep',$tokens),
        DailyRecordRevisionConflict::class,'ruminant selection does not match original animal type'
    );
    b12_gate_reject(
        fn()=>daily_record_revision_save_contract('layer',0,11,'2026-10-13',null,b12_gate_tokens('edit')),
        InvalidArgumentException::class,'missing server farm denied'
    );
    b12_gate_reject(
        fn()=>daily_record_revision_operation_permission('layer','upsert'),
        DailyRecordRevisionConflict::class,'no implicit UPSERT'
    );
    echo "B12_SAVE_GATE_PURE=PASS\nPASS_COUNT={$pass}\n";
} catch (Throwable $error) {
    fwrite(STDERR,"B12_SAVE_GATE_PURE=FAIL " . get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
