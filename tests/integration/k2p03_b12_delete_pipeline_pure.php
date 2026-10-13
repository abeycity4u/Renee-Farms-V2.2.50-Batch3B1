<?php
declare(strict_types=1);
/**
 * B12 DELETE pure contract: source-only PDO fake and injected reversal stubs.
 * No app bootstrap, network, production DB, MySQL, migrations or CI.
 */
$GLOBALS['b12_delete_events'] = [];
$GLOBALS['b12_delete_fail_at'] = '';
function b12_delete_event(string $event): void
{
    $GLOBALS['b12_delete_events'][] = $event;
    if ($GLOBALS['b12_delete_fail_at'] === $event) {
        throw new RuntimeException('Injected ' . $event . ' failure');
    }
}
function delete_daily_feed_usage(PDO $pdo, int $farmId, int $recordId, string $sourceType): void
{
    b12_delete_event('feed');
    if ($farmId !== 7 || $recordId !== 19 || !in_array($sourceType,
        ['daily_layer_record','daily_broiler_record','daily_ruminant_record'],true)) {
        throw new RuntimeException('Wrong deletion feed scope.');
    }
}
function daily_population_remove_mortality(
    PDO $pdo,int $farmId,string $sourceType,int $sourceId,?int $actorId
): array {
    b12_delete_event('mortality');
    if ($farmId !== 7 || $sourceId !== 19 || $actorId !== 9) {
        throw new RuntimeException('Wrong mortality source.');
    }
    return [];
}
function sales_rebuild_layer_egg_allocations(
    PDO $pdo,int $farmId,string $date='1000-01-01',?int $actorId=null
): array {
    b12_delete_event('sales');
    if ($farmId !== 7 || $date !== '2026-10-13' || $actorId !== 9) {
        throw new RuntimeException('Wrong Layer egg allocation scope.');
    }
    return [];
}
require_once dirname(__DIR__,2) . '/lib/daily_record_revision_delete_pipeline.php';

