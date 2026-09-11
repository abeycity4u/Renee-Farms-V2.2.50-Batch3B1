<?php
/**
 * Focused verifier for the V2.3 seat-top-up checkout route.
 *
 * Read-only, database-free and provider-network-free.
 * Static route checks use PHP token normalization so formatting,
 * whitespace and comments do not affect contract verification.
 */

$root = dirname(__DIR__);

$routePath =
    $root . '/billing/seat_topup_checkout.php';

if (!is_file($routePath)) {
    fwrite(
        STDERR,
        "Missing seat-top-up checkout route.\n"
    );
    exit(1);
}

$route = file_get_contents($routePath);

if ($route === false) {
    fwrite(
        STDERR,
        "Unable to read seat-top-up checkout route.\n"
    );
    exit(1);
}

$routeTokens = '';

foreach (token_get_all($route) as $token) {
    if (is_array($token)) {
        if (in_array(
            $token[0],
            [
                T_WHITESPACE,
                T_COMMENT,
                T_DOC_COMMENT,
            ],
            true
        )) {
            continue;
        }

        $routeTokens .= $token[1];
        continue;
    }

    $routeTokens .= $token;
}

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

$check(
    strpos(
        $routeTokens,
        'billing_require_farm_admin_actor($pdo,false)'
    ) !== false,
    'seat-top-up checkout is restricted to the normal Farm Admin actor'
);

$check(
    strpos(
        $routeTokens,
        'require_valid_csrf_post();'
    ) !== false,
    'seat-top-up checkout is protected by the central CSRF boundary'
);

$check(
    strpos(
        $routeTokens,
        'billing_payment_foundation_ready($pdo)'
    ) !== false
    && strpos(
        $routeTokens,
        'billing_seat_change_ready($pdo)'
    ) !== false,
    'payment and durable seat-change foundations must both be ready'
);

$check(
    strpos(
        $routeTokens,
        'billing_route_normalize_seat_topup_input($_POST)'
    ) !== false
    && strpos(
        $routeTokens,
        '$_POST[\'amount\']'
    ) === false
    && strpos(
        $routeTokens,
        '$_POST[\'currency\']'
    ) === false
    && strpos(
        $routeTokens,
        '$_POST[\'farm_id\']'
    ) === false
    && strpos(
        $routeTokens,
        '$_POST[\'plan_code\']'
    ) === false
    && strpos(
        $routeTokens,
        '$_POST[\'seat_addons\']'
    ) === false,
    'route consumes only the centralized narrow browser-input contract'
);

$check(
    strpos(
        $routeTokens,
        'billing_provider_readiness_resolve_checkout('
    ) !== false
    && strpos(
        $routeTokens,
        'billing_provider_register_configured_adapters('
    ) !== false,
    'provider is resolved through centralized deployment readiness'
);

$check(
    strpos(
        $routeTokens,
        'billing_tenant_actor_farm($pdo,$actor)'
    ) !== false
    && strpos(
        $routeTokens,
        '$farm[\'contact_email\']'
    ) !== false
    && strpos(
        $routeTokens,
        'FILTER_VALIDATE_EMAIL'
    ) !== false,
    'provider customer identity comes from the authenticated tenant farm'
);

$check(
    strpos(
        $routeTokens,
        'billing_route_public_url('
    ) !== false
    && strpos(
        $routeTokens,
        '\'/billing/return.php\''
    ) !== false,
    'provider return URL is built by the centralized public-URL helper'
);

$referencePos = strpos(
    $routeTokens,
    'billing_route_provider_reference('
);

$preparePos = strpos(
    $routeTokens,
    'billing_seat_topup_prepare('
);

$providerInitPos = strpos(
    $routeTokens,
    'billing_provider_initialize_checkout('
);

$pendingPos = strpos(
    $routeTokens,
    'billing_audit_mark_pending('
);

$redirectPos = strpos(
    $routeTokens,
    'header('
);

$check(
    $referencePos !== false
    && $preparePos !== false
    && $providerInitPos !== false
    && $pendingPos !== false
    && $redirectPos !== false
    && $referencePos < $preparePos
    && $preparePos < $providerInitPos
    && $providerInitPos < $pendingPos
    && $pendingPos < $redirectPos,
    'server reference, durable initiation, provider call, pending audit and redirect are ordered safely'
);

$check(
    strpos(
        $routeTokens,
        'billing_seat_topup_prepare('
        . '$pdo,'
        . '$farmId,'
        . '$provider,'
        . '$providerReference,'
        . '$selection[\'role_code\'],'
        . '$selection[\'quantity\'],'
        . '(int)$actor[\'user_id\']'
        . ')'
    ) !== false,
    'durable initiation is bound to authenticated farm, actor and normalized role/quantity'
);

$check(
    strpos(
        $routeTokens,
        'billing_provider_initialize_checkout('
        . '$provider,'
        . '$providerReference,'
        . '$prepared[\'checkout_quote\'],'
        . '$context'
        . ')'
    ) !== false,
    'provider receives the frozen server-authoritative top-up checkout quote'
);

$attemptFailPos = strpos(
    $routeTokens,
    'billing_audit_mark_initialization_failed('
);

$requestFailPos = strpos(
    $routeTokens,
    'billing_seat_change_mark_payment_failed('
);

$providerErrorPos = strpos(
    $routeTokens,
    'catch(Throwable$providerError)'
);

$check(
    $providerErrorPos !== false
    && $attemptFailPos !== false
    && $requestFailPos !== false
    && $providerInitPos < $providerErrorPos
    && $providerErrorPos < $attemptFailPos
    && $attemptFailPos < $requestFailPos,
    'provider initialization failure closes payment audit before its durable seat request'
);

$check(
    substr_count(
        $routeTokens,
        '$pdo->beginTransaction()'
    ) === 2
    && substr_count(
        $routeTokens,
        '$pdo->commit()'
    ) === 2
    && substr_count(
        $routeTokens,
        '$pdo->rollBack()'
    ) === 2,
    'post-provider failure and success audit transitions have explicit transaction boundaries'
);

$check(
    strpos(
        $routeTokens,
        'catch(Throwable$cleanupError)'
    ) !== false
    && strpos(
        $routeTokens,
        'thrownewRuntimeException('
    ) !== false
    && strpos(
        $routeTokens,
        "'Seat-top-up checkout cleanup could not be recorded safely.'"
    ) !== false,
    'cleanup transaction failure is surfaced instead of silently suppressed'
);

$directDml =
    '/\b(?:INSERT\s+INTO|DELETE\s+FROM)\b'
    . '|\bUPDATE\s+[A-Za-z_][A-Za-z0-9_]*\b/i';

$check(
    !preg_match(
        $directDml,
        $route
    ),
    'checkout route performs no direct database DML'
);

$check(
    strpos(
        $routeTokens,
        'billing_seat_topup_apply_paid'
    ) === false
    && strpos(
        $routeTokens,
        'billing_paid_attempt_dispatch('
    ) === false
    && strpos(
        $routeTokens,
        'subscription_record_capture('
    ) === false
    && strpos(
        $routeTokens,
        'subscription_seat_save_addons('
    ) === false,
    'checkout route never applies paid entitlement or subscription state'
);

$check(
    strpos(
        $routeTokens,
        'header('
        . '\'Location: \'.'
        . '$checkout[\'checkout_url\'],'
        . 'true,303)'
    ) !== false,
    'successful initialization redirects only after safe provider state recording'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 SEAT-TOP-UP CHECKOUT ROUTE: FAILED\n";
    exit(1);
}

echo "V2.3 SEAT-TOP-UP CHECKOUT ROUTE: PASSED\n";
