<?php
$root = dirname(__DIR__);
$helpers = @file_get_contents($root . '/api/api_helpers.php') ?: '';
$sign = @file_get_contents($root . '/sign.php') ?: '';
$login = @file_get_contents($root . '/login.php') ?: '';

$checks = [];
$add = static function (string $label, bool $ok) use (&$checks): void {
    $checks[] = [$label, $ok];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
};

$add('Guest limiter has shared server-side bucket path helper', str_contains($helpers, 'function rate_limit_guest_bucket_path'));
$add('Guest limiter stores buckets outside PHP session storage', str_contains($helpers, "sys_get_temp_dir()") && str_contains($helpers, "'renee-rate-limit'"));
$add('Guest limiter serializes shared bucket updates with flock', str_contains($helpers, 'flock($handle, LOCK_EX)'));
$add('Guest limiter identity is keyed by limiter key and remote IP', str_contains($helpers, '$guestIdentity=$key.\'|guest|\'.$ip'));
$add('Guest limiter does not key anonymous throttling by user agent', !str_contains($helpers, "HTTP_USER_AGENT"));
$add('Authenticated API rate limiting remains session-scoped', str_contains($helpers, 'if ($userId > 0)') && str_contains($helpers, '$_SESSION[$bucketKey]'));
$add('Guest limiter has safe session fallback when shared storage is unavailable', str_contains($helpers, 'if ($count === null)'));
$add('Rate limit still returns HTTP 429 through shared JSON response', str_contains($helpers, "send_json(['success'=>false,'error'=>'Too many requests. Please try again shortly.'],429)"));
$add('Normal sign-in retains 12 per 300 second limit', str_contains($sign, "require_rate_limit('login_attempt', 12, 300)"));
$add('Subscription recovery retains independent 8 per 300 second limit', str_contains($login, "require_rate_limit('subscription_recovery_attempt', 8, 300)"));

$failures = count(array_filter($checks, static fn(array $check): bool => !$check[1]));
echo PHP_EOL . count($checks) . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