final class B12DeleteFakeStatement extends PDOStatement
{
    public function __construct(private B12DeleteFakePDO $db,private string $sql) {}
    public function execute(?array $params=null):bool {
        if (str_starts_with($this->sql,'SELECT ')) {
            b12_delete_event('lock');
        } elseif (str_starts_with($this->sql,'DELETE ')) {
            b12_delete_event('cas_delete');
        } else {
            throw new RuntimeException('Unexpected SQL');
        }
        $this->db->statements[] = [$this->sql, $params];
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,
        int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed {
        return $this->db->row;
    }
    public function rowCount():int { return $this->db->deleteAffected; }
}
final class B12DeleteFakePDO extends PDO
{
    public bool $active=false;
    public int $deleteAffected=1;
    public bool $committed=false;
    public bool $rolledBack=false;
    public array $statements=[];
    public mixed $row=[
        'id'=>19,'farm_id'=>7,'cycle_id'=>11,
        'record_date'=>'2026-10-13','record_revision'=>'4',
        'animal_type'=>'goat',
    ];
    public function __construct() {}
    public function inTransaction():bool { return $this->active; }
    public function beginTransaction():bool { $this->active=true; b12_delete_event('begin');return true; }
    public function commit():bool {
        b12_delete_event('commit');
        $this->active=false;$this->committed=true;return true;
    }
    public function rollBack():bool {
        $this->active=false;$this->rolledBack=true;b12_delete_event('rollback');return true;
    }
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        return new B12DeleteFakeStatement($this,$query);
    }
}
$checks=0;
function b12_delete_check(bool $ok,string $name):void {
    global $checks;
    if(!$ok)throw new RuntimeException('FAILED: '.$name);
    $checks++;
}
function b12_delete_reject(callable $fn,string $expected,string $name):void {
    try{$fn();}catch(Throwable $e){
        b12_delete_check($e instanceof $expected,$name.' got '.get_class($e));
        return;
    }
    throw new RuntimeException('Expected rejection '.$name);
}
function b12_delete_post(string $type):array {
    return [
        'write_intent'=>'delete','id'=>'19','record_id'=>'19',
        'expected_revision'=>'4','original_cycle_id'=>'11',
        'original_record_date'=>'2026-10-13',
        'original_animal_type'=>$type==='ruminant'?'goat':'',
    ];
}
function b12_delete_action(string $type,B12DeleteFakePDO $pdo,array $post,
    ?callable $authorized=null):array {
    return daily_record_revision_delete_transaction(
        $pdo,$type,7,9,$post,$authorized??static fn():bool=>true
    );
}
try {
    foreach(['layer','broiler','ruminant'] as $type) {
        $GLOBALS['b12_delete_events']=[];$GLOBALS['b12_delete_fail_at']='';
        $pdo=new B12DeleteFakePDO();
        $out=b12_delete_action($type,$pdo,b12_delete_post($type));
        $events=['begin','lock','feed','mortality','lock','cas_delete'];
        if($type==='layer')$events[]='sales';
        $events[]='commit';
        b12_delete_check($GLOBALS['b12_delete_events']===$events,
            $type.' exact lock -> reversals -> revision CAS -> commit');
        b12_delete_check($pdo->committed&&!$pdo->rolledBack,
            $type.' transaction committed');
        b12_delete_check(count($pdo->statements)===3,
            $type.' two locked revision compares and one DELETE');
        b12_delete_check(str_contains($pdo->statements[2][0],
            'WHERE id = ? AND farm_id = ? AND record_revision = ?'),
            $type.' DELETE has immutable revision CAS predicate');
        b12_delete_check($out===['record_id'=>19,'record_date'=>'2026-10-13'],
            $type.' stable original source identity');
        $sourceRow=$pdo->row;
        if($type!=='ruminant')unset($sourceRow['animal_type']);
        $fields=daily_record_revision_delete_form_fields($type,7,$sourceRow);
        b12_delete_check($fields['write_intent']==='delete'&&
            $fields['expected_revision']==='4'&&
            $fields['record_id']==='19',
            $type.' DELETE tokens originate from durable row revision');
    }

    foreach([
        ['write_intent','edit'],['record_id','20'],
        ['expected_revision','0'],['expected_revision','18446744073709551615'],
        ['original_record_date','2026-02-30'],['original_cycle_id','abc'],
        ['original_animal_type','goat'],
    ] as [$name,$bad]) {
        $post=b12_delete_post('layer');$post[$name]=$bad;
        b12_delete_reject(
            fn()=>daily_record_revision_delete_contract('layer',7,$post),
            DailyRecordRevisionConflict::class,
            'malformed or mismatched original '.$name
        );
    }
    $post=b12_delete_post('layer');unset($post['record_id']);
    b12_delete_reject(
        fn()=>daily_record_revision_delete_contract('layer',7,$post),
        DailyRecordRevisionConflict::class,'missing row identity');
    $post=b12_delete_post('layer');$post['id']='20';
    b12_delete_reject(
        fn()=>daily_record_revision_delete_contract('layer',7,$post),
        DailyRecordRevisionConflict::class,'API id and original id mismatch');
    $post=b12_delete_post('ruminant');$post['original_animal_type']='elephant';
    b12_delete_reject(
        fn()=>daily_record_revision_delete_contract('ruminant',7,$post),
        DailyRecordRevisionConflict::class,'unsupported species');
    $post=b12_delete_post('layer');$post['original_cycle_id']='';
    $pdo=new B12DeleteFakePDO();$pdo->row['cycle_id']=null;
    $GLOBALS['b12_delete_events']=[];$GLOBALS['b12_delete_fail_at']='';
    b12_delete_action('layer',$pdo,$post);
    b12_delete_check($pdo->committed,'explicit legacy NULL cycle safely scoped');

    $pdo=new B12DeleteFakePDO();
    $GLOBALS['b12_delete_events']=[];
    b12_delete_reject(
        fn()=>b12_delete_action('layer',$pdo,b12_delete_post('layer'),static fn():bool=>false),
        DailyRecordRevisionPermissionDenied::class,'permission before mutation');
    b12_delete_check($GLOBALS['b12_delete_events']===[],
        'unauthorized delete never begins a transaction');

    $pdo=new B12DeleteFakePDO();
    $pdo->row['record_revision']='5';
    $GLOBALS['b12_delete_events']=[];
    b12_delete_reject(
        fn()=>b12_delete_action('layer',$pdo,b12_delete_post('layer')),
        DailyRecordRevisionConflict::class,'stale revision');
    b12_delete_check($GLOBALS['b12_delete_events']===['begin','lock','rollback'],
        'stale revision never reverses inventory or mortality');
    b12_delete_check($pdo->rolledBack&&!$pdo->committed,
        'stale revision transaction rolled back');

    foreach(['feed','mortality','cas_delete','sales'] as $failure) {
        $GLOBALS['b12_delete_events']=[];
        $GLOBALS['b12_delete_fail_at']=$failure;
        $pdo=new B12DeleteFakePDO();
        b12_delete_reject(
            fn()=>b12_delete_action('layer',$pdo,b12_delete_post('layer')),
            RuntimeException::class,
            'injected '.$failure.' failure bubbles'
        );
        b12_delete_check($pdo->rolledBack&&!$pdo->committed,
            'injected '.$failure.' rolls back entire transaction');
    }
    $GLOBALS['b12_delete_fail_at']='';
    $pdo=new B12DeleteFakePDO();$pdo->deleteAffected=0;
    b12_delete_reject(
        fn()=>b12_delete_action('layer',$pdo,b12_delete_post('layer')),
        DailyRecordRevisionConflict::class,'CAS delete affected zero rows');
    b12_delete_check($pdo->rolledBack&&!$pdo->committed,
        'zero-row CAS failure rolls back side effects');
    echo "B12_DELETE_PIPELINE_PURE=PASS\nPASS_COUNT={$checks}\n";
} catch(Throwable $error) {
    fwrite(STDERR,'B12_DELETE_PIPELINE_PURE=FAIL '.get_class($error).': '
        .$error->getMessage()."\n");
    exit(1);
}
