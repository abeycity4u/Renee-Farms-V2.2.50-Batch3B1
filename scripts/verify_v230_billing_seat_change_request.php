<?php
/**
 * V2.3 durable seat-change request foundation verifier.
 *
 * No config.php, provider call or database mutation.
 */

$root = dirname(__DIR__);

$servicePath =
    $root . '/includes/billing_seat_change_request.php';

$migrationPath =
    $root . '/migrations/045_billing_seat_change_foundation.sql';

foreach (
    [$servicePath, $migrationPath]
    as $path
) {
    if (!is_file($path)) {
        fwrite(
            STDERR,
            "FAIL: missing {$path}\n"
        );
        exit(1);
    }
}

require_once $servicePath;

$source = (string)file_get_contents(
    $servicePath
);

$migration = (string)file_get_contents(
    $migrationPath
);

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
};

$check(
    billing_seat_change_kinds()
        === ['add', 'remove'],
    'seat-change kinds are centrally finite'
);

$check(
    billing_seat_change_statuses()
        === [
            'awaiting_payment',
            'scheduled',
            'applied',
            'cancelled',
            'failed',
        ],
    'seat-change workflow states are centrally finite'
);

$check(
    billing_seat_change_initial_status('add')
        === 'awaiting_payment'
    && billing_seat_change_initial_status('remove')
        === 'scheduled',
    'add and remove requests have distinct safe initial states'
);

$check(
    strpos(
        $migration,
        'billing_seat_change_requests'
    ) !== false
    && strpos(
        $source,
        '045_billing_seat_change_foundation.sql'
    ) !== false,
    'request service is paired with migration 045'
);

$check(
    strpos(
        $source,
        "strcasecmp(\$engine, 'InnoDB') === 0"
    ) !== false
    && strpos(
        $source,
        'billing_seat_change_foreign_keys_ready'
    ) !== false,
    'request readiness requires transactional storage and foreign keys'
);

$add = billing_seat_change_build_contract([
    'farm_id' => 99,
    'change_kind' => 'add',
    'role_code' => 'viewer',
    'from_extra_seats' => 1,
    'to_extra_seats' => 2,
    'plan_code' => 'starter',
    'billing_interval' => 'monthly',
    'modules' => ['poultry'],
    'amount' => '904.72',
    'currency' => 'NGN',
    'current_period_ends_at' =>
        '2026-10-07 23:59:59',
    'quoted_at' =>
        '2026-09-10 20:35:59',
    'lineage_start_at' =>
        '2026-09-07 23:59:59',
    'segment_start_at' =>
        '2026-09-07 23:59:59',
    'segment_end_at' =>
        '2026-10-07 23:59:59',
    'pricing_version' =>
        'ngn-launch-v1',
    'pricing_hash' =>
        str_repeat('a', 64),
    'unit_amount' =>
        '1000.00',
    'partial_unit_amount' =>
        '904.72',
    'future_full_periods' => 0,
    'per_seat_amount' =>
        '904.72',
    'latest_paid_subscription_id' => 321,
    'latest_paid_attempt_id' => 654,
    'effective_at' => null,
    'payment_attempt_id' => 123,
    'initiated_by_user_id' => 7,
]);

$check(
    $add['change_kind'] === 'add'
    && $add['from_extra_seats'] === 1
    && $add['to_extra_seats'] === 2
    && $add['amount'] === '904.72'
    && $add['payment_attempt_id'] === 123
    && $add['effective_at'] === null,
    'seat-add contract preserves immutable paid-top-up facts'
);

$check(
    $add['quoted_at']
        === '2026-09-10 20:35:59'
    && $add['lineage_start_at']
        === '2026-09-07 23:59:59'
    && $add['segment_start_at']
        === '2026-09-07 23:59:59'
    && $add['segment_end_at']
        === '2026-10-07 23:59:59'
    && $add['pricing_version']
        === 'ngn-launch-v1'
    && strlen($add['pricing_hash']) === 64
    && $add['unit_amount'] === '1000.00'
    && $add['partial_unit_amount'] === '904.72'
    && $add['future_full_periods'] === 0
    && $add['per_seat_amount'] === '904.72'
    && $add['latest_paid_subscription_id'] === 321
    && $add['latest_paid_attempt_id'] === 654,
    'seat-add contract freezes the complete authoritative proration snapshot'
);

$badMoney = $add;
$badMoney['amount'] = '904.71';

$badMoneyRejected = false;

try {
    billing_seat_change_build_contract(
        $badMoney
    );
} catch (InvalidArgumentException $e) {
    $badMoneyRejected = true;
}

$check(
    $badMoneyRejected,
    'seat-add contract rejects internally inconsistent proration money'
);

