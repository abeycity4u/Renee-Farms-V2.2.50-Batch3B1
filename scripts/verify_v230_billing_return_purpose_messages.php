<?php
/**
 * Focused verifier for V2.3 purpose-aware billing return messages.
 *
 * Read-only, database-free and provider-network-free.
 */

$root = dirname(__DIR__);
$returnPath = $root . '/billing/return.php';

if (!is_file($returnPath)) {
    fwrite(STDERR, "FAIL: missing billing return route.\n");
    exit(1);
}

$return = file_get_contents($returnPath);

if ($return === false) {
    fwrite(STDERR, "FAIL: unable to read billing return route.\n");
    exit(1);
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
        $return,
        '$purpose = billing_payment_attempt_purpose($updated);'
    ) !== false,
    'return messaging derives purpose from the authoritative verified attempt'
);

$check(
    strpos(
        $return,
        "if (\$purpose === 'subscription')"
    ) !== false
    && strpos(
        $return,
        "} elseif (\$purpose === 'seat_topup') {"
    ) !== false,
    'return route has explicit subscription and seat-topup presentation contracts'
);

$check(
    strpos(
        $return,
        'Payment verified and subscription activated successfully.'
    ) !== false
    && strpos(
        $return,
        'Payment verification is still pending. No subscription change has been applied.'
    ) !== false,
    'subscription return messaging remains unchanged'
);

$check(
    strpos(
        $return,
        'Seat top-up payment verified and additional seats applied successfully.'
    ) !== false
    && strpos(
        $return,
        'Seat top-up payment verification is still pending. No seat change has been applied.'
    ) !== false,
    'seat-topup paid and pending messages describe seat changes rather than subscriptions'
);

$check(
    strpos(
        $return,
        'This seat top-up payment has been recorded as refunded.'
    ) !== false
    && strpos(
        $return,
        'Seat top-up payment was not completed. You can start a new seat top-up when ready.'
    ) !== false,
    'seat-topup refunded and incomplete outcomes use purpose-appropriate copy'
);

$promotion = strpos(
    $return,
    'subscription_recovery_promote_to_login($pdo);'
);

$promotionGuard = strpos(
    $return,
    "if (\$purpose === 'subscription' && \$recoveryMode)"
);

$check(
    $promotion !== false
    && $promotionGuard !== false
    && $promotionGuard < $promotion,
    'recovery login promotion is restricted to a paid subscription purpose'
);

$check(
    strpos(
        $return,
        "\$_GET['purpose']"
    ) === false
    && strpos(
        $return,
        "\$_POST['purpose']"
    ) === false,
    'billing purpose is never accepted from browser input'
);

$directCommercialDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions)\b/i';

$check(
    !preg_match(
        $directCommercialDml,
        $return
    ),
    'return presentation change adds no direct commercial-state DML'
);

$check(
    substr_count(
        $return,
        'billing_paid_attempt_dispatch('
    ) === 1
    && substr_count(
        $return,
        'billing_seat_change_reconcile_terminal_payment('
    ) === 1,
    'purpose-aware presentation leaves payment application and terminal reconciliation call sites unchanged'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 PURPOSE-AWARE BILLING RETURN MESSAGES: FAILED\n";
    exit(1);
}

echo "V2.3 PURPOSE-AWARE BILLING RETURN MESSAGES: PASSED\n";
