<?php
/**
 * V2.3 paid seat-top-up application contract verifier.
 *
 * Database-free, provider-free and mutation-free.
 */

$root = dirname(__DIR__);

$paths = [
    'service' =>
        $root . '/includes/billing_seat_topup_application.php',
    'policy' =>
        $root . '/includes/subscription_seat_policy.php',
    'subscription' =>
        $root . '/includes/billing_subscription_application.php',
    'dispatcher' =>
        $root . '/includes/billing_paid_attempt_dispatcher.php',
];

foreach ($paths as $path) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            'FAIL: missing ' . $path . PHP_EOL
        );
        exit(1);
    }
}

require_once $paths['service'];

$source = [];

foreach ($paths as $key => $path) {
    $source[$key] =
        (string)file_get_contents($path);
}

$service = $source['service'];
$policy = $source['policy'];
$subscription = $source['subscription'];
$dispatcher = $source['dispatcher'];

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

$targetSeats = billing_payment_normalize_seat_addons([
    'poultry_manager' => 2,
    'ruminant_manager' => 0,
    'sales_rep' => 1,
    'viewer' => 1,
]);

$built = billing_payment_build_quote(
    'starter',
    'monthly',
    '1250.00',
    'NGN',
    ['poultry'],
    $targetSeats
);

$attempt = [
    'id' => 501,
    'farm_id' => 17,
    'purpose' => 'seat_topup',
    'status' => 'paid',
    'provider' => 'paystack',
    'provider_reference' =>
        'rf-seat-topup-contract-501',
    'provider_transaction_id' =>
        'seat-topup-contract-tx-501',
    'provider_subscription_id' => null,
    'plan_code' => 'starter',
    'billing_interval' => 'monthly',
    'amount' => '1250.00',
    'currency' => 'NGN',
    'modules_snapshot' =>
        json_encode(['poultry']),
    'seat_addons_snapshot' =>
        json_encode(
            $targetSeats,
            JSON_UNESCAPED_SLASHES
        ),
    'quote_hash' =>
        $built['quote_hash'],
    'initiated_by_user_id' => 44,
    'verified_at' =>
        '2026-09-10 22:45:00',
    'paid_at' =>
        '2026-09-10 22:45:00',
];

$contract =
    billing_seat_topup_attempt_contract(
        $attempt
    );

$check(
    ($contract['attempt_id'] ?? null) === 501
    && ($contract['farm_id'] ?? null) === 17
    && ($contract['plan_code'] ?? null)
        === 'starter'
    && ($contract['seat_addons'] ?? null)
        === $targetSeats,
    'canonical verified paid seat_topup attempt resolves to an immutable application contract'
);

$wrongPurpose = $attempt;
$wrongPurpose['purpose'] = 'subscription';

$wrongPurposeRejected = false;

try {
    billing_seat_topup_attempt_contract(
        $wrongPurpose
    );
} catch (RuntimeException $e) {
    $wrongPurposeRejected =
        strpos(
            $e->getMessage(),
            'seat_topup'
        ) !== false;
}

$check(
    $wrongPurposeRejected,
    'subscription-purpose payment cannot enter the seat-top-up application service'
);

$pending = $attempt;
$pending['status'] = 'pending';

$pendingRejected = false;

try {
    billing_seat_topup_attempt_contract(
        $pending
    );
} catch (RuntimeException $e) {
    $pendingRejected =
        strpos(
            $e->getMessage(),
            'verified paid'
        ) !== false;
}

$check(
    $pendingRejected,
    'non-paid seat-top-up attempt is rejected'
);

$missingVerification = $attempt;
$missingVerification['verified_at'] = null;

$missingVerificationRejected = false;

try {
    billing_seat_topup_attempt_contract(
        $missingVerification
    );
} catch (RuntimeException $e) {
    $missingVerificationRejected =
        strpos(
            $e->getMessage(),
            'verification timestamps'
        ) !== false;
}

$check(
    $missingVerificationRejected,
    'paid seat-top-up requires provider verification timestamps'
);

$missingTransaction = $attempt;
$missingTransaction[
    'provider_transaction_id'
] = null;

$missingTransactionRejected = false;

try {
    billing_seat_topup_attempt_contract(
        $missingTransaction
    );
} catch (RuntimeException $e) {
    $missingTransactionRejected =
        strpos(
            $e->getMessage(),
            'transaction id'
        ) !== false;
}

$check(
    $missingTransactionRejected,
    'paid seat-top-up requires provider transaction identity'
);

$tampered = $attempt;
$tampered['quote_hash'] =
    str_repeat('0', 64);

$tamperedRejected = false;

try {
    billing_seat_topup_attempt_contract(
        $tampered
    );
} catch (RuntimeException $e) {
    $tamperedRejected =
        strpos(
            $e->getMessage(),
            'integrity'
        ) !== false;
}

$check(
    $tamperedRejected,
    'frozen seat-top-up quote tampering is rejected'
);

$requestContract = [
    'farm_id' => 17,
    'change_kind' => 'add',
    'payment_attempt_id' => 501,
    'initiated_by_user_id' => 44,
    'plan_code' => 'starter',
    'billing_interval' => 'monthly',
    'amount' => '1250.00',
    'currency' => 'NGN',
    'modules' => ['poultry'],
];

$bindingPassed = true;

try {
    billing_seat_topup_assert_request_matches_attempt(
        $requestContract,
        $contract,
        $targetSeats
    );
} catch (Throwable $e) {
    $bindingPassed = false;
}

