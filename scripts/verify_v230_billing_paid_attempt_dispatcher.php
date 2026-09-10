<?php
/**
 * Static V2.3 paid-attempt purpose-dispatch contract verifier.
 *
 * Database-free, provider-free and mutation-free.
 */

$root = dirname(__DIR__);

$paths = [
    'dispatcher' =>
        $root . '/includes/billing_paid_attempt_dispatcher.php',
    'return' =>
        $root . '/billing/return.php',
    'webhook' =>
        $root . '/billing/webhook.php',
];

$source = [];

foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            'FAIL: missing ' . $path . PHP_EOL
        );
        exit(1);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        fwrite(
            STDERR,
            'FAIL: unable to read ' . $path . PHP_EOL
        );
        exit(1);
    }

    $source[$key] = $content;
}

$dispatcher = $source['dispatcher'];
$return = $source['return'];
$webhook = $source['webhook'];

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }

    $failures++;
    echo 'FAIL: ' . $label . PHP_EOL;
};

$check(
    strpos(
        $dispatcher,
        "billing_payment_foundation.php"
    ) !== false
    && strpos(
        $dispatcher,
        "billing_payment_audit_state.php"
    ) !== false
    && strpos(
        $dispatcher,
        "billing_subscription_application.php"
    ) !== false,
    'dispatcher reuses the canonical payment, audit and subscription services'
);

$check(
    strpos(
        $dispatcher,
        'function billing_paid_attempt_dispatch('
    ) !== false,
    'dispatcher exposes one centralized paid-attempt application entry point'
);

$check(
    preg_match(
        '/\$pdo\s*->\s*inTransaction\s*\(\s*\)/',
        $dispatcher
    ) === 1
    && strpos(
        $dispatcher,
        'requires an active database transaction'
    ) !== false,
    'dispatcher requires the caller transaction used by verified-payment routes'
);

$check(
    strpos(
        $dispatcher,
        'billing_audit_attempt_by_id('
    ) !== false
    && preg_match(
        '/billing_audit_attempt_by_id\s*\('
        . '[\s\S]*?\$attemptId\s*,\s*true\s*\)/',
        $dispatcher
    ) === 1,
    'dispatcher re-locks the authoritative billing attempt by id'
);

$check(
    preg_match(
        '/!==\s*[\'"]paid[\'"]/',
        $dispatcher
    ) === 1
    && strpos(
        $dispatcher,
        'provider-verified paid billing attempt'
    ) !== false,
    'dispatcher refuses attempts that are not already verified paid'
);

$check(
    strpos(
        $dispatcher,
        'billing_payment_attempt_purpose('
    ) !== false,
    'dispatcher resolves purpose through the canonical payment-purpose contract'
);

$subscriptionPos = preg_match(
    '/\$purpose\s*===\s*[\'"]subscription[\'"]/',
    $dispatcher,
    $subscriptionMatch,
    PREG_OFFSET_CAPTURE
) === 1
    ? $subscriptionMatch[0][1]
    : false;

$subscriptionApplyPos = strpos(
    $dispatcher,
    'billing_subscription_apply_paid_attempt('
);

$check(
    $subscriptionPos !== false
    && $subscriptionApplyPos !== false
    && $subscriptionPos < $subscriptionApplyPos,
    'subscription purpose delegates only to the proven subscription application service'
);

$seatTopupPos = preg_match(
    '/\$purpose\s*===\s*[\'"]seat_topup[\'"]/',
    $dispatcher,
    $seatTopupMatch,
    PREG_OFFSET_CAPTURE
) === 1
    ? $seatTopupMatch[0][1]
    : false;

$seatTopupClosedPos = strpos(
    $dispatcher,
    'Paid seat top-up application is not enabled yet.'
);

$check(
    $seatTopupPos !== false
    && $seatTopupClosedPos !== false
    && $seatTopupPos < $seatTopupClosedPos,
    'seat_topup purpose remains explicitly fail-closed until its dedicated service exists'
);

$check(
    strpos(
        $dispatcher,
        'has no supported application service'
    ) !== false
    && strpos(
        $dispatcher,
        'unsupported application purpose'
    ) !== false,
    'unknown or malformed paid purposes fail closed'
);

$protectedDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions|'
    . 'billing_seat_change_requests)\b/i';

$check(
    preg_match(
        $protectedDml,
        $dispatcher
    ) !== 1
    && strpos(
        $dispatcher,
        'billing_provider_verify_payment('
    ) === false
    && strpos(
        $dispatcher,
        'billing_provider_initialize_checkout('
    ) === false,
    'dispatcher itself contains no provider call or direct commercial-state DML'
);

foreach ([
    'return' => $return,
    'webhook' => $webhook,
] as $routeName => $route) {
    $check(
        strpos(
            $route,
            'billing_paid_attempt_dispatcher.php'
        ) !== false
        && strpos(
            $route,
            'billing_paid_attempt_dispatch('
        ) !== false,
        $routeName
        . ' route applies verified paid attempts through the central dispatcher'
    );

    $check(
        strpos(
            $route,
            'billing_subscription_application.php'
        ) === false
        && strpos(
            $route,
            'billing_subscription_apply_paid_attempt('
        ) === false,
        $routeName
        . ' route no longer bypasses purpose dispatch into subscription application'
    );

    $auditApplyPos = strpos(
        $route,
        'billing_audit_apply_verification('
    );

    $dispatchPos = strpos(
        $route,
        'billing_paid_attempt_dispatch('
    );

    $check(
        $auditApplyPos !== false
        && $dispatchPos !== false
        && $auditApplyPos < $dispatchPos,
        $routeName
        . ' route dispatches only after authoritative provider verification is applied'
    );
}

echo PHP_EOL;
echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;

if ($failures > 0) {
    exit(1);
}

echo "V2.3 PAID-ATTEMPT PURPOSE DISPATCHER: PASSED\n";
