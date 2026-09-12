<?php
/**
 * Focused static verifier for V2.3 initialized-attempt checkout integration.
 *
 * Database-free, provider-free and mutation-free.
 */

$root = dirname(__DIR__);
$path = $root . '/billing/checkout.php';

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

if (!is_file($path)) {
    echo "FAIL: checkout route is missing\n";
    echo "\nChecks: 1\nFailures: 1\n";
    echo "V2.3 INITIALIZED ATTEMPT CHECKOUT ROUTE: FAILED\n";
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    echo "FAIL: checkout route could not be read\n";
    echo "\nChecks: 1\nFailures: 1\n";
    echo "V2.3 INITIALIZED ATTEMPT CHECKOUT ROUTE: FAILED\n";
    exit(1);
}

$check(
    strpos(
        $source,
        "billing_initialized_attempt_recovery.php"
    ) !== false,
    'checkout route loads centralized initialized-attempt recovery'
);

$recoveryPos = strpos(
    $source,
    'billing_initialized_attempt_recover_next('
);

$terminalPos = strpos(
    $source,
    'billing_commercial_attempt_reconcile_terminal_candidates_for_replacement('
);

$providerResolvePos = strpos(
    $source,
    'billing_provider_readiness_resolve_checkout('
);

$preparePos = strpos(
    $source,
    'billing_subscription_checkout_prepare('
);

$check(
    $recoveryPos !== false
    && $terminalPos !== false
    && $providerResolvePos !== false
    && $preparePos !== false
    && $recoveryPos < $terminalPos
    && $terminalPos < $providerResolvePos
    && $providerResolvePos < $preparePos,
    'initialized recovery precedes terminal reconciliation, provider selection and fresh attempt creation'
);

$check(
    strpos(
        $source,
        "\$farmId,\n            (int)\$actor['user_id']"
    ) !== false,
    'initialized recovery is bound to current tenant and authenticated actor'
);

$check(
    strpos(
        $source,
        "'initialized_blocked'"
    ) !== false
    && strpos(
        $source,
        "'pending_blocked'"
    ) !== false
    && strpos(
        $source,
        "'paid_applied'"
    ) !== false
    && strpos(
        $source,
        "'superseded'"
    ) !== false
    && strpos(
        $source,
        "'refunded_settled'"
    ) !== false,
    'checkout recognizes the complete initialized-recovery outcome contract'
);

$paidStart = strpos(
    $source,
    "if (\$initializedOutcome === 'paid_applied')"
);

$pendingStart = strpos(
    $source,
    "if (\$initializedOutcome === 'pending_blocked')"
);

$initializedBlockedStart = strpos(
    $source,
    "if (\$initializedOutcome === 'initialized_blocked')"
);

$settledStart = strpos(
    $source,
    "if (in_array(\n        \$initializedOutcome"
);

$paidBlock = '';

if ($paidStart !== false
    && $pendingStart !== false
    && $pendingStart > $paidStart) {
    $paidBlock = substr(
        $source,
        $paidStart,
        $pendingStart - $paidStart
    );
}

$check(
    $paidBlock !== ''
    && strpos(
        $paidBlock,
        'subscription_recovery_promote_to_login('
    ) !== false
    && strpos(
        $paidBlock,
        '/dashboard.php'
    ) !== false
    && strpos(
        $paidBlock,
        '303'
    ) !== false
    && strpos(
        $paidBlock,
        'exit();'
    ) !== false,
    'recovered paid subscription stops replacement checkout and restores normal access'
);

$pendingBlock = '';

if ($pendingStart !== false
    && $initializedBlockedStart !== false
    && $initializedBlockedStart > $pendingStart) {
    $pendingBlock = substr(
        $source,
        $pendingStart,
        $initializedBlockedStart - $pendingStart
    );
}

$check(
    $pendingBlock !== ''
    && strpos(
        $pendingBlock,
        'http_response_code(409)'
    ) !== false
    && strpos(
        $pendingBlock,
        'No new checkout was started.'
    ) !== false
    && strpos(
        $pendingBlock,
        'exit('
    ) !== false,
    'verified pending recovery blocks duplicate checkout'
);

$initializedBlockedBlock = '';

if ($initializedBlockedStart !== false
    && $settledStart !== false
    && $settledStart > $initializedBlockedStart) {
    $initializedBlockedBlock = substr(
        $source,
        $initializedBlockedStart,
        $settledStart - $initializedBlockedStart
    );
}

$check(
    $initializedBlockedBlock !== ''
    && strpos(
        $initializedBlockedBlock,
        'http_response_code(409)'
    ) !== false
    && strpos(
        $initializedBlockedBlock,
        'could not yet be verified safely'
    ) !== false
    && strpos(
        $initializedBlockedBlock,
        'exit('
    ) !== false,
    'ambiguous initialized recovery remains blocking without guessing provider state'
);

$check(
    $settledStart !== false
    && strpos(
        substr(
            $source,
            $settledStart,
            $terminalPos - $settledStart
        ),
        "'superseded'"
    ) !== false
    && strpos(
        substr(
            $source,
            $settledStart,
            $terminalPos - $settledStart
        ),
        "'refunded_settled'"
    ) !== false
    && strpos(
        substr(
            $source,
            $settledStart,
            $terminalPos - $settledStart
        ),
        "!== false"
    ) !== false,
    'only non-blocking settled initialized outcomes may continue'
);

$check(
    $terminalPos !== false
    && strpos(
        $source,
        "'pending_blocked'",
        $terminalPos
    ) !== false
    && strpos(
        $source,
        "'paid_applied'",
        $terminalPos
    ) !== false,
    'existing terminal reconciliation safety gates remain intact'
);

$forbiddenDirectCalls = [
    'billing_provider_verify_payment(',
    'billing_audit_apply_verification(',
    'billing_paid_attempt_dispatch(',
    'billing_commercial_attempt_mark_superseded_after_verified_terminal(',
];

$hasForbiddenDirectCall = false;

foreach ($forbiddenDirectCalls as $needle) {
    if (strpos($source, $needle) !== false) {
        $hasForbiddenDirectCall = true;
        break;
    }
}

$check(
    !$hasForbiddenDirectCall,
    'checkout route delegates provider verification and payment-state mutation to centralized services'
);

$protectedDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:billing_payment_attempts|farms|farm_modules|'
    . 'farm_role_limits|farm_subscription_seat_addons|'
    . 'subscriptions)\b/i';

$check(
    preg_match(
        $protectedDml,
        $source
    ) !== 1,
    'checkout integration adds no direct billing, entitlement or subscription DML'
);

$check(
    strpos(
        $source,
        'billing_provider_readiness_resolve_checkout('
    ) > $terminalPos
    && strpos(
        $source,
        'billing_provider_register_configured_adapters(',
        $providerResolvePos
    ) !== false,
    'brand-new provider checkout is considered only after historical recovery completes'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 INITIALIZED ATTEMPT CHECKOUT ROUTE: FAILED\n";
    exit(1);
}

echo "V2.3 INITIALIZED ATTEMPT CHECKOUT ROUTE: PASSED\n";
?>
