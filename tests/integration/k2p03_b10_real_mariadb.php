<?php
declare(strict_types=1);

/**
 * C137 GC24 K2/P03 B10 R1 — synthetic multi-tenant, role and CSRF guards.
 * Uses real app permission/CSRF functions and real shared tenant-source
 * resolution against one disposable GitHub Actions MariaDB schema.
 *
 * Worker subprocesses isolate the application's per-request role cache,
 * session identity and terminating HTTP guards; no external HTTP network,
 * authenticated production browser request, config secrets or real tenant.
 */
require_once __DIR__.'/k2p03_ci_isolation.php';

$stage='EXACT_CI_ENVIRONMENT';
$pdo=null;

function b10_snapshot(PDO $db,array $farms): array
{
    $map=[];
    foreach(['farms','users','permissions','farm_modules',
              'production_cycles','layer_daily_records'] as $table) {
        $q=$db->prepare('SELECT * FROM '.$table.' WHERE '.(
            $table==='farms'?'id':'farm_id'
        ).' IN (?,?) ORDER BY id');
        $q->execute($farms);
        $map[$table]=$q->fetchAll(PDO::FETCH_ASSOC);
    }
    $q=$db->prepare(
        'SELECT ur.user_id,ur.role_id FROM user_roles ur
         INNER JOIN users u ON u.id=ur.user_id
         WHERE u.farm_id IN (?,?) ORDER BY ur.user_id,ur.role_id'
    );
    $q->execute($farms);
    $map['user_roles']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$db->query(
        "SELECT * FROM roles WHERE code IN
         ('farm_admin','poultry_manager','sales_rep') ORDER BY id"
    );
    $map['roles']=$q->fetchAll(PDO::FETCH_ASSOC);
    return $map;
}

