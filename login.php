<?php
/**
 * Canonical sign-in entry point with a restricted subscription-recovery bridge.
 * Normal sign-in remains owned by sign.php; only suspended/cancelled Farm Admin
 * credentials are intercepted into the billing-only recovery session.
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/api/api_helpers.php';
require_once __DIR__ . '/includes/subscription_recovery.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
    && (($_POST['account_type'] ?? 'farm') === 'farm')) {
    // This bridge intercepts the same credential POST that would otherwise
    // fall through to sign.php. Validate the shared CSRF token before any
    // account lookup or password verification so the special recovery path
    // cannot bypass the canonical sign-in request boundary.
    csrf_validate_request();

    $candidate = subscription_recovery_login_candidate(
        $pdo,
        (string)($_POST['farm_slug'] ?? ''),
        (string)($_POST['username'] ?? '')
    );

    if ($candidate && subscription_recovery_status_is_target((string)($candidate['subscription_status'] ?? ''))) {
        // Separate limiter prevents recovery-password verification from becoming
        // an unthrottled bypass while leaving the existing normal-login limiter intact.
        require_rate_limit('subscription_recovery_attempt', 8, 300);
        if (subscription_recovery_is_farm_admin($candidate)
            && subscription_recovery_verify_password($pdo, $candidate, (string)($_POST['password'] ?? ''))) {
            subscription_recovery_start($candidate);
            header('Location: ' . BASE_URL . '/billing/recover.php', true, 303);
            exit();
        }
    }
}

require __DIR__ . '/sign.php';
