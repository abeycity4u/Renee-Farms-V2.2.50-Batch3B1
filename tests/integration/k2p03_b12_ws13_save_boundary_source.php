<?php
declare(strict_types=1);
/**
 * WS13 source-only SAVE transport regression. Reads source, calls pure
 * transport/closed-gate functions; no web app bootstrap, PDO, writes, CI.
 */
$root = dirname(__DIR__, 2);
$count = 0;
function ws13_assert(bool $ok, string $description): void {
    global $count;
    if (!$ok) throw new RuntimeException('FAIL ' . $description);
    $count++;
}
function ws13_source(string $path): string {
    global $root;
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) throw new RuntimeException('Missing ' . $path);
    return $value;
}
try {
    $gate=ws13_source('lib/daily_record_revision_cutover_gate.php');
    ws13_assert(
        preg_match('~function\s+daily_record_revision_cutover_enabled\s*\(\s*\)\s*:\s*bool\s*\{\s*return\s+false\s*;\s*\}~',$gate)===1,
        'hard-off source fuse unchanged'
    );
    require_once $root.'/lib/daily_record_revision_save_http.php';
    ws13_assert(daily_record_revision_cutover_enabled()===false,'OFF gate executes false');
    // The OFF branch remains a traditional HTML form, even without XHR.
    daily_record_revision_save_http_require_ajax([]);
    ws13_assert(true,'OFF native POST remains allowed');
    $ajax=['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest','HTTP_ACCEPT'=>'application/json'];
    foreach([
        $ajax,
        ['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'xmlhttprequest','HTTP_ACCEPT'=>'text/html, application/json; q=0.9'],
        ['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest','HTTP_ACCEPT'=>'APPLICATION/JSON; charset=UTF-8']
    ] as $request){
        ws13_assert(daily_record_revision_http_is_ajax($request),'valid B12 AJAX format');
    }
    foreach([
        [],
        ['REQUEST_METHOD'=>'GET','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest','HTTP_ACCEPT'=>'application/json'],
        ['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'','HTTP_ACCEPT'=>'application/json'],
        ['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest','HTTP_ACCEPT'=>'text/html'],
        ['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest','HTTP_ACCEPT'=>'application/jsonp'],
        ['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest','HTTP_ACCEPT'=>'application/jsonjunk']
    ] as $request){
        ws13_assert(!daily_record_revision_http_is_ajax($request),'reject malformed B12 AJAX transport');
    }
    $shared=ws13_source('lib/daily_record_revision_http_transport.php');
    $save=ws13_source('lib/daily_record_revision_save_http.php');
    $delete=ws13_source('lib/daily_record_revision_delete_http.php');
    ws13_assert(
        str_contains($save,"require_once __DIR__ . '/daily_record_revision_http_transport.php'") &&
        str_contains($delete,"require_once __DIR__ . '/daily_record_revision_http_transport.php'") &&
        str_contains($delete,'return daily_record_revision_http_is_ajax($server);') &&
        str_contains($save,'daily_record_revision_http_is_ajax($server)'),
        'SAVE and Ruminant DELETE share one AJAX predicate'
    );
    ws13_assert(
        substr_count($shared, "header('Content-Type: application/json")===1 &&
        str_contains($shared,"header('Cache-Control: no-store')") &&
        str_contains($save,'daily_record_revision_http_send_json($status, ['),
        'SAVE serializes no-store JSON through centralized transport'
    );
    ws13_assert(
        str_contains($save,"409, 'revision_client_required'") &&
        str_contains($save,"419, 'invalid_csrf'") &&
        str_contains($save,"422, 'invalid_record'") &&
        str_contains($save,'$_SESSION[\'error\'] = $message;') &&
        str_contains($save,"header('Location: ' . $legacyRedirect);"),
        'malformed client, CSRF, validation and original legacy redirect are distinct'
    );
    foreach([
        'layer'=>['poultry/layers_daily_record.php',6],
        'broiler'=>['poultry/broiler_daily_record.php',5],
        'ruminant'=>['ruminant/ruminant_daily_record.php',6]
    ] as $type=>[$path,$failureCount]) {
        $page=ws13_source($path);
        $begin=strpos($page,'// Handle form submission');
        $gateAt=strpos($page,'if (daily_record_revision_cutover_enabled()) {',$begin===false?0:$begin);
        ws13_assert($begin!==false && $gateAt!==false && $gateAt>$begin,
            $type.' save section has a guarded B12 dispatcher');
        $early=substr($page,$begin,$gateAt-$begin);
        $ajaxAt=strpos($early,'daily_record_revision_save_http_require_ajax($_SERVER);');
        $csrfAt=strpos($early,'verify_csrf_token(');
        $validationAt=strpos($early,'daily_record_revision_save_http_validation_failure(');
        ws13_assert(
            str_contains($early,"require_once __DIR__ . '/../lib/daily_record_revision_save_http.php'") &&
            $ajaxAt!==false && $csrfAt!==false && $validationAt!==false &&
            $ajaxAt<$csrfAt && $csrfAt<$validationAt &&
            str_contains($early,'daily_record_revision_save_http_csrf_failure();'),
            $type.' hard-gated AJAX preflight occurs before CSRF and business validation'
        );
        ws13_assert(
            substr_count($early,'daily_record_revision_save_http_validation_failure(')===$failureCount &&
            !str_contains($early,"header('Location:") &&
            !str_contains($early,"$_SESSION['error'] = ") &&
            !str_contains($early,"exit('Invalid request token."),
            $type.' every pre-dispatch validation failure uses shared JSON/legacy policy'
        );
        ws13_assert(
            str_contains($page,"daily_record_revision_route_dispatch_json(") &&
            str_contains($page,"daily_record_revision_page_future_form_lock()"),
            $type.' future CAS dispatch and WS11 initial form lock remain'
        );
    }
    $adapter=ws13_source('lib/daily_record_revision_route_adapter.php');
    $start=strpos($adapter,'function daily_record_revision_route_dispatch_json(');
    $status=strpos($adapter,'$status = 200;',$start===false?0:$start);
    $off=strpos($adapter,'if (!daily_record_revision_cutover_enabled()) {',$start===false?0:$start);
    $xhr=strpos($adapter,'daily_record_revision_save_http_require_ajax($_SERVER);',$start===false?0:$start);
    $transaction=strpos($adapter,'daily_record_revision_route_transaction(',$start===false?0:$start);
    ws13_assert(
        $start!==false && $off!==false && $xhr!==false && $status!==false &&
        $transaction!==false && $start<$off && $off<$xhr &&
        $xhr<$status && $status<$transaction,
        'shared JSON dispatcher independently refuses gate-OFF and native POST before transaction'
    );
    ws13_assert(
        str_contains($adapter,"503, 'revision_unavailable'") &&
        str_contains($adapter,"'code'=>'record_conflict'") &&
        str_contains($adapter,"'code'=>'invalid_record'") &&
        str_contains($adapter,"'code'=>'storage_unavailable'"),
        'existing shared error/409/422/503 contracts retained'
    );
    echo "B12_WS13_SAVE_BOUNDARY_SOURCE=PASS\nPASS_COUNT={$count}\n";
} catch(Throwable $e) {
    fwrite(STDERR,"B12_WS13_SAVE_BOUNDARY_SOURCE=FAIL ".$e->getMessage()."\n");
    exit(1);
}
