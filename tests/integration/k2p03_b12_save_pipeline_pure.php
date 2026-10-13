<?php
declare(strict_types=1);

/**
 * B12 offline transaction pipeline sequencing verifier. Existing canonical
 * helpers are replaced with test-only event stubs in THIS PHP process.
 * No application boot, database, SQL migration, customer data, or CI job.
 */
$GLOBALS['b12_pipeline_events'] = [];
$GLOBALS['b12_fail_at'] = null;
function b12_pipe_log(string $event): void
{
    $GLOBALS['b12_pipeline_events'][] = $event;
    if ($GLOBALS['b12_fail_at'] === $event) {
        throw new RuntimeException('Injected ' . $event . ' failure.');
    }
}
function daily_record_revision_prepare_save(
    PDO $pdo, string $type, int $farmId, ?int $cycleId,
    string $recordDate, ?string $animalType, array $tokens, callable $authorized
): array {
    b12_pipe_log('prepare');
    $cap = daily_record_revision_capabilities($type);
    if (!$authorized($cap['module'], $cap['view'], $cap['edit'])) {
        throw new DailyRecordRevisionPermissionDenied('Access denied.');
    }
    return [
        'contract'=>[
            'scope'=>[
                'cycle_id'=>$cycleId, 'record_date'=>$recordDate,
                'animal_type'=>$animalType,
            ],
            'type'=>$type,
            'mode'=>'edit',
            'record_id'=>19,
            'expected_revision'=>4,
        ],
        'locked_row'=>['id'=>19,'farm_id'=>$farmId],
        'create_scope'=>null,
    ];
}
function daily_record_revision_persist_prepared(
    PDO $pdo, array $prepared, int $farmId, int $actorId, array $fields
): array {
    b12_pipe_log('persist');
    return ['record_id'=>19,'record_revision'=>5,'mode'=>'edit'];
}
function daily_record_revision_continuity_apply(
    PDO $pdo, int $farmId, int $cycleId, string $type,
    string $recordDate, int $openingStock, int $mortality,
    ?string $animalType, int $sourceId
): array {
    b12_pipe_log('continuity');
    if ($sourceId !== 19 || $cycleId !== 11 ||
        $farmId !== 7 || $recordDate !== '2026-10-13') {
        throw new RuntimeException('Bad continuity identity.');
    }
    return ['applied_count'=>2,'affected_count'=>2];
}
function sync_daily_feed_usage(
    PDO $pdo, int $farmId, int $recordId, ?int $feedItemId,
    float $quantity, ?int $cycleId, string $transactionDate,
    string $farmType, string $feedCategory, string $sourceType
): void {
    b12_pipe_log('feed');
    $expected = [
        'layer'=>['poultry','layer','daily_layer_record'],
        'broiler'=>['poultry','broiler','daily_broiler_record'],
        'ruminant'=>['ruminant','ruminant','daily_ruminant_record'],
    ];
    $recordType = str_replace(['daily_','_record'],'',$sourceType);
    if (!isset($expected[$recordType]) ||
        [$farmType,$feedCategory,$sourceType] !== $expected[$recordType]) {
        throw new RuntimeException('Bad canonical feed source tuple.');
    }
}
function daily_population_sync_mortality(
    PDO $pdo, int $farmId, string $sourceType, int $sourceId,
    ?int $cycleId, string $recordDate, int $mortality, int $actorId
): void {
    b12_pipe_log('mortality');
    if ($sourceId !== 19 || $actorId !== 9 || $mortality !== 2) {
        throw new RuntimeException('Bad canonical mortality projection.');
    }
}
function sales_rebuild_layer_egg_allocations(
    PDO $pdo, int $farmId, string $fromDate='1000-01-01', ?int $userId=null
): array {
    b12_pipe_log('sales');
    return ['rebuilt'=>0,'allocated'=>0,'unallocated'=>0];
}
require_once dirname(__DIR__, 2) . '/lib/daily_record_revision_save_pipeline.php';

