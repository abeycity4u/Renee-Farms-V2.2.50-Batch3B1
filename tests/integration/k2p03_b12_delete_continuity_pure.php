<?php
declare(strict_types=1);

/**
 * B12 WS05A DELETE continuity pure fake. No app bootstrap, database, HTTP,
 * SQL, migrations, or concurrent MariaDB claims.
 */
$GLOBALS['ws05_later'] = [];
$GLOBALS['ws05_opening'] = ['opening_stock'=>120,'tracking_status'=>'canonical'];
$GLOBALS['ws05_projection_removed'] = false;
$GLOBALS['ws05_fail_apply'] = false;
$GLOBALS['ws05_trace'] = [];

function daily_population_continuity_later_rows(
    PDO $pdo,int $farmId,int $cycleId,string $type,
    string $date,?string $species=null,bool $forUpdate=false,
    ?int $sourceId=null
): array {
    $GLOBALS['ws05_trace'][] = 'later_locked';
    if (!$pdo->inTransaction() || !$forUpdate || $farmId!==7 ||
        $cycleId!==11 || $date!=='2026-10-13' || $sourceId!==null ||
        ($type==='ruminant' && $species!=='goat')) {
        throw new RuntimeException('DELETE later-row scope must lock exact cycle.');
    }
    return $GLOBALS['ws05_later'];
}

function daily_population_continuity_expected_opening(
    PDO $pdo,int $farmId,int $cycleId,string $type,
    string $date,?string $species=null
): ?array {
    $GLOBALS['ws05_trace'][] = 'canonical';
    if (!$pdo->inTransaction() || $farmId!==7 || $cycleId!==11 ||
        $date!=='2026-10-13' ||
        ($type==='ruminant' && $species!=='goat')) {
        throw new RuntimeException('DELETE canonical population scope mismatch.');
    }
    return $GLOBALS['ws05_opening'];
}

function daily_record_revision_continuity_apply(
    PDO $pdo,int $farmId,int $cycleId,string $type,
    string $date,int $opening,int $mortality,
    ?string $species,int $sourceId
): array {
    $GLOBALS['ws05_trace'][] = 'revision_rebase';
    if (!$pdo->inTransaction() ||
        !$GLOBALS['ws05_projection_removed'] ||
        $farmId!==7 || $cycleId!==11 || $date!=='2026-10-13' ||
        $sourceId!==19 || $opening!==120 || $mortality!==0 ||
        ($type==='ruminant' && $species!=='goat')) {
        throw new RuntimeException('DELETE rebase must follow projection reversal.');
    }
    if ($GLOBALS['ws05_fail_apply']) {
        throw new DailyRecordRevisionConflict('Injected guarded later-row CAS failure.');
    }
    return ['applied_count'=>2,'affected_count'=>2];
}

require_once dirname(__DIR__,2).'/lib/daily_record_revision_delete_continuity.php';