$remove = billing_seat_change_build_contract([
    'farm_id' => 99,
    'change_kind' => 'remove',
    'role_code' => 'viewer',
    'from_extra_seats' => 2,
    'to_extra_seats' => 1,
    'plan_code' => 'starter',
    'billing_interval' => 'monthly',
    'modules' => ['poultry'],
    'amount' => '0.00',
    'currency' => 'NGN',
    'current_period_ends_at' =>
        '2026-10-07 23:59:59',
    'effective_at' =>
        '2026-10-07 23:59:59',
    'payment_attempt_id' => null,
    'initiated_by_user_id' => 7,
]);

$check(
    $remove['change_kind'] === 'remove'
    && $remove['from_extra_seats'] === 2
    && $remove['to_extra_seats'] === 1
    && $remove['amount'] === '0.00'
    && $remove['payment_attempt_id'] === null
    && $remove['effective_at']
        === $remove['current_period_ends_at'],
    'seat removal is no-refund and scheduled at paid period end'
);

$check(
    $remove['quoted_at'] === null
    && $remove['pricing_version'] === null
    && $remove['pricing_hash'] === null
    && $remove['latest_paid_subscription_id'] === null
    && $remove['latest_paid_attempt_id'] === null,
    'scheduled no-refund removal carries no paid-proration snapshot'
);

$addRejected = false;

try {
    billing_seat_change_build_contract([
        'farm_id' => 99,
        'change_kind' => 'add',
        'role_code' => 'viewer',
        'from_extra_seats' => 2,
        'to_extra_seats' => 1,
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => ['poultry'],
        'amount' => '100.00',
        'currency' => 'NGN',
        'current_period_ends_at' =>
            '2026-10-07 23:59:59',
        'payment_attempt_id' => 123,
    ]);
} catch (InvalidArgumentException $e) {
    $addRejected = true;
}

$check(
    $addRejected,
    'seat-add contract fails closed unless capacity increases'
);

$removeChargeRejected = false;

try {
    billing_seat_change_build_contract([
        'farm_id' => 99,
        'change_kind' => 'remove',
        'role_code' => 'viewer',
        'from_extra_seats' => 2,
        'to_extra_seats' => 1,
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => ['poultry'],
        'amount' => '1.00',
        'currency' => 'NGN',
        'current_period_ends_at' =>
            '2026-10-07 23:59:59',
        'effective_at' =>
            '2026-10-07 23:59:59',
    ]);
} catch (InvalidArgumentException $e) {
    $removeChargeRejected = true;
}

$check(
    $removeChargeRejected,
    'scheduled removal rejects any refund or charge amount'
);

$wrongEffectiveRejected = false;

try {
    billing_seat_change_build_contract([
        'farm_id' => 99,
        'change_kind' => 'remove',
        'role_code' => 'viewer',
        'from_extra_seats' => 2,
        'to_extra_seats' => 1,
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => ['poultry'],
        'amount' => '0.00',
        'currency' => 'NGN',
        'current_period_ends_at' =>
            '2026-10-07 23:59:59',
        'effective_at' =>
            '2026-10-06 23:59:59',
    ]);
} catch (InvalidArgumentException $e) {
    $wrongEffectiveRejected = true;
}

$check(
    $wrongEffectiveRejected,
    'seat removal cannot become effective before paid period end'
);

$hash1 = billing_seat_change_request_hash(
    $add
);

$hash2 = billing_seat_change_request_hash(
    $add
);

$changed = $add;
$changed['payment_attempt_id'] = 124;

$hash3 = billing_seat_change_request_hash(
    $changed
);

$check(
    preg_match('/^[a-f0-9]{64}$/', $hash1) === 1
    && hash_equals($hash1, $hash2)
    && !hash_equals($hash1, $hash3),
    'request hash is deterministic and binds payment-attempt identity'
);

$row = [
    'id' => 1,
    'farm_id' => $add['farm_id'],
    'change_kind' => $add['change_kind'],
    'status' => 'awaiting_payment',
    'role_code' => $add['role_code'],
    'from_extra_seats' =>
        $add['from_extra_seats'],
    'to_extra_seats' =>
        $add['to_extra_seats'],
    'plan_code' => $add['plan_code'],
    'billing_interval' =>
        $add['billing_interval'],
    'modules_snapshot' => json_encode(
        $add['modules'],
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
    ),
    'amount' => $add['amount'],
    'currency' => $add['currency'],
    'current_period_ends_at' =>
        $add['current_period_ends_at'],
    'quoted_at' => $add['quoted_at'],
    'lineage_start_at' =>
        $add['lineage_start_at'],
    'segment_start_at' =>
        $add['segment_start_at'],
    'segment_end_at' =>
        $add['segment_end_at'],
    'pricing_version' =>
        $add['pricing_version'],
    'pricing_hash' =>
        $add['pricing_hash'],
    'unit_amount' =>
        $add['unit_amount'],
    'partial_unit_amount' =>
        $add['partial_unit_amount'],
    'future_full_periods' =>
        $add['future_full_periods'],
    'per_seat_amount' =>
        $add['per_seat_amount'],
    'latest_paid_subscription_id' =>
        $add['latest_paid_subscription_id'],
    'latest_paid_attempt_id' =>
        $add['latest_paid_attempt_id'],
    'effective_at' => $add['effective_at'],
    'payment_attempt_id' =>
        $add['payment_attempt_id'],
    'initiated_by_user_id' =>
        $add['initiated_by_user_id'],
    'request_hash' => $hash1,
    'applied_at' => null,
    'cancelled_at' => null,
];