$check(
    $bindingPassed,
    'durable add-seat request binds exactly to its paid attempt and target seat snapshot'
);

$wrongAttempt = $contract;
$wrongAttempt['attempt_id'] = 999;

$wrongBindingRejected = false;

try {
    billing_seat_topup_assert_request_matches_attempt(
        $requestContract,
        $wrongAttempt,
        $targetSeats
    );
} catch (RuntimeException $e) {
    $wrongBindingRejected = true;
}

$check(
    $wrongBindingRejected,
    'foreign payment attempt cannot consume a durable seat-change request'
);

$check(
    strpos(
        $policy,
        'function subscription_seat_save_effective_limits('
    ) !== false
    && strpos(
        $policy,
        'subscription_plan_effective_role_limits('
    ) !== false
    && strpos(
        $policy,
        'INSERT INTO farm_role_limits'
    ) !== false,
    'effective role-limit persistence is centralized in the shared seat policy'
);

$check(
    strpos(
        $subscription,
        'return subscription_seat_save_effective_limits('
    ) !== false,
    'full subscription application reuses the centralized effective-limit writer'
);

$check(
    strpos(
        $service,
        'billing_seat_change_request_by_payment('
    ) !== false
    && preg_match(
        '/billing_seat_change_request_by_payment\s*\('
        . '[\s\S]*?\$attemptId\s*,\s*true\s*\)/',
        $service
    ) === 1,
    'seat-top-up application locks the durable request by paid attempt'
);

$check(
    strpos(
        $service,
        'billing_seat_change_row_contract('
    ) !== false,
    'seat-top-up application revalidates the immutable durable request hash'
);

$check(
    strpos(
        $service,
        "'awaiting_payment'"
    ) !== false
    && strpos(
        $service,
        "'applied'"
    ) !== false
    && strpos(
        $service,
        'idempotent'
    ) !== false,
    'seat-top-up application has explicit awaiting-payment and exactly-once applied states'
);

$check(
    strpos(
        $service,
        'billing_seat_change_current_context('
    ) !== false,
    'seat-top-up application rejects stale product or seat-count context'
);

$check(
    strpos(
        $service,
        'billing_seat_topup_assert_current_lineage('
    ) !== false
    && strpos(
        $service,
        'latest_paid_subscription_id'
    ) !== false
    && strpos(
        $service,
        'latest_paid_attempt_id'
    ) !== false,
    'seat-top-up application revalidates the authoritative paid commercial lineage'
);

$saveAddonsPos = strpos(
    $service,
    'subscription_seat_save_addons('
);

$saveLimitsPos = strpos(
    $service,
    'subscription_seat_save_effective_limits('
);

$historyPos = strpos(
    $service,
    'subscription_record_capture('
);

$requestApplyPos = strpos(
    $service,
    "SET status = 'applied'"
);

$check(
    $saveAddonsPos !== false
    && $saveLimitsPos !== false
    && $historyPos !== false
    && $requestApplyPos !== false
    && $saveAddonsPos < $saveLimitsPos
    && $saveLimitsPos < $historyPos
    && $historyPos < $requestApplyPos,
    'seat state, effective limits and immutable history are written before the request is marked applied'
);

$check(
    strpos(
        $service,
        "'seat_topup_payment_applied'"
    ) !== false,
    'applied seat top-up receives a dedicated commercial-history reason'
);

$check(
    strpos(
        $service,
        "effective_at ="
    ) === false,
    'paid seat application does not mutate effective_at because it is request-hash-bound'
);

$protectedDirectDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|'
    . 'farm_subscription_seat_addons|'
    . 'farm_role_limits|subscriptions|'
    . 'billing_payment_attempts)\b/i';

$check(
    preg_match(
        $protectedDirectDml,
        $service
    ) !== 1,
    'seat-top-up application performs commercial writes only through centralized policy/history services'
);

$check(
    strpos(
        $service,
        'billing_provider_verify_payment('
    ) === false
    && strpos(
        $service,
        'billing_provider_initialize_checkout('
    ) === false
    && strpos(
        $service,
        'curl_'
    ) === false,
    'seat-top-up application makes no provider or network call'
);

$check(
    preg_match(
        '/UPDATE\s+billing_seat_change_requests/i',
        $service
    ) === 1
    && strpos(
        $service,
        "AND status = 'awaiting_payment'"
    ) !== false,
    'durable request workflow is the only direct application-state mutation'
);

$seatTopupDispatchBranch = preg_match(
    '/\$purpose\s*===\s*[\'"]seat_topup[\'"]/',
    $dispatcher,
    $seatTopupDispatchMatch,
    PREG_OFFSET_CAPTURE
) === 1
    ? $seatTopupDispatchMatch[0][1]
    : false;

$seatTopupDispatchApply = strpos(
    $dispatcher,
    'billing_seat_topup_apply_paid_attempt('
);

$check(
    strpos(
        $dispatcher,
        'billing_seat_topup_application.php'
    ) !== false
    && $seatTopupDispatchBranch !== false
    && $seatTopupDispatchApply !== false
    && $seatTopupDispatchBranch
        < $seatTopupDispatchApply
    && strpos(
        $dispatcher,
        'Paid seat top-up application is not enabled yet.'
    ) === false,
    'central dispatcher delegates seat_topup only to the independently verified application service'
);

echo PHP_EOL;
echo 'Checks: ' . $checks . PHP_EOL;
echo 'Failures: ' . $failures . PHP_EOL;

if ($failures > 0) {
    exit(1);
}

echo "V2.3 SEAT-TOP-UP APPLICATION FOUNDATION: PASSED\n";
