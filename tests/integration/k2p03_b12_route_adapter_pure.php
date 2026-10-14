<?php
declare(strict_types=1);

/**
 * B12 offline route-adapter tests. No database/app bootstrap/HTTP/CI.
 * Synthetic PDO captures tenant-qualified SQL; original live writers remain
 * guarded OFF. This is not MariaDB or browser concurrency certification.
 */
$GLOBALS['b12_pipeline_invoked'] = [];
$GLOBALS['b12_expected_opening'] = ['opening_stock'=>120];
$GLOBALS['b12_inject_failure'] = false;

function checkAccess(string $module): bool { return $module==='poultry' || $module==='ruminant'; }
function permission_runtime_has(string $permission): bool {
    return str_contains($permission,'daily_');
}
function daily_population_continuity_expected_opening(
    PDO $pdo,int $farmId,int $cycleId,string $recordType,
    string $recordDate,?string $animalType=null
): ?array {
    return $GLOBALS['b12_expected_opening'];
}
function daily_record_revision_save_pipeline(
    PDO $pdo,string $type,int $farmId,int $actorId,?int $cycleId,
    string $recordDate,?string $animalType,array $tokens,
    array $fields,callable $authorized,callable $revalidateLocked
): array {
    if (!$authorized($type==='ruminant'?'ruminant':'poultry',
        $type==='ruminant'?'ruminant_daily':'poultry_daily_'.$type,
        $type==='ruminant'?'ruminant_daily_edit':'poultry_daily_'.$type.'_edit')) {
        throw new RuntimeException('Unexpected denied permission.');
    }
    $revalidateLocked($pdo,[]);
    $GLOBALS['b12_pipeline_invoked'][]=[
        'type'=>$type,'farm'=>$farmId,'actor'=>$actorId,
        'cycle'=>$cycleId,'date'=>$recordDate,'species'=>$animalType,
        'tokens'=>$tokens,'fields'=>$fields
    ];
    if ($GLOBALS['b12_inject_failure']) {
        throw new RuntimeException('Injected downstream failure.');
    }
    return ['record_id'=>19,'record_revision'=>5,
            'mode'=>'edit','continuity_affected'=>2];
}
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_route_adapter.php';

