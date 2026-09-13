<?php
/**
 * Billing checkout failure PRG regression contract.
 *
 * Normal Farm Admin failures:
 * POST checkout -> shared session notification -> HTTP 303 -> Billing Account GET.
 *
 * Restricted recovery failures retain their dedicated raw fallback.
 */

$root = dirname(__DIR__);

$routePath =
    $root . '/billing/checkout.php';

$accountPath =
    $root . '/billing/account.php';

$navbarPath =
    $root . '/navbar.php';

$notificationsPath =
    $root . '/includes/notifications.php';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$message}\n";
};

foreach (
    [
        $routePath,
        $accountPath,
        $navbarPath,
        $notificationsPath,
    ] as $path
) {
    $check(
        is_file($path),
        basename($path) . ' exists'
    );
}

$route = is_file($routePath)
    ? file_get_contents($routePath)
    : '';

$account = is_file($accountPath)
    ? file_get_contents($accountPath)
    : '';

$navbar = is_file($navbarPath)
    ? file_get_contents($navbarPath)
    : '';

$notifications = is_file($notificationsPath)
    ? file_get_contents($notificationsPath)
    : '';

$notificationCompact =
    preg_replace(
        '/\s+/',
        '',
        (string)$notifications
    );

$check(
    strpos(
        $notifications,
        'function redirectWithNotification('
    ) !== false,
    'shared notification PRG helper exists'
);

$check(
    strpos(
        $notificationCompact,
        "['error','success','warning','info']"
    ) !== false,
    'shared helper restricts notification types'
);

$check(
    strpos(
        $notifications,
        "str_starts_with(\$internalPath, '/')"
    ) !== false
    && strpos(
        $notifications,
        "str_starts_with(\$internalPath, '//')"
    ) !== false
    && strpos(
        $notifications,
        "preg_match('/[\\r\\n]/', \$internalPath)"
    ) !== false,
    'shared helper accepts only safe internal application paths'
);

$check(
    strpos(
        $notifications,
        'session_status() !== PHP_SESSION_ACTIVE'
    ) !== false
    && strpos(
        $notifications,
        '$_SESSION[$type] = $message;'
    ) !== false,
    'shared helper requires active session and stores the flash message'
);

$check(
    strpos(
        $notificationCompact,
        "header('Location:'.BASE_URL.\$internalPath,true,303)"
    ) !== false,
    'shared helper always performs HTTP 303 Post/Redirect/Get'
);

$check(
    strpos(
        $route,
        "require_once dirname(__DIR__) . '/includes/notifications.php';"
    ) !== false,
    'checkout explicitly loads the shared notification helper'
);

$check(
    strpos(
        $route,
        '$redirectNormalBillingFailure'
    ) === false,
    'checkout no longer owns a route-local notification redirect helper'
);

$check(
    substr_count(
        $route,
        'redirectWithNotification('
    ) >= 3,
    'checkout delegates normal failure redirects to the shared helper'
);

$check(
    strpos(
        $route,
        'billing_tenant_actor_is_recovery('
    ) !== false
    && strpos(
        $route,
        "http_response_code(422);"
    ) !== false
    && strpos(
        $route,
        "http_response_code(503);"
    ) !== false,
    'restricted recovery retains existing raw HTTP fallbacks'
);

$check(
    strpos(
        $route,
        'Payment checkout is currently unavailable. Please try again later.'
    ) !== false
    && strpos(
        $route,
        'Subscription checkout could not be validated. Please refresh Billing & Subscription and try again.'
    ) !== false,
    'tenant-facing checkout failure messages remain explicit'
);

$check(
    strpos(
        $account,
        "include dirname(__DIR__) . '/navbar.php';"
    ) !== false
    && strpos(
        $navbar,
        'renderSessionNotifications();'
    ) !== false
    && strpos(
        $notifications,
        "['error', \$_SESSION['error'] ?? null]"
    ) !== false,
    'Billing Account consumes the same shared styled notification system'
);

$check(
    strpos(
        $route,
        "billing_payment_attempt_create("
    ) === false,
    'checkout route still does not directly create payment attempts'
);

echo "Checks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    exit(1);
}

echo "PASS: billing checkout failures use the shared styled notification PRG helper without changing recovery fallback behavior.\n";
?>
