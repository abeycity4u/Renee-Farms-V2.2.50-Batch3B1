<?php
/**
 * V2.3 Gap D Phase 2 verifier.
 *
 * Proves forward, exactly-once capture of provider-verified
 * post-application refunds without entitlement reversal.
 *
 * Static/database-free/provider-network-free.
 */

$root = dirname(__DIR__);

$servicePath =
    $root . '/includes/billing_refund_resolution.php';

$returnPath =
    $root . '/billing/return.php';

$webhookPath =
    $root . '/billing/webhook.php';

foreach ([
    $servicePath,
    $returnPath,
    $webhookPath,
] as $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            "FAIL: required Phase 2 source is missing.\n"
        );
        exit(1);
    }
}

require_once $servicePath;

$service =
    (string)file_get_contents($servicePath);

$return =
    (string)file_get_contents($returnPath);

$webhook =
    (string)file_get_contents($webhookPath);

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    function_exists(
        'billing_refund_resolution_capture_verified'
    ),
    'shared verified-refund capture function exists'
);

$captureStart = strpos(
    $service,
    'function billing_refund_resolution_capture_verified'
);

$capture =
    $captureStart === false
        ? ''
        : substr($service, $captureStart);

$check(
    strpos(
        $capture,
        'Refund-resolution capture requires an active database transaction.'
    ) !== false,
    'refund capture requires caller transaction'
);

$paymentLock = strpos(
    $capture,
    'billing_audit_attempt_by_id'
);

$resolutionLock = strpos(
    $capture,
    'billing_refund_resolution_by_payment'
);

$seatLock = strpos(
    $capture,
    'billing_seat_change_request_by_payment'
);

$check(
    $paymentLock !== false
    && $resolutionLock !== false
    && $seatLock !== false
    && $paymentLock < $resolutionLock
    && $resolutionLock < $seatLock,
    'capture preserves payment-attempt then refund-resolution then seat-request order'
);

$check(
    strpos(
        $capture,
        "'payment_not_refunded'"
    ) !== false,
    'non-refunded payment is a capture no-op'
);

$check(
    substr_count(
        $capture,
        "'refund_not_post_application'"
    ) >= 2,
    'unapplied subscription and seat refunds require no commercial review row'
);

$check(
    strpos(
        $capture,
        'applied_subscription_record_id'
    ) !== false
    && strpos(
        $capture,
        'FROM subscriptions'
    ) !== false,
    'subscription refund capture binds exact applied subscription lineage'
);

$check(
    strpos(
        $capture,
        "\$requestState['status'] !== 'applied'"
    ) !== false
    && strpos(
        $capture,
        'seat_change_request_id'
    ) !== false,
    'seat-top-up refund capture requires already-applied durable seat request'
);

$check(
    strpos(
        $capture,
        "'already_captured'"
    ) !== false
    && strpos(
        $capture,
        'billing_refund_resolution_by_payment'
    ) !== false,
    'repeat capture reuses existing resolution idempotently'
);

$check(
    strpos(
        $capture,
        'INSERT INTO billing_refund_resolutions'
    ) !== false
    && strpos(
        $capture,
        "'pending_review'"
    ) !== false,
    'post-application refund creates pending-review resolution'
);

$check(
    strpos(
        $capture,
        "'post_application_refund'"
    ) !== false,
    'new capture reports explicit post-application refund result'
);

$protectedDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions|'
    . 'billing_payment_attempts|'
    . 'billing_seat_change_requests)\b/i';

$check(
    !preg_match(
        $protectedDml,
        $capture
    ),
    'capture performs no payment or entitlement mutation'
);

$providerOps =
    '/\bcurl_(?:init|exec)|'
    . 'billing_provider_(?:initialize|verify|charge)/i';

$check(
    !preg_match(
        $providerOps,
        $capture
    ),
    'capture performs no provider or network call'
);

foreach ([
    'return' => $return,
    'webhook' => $webhook,
] as $label => $route) {
    $check(
        strpos(
            $route,
            "/includes/billing_refund_resolution.php"
        ) !== false,
        "{$label} route loads shared refund-resolution service"
    );

    $audit = strpos(
        $route,
        'billing_audit_apply_verification'
    );

    $refundGuard = strpos(
        $route,
        "=== 'refunded')"
    );

    $captureCall = strpos(
        $route,
        'billing_refund_resolution_capture_verified'
    );

    $reconcile = strpos(
        $route,
        'billing_seat_change_reconcile_terminal_payment'
    );

    $paid = strpos(
        $route,
        'billing_paid_attempt_dispatch($pdo'
    );

    $commit = strpos(
        $route,
        '$pdo->commit()'
    );

    $check(
        $audit !== false
        && $refundGuard !== false
        && $captureCall !== false
        && $reconcile !== false
        && $paid !== false
        && $commit !== false
        && $audit < $refundGuard
        && $refundGuard < $captureCall
        && $captureCall < $reconcile
        && $reconcile < $paid
        && $paid < $commit,
        "{$label} transaction preserves verified refund capture lock order"
    );

    $check(
        substr_count(
            $route,
            'billing_refund_resolution_capture_verified'
        ) === 1,
        "{$label} route has exactly one refund capture call"
    );
}

$check(
    strpos(
        $service,
        'preserve_entitlement'
    ) !== false
    && strpos(
        $service,
        'reverse_entitlement'
    ) !== false,
    'resolution actions remain explicit policy choices rather than automatic refund effects'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 REFUND RESOLUTION CAPTURE: FAILED\n";
    exit(1);
}

echo "V2.3 REFUND RESOLUTION CAPTURE: PASSED\n";
