<?php
/**
 * Focused verifier for V2.3 seat-top-up terminal payment reconciliation.
 *
 * Read-only, database-free and provider-network-free.
 */

$root = dirname(__DIR__);
$servicePath =
    $root . '/includes/billing_seat_change_request.php';

if (!is_file($servicePath)) {
    fwrite(STDERR, "FAIL: missing seat-change service.\n");
    exit(1);
}

require_once $servicePath;

$source = (string)file_get_contents(
    $servicePath
);

$start = strpos(
    $source,
    'function billing_seat_change_reconcile_terminal_payment('
);

$end = $start === false
    ? false
    : strpos(
        $source,
        "if (!function_exists('billing_seat_change_attempt_modules'))",
        $start
    );

$helper = $start !== false
    && $end !== false
    && $end > $start
        ? substr(
            $source,
            $start,
            $end - $start
        )
        : '';

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
    function_exists(
        'billing_seat_change_reconcile_terminal_payment'
    )
    && $helper !== '',
    'central terminal seat-payment reconciler exists'
);

$check(
    strpos(
        $helper,
        'inTransaction()'
    ) !== false
    && strpos(
        $helper,
        'requires an active database transaction'
    ) !== false,
    'terminal reconciliation requires the caller transaction'
);

$attemptLock = strpos(
    $helper,
    'billing_audit_attempt_by_id('
);

$requestLock = strpos(
    $helper,
    'billing_seat_change_request_by_payment('
);

$check(
    $attemptLock !== false
    && $requestLock !== false
    && $attemptLock < $requestLock
    && strpos(
        $helper,
        '$paymentAttemptId,'
    ) !== false
    && strpos(
        $helper,
        'true'
    ) !== false,
    'terminal reconciliation preserves attempt-before-request locking'
);

$check(
    strpos(
        $helper,
        "\$purpose !== 'seat_topup'"
    ) !== false
    && strpos(
        $helper,
        "'handled' => false"
    ) !== false,
    'shared payment routes safely ignore non-seat-topup purposes'
);

$check(
    strpos(
        $helper,
        "['failed', 'cancelled']"
    ) !== false
    && strpos(
        $helper,
        'billing_seat_change_mark_payment_failed('
    ) !== false,
    'failed terminal outcome delegates to the existing proven failed-payment transition'
);

$check(
    strpos(
        $helper,
        "'verified_at'"
    ) !== false
    && strpos(
        $helper,
        'must be provider verified'
    ) !== false,
    'cancelled seat-top-up reconciliation requires provider verification'
);

$check(
    preg_match(
        "/UPDATE\\s+billing_seat_change_requests"
        . "[\\s\\S]*?SET\\s+status\\s*=\\s*'cancelled'"
        . "[\\s\\S]*?cancelled_at\\s*=\\s*\\?"
        . "[\\s\\S]*?AND\\s+status\\s*=\\s*'awaiting_payment'/i",
        $helper
    ) === 1
    && strpos(
        $helper,
        '$update->rowCount() !== 1'
    ) !== false,
    'cancelled payment closes awaiting request exactly once and timestamps cancellation'
);

$check(
    strpos(
        $helper,
        "\$requestState['status'] === 'cancelled'"
    ) !== false
    && strpos(
        $helper,
        "'idempotent' => true"
    ) !== false,
    'already-cancelled durable request is idempotent'
);

$protectedDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions)\b/i';

$check(
    !preg_match(
        $protectedDml,
        $helper
    ),
    'terminal reconciliation performs no entitlement or subscription DML'
);

$providerCall =
    '/\bcurl_(?:init|exec)|'
    . 'billing_provider_(?:initialize|verify|charge)/i';

$check(
    !preg_match(
        $providerCall,
        $helper
    ),
    'terminal reconciliation performs no provider or network call'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 SEAT TERMINAL RECONCILIATION: FAILED\n";
    exit(1);
}

echo "V2.3 SEAT TERMINAL RECONCILIATION: PASSED\n";
