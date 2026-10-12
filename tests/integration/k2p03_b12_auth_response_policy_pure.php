<?php
declare(strict_types=1);
/** Source-only response-policy tests: no bootstrap, session, DB, network, or HTTP. */
require_once dirname(__DIR__, 2) . '/includes/b12_auth_response_policy.php';
$passed = 0;
function check(bool $condition, string $name): void {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: '.$name);
    ++$passed;
    echo 'PASS: '.$name.PHP_EOL;
}
function mustReject(callable $f, string $name): void {
    try { $f(); } catch (InvalidArgumentException $e) { check(true, $name); return; }
    check(false, $name);
}
try {
    $allowed = [
        '/api/daily_record_revision_read.php',
        '/app/api/daily_record_revision_read.php',
        '/tenant_demo/v2/api/daily_record_revision_read.php',
    ];
    foreach ($allowed as $path) {
        check(b12_auth_response_is_json_route($path), 'exact target in safe app root');
    }
    $blocked = [
        '', 'api/daily_record_revision_read.php',
        '/api/get_record.php', '/api/daily_record_revision_read.php.bak',
        '/api/daily_record_revision_read.php?json=1',
        '/api/daily_record_revision_read.php#fragment',
        '/api//daily_record_revision_read.php',
        '/app/../api/daily_record_revision_read.php',
        '/app/./api/daily_record_revision_read.php',
        '/app%2f/api/daily_record_revision_read.php',
        '/app\\api\\daily_record_revision_read.php',
        '/api/daily_record_revision_read.php/extra',
        '/a.b/api/daily_record_revision_read.php',
    ];
    foreach ($blocked as $path) {
        check(!b12_auth_response_is_json_route($path), 'unapproved or malformed script path remains HTML');
    }
    $events = [
        'unauthenticated' => [401, 'authentication_required', '/login.php', 302],
        'session_expired' => [401, 'session_expired', '/login.php?timeout=1', 302],
        'invalid_account' => [401, 'authentication_required', '/login.php', 302],
        'farm_invalid' => [401, 'authentication_required', '/sign.php', 302],
        'subscription_recovery' => [403, 'subscription_recovery_required', '/billing/recover.php', 303],
        'subscription_expired' => [403, 'subscription_access_required', '/sign.php', 303],
        'entitlement_denied' => [403, 'forbidden', '/no_access.php', 302],
        'permission_denied' => [403, 'forbidden', '/no_access.php', 302],
    ];
    foreach ($events as $event => [$status, $code, $path, $redirect]) {
        $json = b12_auth_response_plan('/api/daily_record_revision_read.php', $event);
        check($json['transport'] === 'json' && $json['http_status'] === $status,
            "$event API receives JSON with expected status");
        check($json['body']['success'] === false && $json['body']['code'] === $code,
            "$event API receives closed safe code");
        check(!array_key_exists('redirect_path', $json) && !array_key_exists('farm_id', $json['body']),
            "$event API has no redirect or tenant leakage");
        $html = b12_auth_response_plan('/poultry/layers_daily_record.php', $event);
        check($html === ['transport'=>'redirect', 'http_status'=>$redirect, 'redirect_path'=>$path],
            "$event HTML redirect preserved");
        $nestedHtml = b12_auth_response_plan('/app/api/get_record.php', $event);
        check($nestedHtml['transport'] === 'redirect', "$event unscoped legacy API unchanged");
    }
    foreach (['permission_denied', 'entitlement_denied'] as $event) {
        $html = b12_auth_response_plan('/ruminant/ruminant_daily_record.php', $event, 'POST');
        check($html === ['transport'=>'html_forbidden', 'http_status'=>403, 'body'=>null],
            "$event non-GET HTML remains denied without redirect");
        $json = b12_auth_response_plan('/api/daily_record_revision_read.php', $event, 'POST');
        check($json['transport'] === 'json' && $json['http_status'] === 403,
            "$event future API never falls back to HTML on POST");
    }
    mustReject(fn()=>b12_auth_response_plan('/api/daily_record_revision_read.php', 'untrusted_event'),
        'unregistered denial reason rejected');
    foreach (['get', '', 'GET/POST', 'GET\n'] as $method) {
        mustReject(fn()=>b12_auth_response_plan('/api/daily_record_revision_read.php', 'unauthenticated', $method),
            'malformed or noncanonical method rejected');
    }
    check(count($events) === 8, 'every canonical event explicitly enumerated');
    echo 'B12_AUTH_RESPONSE_POLICY_PURE=PASS'.PHP_EOL;
    echo 'PASS_COUNT='.$passed.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'B12_AUTH_RESPONSE_POLICY_PURE=FAIL '.get_class($error).' '.$error->getMessage().PHP_EOL);
    exit(1);
}
