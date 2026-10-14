<?php
declare(strict_types=1);
/**
 * Execute each case in a dedicated CLI process, network none /tmp only.
 * Test copies of actual response code receive a temporary ON-only gate
 * stub. The committed cutover file is neither included nor modified by
 * an ON case. No application bootstrap, SQL, HTTP server or sessions.
 */
$root=dirname(__DIR__,2);
$case=(string)($argv[1]??'');
$scenarios=[
    'off_layer_auth'=>[false,'authentication_required','/poultry/layers_daily_record.php','POST',['save_record'=>'1']],
    'on_layer_auth'=>[true,'authentication_required','/poultry/layers_daily_record.php','POST',['save_record'=>'1']],
    'on_broiler_forbidden'=>[true,'forbidden','/poultry/broiler_daily_record.php','POST',['save_record'=>'1']],
    'on_ruminant_subscription'=>[true,'subscription_access_required','/ruminant/ruminant_daily_record.php','POST',['save_record'=>'1']],
    'on_layer_conflict'=>[true,'record_conflict','/renee/poultry/layers_daily_record.php','POST',['save_record'=>'1']],
    'on_broiler_storage'=>[true,'storage_unavailable','/poultry/broiler_daily_record.php','POST',['save_record'=>'1']],
    'on_get'=>[true,'forbidden','/poultry/layers_daily_record.php','GET',['save_record'=>'1']],
    'on_delete'=>[true,'forbidden','/ruminant/ruminant_daily_record.php','POST',['delete_record'=>'1']],
    'on_unrelated'=>[true,'forbidden','/management/expenses.php','POST',['save_record'=>'1']],
    'on_missing_trigger'=>[true,'forbidden','/poultry/layers_daily_record.php','POST',[]],
    'on_path_lookalike'=>[true,'forbidden','/poultry/layers_daily_record.php/extra','POST',['save_record'=>'1']],
];
if(!isset($scenarios[$case])) {
    fwrite(STDERR,"UNKNOWN_FIXTURE_CASE\n");
    exit(2);
}
[$on,$reason,$path,$method,$post]=$scenarios[$case];
$dir=null;
if($on) {
    $dir=sys_get_temp_dir().'/b12-ws15-'.bin2hex(random_bytes(10));
    if(!mkdir($dir,0700)||is_link($dir)){
        fwrite(STDERR,"TEMP_SETUP_FAILED\n");exit(2);
    }
    foreach([
        'daily_record_revision_http_transport.php',
        'daily_record_revision_preroute_http.php'
    ] as $name) {
        if(!copy($root.'/lib/'.$name,$dir.'/'.$name)) {
            fwrite(STDERR,"TEST_COPY_FAILED\n");exit(2);
        }
    }
    $shim="<?php\ndeclare(strict_types=1);\n".
        "function daily_record_revision_cutover_enabled():bool{return true;}\n";
    if(file_put_contents($dir.'/daily_record_revision_cutover_gate.php',$shim,LOCK_EX)!==strlen($shim)){
        fwrite(STDERR,"SHIM_WRITE_FAILED\n");exit(2);
    }
    require $dir.'/daily_record_revision_preroute_http.php';
} else {
    require $root.'/lib/daily_record_revision_preroute_http.php';
}
http_response_code(200);
register_shutdown_function(static function()use($root,$dir):void{
    echo "\nHTTP_STATUS=".http_response_code()."\n";
    echo "ORIGINAL_GATE_SHA256=".
        hash_file('sha256',$root.'/lib/daily_record_revision_cutover_gate.php')."\n";
    if($dir!==null&&is_dir($dir)){
        foreach([
            'daily_record_revision_http_transport.php',
            'daily_record_revision_preroute_http.php',
            'daily_record_revision_cutover_gate.php'
        ] as $name){
            $file=$dir.'/'.$name;
            if(is_file($file))unlink($file);
        }
        rmdir($dir);
    }
    echo "TEMP_FIXTURE_REMOVED=".($dir===null||!is_dir($dir)?'YES':'NO')."\n";
});
$server=['SCRIPT_NAME'=>$path,'REQUEST_METHOD'=>$method,
    'HTTP_ACCEPT'=>'application/json','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'];
daily_record_revision_preroute_maybe_send($reason,$server,$post);
echo "LEGACY_FALLTHROUGH\n";