final class B12WS05PDO extends PDO
{
    public bool $active=true;
    public function __construct() {}
    public function inTransaction():bool { return $this->active; }
    public function prepare(string $sql,array $options=[]):PDOStatement|false {
        throw new LogicException('No actual SQL is allowed in pure contract test.');
    }
}
$pass=0;
function ws05_check(bool $condition,string $label):void {
    global $pass;
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    $pass++;
}
function ws05_reject(callable $action,string $expected,string $label):void {
    try { $action(); }
    catch(Throwable $error) {
        ws05_check($error instanceof $expected,$label);
        return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}
function ws05_reset():void {
    $GLOBALS['ws05_later'] = [['id'=>27,'record_date'=>'2026-10-14']];
    $GLOBALS['ws05_opening'] = [
        'opening_stock'=>120,'tracking_status'=>'canonical'
    ];
    $GLOBALS['ws05_projection_removed'] = false;
    $GLOBALS['ws05_fail_apply'] = false;
    $GLOBALS['ws05_trace'] = [];
}
function ws05_plan(B12WS05PDO $pdo,string $type,?int $cycle=11):array {
    return daily_record_revision_delete_continuity_lock(
        $pdo,$type,7,19,$cycle,'2026-10-13',
        $type==='ruminant'?' Goat ':null
    );
}

try {
    foreach(['layer','broiler','ruminant'] as $type) {
        ws05_reset();
        $pdo=new B12WS05PDO();
        $plan=ws05_plan($pdo,$type);
        ws05_check($plan['needs_rebase']===true &&
            $plan['type']===$type && $plan['farm_id']===7 &&
            $plan['record_id']===19 && $plan['cycle_id']===11 &&
            $plan['record_date']==='2026-10-13' &&
            $plan['animal_type']===($type==='ruminant'?'goat':null),
            $type.' locked canonical DELETE plan exact identity');
        ws05_check($GLOBALS['ws05_trace']===['later_locked','canonical'],
            $type.' later rows lock before first canonical preflight');
        // This state flip represents the canonical mortality service
        // completing its reversal inside the caller transaction.
        $GLOBALS['ws05_projection_removed']=true;
        $count=daily_record_revision_delete_continuity_apply($pdo,$plan);
        ws05_check($count===2,$type.' two later CAS revision updates');
        ws05_check($GLOBALS['ws05_trace']===
            ['later_locked','canonical','canonical','revision_rebase'],
            $type.' post-reversal canonical preview precedes revision updates');

        ws05_reset();
        $GLOBALS['ws05_later']=[];
        $pdo=new B12WS05PDO();
        $plan=ws05_plan($pdo,$type);
        ws05_check($plan['needs_rebase']===false &&
            $GLOBALS['ws05_trace']===['later_locked'],
            $type.' no later records means no unnecessary canonical baseline');
        ws05_check(daily_record_revision_delete_continuity_apply($pdo,$plan)===0 &&
            $GLOBALS['ws05_trace']===['later_locked'],
            $type.' empty later scope never increments revision');

        ws05_reset();
        $GLOBALS['ws05_opening']=null;
        $pdo=new B12WS05PDO();
        ws05_reject(fn()=>ws05_plan($pdo,$type),
            DailyRecordRevisionConflict::class,
            $type.' legacy/pre-baseline later rows reject before reversal');
        ws05_check($GLOBALS['ws05_trace']===['later_locked','canonical'],
            $type.' pre-baseline rejection has no later CAS mutation');

        ws05_reset();
        $pdo=new B12WS05PDO();
        $plan=ws05_plan($pdo,$type);
        $GLOBALS['ws05_projection_removed']=true;
        $GLOBALS['ws05_opening']=null;
        ws05_reject(
            fn()=>daily_record_revision_delete_continuity_apply($pdo,$plan),
            DailyRecordRevisionConflict::class,
            $type.' canonical baseline disappeared after reversal');
        ws05_check(!in_array('revision_rebase',$GLOBALS['ws05_trace'],true),
            $type.' vanished baseline cannot update later records');
    }

    ws05_reset();
    $pdo=new B12WS05PDO();
    $plan=ws05_plan($pdo,'ruminant',null);
    ws05_check(!$plan['needs_rebase'] && $plan['cycle_id']===null &&
        $GLOBALS['ws05_trace']===[],
        'legacy NULL-cycle exact DELETE unchanged, no fictitious cohort');
    ws05_check(daily_record_revision_delete_continuity_apply($pdo,$plan)===0,
        'legacy NULL-cycle plan never fabricates a rebase');

    ws05_reset();
    $pdo=new B12WS05PDO();$pdo->active=false;
    ws05_reject(fn()=>ws05_plan($pdo,'layer'),
        LogicException::class,'source lock requires transaction');
    ws05_check($GLOBALS['ws05_trace']===[],
        'transaction failure before any preview');

    ws05_reset();
    $pdo=new B12WS05PDO();
    ws05_reject(
        fn()=>daily_record_revision_delete_continuity_lock(
            $pdo,'ruminant',7,19,11,'2026-10-13','elephant'),
        DailyRecordRevisionConflict::class,
        'unsupported Ruminant species cannot authorize DELETE rebase'
    );
    ws05_check($GLOBALS['ws05_trace']===[],
        'unsupported species fails before later locks');

    ws05_reset();
    $pdo=new B12WS05PDO();
    $plan=ws05_plan($pdo,'layer');
    $GLOBALS['ws05_projection_removed']=true;
    $GLOBALS['ws05_fail_apply']=true;
    ws05_reject(
        fn()=>daily_record_revision_delete_continuity_apply($pdo,$plan),
        DailyRecordRevisionConflict::class,
        'later-row CAS failure bubbles for transaction owner rollback'
    );
    ws05_check(end($GLOBALS['ws05_trace'])==='revision_rebase',
        'later-row CAS fail occurs after canonical revalidation');

    ws05_reset();
    $pdo=new B12WS05PDO();
    ws05_reject(
        fn()=>daily_record_revision_delete_continuity_apply($pdo,[]),
        DailyRecordRevisionConflict::class,
        'incomplete DELETE continuity plan fails closed'
    );

    echo "B12_DELETE_CONTINUITY_PURE=PASS\nPASS_COUNT={$pass}\n";
} catch(Throwable $error) {
    fwrite(STDERR,'B12_DELETE_CONTINUITY_PURE=FAIL ' .
        get_class($error).': '.$error->getMessage()."\n");
    exit(1);
}