function b10_worker(array $argv): never
{
    $mode=(string)($argv[2]??'');
    $farm=(int)($argv[3]??0);
    $actor=(int)($argv[4]??0);
    $target=(int)($argv[5]??0);
    $cycle=(int)($argv[6]??0);
    if($farm<1||$actor<1||!in_array($mode,[
        'allowed_manager','role_denied','team_cross_farm',
        'record_cross_farm','record_scope','csrf_missing','csrf_forged',
        'csrf_header_precedence','csrf_valid','csrf_wrong_method'
    ],true)) {
        throw new RuntimeException('B10 worker arguments invalid.');
    }

    // Full application CSRF verifier/role cache uses only the synthetic DB.
    // This is set only AFTER the CI identity guard and before config.php.
    foreach([
        'DB_HOST'=>'127.0.0.1','DB_USER'=>'ci_k2p03',
        'DB_PASS'=>(string)getenv('CI_DB_PASSWORD'),
        'DB_NAME'=>'renee_k2p03_ci_b10_r1'
    ] as $k=>$v) putenv($k.'='.$v);
    $_SERVER['DOCUMENT_ROOT']=dirname(__DIR__,2);
    $_SERVER['SCRIPT_NAME']='/tests/integration/k2p03_b10_real_mariadb.php';
    $_SERVER['REQUEST_METHOD']='GET';
    $_SERVER['REMOTE_ADDR']='127.0.0.1';

    // The real config creates the local PDO connection. Bind the same
    // variable globally so app permission helpers use the isolated DB.
    global $pdo;
    require_once dirname(__DIR__,2).'/config.php';
    http_response_code(200);
    register_shutdown_function(static function ():void {
        echo "\nB10_WORKER_HTTP_STATUS=".http_response_code()."\n";
    });
    // Install the synthetic actor BEFORE init.php evaluates/caches roles.
    $_SESSION['user_id']=$actor;
    $_SESSION['farm_id']=$farm;
    $q=$pdo->prepare('SELECT user_type FROM users WHERE id=? AND farm_id=?');
    $q->execute([$actor,$farm]);
    $role=(string)($q->fetchColumn()?:'');
    if($role==='') throw new RuntimeException('Actor is not part of its tenant.');
    $_SESSION['user_type']=$role;
    $_SESSION['csrf_token']=str_repeat('a',64);
    // Use the production bootstrap order: init.php loads functions before
    // permission_runtime dispatch. Loading functions.php first would invert
    // that dependency and can evaluate a partial permission stack.
    require_once dirname(__DIR__,2).'/init.php';

    if($mode==='allowed_manager'||$mode==='role_denied'){
        $granted=permission_runtime_has('poultry_daily_layer_edit');
        if($mode==='allowed_manager'){
            if(!$granted) throw new RuntimeException('Expected role grant missing.');
            echo 'B10_ALLOWED_ROLE=YES'.PHP_EOL;
            exit(0);
        }
        if($granted) throw new RuntimeException('Unexpected role grant.');
        echo 'B10_WRONG_ROLE_DENIED=YES'.PHP_EOL;
        // POST is the mutating request boundary; GET denial redirects.
        $_SERVER['REQUEST_METHOD']='POST';
        permission_runtime_deny('Permission denied.');
        throw new RuntimeException('Permission deny returned.');
    }
    if($mode==='team_cross_farm'){
        $_SERVER['SCRIPT_NAME']='/management/users.php';
        $_SERVER['REQUEST_METHOD']='POST';
        $_POST=['edit_user'=>'1','user_id'=>(string)$target];
        echo 'B10_TEAM_GUARD_INVOKED=YES'.PHP_EOL;
        user_management_tenant_guard($pdo); // must exit with 404
        throw new RuntimeException('Cross-tenant Team Users guard returned.');
    }
    if($mode==='record_cross_farm'){
        $pdo->beginTransaction();
        try {
            daily_feed_sync_authoritative_cycle_guard(
                $pdo,$farm,$target,$cycle,'daily_layer_record'
            );
        } catch(RuntimeException $e) {
            $pdo->rollBack();
            // Only the shared resolver's explicit source-not-found denial
            // qualifies. A database error or unrelated exception must fail.
            if($e->getMessage()!==
                'The authoritative Daily Record linked to this stock movement was not found.') {
                throw $e;
            }
            echo 'B10_FOREIGN_RECORD_SOURCE_REJECTED=YES'.PHP_EOL;
            exit(0);
        }
        $pdo->rollBack();
        throw new RuntimeException('Foreign Daily Record was resolved.');
    }
    if($mode==='record_scope'){
        $_POST=['record_date'=>'2026-09-21','cycle_id'=>(string)$cycle];
        if(permission_runtime_existing_daily_record($pdo,'layer')){
            throw new RuntimeException('Foreign Daily Record found by farm-scoped lookup.');
        }
        echo 'B10_FOREIGN_RECORD_NOT_FOUND=YES'.PHP_EOL;
        exit(0);
    }

    // Exercise the unchanged actual config.php verify_csrf_token() through
    // includes/csrf.php::require_valid_csrf_post(). An exit must be captured
    // from shutdown status, never interpreted as successful authorization.
    $_SERVER['REQUEST_METHOD']=$mode==='csrf_wrong_method'?'GET':'POST';
    $_POST=[];
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    if($mode==='csrf_forged') $_POST['csrf_token']=str_repeat('b',64);
    if($mode==='csrf_header_precedence') {
        $_POST['csrf_token']=str_repeat('a',64);
        $_SERVER['HTTP_X_CSRF_TOKEN']=str_repeat('c',64);
    }
    if($mode==='csrf_valid'||$mode==='csrf_wrong_method') {
        $_POST['csrf_token']=str_repeat('a',64);
    }
    require_valid_csrf_post();
    if($mode!=='csrf_valid') {
        throw new RuntimeException('Invalid method/token unexpectedly authorized.');
    }
    echo 'B10_VALID_CSRF_ACCEPTED=YES'.PHP_EOL;
    exit(0);
}

function b10_exec_worker(string $mode,array $ids): array
{
    $args=array_merge([PHP_BINARY,__FILE__,'--worker',$mode],
       array_map('strval',$ids));
    $pipes=[];
    $proc=proc_open($args,[
        0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']
    ],$pipes,dirname(__DIR__,2));
    if(!is_resource($proc)) {
        throw new RuntimeException('B10 isolated local worker failed to start.');
    }
    fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);
    $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    $exit=proc_close($proc);
    if($exit!==0||trim((string)$err)!=='') {
        throw new RuntimeException('B10 worker failed: '.$mode);
    }
    return ['output'=>(string)$out,'exit'=>$exit];
}