class B12RouteFakeStatement extends PDOStatement
{
    public function __construct(private B12RouteFakePDO $db,private string $sql) {}
    public function execute(?array $params=null): bool {
        $this->db->calls[]=[$this->sql,$params];
        if (str_contains($this->sql,'production_cycles')) {
            $this->db->cycleScopeMatched =
                str_contains($this->sql, 'LOWER(TRIM(production_type)) = ?')
                && is_array($params)
                && end($params) === $this->db->cycleProductionType;
        }
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,
        int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed {
        if (str_contains($this->sql,'production_cycles')) {
            return $this->db->cycleScopeMatched
                ? ['id'=>11,'start_date'=>'2026-01-01','expected_end_date'=>null]
                : false;
        }
        return $this->db->previousRow;
    }
}
class B12RouteFakePDO extends PDO
{
    public bool $active=false;
    public bool $committed=false;
    public bool $rolledBack=false;
    public array $calls=[];
    public mixed $previousRow=['opening_stock'=>122,'mortality'=>2];
    public string $cycleProductionType='goat';
    public bool $cycleScopeMatched=false;
    public function __construct() {}
    public function inTransaction():bool { return $this->active; }
    public function beginTransaction():bool { $this->active=true;return true; }
    public function commit():bool { $this->active=false;$this->committed=true;return true; }
    public function rollBack():bool { $this->active=false;$this->rolledBack=true;return true; }
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        return new B12RouteFakeStatement($this,$query);
    }
}
$passes=0;
function b12_route_check(bool $ok,string $label):void {
    global $passes;
    if (!$ok) throw new RuntimeException('FAILED '.$label);
    $passes++;
}
function b12_route_reject(callable $action,string $exception,string $label):void {
    try{$action();}catch(Throwable $error){
        b12_route_check($error instanceof $exception,$label.' '.get_class($error));
        return;
    }
    throw new RuntimeException('Expected rejection '.$label);
}
function b12_route_facts(string $type):array {
    $f=['opening_stock'=>120,'mortality'=>2,'feed_consumption'=>1.25,
        'feed_item_id'=>5,'water_consumption'=>20.0];
    if($type==='layer')return $f+[
        'birds_age'=>210,'egg_production'=>90,
        'crates_count'=>3.0,'laying_rate'=>75.0];
    if($type==='broiler')return $f+['birds_age'=>40];
    return $f+['tag_no'=>''];
}
function b12_route_post():array {
    return [
        'write_intent'=>'edit','record_id'=>'19','expected_revision'=>'4',
        'original_cycle_id'=>'11','original_record_date'=>'2026-10-13',
        'original_animal_type'=>'','medications'=>'med',
        'remarks'=>'text','feed_consumption_unit'=>'kg',
        'other_details'=>'details','reproduction_details'=>'repro',
    ];
}
try {
    foreach(['layer','broiler','ruminant'] as $type) {
        $post=b12_route_post();
        $post['original_animal_type']=$type==='ruminant'?'goat':'';
        $fields=daily_record_revision_route_fields($type,$post,b12_route_facts($type));
        b12_route_check($fields['opening_stock']===120 &&
            $fields['mortality']===2 && $fields['feed_item_id']===5,
            $type.' preserves trusted persisted facts');
        b12_route_check(
            isset($fields[$type==='ruminant'?'feed_consumption_kg':'feed_consumption_bags']),
            $type.' canonical feed column');
        b12_route_check(
            array_key_exists('animal_type',$fields)===false &&
            array_key_exists('record_revision',$fields)===false &&
            array_key_exists('farm_id',$fields)===false,
            $type.' no caller-controlled identity in source UPDATE');
        $GLOBALS['b12_pipeline_invoked']=[];
        $pdo=new B12RouteFakePDO();
        $pdo->cycleProductionType=$type==='ruminant'?'goat':$type;
        $out=daily_record_revision_route_transaction(
            $pdo,$type,7,9,11,'2026-10-13',
            $type==='ruminant'?'goat':null,$post,b12_route_facts($type)
        );
        b12_route_check($pdo->committed && !$pdo->rolledBack &&
            !$pdo->active,$type.' transaction commits once');
        b12_route_check(count($GLOBALS['b12_pipeline_invoked'])===1 &&
            $GLOBALS['b12_pipeline_invoked'][0]['farm']===7,
            $type.' one authorized pipeline');
        b12_route_check(count($pdo->calls)===1 &&
            str_contains($pdo->calls[0][0],'FOR UPDATE') &&
            str_contains($pdo->calls[0][0],'farm_id = ?') &&
            str_contains($pdo->calls[0][0],'LOWER(TRIM(production_type)) = ?') &&
            $pdo->cycleScopeMatched,
            $type.' locked tenant-and-species-scoped cycle revalidation');
    }

    foreach(['medications','remarks','other_details','reproduction_details',
             'feed_consumption_unit'] as $field) {
        $bad=b12_route_post();
        $bad[$field]=['untrusted','array'];
        $type=in_array($field,['other_details','reproduction_details','feed_consumption_unit'])
            ? 'ruminant' : 'layer';
        b12_route_reject(
            fn()=>daily_record_revision_route_fields($type,$bad,b12_route_facts($type)),
            DailyRecordRevisionConflict::class,
            $type.' disallows array '.$field
        );
    }
    $bad=b12_route_facts('layer');unset($bad['opening_stock']);
    b12_route_reject(
        fn()=>daily_record_revision_route_fields('layer',b12_route_post(),$bad),
        InvalidArgumentException::class,'missing validated opening fact'
    );

    $GLOBALS['b12_expected_opening']=null;
    $pdo=new B12RouteFakePDO();
    $pdo->active=true;
    daily_record_revision_route_revalidate_locked(
        $pdo,'ruminant',7,11,'2026-10-13','goat',120
    );
    b12_route_check(count($pdo->calls)===2 &&
        str_contains($pdo->calls[1][0],'LOWER(TRIM(animal_type)) = ?') &&
        str_contains($pdo->calls[1][0],'ORDER BY record_date DESC, id DESC'),
        'legacy ruminant predecessor scoped by species and tenant');

    $pdo=new B12RouteFakePDO();
    $pdo->active=true;
    $pdo->cycleProductionType='cattle';
    b12_route_reject(
        fn()=>daily_record_revision_route_revalidate_locked(
            $pdo,'ruminant',7,11,'2026-10-13','goat',120
        ),
        DailyRecordRevisionConflict::class,
        'goat Daily Record cannot be written into cattle cycle'
    );
    b12_route_check(!$pdo->cycleScopeMatched && count($pdo->calls)===1,
        'cross-species mismatch fails before canonical/legacy predecessor lookup');

    $GLOBALS['b12_expected_opening']=['opening_stock'=>99];
    $pdo->cycleProductionType='layer';
    $callsBeforeCanonical=count($pdo->calls);
    b12_route_reject(
        fn()=>daily_record_revision_route_revalidate_locked(
            $pdo,'layer',7,11,'2026-10-13',null,120
        ),
        DailyRecordRevisionConflict::class,'stale canonical opening rejects'
    );
    b12_route_check($pdo->cycleScopeMatched &&
        count($pdo->calls)===$callsBeforeCanonical+1,
        'canonical opening mismatch is reached AFTER matching Layer cycle lock');
    $GLOBALS['b12_expected_opening']=['opening_stock'=>120];
    $GLOBALS['b12_inject_failure']=true;
    $pdo=new B12RouteFakePDO();
    $pdo->cycleProductionType='layer';
    b12_route_reject(
        fn()=>daily_record_revision_route_transaction(
            $pdo,'layer',7,9,11,'2026-10-13',null,
            b12_route_post(),b12_route_facts('layer')
        ),
        RuntimeException::class,'downstream failure bubbles'
    );
    b12_route_check($pdo->rolledBack && !$pdo->committed,
        'one caller rollback after failed downstream writer');
    echo "B12_ROUTE_ADAPTER_PURE=PASS\nPASS_COUNT={$passes}\n";
} catch(Throwable $error){
    fwrite(STDERR,"B12_ROUTE_ADAPTER_PURE=FAIL ".get_class($error)
        .': '.$error->getMessage()."\n");
    exit(1);
}
