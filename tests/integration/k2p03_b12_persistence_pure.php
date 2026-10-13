<?php
declare(strict_types=1);
/** Offline B12 persistence SQL contract; no DB connection or runtime routes. */
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_persistence.php';
final class B12PersistStatement extends PDOStatement
{
    private array $params = [];
    public function __construct(private B12PersistPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $this->params = $params ?? [];
        $this->db->executed[] = [$this->sql, $this->params];
        return $this->db->executeOk;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->db->row;
    }
    public function rowCount(): int { return $this->db->affected; }
}
final class B12PersistPDO extends PDO
{
    public bool $active = true;
    public bool $executeOk = true;
    public int $affected = 1;
    public array $executed = [];
    public array $row = [
        'id'=>19, 'farm_id'=>7, 'cycle_id'=>11, 'record_date'=>'2026-10-13',
        'record_revision'=>'4', 'animal_type'=>'goat'
    ];
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new B12PersistStatement($this, $query);
    }
    public function lastInsertId(?string $name = null): string|false { return '28'; }
}
$passed = 0;
function ok(bool $value, string $label): void
{
    global $passed;
    if (!$value) throw new RuntimeException('FAILED ' . $label);
    ++$passed;
}
function reject(callable $fn, string $class, string $label): void
{
    try { $fn(); } catch (Throwable $error) {
        ok($error instanceof $class, $label . ' actual ' . get_class($error));
        return;
    }
    throw new RuntimeException('Expected rejection ' . $label);
}
function prepared(string $type, string $mode): array
{
    $scope = ['cycle_id'=>11, 'record_date'=>'2026-10-13', 'animal_type'=>$type==='ruminant'?'goat':null];
    return [
        'contract'=>['type'=>$type,'mode'=>$mode,'scope'=>$scope,
            'record_id'=>$mode==='edit'?19:null,
            'expected_revision'=>$mode==='edit'?4:null],
        'locked_row'=>$mode==='edit'?['id'=>19,'farm_id'=>7]:null,
        'create_scope'=>$mode==='create'?
            ['status'=>'locked_absent','farm_id'=>7]+$scope:null,
    ];
}
function fields(string $type): array
{
    $core=['opening_stock'=>120,'mortality'=>2,'feed_item_id'=>null];
    if($type==='layer') return $core+['birds_age'=>140,'egg_production'=>70,'laying_rate'=>58.33,'crates_count'=>2.33];
    if($type==='broiler') return $core+['birds_age'=>40];
    return $core+['feed_consumption_kg'=>1.25,'feed_consumption_unit'=>'kg'];
}
try {
    foreach(['layer','broiler','ruminant'] as $type) {
        $pdo = new B12PersistPDO();
        $created=daily_record_revision_persist_prepared($pdo,prepared($type,'create'),7,5,fields($type));
        ok($created===['record_id'=>28,'record_revision'=>1,'mode'=>'create'], $type.' create returns generated identity');
        ok(count($pdo->executed)===1 && str_starts_with($pdo->executed[0][0],'INSERT INTO '),$type.' exactly one create INSERT');
        ok(!str_contains($pdo->executed[0][0],'record_revision'),$type.' DB supplies revision default');
        ok(str_contains($pdo->executed[0][0],'user_id'),$type.' authenticated actor attribution');
        ok(str_contains($pdo->executed[0][0],'animal_type') === ($type==='ruminant'),$type.' species insert identity');
        ok($pdo->executed[0][1][0]===7 && $pdo->executed[0][1][1]===11,
            $type.' tenant and cycle values ordered');

        $pdo = new B12PersistPDO();
        $edited=daily_record_revision_persist_prepared($pdo,prepared($type,'edit'),7,5,fields($type));
        ok($edited===['record_id'=>19,'record_revision'=>5,'mode'=>'edit'],$type.' EDIT uses original revision CAS');
        ok(count($pdo->executed)===2,$type.' SELECT FOR UPDATE then CAS UPDATE');
        ok(str_contains($pdo->executed[0][0],'FOR UPDATE') && str_contains($pdo->executed[1][0],'record_revision = record_revision + 1'),$type.' revision guarded mutation');
        ok(str_contains($pdo->executed[1][0],'WHERE id = ? AND farm_id = ? AND record_revision = ?'),$type.' immutable row CAS where');
    }
    $pdo=new B12PersistPDO();$pdo->row['record_revision']='5';
    reject(fn()=>daily_record_revision_persist_prepared($pdo,prepared('layer','edit'),7,5,fields('layer')),DailyRecordRevisionConflict::class,'stale edit rejected');
    ok(count($pdo->executed)===1,'stale edit cannot UPDATE');
    $pdo=new B12PersistPDO();$pdo->active=false;
    reject(fn()=>daily_record_revision_persist_prepared($pdo,prepared('layer','create'),7,5,fields('layer')),LogicException::class,'transaction mandatory');
    ok($pdo->executed===[],'no SQL outside transaction');
    foreach([['create_scope',null],['locked_row',['id'=>20,'farm_id'=>7]]] as [$key,$wrong]) {
        $type=$key==='create_scope'?'create':'edit';
        $p=prepared('layer',$type);$p[$key]=$wrong;
        reject(fn()=>daily_record_revision_persist_prepared(new B12PersistPDO(),$p,7,5,fields('layer')),
            DailyRecordRevisionConflict::class,'bad prepared '.$key);
    }
    foreach([['farm_id',9],['record_date','2026-10-14'],['status','unknown']] as [$key,$wrong]) {
        $p=prepared('ruminant','create');$p['create_scope'][$key]=$wrong;
        reject(fn()=>daily_record_revision_persist_prepared(new B12PersistPDO(),$p,7,5,fields('ruminant')),
            DailyRecordRevisionConflict::class,'bad CREATE scope '.$key);
    }
    reject(fn()=>daily_record_revision_persist_prepared(new B12PersistPDO(),prepared('layer','create'),7,5,['farm_id'=>5,'opening_stock'=>3,'mortality'=>0,'birds_age'=>1,'egg_production'=>1]),InvalidArgumentException::class,'forbidden tenant column');
    reject(fn()=>daily_record_revision_persist_prepared(new B12PersistPDO(),prepared('layer','create'),7,0,fields('layer')),InvalidArgumentException::class,'invalid actor');
    reject(fn()=>daily_record_revision_persist_prepared(new B12PersistPDO(),prepared('layer','create'),0,5,fields('layer')),InvalidArgumentException::class,'invalid tenant');
    reject(fn()=>daily_record_revision_persist_prepared(new B12PersistPDO(),prepared('layer','create'),7,5,['opening_stock'=>1,'mortality'=>0,'egg_production'=>1]),InvalidArgumentException::class,'missing bird age');
    reject(fn()=>daily_record_revision_persist_prepared(new B12PersistPDO(),prepared('ruminant','create'),7,5,['opening_stock'=>1]),InvalidArgumentException::class,'missing mortality');
    echo "B12_PERSISTENCE_PURE=PASS\nPASS_COUNT={$passed}\n";
} catch(Throwable $err){fwrite(STDERR,"B12_PERSISTENCE_PURE=FAIL ".get_class($err).": ".$err->getMessage()."\n");exit(1);}
