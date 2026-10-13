<?php
declare(strict_types=1);
/**
 * B12 source-only comparison. Models observed config.php expressions without
 * loading bootstrap, connecting to a database, or mutating real sessions.
 * Differences are recorded, never auto-corrected.
 */
require_once dirname(__DIR__, 2) . '/includes/b12_auth_core_decisions.php';
$pass=0; $differences=[];
function check(bool $ok,string $label):void {global $pass;if(!$ok)throw new RuntimeException($label);$pass++;}
function legacyTimeout(array $s,int $now,int $limit):?string {
    if(isset($s['user_id'],$s['LAST_ACTIVITY']) && $now-$s['LAST_ACTIVITY']>$limit)return 'session_expired';
    return null;
}
function legacyAccount(array|false $r):array {
    if($r===false)return ['event'=>'invalid_account','user_type'=>null,'full_name'=>null];
    $owner=(int)($r['is_platform_owner']??0)===1;
    return ['event'=>!$owner&&in_array($r['subscription_status']??null,['suspended','cancelled'],true)?'invalid_account':null,
      'user_type'=>$r['user_type']??null,'full_name'=>$r['full_name']??null];
}
foreach ([0,899,900,901,1200] as $elapsed) {
    $session=['user_id'=>8,'LAST_ACTIVITY'=>1000];
    check(legacyTimeout($session,1000+$elapsed,900)===b12_auth_core_session_timeout_event($session,1000+$elapsed,900),
      "elapsed $elapsed parity");
}
foreach ([[],['user_id'=>8],['LAST_ACTIVITY'=>1000]] as $s) {
    check(legacyTimeout($s,2000,900)===b12_auth_core_session_timeout_event($s,2000,900),'missing key parity');
}
foreach ([0,1,4,-1] as $farm) {
    check(((int)$farm<1?'farm_invalid':null)===b12_auth_core_farm_event($farm),'farm parity');
}
foreach (['active','trial','past_due','suspended','cancelled','unknown'] as $status) {
    foreach ([0,1] as $owner) {
        $a=['user_type'=>'farm_admin','full_name'=>'A','subscription_status'=>$status,'is_platform_owner'=>$owner];
        check(legacyAccount($a)===b12_auth_core_account_outcome($a),"$status/$owner parity");
    }
}
check(legacyAccount(false)===b12_auth_core_account_outcome(false),'missing account parity');
// Compare known edge inputs without hiding differences.
foreach ([['user_id'=>8,'LAST_ACTIVITY'=>2001],['user_id'=>8,'LAST_ACTIVITY'=>'1000'],['user_id'=>8,'LAST_ACTIVITY'=>'bad']] as $s) {
    try {$old=legacyTimeout($s,2000,900);}catch(Throwable $e){$old=get_class($e);}
    try {$new=b12_auth_core_session_timeout_event($s,2000,900);}catch(Throwable $e){$new=get_class($e);}
    if($old!==$new)$differences[]=['case'=>'timestamp','last'=>$s['LAST_ACTIVITY'],'legacy'=>$old,'proposed'=>$new];
}
$malformed=['user_type'=>'farm_admin','full_name'=>'A','subscription_status'=>'active'];
try {$old=legacyAccount($malformed);}catch(Throwable $e){$old=get_class($e);}
try {$new=b12_auth_core_account_outcome($malformed);}catch(Throwable $e){$new=get_class($e);}
if($old!==$new)$differences[]=['case'=>'missing projected owner flag','legacy'=>$old,'proposed'=>$new];
check(count($differences)>=2,'known semantic differences detected (not silently reconciled)');
echo "B12_CONFIG_AUTH_EQUIVALENCE_PURE=PASS\n";
echo "MATCH_ASSERTIONS=$pass\n";
echo "DIFFERENCES=".json_encode($differences,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