try {
    k2p03_ci_require_environment(
        'c137-gc24-k2p03-ci-mariadb-b10-r1','B10R1','renee_k2p03_ci_b10_r1'
    );
    if(($argv[1]??'')==='--worker') {
        b10_worker($argv);
    }
    $root=dirname(__DIR__,2);
    k2p03_ci_equal(
        '9503771b47c6c2fe26bb3237da69a17874e724a3',
        k2p03_ci_blob($root.'/lib/daily_population_continuity.php'),
        'original shared continuity Git blob remains pinned'
    );
    k2p03_ci_equal(
        'e77f287d62c5812a3d55dc23b5b20f5d167db389',
        k2p03_ci_blob($root.'/database_schema.sql'),
        'original 69-table SQL Git blob remains pinned'
    );
    $pdo=k2p03_ci_connection('renee_k2p03_ci_b10_r1');
    $stage='TWO_SYNTHETIC_TENANTS_AND_ACTORS';
    $farm=[];$cycles=[];$records=[];$users=[];
    foreach(['alpha','beta'] as $name) {
        $pdo->prepare(
            "INSERT INTO farms(name,slug,subscription_status)
             VALUES (?,?, 'active')"
        )->execute(['B10 synthetic '.$name,'k2p03-b10-'.$name]);
        $fid=(int)$pdo->lastInsertId();
        $farm[$name]=$fid;
        $pdo->prepare(
            "INSERT INTO farm_modules(farm_id,module_code,is_enabled)
             VALUES (?,'poultry',1)"
        )->execute([$fid]);
        $pdo->prepare(
            "INSERT INTO production_cycles(farm_id,cycle_code,
              farm_type,production_type,status,start_date,opening_headcount)
             VALUES (?,?,'poultry','layer','active','2026-09-21',30)"
        )->execute([$fid,'b10-'.$name.'-layer']);
        $cycles[$name]=(int)$pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO layer_daily_records(farm_id,cycle_id,record_date,
              opening_stock,mortality,egg_production,crates_count,
              laying_rate,birds_age)
             VALUES (?,?,'2026-09-21',30,1,5,0,16.67,25)"
        )->execute([$fid,$cycles[$name]]);
        $records[$name]=(int)$pdo->lastInsertId();
    }
    k2p03_ci_check($farm['alpha']!==$farm['beta'],
        'two distinct synthetic tenants');
    k2p03_ci_check($cycles['alpha']!==$cycles['beta'],
        'two disjoint farm-scoped Layer cycles');
    k2p03_ci_check($records['alpha']!==$records['beta'],
        'two disjoint saved Daily Record identities');
    foreach(['farm_admin','poultry_manager','sales_rep'] as $role) {
        $pdo->prepare(
            'INSERT INTO roles(code,name) VALUES (?,?)'
        )->execute([$role,'B10 '.$role]);
    }
    $allRoles=$pdo->query("SELECT code,id FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach([
        'alpha'=>['admin'=>'farm_admin','manager'=>'poultry_manager'],
        'beta'=>['manager'=>'poultry_manager','sales'=>'sales_rep']
    ] as $name=>$roles) {
        foreach($roles as $label=>$role) {
            $pdo->prepare(
                'INSERT INTO users(farm_id,username,password,user_type,full_name)
                 VALUES (?,?,?,?,?)'
            )->execute([$farm[$name], 'b10-'.$name.'-'.$label,
                'synthetic-nonsecret-disabled-login',$role,
                'B10 synthetic '.$name.' '.$label]);
            $id=(int)$pdo->lastInsertId();
            $users[$name][$label]=$id;
            $pdo->prepare(
                'INSERT INTO user_roles(user_id,role_id) VALUES (?,?)'
            )->execute([$id,(int)$allRoles[$role]]);
        }
    }
    foreach(['alpha','beta'] as $name) {
        $pdo->prepare(
            "INSERT INTO permissions(farm_id,role,module,allowed)
             VALUES (?,'poultry_manager','poultry_daily_layer_edit',1)"
        )->execute([$farm[$name]]);
    }
    // Deliberately contradictory delegated flag: Sales Rep is not a Poultry
    // Manager and must be rejected by canonical role applicability.
    $pdo->prepare(
        "INSERT INTO permissions(farm_id,role,module,allowed)
         VALUES (?,'sales_rep','poultry_daily_layer_edit',1)"
    )->execute([$farm['beta']]);

    $stage='REAL_GUARD_WORKER_MATRIX';
    $baseline=b10_snapshot($pdo,array_values($farm));
    $cases=[
      ['alpha manager valid permission','allowed_manager',
        [$farm['alpha'],$users['alpha']['manager'],0,0],
        'B10_ALLOWED_ROLE=YES',200],
      ['beta manager valid permission','allowed_manager',
        [$farm['beta'],$users['beta']['manager'],0,0],
        'B10_ALLOWED_ROLE=YES',200],
      ['wrong role even with forged grant','role_denied',
        [$farm['beta'],$users['beta']['sales'],0,0],
        'B10_WRONG_ROLE_DENIED=YES',403],
      ['cross-farm Team Users target denied','team_cross_farm',
        [$farm['alpha'],$users['alpha']['admin'],$users['beta']['sales'],0],
        'Team user not found.',404],
      ['cross-farm Daily Record source rejected','record_cross_farm',
        [$farm['beta'],$users['beta']['manager'],$records['alpha'],$cycles['alpha']],
        'B10_FOREIGN_RECORD_SOURCE_REJECTED=YES',200],
      ['cross-farm Daily Record lookup not found','record_scope',
        [$farm['beta'],$users['beta']['manager'],$records['alpha'],$cycles['alpha']],
        'B10_FOREIGN_RECORD_NOT_FOUND=YES',200],
      ['missing CSRF denied','csrf_missing',
        [$farm['alpha'],$users['alpha']['manager'],0,0],
        'Invalid request token.',419],
      ['forged CSRF denied','csrf_forged',
        [$farm['alpha'],$users['alpha']['manager'],0,0],
        'Invalid request token.',419],
      ['invalid header overrides valid POST token','csrf_header_precedence',
        [$farm['alpha'],$users['alpha']['manager'],0,0],
        'Invalid request token.',419],
      ['wrong HTTP method denied','csrf_wrong_method',
        [$farm['alpha'],$users['alpha']['manager'],0,0],
        'Method not allowed.',405],
      ['matching CSRF accepted without mutation','csrf_valid',
        [$farm['alpha'],$users['alpha']['manager'],0,0],
        'B10_VALID_CSRF_ACCEPTED=YES',200],
    ];
    foreach($cases as [$label,$mode,$ids,$needle,$http]) {
        $result=b10_exec_worker($mode,$ids);
        k2p03_ci_check(str_contains($result['output'],$needle),
            $label.' — authentic shared guard result');
        k2p03_ci_check(
            str_contains(
                $result['output'],'B10_WORKER_HTTP_STATUS='.$http
            ),$label.' — response status');
        k2p03_ci_equal($baseline,
            b10_snapshot($pdo,array_values($farm)),
            $label.' — both tenant farms and roles unchanged');
    }
    $stage='FINAL_TENANT_ISOLATION_INVARIANTS';
    k2p03_ci_equal(2,count($baseline['farms']),
        'two unchanged tenant farm identities');
    k2p03_ci_equal(4,count($baseline['users']),
        'all four synthetic actors remain original');
    k2p03_ci_equal(2,count($baseline['layer_daily_records']),
        'both saved Layer Daily Records unchanged');
    k2p03_ci_equal(2,count($baseline['production_cycles']),
        'both synthetic cycles unchanged');
    k2p03_ci_equal(3,count($baseline['permissions']),
        'including malicious role grant, original policy rows unchanged');
    k2p03_ci_equal($baseline,b10_snapshot($pdo,array_values($farm)),
        'final eight-table cross-farm authorization invariants');
    echo 'K2_P03_B10_R1_REAL_MARIADB=PASS'.PHP_EOL;
} catch(Throwable $e) {
    if($pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'FAILURE_STAGE='.$stage.PHP_EOL);
    fwrite(STDERR,'FAILURE_CLASS='.get_class($e).PHP_EOL);
    fwrite(STDERR,'K2_P03_B10_R1_REAL_MARIADB=FAIL'.PHP_EOL);
    exit(1);
}