$rebuilt =
    billing_seat_change_row_contract(
        $row
    );

$check(
    $rebuilt['request_hash'] === $hash1
    && $rebuilt['status']
        === 'awaiting_payment',
    'stored request contract revalidates immutable request facts'
);

$tampered = $row;
$tampered['to_extra_seats'] = 3;

$tamperRejected = false;

try {
    billing_seat_change_row_contract(
        $tampered
    );
} catch (Throwable $e) {
    $tamperRejected = true;
}

$check(
    $tamperRejected,
    'stored request tampering fails closed against request hash'
);

$check(
    strpos(
        $source,
        "!== 'seat_topup'"
    ) !== false
    && strpos(
        $source,
        'Seat-add request requires a seat_topup payment purpose.'
    ) !== false,
    'durable add request requires tenant-bound seat_topup payment purpose'
);

$check(
    strpos(
        $source,
        "'awaiting_payment'"
    ) !== false
    && strpos(
        $source,
        "'scheduled'"
    ) !== false,
    'request insertion derives safe initial workflow state server-side'
);

$check(
    strpos(
        $source,
        "require_once __DIR__ . '/billing_current_product.php';"
    ) !== false
    && strpos(
        $source,
        'billing_seat_change_current_context'
    ) !== false,
    'request insertion revalidates current tenant commercial state'
);

$check(
    strpos(
        $source,
        'Seat-change request is stale because the current extra-seat count has changed.'
    ) !== false
    && strpos(
        $source,
        'Seat-change request no longer matches the tenant current paid period.'
    ) !== false,
    'request insertion rejects stale seat counts and paid periods'
);

$check(
    strpos(
        $source,
        "if (\$contract['change_kind'] === 'remove')"
    ) !== false
    && strpos(
        $source,
        'subscription_seat_assert_capacity'
    ) !== false,
    'scheduled removal checks future seat capacity before persistence'
);

$check(
    strpos(
        $source,
        "AND status IN ("
    ) !== false
    && strpos(
        $source,
        "'awaiting_payment'"
    ) !== false
    && strpos(
        $source,
        "'scheduled'"
    ) !== false
    && strpos(
        $source,
        'Another pending seat change already exists for this role.'
    ) !== false,
    'one open seat change per tenant role is enforced under the farm lock'
);

$check(
    strpos(
        $source,
        'LIMIT 1' . "\n" . '                 FOR UPDATE'
    ) !== false
    && strpos(
        $source,
        '$pdo->beginTransaction()'
    ) !== false
    && strpos(
        $source,
        '$pdo->rollBack()'
    ) !== false,
    'request insertion uses transactional tenant and payment locking'
);

$check(
    strpos(
        $source,
        "if (\$status !== 'initialized')"
    ) !== false
    && strpos(
        $source,
        'Seat-top-up payment must be bound to its request before provider processing begins.'
    ) !== false,
    'seat-top-up request binds only to a pre-provider initialized attempt'
);

$check(
    strpos(
        $source,
        'billing_payment_build_quote('
    ) !== false
    && strpos(
        $source,
        'Seat-top-up payment quote integrity check failed.'
    ) !== false,
    'seat-top-up request revalidates the frozen payment quote hash'
);

$check(
    strpos(
        $source,
        'Seat-top-up payment does not match the immutable seat-change request.'
    ) !== false
    && strpos(
        $source,
        '$attemptSeats'
    ) !== false
    && strpos(
        $source,
        "\$context['target_seat_addons']"
    ) !== false,
    'payment amount/product/modules and complete target seat snapshot are bound to the request'
);

$check(
    strpos(
        $source,
        'Seat-top-up payment actor does not match the seat-change request.'
    ) !== false,
    'payment initiator identity is bound to the durable request'
);

$check(
    strpos(
        $source,
        '046_billing_seat_quote_snapshot.sql'
    ) !== false
    && strpos(
        $source,
        "'quoted_at'"
    ) !== false
    && strpos(
        $source,
        "'latest_paid_attempt_id'"
    ) !== false,
    'request readiness and storage are paired with migration 046 quote snapshot'
);

