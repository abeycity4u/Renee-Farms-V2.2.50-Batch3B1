<?php

declare(strict_types=1);

/** Source contract for the restricted subscription-recovery login bridge. */

$root = dirname(__DIR__);
$failures = 0;

$read = static function (string $relative) use ($root): string {
    $content = @file_get_contents($root . '/' . $relative);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read ' . $relative);
    }
    return $content;
};

$check = static function (bool $ok, string $label) use (&$failures): void {
    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }
    echo "FAIL: {$label}\n";
    $failures++;
};

try {
    $bridge = $read('login.php');
    $sign = $read('sign.php');
    $recovery = $read('includes/subscription_recovery.php');
} catch (Throwable $e) {
    echo "FAIL: required subscription recovery security source is unavailable.\n";
    echo "FAILURES=1\nSUBSCRIPTION_RECOVERY_LOGIN_SECURITY=FAIL\n";
    exit(1);
}

$csrfPos = strpos($bridge, 'csrf_validate_request();');
$candidatePos = strpos($bridge, 'subscription_recovery_login_candidate(');
$verifyPos = strpos($bridge, 'subscription_recovery_verify_password(');
$startPos = strpos($bridge, 'subscription_recovery_start(');

$check(
    str_contains($bridge, "require_once __DIR__ . '/init.php';")
        && str_contains($bridge, "require_once __DIR__ . '/includes/subscription_recovery.php';"),
    'recovery bridge loads canonical bootstrap and recovery service'
);
$check(
    str_contains($bridge, "['REQUEST_METHOD'] ?? 'GET')) === 'POST'")
        && str_contains($bridge, "['account_type'] ?? 'farm') === 'farm'"),
    'recovery interception is limited to farm-login POST requests'
);
$check(
    $csrfPos !== false && $candidatePos !== false && $csrfPos < $candidatePos,
    'shared CSRF validation occurs before recovery account lookup'
);
$check(
    $csrfPos !== false && $verifyPos !== false && $csrfPos < $verifyPos,
    'shared CSRF validation occurs before recovery password verification'
);
$check(
    str_contains($bridge, "require_rate_limit('subscription_recovery_attempt', 8, 300)"),
    'recovery password verification retains dedicated rate limiting'
);
$check(
    $verifyPos !== false && $startPos !== false && $verifyPos < $startPos,
    'recovery session begins only after password verification'
);
$check(
    str_contains($bridge, "require __DIR__ . '/sign.php';"),
    'non-recovery requests continue through canonical sign-in flow'
);
$check(
    str_contains($sign, 'csrf_field()') && str_contains($sign, 'csrf_validate_request();'),
    'canonical sign-in form and POST handler retain CSRF protection'
);
$check(
    str_contains($recovery, 'session_regenerate_id(true)')
        || str_contains($recovery, 'security_regenerate_session('),
    'billing-only recovery session rotates the session identifier'
);
$check(
    str_contains($recovery, "'past_due'")
        && str_contains($recovery, "'suspended'")
        && str_contains($recovery, "'cancelled'"),
    'recovery mode remains restricted to designated subscription states'
);

echo "FAILURES={$failures}\n";
if ($failures === 0) {
    echo "SUBSCRIPTION_RECOVERY_LOGIN_SECURITY=PASS\n";
    exit(0);
}
echo "SUBSCRIPTION_RECOVERY_LOGIN_SECURITY=FAIL\n";
exit(1);
