<?php
declare(strict_types=1);
/**
 * B12 WS15 source/isolated pure contract audit. No application bootstrap,
 * SQL, HTTP, runtime settings, CI or migration. ON behavior is tested by
 * separate disposable fixture; committed source fuse stays literal false.
 */
$root=dirname(__DIR__,2);
$passes=0;
function ws15(bool $truth,string $description):void {
    global $passes;
    if (!$truth) throw new RuntimeException('FAIL '.$description);
    $passes++;
}
function ws15_read(string $relative):string {
    global $root;
    $s=file_get_contents($root.'/'.$relative);
    if (!is_string($s)) throw new RuntimeException('Source missing '.$relative);
    return $s;
}
try {
    $gate=ws15_read('lib/daily_record_revision_cutover_gate.php');
    ws15(preg_match('~function\s+daily_record_revision_cutover_enabled\s*\(\s*\)\s*:\s*bool\s*\{\s*return\s+false\s*;\s*\}~',$gate)===1,
        'committed fuse is hard OFF');
    require_once $root.'/lib/daily_record_revision_preroute_http.php';
    $exact=['REQUEST_METHOD'=>'POST','SCRIPT_NAME'=>'/poultry/layers_daily_record.php',
        'HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest','HTTP_ACCEPT'=>'application/json'];
    ws15(daily_record_revision_preroute_is_save($exact,['save_record'=>'1'])===false,
        'OFF is never an active JSON pre-route');
    daily_record_revision_preroute_maybe_send('forbidden',$exact,['save_record'=>'1']);
    ws15(true,'OFF early denial leaves traditional legacy handler in control');
    foreach([
        'authentication_required'=>[401,'authentication_required'],
        'forbidden'=>[403,'forbidden'],
        'subscription_access_required'=>[403,'subscription_access_required'],
        'record_conflict'=>[409,'record_conflict'],
        'storage_unavailable'=>[503,'storage_unavailable'],
    ] as $reason=>[$status,$code]) {
        $response=daily_record_revision_preroute_failure_envelope($reason);
        ws15($response['status']===$status &&
             $response['body']['success']===false &&
             $response['body']['code']===$code &&
             is_string($response['body']['error']) &&
             strlen($response['body']['error'])>8,
             $reason.' yields a sanitized fixed JSON envelope');
    }
    try {
        daily_record_revision_preroute_failure_envelope('user-id=51');
        throw new RuntimeException('Unsupported reason was accepted.');
    } catch(InvalidArgumentException $expected) {
        ws15(true,'arbitrary user/SQL messages cannot appear in fixed JSON envelope');
    }
    $helper=ws15_read('lib/daily_record_revision_preroute_http.php');
    ws15(str_contains($helper,'daily_record_revision_http_send_json(') &&
        str_contains($helper,'daily_record_revision_cutover_enabled()') &&
        str_contains($helper,"isset(".'$post['."'save_record'".']'.")") &&
        !str_contains($helper,'->prepare(') &&
        !str_contains($helper,'$_SESSION'),
        'pre-route helper is narrow and never authorizes or queries DB');

    $cfg=ws15_read('config.php');
    $seam=strpos($cfg,"require_once __DIR__ . '/lib/daily_record_revision_preroute_http.php';");
    $session=strpos($cfg,'session_start();');
    $expire=strpos($cfg,"header('Location: ' . BASE_URL . '/login.php?timeout=1');");
    $db=strpos($cfg,'new PDO(');
    $dbFail=strpos($cfg,"die('Unable to connect to the database.");
    ws15($seam!==false && $session!==false && $expire!==false && $db!==false &&
        $dbFail!==false && $seam<$session && $session<$expire && $expire<$db,
        'error owner is ready before session expiry and database startup');
    ws15(str_contains($cfg,
        "daily_record_revision_preroute_maybe_send('authentication_required', ".'$_SERVER, $_POST);') &&
        str_contains($cfg,
        "daily_record_revision_preroute_maybe_send('storage_unavailable', ".'$_SERVER, $_POST);'),
        'session/login/farm/DB error paths preserve original decisions');
    ws15(substr_count($cfg,
        "daily_record_revision_preroute_maybe_send('authentication_required', ".'$_SERVER, $_POST);')===4,
        'four early authentication/missing farm hooks');
    $owner=ws15_read('includes/farm_entitlement_runtime.php');
    ws15(str_contains($owner,
        "daily_record_revision_preroute_maybe_send('subscription_access_required', ".'$_SERVER, $_POST);'),
        'entitlement denial remains centralized');
    $lifecycle=ws15_read('includes/subscription_lifecycle.php');
    ws15(str_contains($lifecycle,
        "daily_record_revision_preroute_maybe_send('subscription_access_required', ".'$_SERVER, $_POST);') &&
        strpos($lifecycle,'function subscription_lifecycle_redirect(') <
        strpos($lifecycle,'daily_record_revision_preroute_maybe_send('),
        'subscription renewal redirect is categorized only after lifecycle decision');
    $perm=ws15_read('includes/permission_runtime.php');
    ws15(str_contains($perm,
        "daily_record_revision_preroute_maybe_send('forbidden', ".'$_SERVER, $_POST);') &&
        str_contains($perm,
        "daily_record_revision_preroute_maybe_send('record_conflict', ".'$_SERVER, $_POST);') &&
        strpos($perm,'daily_record_revision_parse_write_intent(') <
        strpos($perm,"daily_record_revision_preroute_maybe_send('record_conflict'"),
        'permission/immutable-intent denial is not bypassed');

    foreach([
        'poultry/layers_daily_record.php',
        'poultry/broiler_daily_record.php',
        'ruminant/ruminant_daily_record.php'
    ] as $page) {
        $source=ws15_read($page);
        $denial=strpos($source,"daily_record_revision_preroute_maybe_send('forbidden', ".'$_SERVER, $_POST);');
        $redirect=strpos($source,"header('Location: dashboard.php');");
        $xhr=strpos($source,'daily_record_revision_save_http_require_ajax($_SERVER);');
        ws15($denial!==false && $redirect!==false && $xhr!==false &&
            $denial<$redirect && $redirect<$xhr,
            $page.' module guard precedes ordinary future SAVE transport');
        ws15(str_contains($source,'daily_record_revision_route_dispatch_json(') &&
            str_contains($source,'daily_record_revision_page_future_form_lock();'),
            $page.' retains CAS and fail-closed edit form');
    }
    $save=ws15_read('lib/daily_record_revision_save_http.php');
    $http=ws15_read('lib/daily_record_revision_http_transport.php');
    ws15(str_contains($save,'daily_record_revision_save_http_require_ajax(') &&
        str_contains($http,'daily_record_revision_http_is_ajax(') &&
        str_contains($http,"header('Cache-Control: no-store')"),
        'WS13 inner native POST denial and shared JSON responder retained');
    $client=ws15_read('assets/js/daily-record-revision-submit.js');
    $editor=ws15_read('assets/js/daily-record-revision-editor.js');
    $boot=ws15_read('assets/js/daily-record-revision-page-bootstrap.js');
    ws15(str_contains($client,'const failureReasons=Object.freeze({') &&
        str_contains($client,"'401|authentication_required'") &&
        str_contains($client,"'403|forbidden'") &&
        str_contains($client,"'409|revision_client_required'") &&
        str_contains($client,"'419|invalid_csrf'"),
        'client maps only declared status and error-code combinations');
    ws15(str_contains($editor,'safeFailures.includes(outcome.reason)') &&
        str_contains($editor,"invalidate();") &&
        str_contains($boot,'authentication_required:') &&
        str_contains($boot,'subscription_access_required:'),
        'editor clears stale authority and shows safe recovery notification');
    echo "B12_WS15_PREROUTE_SOURCE=PASS\nPASS_COUNT={$passes}\n";
} catch(Throwable $e) {
    fwrite(STDERR,"B12_WS15_PREROUTE_SOURCE=FAIL ".$e->getMessage()."\n");
    exit(1);
}
