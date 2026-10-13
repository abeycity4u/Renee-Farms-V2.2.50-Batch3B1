<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/b12_auth_malformed_state_policy.php';
$passed=0;
function b12Check(bool $ok,string $label):void {global $passed;if(!$ok)throw new RuntimeException($label);$passed++;}
$account=['user_type'=>'poultry_manager','full_name'=>'Tester','subscription_status'=>'active','is_platform_owner'=>'0'];
foreach ([0,1,1000,2000] as $last) b12Check(b12_auth_malformed_state_event('last_activity',$last,2000)===null,'valid trusted timestamp');
foreach ([2001,-1,'-1','abc',[],null,1.5,'01','9223372036854775808'] as $last) b12Check(b12_auth_malformed_state_event('last_activity',$last,2000)==='invalid_account','malformed timestamp');
b12Check(b12_auth_malformed_state_event('account_projection',$account)===null,'valid account projection');
b12Check(b12_auth_malformed_state_event('account_projection',false)==='invalid_account','missing account');
foreach (array_keys($account) as $key) {
    $bad=$account;unset($bad[$key]);
    b12Check(b12_auth_malformed_state_event('account_projection',$bad)==='invalid_account','missing projection key');
}
foreach ([['is_platform_owner'=>2],['full_name'=>null],['user_type'=>''],['subscription_status'=>null]] as $bad) {
    b12Check(b12_auth_malformed_state_event('account_projection',array_replace($account,$bad))==='invalid_account','malformed projection');
}
b12Check(b12_auth_malformed_state_event('account_projection',array_replace($account,['subscription_status'=>'past_due']))===null,'past_due belongs to lifecycle guard');
foreach (['0','1',0,1] as $owner) b12Check(b12_auth_malformed_state_event('account_projection',array_replace($account,['is_platform_owner'=>$owner]))===null,'canonical owner flag');
foreach ([['unknown','x'],['last_activity',1]] as [$source,$value]) {
    try {b12_auth_malformed_state_event($source,$value,null);throw new RuntimeException('Expected exception');}
    catch (InvalidArgumentException $e) {b12Check(true,'invalid input fails closed');}
}
echo "B12_MALFORMED_AUTH_STATE_PURE=PASS\nPASS_COUNT=$passed\n";