$check(
    strpos(
        $source,
        "'fk_billing_seat_change_latest_subscription'"
    ) !== false
    && strpos(
        $source,
        "'fk_billing_seat_change_latest_attempt'"
    ) !== false
    && strpos(
        $source,
        "'RESTRICT'"
    ) !== false,
    'request readiness requires restrictive immutable paid-lineage foreign keys'
);

$check(
    strpos(
        $source,
        'billing_seat_change_assert_authoritative_proration'
    ) !== false
    && strpos(
        $source,
        'billing_seat_proration_quote('
    ) !== false
    && strpos(
        $source,
        'Seat-proration quote is stale or has a future timestamp.'
    ) !== false,
    'durable seat addition is recomputed through fresh server-authoritative proration'
);

$check(
    strpos(
        $source,
        'lineage_start_at'
    ) !== false
    && strpos(
        $source,
        'partial_unit_amount'
    ) !== false
    && strpos(
        $source,
        'future_full_periods'
    ) !== false
    && strpos(
        $source,
        'latest_paid_subscription_id'
    ) !== false,
    'request persistence carries the complete migration 046 quote evidence'
);

$protectedEntitlementWrite =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions)\b/i';

$check(
    !preg_match(
        $protectedEntitlementWrite,
        $source
    ),
    'request foundation performs no tenant entitlement or subscription DML'
);

$providerCall =
    '/\bcurl_(?:init|exec)|'
    . 'billing_provider_(?:initialize|verify|charge)/i';

$check(
    !preg_match(
        $providerCall,
        $source
    ),
    'request foundation performs no provider or network call'
);

$check(
    function_exists(
        'billing_seat_change_mark_payment_failed'
    ),
    'request foundation exposes one centralized failed-payment workflow transition'
);

$failureHelperStart = strpos(
    $source,
    'function billing_seat_change_mark_payment_failed('
);

$failureHelperEnd = $failureHelperStart === false
    ? false
    : strpos(
        $source,
        "if (!function_exists('billing_seat_change_attempt_modules'))",
        $failureHelperStart
    );

$failureHelperSource =
    $failureHelperStart !== false
    && $failureHelperEnd !== false
    && $failureHelperEnd > $failureHelperStart
        ? substr(
            $source,
            $failureHelperStart,
            $failureHelperEnd - $failureHelperStart
        )
        : '';

$check(
    $failureHelperSource !== ''
    && strpos(
        $failureHelperSource,
        'inTransaction()'
    ) !== false
    && strpos(
        $failureHelperSource,
        'requires an active database transaction'
    ) !== false,
    'failed-payment cleanup requires the caller transaction'
);

$attemptLockPos = strpos(
    $failureHelperSource,
    'billing_audit_attempt_by_id('
);

$requestLockPos = strpos(
    $failureHelperSource,
    'billing_seat_change_request_by_payment('
);

$check(
    $attemptLockPos !== false
    && $requestLockPos !== false
    && $attemptLockPos < $requestLockPos
    && preg_match(
        '/billing_audit_attempt_by_id\\s*\\('
        . '[\\s\\S]*?\\$paymentAttemptId\\s*,\\s*true\\s*\\)/',
        $failureHelperSource
    ) === 1
    && preg_match(
        '/billing_seat_change_request_by_payment\\s*\\('
        . '[\\s\\S]*?\\$paymentAttemptId\\s*,\\s*true\\s*\\)/',
        $failureHelperSource
    ) === 1,
    'failed-payment cleanup locks payment attempt before its durable seat request'
);

$check(
    strpos(
        $failureHelperSource,
        "billing_payment_attempt_purpose("
    ) !== false
    && strpos(
        $failureHelperSource,
        "!== 'seat_topup'"
    ) !== false
    && strpos(
        $failureHelperSource,
        "!== 'failed'"
    ) !== false,
    'failed-payment cleanup requires a failed seat_topup payment attempt'
);

$check(
    preg_match(
        "/UPDATE\\s+billing_seat_change_requests"
        . "[\\s\\S]*?SET\\s+status\\s*=\\s*'failed'"
        . "[\\s\\S]*?AND\\s+status\\s*=\\s*'awaiting_payment'/i",
        $failureHelperSource
    ) === 1
    && strpos(
        $failureHelperSource,
        '$update->rowCount() !== 1'
    ) !== false,
    'failed-payment cleanup transitions awaiting_payment to failed exactly once'
);

$check(
    preg_match(
        '/\$requestState\s*\[\s*[\'"]status[\'"]\s*\]'
        . '\s*===\s*[\'"]failed[\'"]/',
        $failureHelperSource
    ) === 1
    && preg_match(
        '/[\'"]idempotent[\'"]\s*=>\s*true/',
        $failureHelperSource
    ) === 1,
    'repeated failed-payment cleanup returns idempotently'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    exit(1);
}

echo "V2.3 BILLING SEAT-CHANGE REQUEST FOUNDATION: PASSED\n";
