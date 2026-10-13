<?php
declare(strict_types=1);
require_once __DIR__ . '/b12_auth_core_decisions.php';
$n=0;
function pass(bool $condition,string $reason): void {
    global $n;
    if (!$condition) throw new RuntimeException('FAIL '.$reason);
    $n++;
}
function rejects(callable $f): void {
    try {$f();} catch (InvalidArgumentException $e) { pass(true,'reject'); return; }
    pass(false,'expected rejection');
}
try {
    foreach ([0,899,900] as $diff) {
        pass(b12_auth_core_session_timeout_event(['user_id'=>5,'LAST_ACTIVITY'=>1000],1000+$diff,900)===null,'not timed out');
    }
    pass(b12_auth_core_session_timeout_event(['user_id'=>5,'LAST_ACTIVITY'=>1000],1901,900)==='session_expired','timeout strict');
    pass(b12_auth_core_session_timeout_event([],1901,900)===null,'anonymous');
    pass(b12_auth_core_session_timeout_event(['user_id'=>5],1901,900)===null,'unset last');
    pass(b12_auth_core_session_timeout_event(['LAST_ACTIVITY'=>1000],1901,900)===null,'unset user');
    pass(b12_auth_core_session_timeout_event(['user_id'=>5,'LAST_ACTIVITY'=>'1000'],1901,900)==='session_expired','numeric session');
    foreach ([['user_id'=>5,'LAST_ACTIVITY'=>'garbage'],['user_id'=>5,'LAST_ACTIVITY'=>[]]] as $s) {
        rejects(fn()=>b12_auth_core_session_timeout_event($s,2000,900));
    }
    rejects(fn()=>b12_auth_core_session_timeout_event(['user_id'=>5,'LAST_ACTIVITY'=>1000],999,900));
    rejects(fn()=>b12_auth_core_session_timeout_event(['user_id'=>5,'LAST_ACTIVITY'=>1000],1200,0));
    pass(b12_auth_core_farm_event(0)==='farm_invalid','no farm');
    pass(b12_auth_core_farm_event(-3)==='farm_invalid','negative farm');
    pass(b12_auth_core_farm_event(1)===null,'farm present');
    $base=['user_type'=>'poultry_manager','full_name'=>'Ada','subscription_status'=>'active','is_platform_owner'=>0];
    pass(b12_auth_core_account_outcome(false)['event']==='invalid_account','account absent');
    foreach (['active','trial','past_due','suspended','cancelled'] as $status) {
        $a=array_replace($base,['subscription_status'=>$status]);
        $o=b12_auth_core_account_outcome($a);
        pass($o['event']===(in_array($status,['suspended','cancelled'],true)?'invalid_account':null),'status decision');
        pass($o['user_type']==='poultry_manager'&&$o['full_name']==='Ada','fresh identity');
        $owner=b12_auth_core_account_outcome(array_replace($a,['is_platform_owner'=>1]));
        pass($owner['event']===null,'owner bypass');
    }
    foreach (array_keys($base) as $key) { $a=$base; unset($a[$key]); rejects(fn()=>b12_auth_core_account_outcome($a)); }
    foreach ([['subscription_status'=>null],['is_platform_owner'=>3],['user_type'=>[]],['full_name'=>null]] as $bad) {
        rejects(fn()=>b12_auth_core_account_outcome(array_replace($base,$bad)));
    }
    echo "B12_AUTH_CORE_DECISIONS_PURE=PASS\nPASS_COUNT=$n\n";
} catch (Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
