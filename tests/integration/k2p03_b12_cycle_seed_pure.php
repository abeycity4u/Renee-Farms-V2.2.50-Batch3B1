<?php
declare(strict_types=1);

/**
 * B12 source-only cycle-seed tests using fake PDO.
 *
 * No app bootstrap, HTTP, production schema, DB, migrations or CI actions.
 * Must be executed locally against the exact checked-out source before any
 * cutover approval; this fixture does not certify real MariaDB concurrency.
 */
$GLOBALS['b12_seed_cycle'] = [];

function production_cycle_get(PDO $pdo, int $farmId, int $cycleId, bool $forUpdate=false): array
{
    if (!$forUpdate || $farmId !== 7 || $cycleId !== 11) {
        throw new RuntimeException('Seed must resolve exact locked canonical cycle.');
    }
    return $GLOBALS['b12_seed_cycle'];
}

require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_cycle_seed.php';

final class B12SeedStatement extends PDOStatement
{
    public function __construct(private B12SeedPDO $pdo, private string $sql) {}
    public function execute(?array $params=null): bool
    {
        $this->pdo->calls[] = [$this->sql, $params];
        return $this->pdo->executeOk;
    }
    public function fetchColumn(int $column=0): mixed { return $this->pdo->existingId; }
    public function rowCount(): int { return $this->pdo->insertCount; }
}
final class B12SeedPDO extends PDO
{
    public bool $active=true;
    public bool $executeOk=true;
    public int $insertCount=1;
    public mixed $existingId=false;
    public array $calls=[];
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function prepare(string $query, array $options=[]):PDOStatement|false
    {
        return new B12SeedStatement($this, $query);
    }
    public function lastInsertId(?string $name=null):string|false {return '49';}
}
$count=0;
function b12_seed_assert(bool $ok,string $label):void
{
    global $count;
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    $count++;
}
function b12_seed_reject(callable $fn,string $kind,string $label):void
{
    try { $fn(); }
    catch(Throwable $e) {
        b12_seed_assert($e instanceof $kind,$label.' ['.get_class($e).']');
        return;
    }
    throw new RuntimeException('Expected rejection '.$label);
}
function b12_cycle(string $farmType,string $productionType,int $headcount=3000):array
{
    return [
        'id'=>11,'farm_id'=>7,'farm_type'=>$farmType,
        'production_type'=>$productionType,'status'=>'active',
        'start_date'=>'2026-10-13','opening_headcount'=>$headcount,
    ];
}
function b12_seed(B12SeedPDO $pdo,int $count=3000,int $age=40,?int $actor=9):array
{
    return daily_record_revision_seed_cycle_entry(
        $pdo,7,11,'2026-10-13',$count,$age,$actor
    );
}
try {
    foreach ([
        ['poultry','layer','layer'],
        ['poultry','broiler','broiler'],
        ['ruminant','goat','ruminant'],
        ['ruminant','other','ruminant'],
    ] as [$farm,$production,$type]) {
        $GLOBALS['b12_seed_cycle']=b12_cycle($farm,$production);
        $pdo=new B12SeedPDO();
        $result=b12_seed($pdo);
        b12_seed_assert($result===['record_id'=>49,'record_revision'=>1,'type'=>$type],
            $type.' returns durable seeded identity and revision 1');
        b12_seed_assert(count($pdo->calls)===2,
            $type.' one locked existence probe followed by one INSERT');
        [$probe,$args]=$pdo->calls[0];
        [$sql,$params]=$pdo->calls[1];
        b12_seed_assert(str_contains($probe,'FOR UPDATE') &&
            str_contains($probe,'farm_id = ? AND cycle_id = ? AND record_date = ?'),
            $type.' tenant and cycle scoped duplicate probe');
        b12_seed_assert(str_contains($sql,'INSERT INTO '.$type.'_daily_records') &&
            str_contains($sql,'record_revision') &&
            end($params)===1,
            $type.' first row explicitly sets DB revision to one');
        b12_seed_assert($params[0]===7 && $params[1]===11 &&
            $params[2]==='2026-10-13',
            $type.' inserted tenant/cycle/date from canonical identity');
        b12_seed_assert(str_contains($sql,'animal_type') === ($type==='ruminant') &&
            count($args)===($type==='ruminant'?4:3),
            $type.' species identity only when applicable');
        b12_seed_assert(str_contains($sql,'birds_age') === ($type!=='ruminant') &&
            str_contains($sql,'egg_production') === ($type==='layer'),
            $type.' preserves existing type-specific seed columns');
        b12_seed_assert(str_contains($sql,'user_id') &&
            !str_contains($sql,'feed_item_id'),
            $type.' actor stored and legacy unassigned feed untouched');
    }

    $GLOBALS['b12_seed_cycle']=b12_cycle('poultry','layer',0);
    $pdo=new B12SeedPDO();
    $result=b12_seed($pdo,0,1,null);
    b12_seed_assert($result['record_revision']===1 &&
        in_array(null,$pdo->calls[1][1],true),
        'existing zero-headcount and nullable creator semantics preserved');

    $GLOBALS['b12_seed_cycle']=b12_cycle('poultry','broiler');
    $pdo=new B12SeedPDO();$pdo->existingId=200;
    b12_seed_reject(fn()=>b12_seed($pdo),
        DailyRecordRevisionConflict::class,'duplicate first row rejected');
    b12_seed_assert(count($pdo->calls)===1,'duplicate does not insert');

    $pdo=new B12SeedPDO();$pdo->active=false;
    b12_seed_reject(fn()=>b12_seed($pdo),
        LogicException::class,'must be inside cycle creation transaction');
    b12_seed_assert($pdo->calls===[],'no SQL outside transaction');

    foreach ([
        ['id',12],
        ['farm_id',8],
        ['status','completed'],
        ['start_date','2026-10-12'],
        ['opening_headcount',123],
        ['farm_type','other'],
        ['production_type','unknown'],
    ] as [$field,$bad]) {
        $GLOBALS['b12_seed_cycle']=b12_cycle('poultry','layer');
        $GLOBALS['b12_seed_cycle'][$field]=$bad;
        $pdo=new B12SeedPDO();
        b12_seed_reject(fn()=>b12_seed($pdo),
            DailyRecordRevisionConflict::class,'tampered canonical cycle '.$field);
        b12_seed_assert($pdo->calls===[],'tampered cycle cannot insert '.$field);
    }

    $GLOBALS['b12_seed_cycle']=b12_cycle('ruminant',str_repeat('A',101));
    $pdo=new B12SeedPDO();
    b12_seed_reject(fn()=>b12_seed($pdo),
        InvalidArgumentException::class,'species longer than permitted');
    b12_seed_assert($pdo->calls===[],'invalid species never inserts');

    $GLOBALS['b12_seed_cycle']=b12_cycle('poultry','layer');
    $pdo=new B12SeedPDO();$pdo->insertCount=0;
    b12_seed_reject(fn()=>b12_seed($pdo),
        DailyRecordRevisionConflict::class,'failed INSERT is a conflict');
    b12_seed_assert(count($pdo->calls)===2,'failed INSERT stays in caller transaction');

    $pdo=new B12SeedPDO();
    b12_seed_reject(fn()=>b12_seed($pdo,-1),
        InvalidArgumentException::class,'negative seed count rejected');
    b12_seed_assert($pdo->calls===[],'negative seed never queries');

    echo "B12_CYCLE_SEED_PURE=PASS\nPASS_COUNT={$count}\n";
} catch(Throwable $e) {
    fwrite(STDERR,"B12_CYCLE_SEED_PURE=FAIL ".
        get_class($e).": ".$e->getMessage()."\n");
    exit(1);
}
