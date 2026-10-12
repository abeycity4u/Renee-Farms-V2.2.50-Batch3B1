<?php
declare(strict_types=1);
/** B12 offline callback-order test; no app bootstrap, DB, session, or headers. */
require_once dirname(__DIR__, 2) . '/includes/b12_auth_early_bootstrap_seam.php';
$passed = 0;
function ok(bool $condition, string $label): void {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $passed++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function reject(callable $callback, string $label, string $expectedClass): void {
    try { $callback(); } catch (Throwable $e) {
        ok($e instanceof $expectedClass, $label);
        return;
    }
    ok(false, $label);
}
try {
    $events = [
        'unauthenticated' => ['config.require_login', 401, 'authentication_required'],
        'session_expired' => ['config.session_timeout', 401, 'session_expired'],
        'invalid_account' => ['config.require_login', 401, 'authentication_required'],
        'farm_invalid' => ['config.require_farm', 401, 'authentication_required'],
        'subscription_recovery' => ['subscription_lifecycle.request_guard', 403, 'subscription_recovery_required'],
        'subscription_expired' => ['subscription_lifecycle.request_guard', 403, 'subscription_access_required'],
        'entitlement_denied' => ['farm_entitlement_runtime.module_guard', 403, 'forbidden'],
        'permission_denied' => ['daily_record_api.permission_guard', 403, 'forbidden'],
    ];
    $jsonPath='/api/daily_record_revision_read.php';
    foreach ($events as $event=>[$site, $http, $code]) {
        ok(b12_auth_early_bootstrap_event_site($event)===$site, "$event bound to existing canonical site");
        $steps=[];
        $result=b12_auth_early_bootstrap_denial(
            $jsonPath, 'GET', $event, $site,
            static function() use (&$steps) { $steps[]='complete_original_side_effects'; return true; },
            static function(array $plan) use (&$steps) { $steps[]='emit_safe_response'; return $plan; }
        );
        ok($steps===['complete_original_side_effects','emit_safe_response'], "$event completes canonical denial before transport");
        ok($result['transport']==='json' && $result['http_status']===$http,
            "$event API emits correct JSON status");
        ok($result['body']['code']===$code && !isset($result['redirect_path']),
            "$event does not redirect or expose identity");
        $htmlSteps=[];
        $html=b12_auth_early_bootstrap_denial(
            '/poultry/layers_daily_record.php', 'GET', $event, $site,
            static function() use (&$htmlSteps) { $htmlSteps[]='canonical'; return true; },
            static function(array $plan) use (&$htmlSteps) { $htmlSteps[]='transport'; return $plan; }
        );
        ok($htmlSteps===['canonical','transport'] && $html['transport']==='redirect',
            "$event legacy HTML response remains redirect");
        $unrelated=b12_auth_response_plan('/api/get_record.php', $event);
        ok($unrelated['transport']==='redirect', "$event never alters legacy API route");
    }
    foreach ($events as $event=>[$site]) {
        $steps=[];
        reject(static fn()=>b12_auth_early_bootstrap_denial(
            $jsonPath, 'GET', $event, 'some.other.guard',
            static function() use (&$steps) { $steps[]='side_effect'; return true; },
            static function(array $plan) use (&$steps) { $steps[]='emit'; return $plan; }
        ), "$event rejects wrong canonical guard", LogicException::class);
        ok($steps===[], "$event mismatch triggers no side effects and no output");
    }
    $steps=new ArrayObject();
    reject(static fn()=>b12_auth_early_bootstrap_denial(
        $jsonPath, 'GET', 'session_expired', 'config.session_timeout',
        static function() use (&$steps) { $steps[]='canonical'; return false; },
        static function(array $plan) use (&$steps) { $steps[]='emit'; return $plan; }
    ), 'incomplete security teardown denies response emission', LogicException::class);
    ok((array)$steps===['canonical'], 'incomplete teardown emits no response');
    $steps=new ArrayObject();
    reject(static fn()=>b12_auth_early_bootstrap_denial(
        $jsonPath, 'GET', 'subscription_recovery', 'subscription_lifecycle.request_guard',
        static function() use (&$steps) { $steps[]='start_recovery'; throw new RuntimeException('recovery seeding failed'); },
        static function(array $plan) use (&$steps) { $steps[]='emit'; return $plan; }
    ), 'failed recovery transition propagates rather than responding', RuntimeException::class);
    ok((array)$steps===['start_recovery'], 'failed recovery emits nothing');
    $steps=new ArrayObject();
    reject(static fn()=>b12_auth_early_bootstrap_denial(
        $jsonPath, 'GET/POST', 'unauthenticated', 'config.require_login',
        static function() use (&$steps) { $steps[]='canonical'; return true; },
        static function(array $plan) use (&$steps) { $steps[]='emit'; return $plan; }
    ), 'invalid method detected before account/session side effects', InvalidArgumentException::class);
    ok((array)$steps===[], 'invalid method runs no callback');
    $steps=new ArrayObject();
    reject(static fn()=>b12_auth_early_bootstrap_denial(
        $jsonPath, 'GET', 'unregistered', 'some.guard',
        static function() use (&$steps) { $steps[]='canonical'; return true; },
        static function(array $plan) use (&$steps) { $steps[]='emit'; return $plan; }
    ), 'unknown denial event fails before callbacks', InvalidArgumentException::class);
    ok((array)$steps===[], 'unknown event has no side effects');
    foreach (['entitlement_denied','permission_denied'] as $event) {
        $site=b12_auth_early_bootstrap_event_site($event);
        $result=b12_auth_early_bootstrap_denial(
            '/management/production_cycles.php', 'POST', $event, $site,
            static fn()=>true, static fn($p)=>$p
        );
        ok($result===['transport'=>'html_forbidden','http_status'=>403,'body'=>null],
            "$event HTML non-GET response remains forbidden");
    }
    echo 'B12_EARLY_BOOTSTRAP_SEAM_PURE=PASS'.PHP_EOL;
    echo 'PASS_COUNT='.$passed.PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,'B12_EARLY_BOOTSTRAP_SEAM_PURE=FAIL '.get_class($e).' '.$e->getMessage().PHP_EOL);
    exit(1);
}
