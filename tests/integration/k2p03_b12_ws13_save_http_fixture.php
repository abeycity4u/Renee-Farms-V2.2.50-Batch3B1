<?php
declare(strict_types=1);
/**
 * WS13 single-case PHP CLI harness for real source response helpers.
 * ON uses temporary COPIES with a test gate stub in disposable /tmp.
 * Never edits the repository hard-OFF fuse or starts the application.
 *
 * Execute each case as a separate network-disabled php CLI process.
 */
$root=dirname(__DIR__,2);
$case=(string)($argv[1]??'');
$cases=[
    'off_native_ok','off_csrf','off_validation',
    'on_native_denied','on_bad_accept','on_bad_method',
    'on_ajax_ok','on_csrf','on_validation'
];
if(!in_array($case,$cases,true)){
    fwrite(STDERR,"INVALID_FIXTURE_CASE\n");
    exit(2);
}
$on=str_starts_with($case,'on_');
$dir=null;
if($on){
    $dir=sys_get_temp_dir().'/b12-ws13-'.bin2hex(random_bytes(8));
    if(!mkdir($dir,0700)||is_link($dir)){
        fwrite(STDERR,"FIXTURE_CREATION_FAILED\n");exit(2);
    }
    foreach(['daily_record_revision_http_transport.php','daily_record_revision_save_http.php'] as $name){
        if(!copy($root.'/lib/'.$name,$dir.'/'.$name)){
            fwrite(STDERR,"FIXTURE_COPY_FAILED\n");exit(2);
        }
    }
    $gate= "<?php\n".
        "declare(strict_types=1);\n".
        "function daily_record_revision_cutover_enabled():bool{return true;}\n";
    if(file_put_contents($dir.'/daily_record_revision_cutover_gate.php',$gate,LOCK_EX)!==strlen($gate)){
        fwrite(STDERR,"FIXTURE_GATE_FAILED\n");exit(2);
    }
    require $dir.'/daily_record_revision_save_http.php';
} else {
    require $root.'/lib/daily_record_revision_save_http.php';
}
$_SESSION=[];
http_response_code(200);
register_shutdown_function(static function()use($dir,$root,$on):void{
    $status=http_response_code();
    echo "\nHTTP_STATUS=".($status===false?'unset':$status)."\n";
    echo "SESSION_ERROR=".($_SESSION['error']??'none')."\n";
    echo "ORIGINAL_GATE_SHA256=".hash_file('sha256',$root.'/lib/daily_record_revision_cutover_gate.php')."\n";
    if($dir!==null && is_dir($dir)){
        foreach([
            'daily_record_revision_http_transport.php',
            'daily_record_revision_save_http.php',
            'daily_record_revision_cutover_gate.php'
        ] as $name) {
            $target=$dir.'/'.$name;
            if(is_file($target))unlink($target);
        }
        rmdir($dir);
    }
    echo "ISOLATED_FIXTURE_REMOVED=".($dir===null||!is_dir($dir)?'YES':'NO')."\n";
});
$good=['REQUEST_METHOD'=>'POST','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest',
       'HTTP_ACCEPT'=>'application/json'];
switch($case){
    case 'off_native_ok':
        daily_record_revision_save_http_require_ajax([]);
        echo "OFF_NATIVE_ALLOWED\n";
        break;
    case 'off_csrf':
    case 'on_csrf':
        daily_record_revision_save_http_csrf_failure();
        break;
    case 'off_validation':
    case 'on_validation':
        daily_record_revision_save_http_validation_failure(
            'Synthetic invalid input.', 'test-only-legacy-destination.php'
        );
        break;
    case 'on_native_denied':
        daily_record_revision_save_http_require_ajax([
            'REQUEST_METHOD'=>'POST'
        ]);
        break;
    case 'on_bad_accept':
        $bad=$good;$bad['HTTP_ACCEPT']='text/html';
        daily_record_revision_save_http_require_ajax($bad);
        break;
    case 'on_bad_method':
        $bad=$good;$bad['REQUEST_METHOD']='GET';
        daily_record_revision_save_http_require_ajax($bad);
        break;
    case 'on_ajax_ok':
        daily_record_revision_save_http_require_ajax($good);
        echo "ON_AJAX_ACCEPTED\n";
        break;
}