final class B12PipelinePDO extends PDO
{
    public bool $active=true;
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
}
$passes=0;
function b12_pipeline_check(bool $result,string $label): void
{
    global $passes;
    if (!$result) throw new RuntimeException('FAILED: ' . $label);
    ++$passes;
}
function b12_pipeline_reject(callable $fn, string $class, string $label): void
{
    try { $fn(); }
    catch (Throwable $error) {
        b12_pipeline_check($error instanceof $class,
            $label . ' (' . get_class($error) . ')');
        return;
    }
    throw new RuntimeException('Expected error: ' . $label);
}
function b12_pipeline_fields(string $type): array
{
    $base=['opening_stock'=>120,'mortality'=>2,'feed_item_id'=>5];
    if ($type==='ruminant') return $base+['feed_consumption_kg'=>1.25];
    return $base+['feed_consumption_bags'=>1.25];
}
function b12_pipeline_run(
    string $type, ?int $cycleId=11, ?array $fields=null, ?callable $validate=null
): array {
    return daily_record_revision_save_pipeline(
        new B12PipelinePDO(),$type,7,9,$cycleId,'2026-10-13',
        $type==='ruminant'?'goat':null,
        ['write_intent'=>'edit'],
        $fields??b12_pipeline_fields($type),
        static fn():bool=>true,
        $validate??static function(PDO $pdo,array $prepared):void {
            b12_pipe_log('validate');
        }
    );
}
try {
    foreach (['layer','broiler','ruminant'] as $type) {
        $GLOBALS['b12_pipeline_events']=[];
        $out=b12_pipeline_run($type);
        $expected=['prepare','validate','persist','continuity','feed','mortality'];
        if ($type==='layer') $expected[]='sales';
        b12_pipeline_check($GLOBALS['b12_pipeline_events']===$expected,
            $type.' preserves atomic downstream order');
        b12_pipeline_check($out===['record_id'=>19,'record_revision'=>5,
            'mode'=>'edit','continuity_affected'=>2],
            $type.' result identity and continuity count');
    }
    $GLOBALS['b12_pipeline_events']=[];
    $out=b12_pipeline_run('broiler',null);
    b12_pipeline_check($out['continuity_affected']===0 &&
        !in_array('continuity',$GLOBALS['b12_pipeline_events'],true),
        'legacy NULL-cycle path does not invent continuity target');

    foreach (['validate','persist','continuity','feed','mortality','sales'] as $fail) {
        $GLOBALS['b12_fail_at']=$fail;
        $GLOBALS['b12_pipeline_events']=[];
        b12_pipeline_reject(
            fn()=>b12_pipeline_run('layer'),
            RuntimeException::class,
            'downstream '.$fail.' exception bubbles for caller rollback'
        );
        b12_pipeline_check(end($GLOBALS['b12_pipeline_events'])===$fail,
            'later side effects not attempted after '.$fail);
        $GLOBALS['b12_fail_at']=null;
    }

    foreach ([
        ['opening_stock'=>0],
        ['mortality'=>121],
        ['feed_item_id'=>null],
        ['feed_consumption_bags'=>-1],
    ] as $incorrect) {
        $GLOBALS['b12_pipeline_events']=[];
        b12_pipeline_reject(
            fn()=>b12_pipeline_run('layer',11,
                array_replace(b12_pipeline_fields('layer'),$incorrect)),
            InvalidArgumentException::class,
            'bad side effects rejected before original write'
        );
        b12_pipeline_check($GLOBALS['b12_pipeline_events']===[],
            'bad data never reaches lock or side effects');
    }
    $none=new B12PipelinePDO();
    $none->active=false;
    b12_pipeline_reject(
        fn()=>daily_record_revision_save_pipeline(
            $none,'layer',7,9,11,'2026-10-13',null,
            [],b12_pipeline_fields('layer'),static fn():bool=>true,
            static function():void {}
        ),
        LogicException::class,
        'no transaction means no writer starts'
    );
    echo "B12_SAVE_PIPELINE_PURE=PASS\nPASS_COUNT={$passes}\n";
} catch (Throwable $error) {
    fwrite(STDERR,'B12_SAVE_PIPELINE_PURE=FAIL ' .
        get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
