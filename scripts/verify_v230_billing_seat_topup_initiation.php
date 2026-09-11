<?php
/**
 * Contract verifier for the V2.3 seat-top-up initiation foundation.
 *
 * This verifier performs no database or provider mutation.
 */

$root = dirname(__DIR__);

$servicePath =
    $root
    . '/includes/billing_seat_topup_initiation.php';

if (!is_file($servicePath)) {
    fwrite(
        STDERR,
        "Missing seat-top-up initiation service.\n"
    );
    exit(1);
}

require_once $servicePath;

$source = file_get_contents($servicePath);

if ($source === false) {
    fwrite(
        STDERR,
        "Unable to read seat-top-up initiation service.\n"
    );
    exit(1);
}

$serviceLines = file($servicePath);

if (!is_array($serviceLines)) {
    fwrite(
        STDERR,
        "Unable to load initiation service lines.\n"
    );
    exit(1);
}

$prepareReflection =
    new ReflectionFunction(
        'billing_seat_topup_prepare'
    );

$prepareStart =
    $prepareReflection->getStartLine();

$prepareLength =
    $prepareReflection->getEndLine()
    - $prepareStart
    + 1;

$prepareSource = implode(
    '',
    array_slice(
        $serviceLines,
        $prepareStart - 1,
        $prepareLength
    )
);

if ($prepareSource === '') {
    fwrite(
        STDERR,
        "Unable to isolate seat-top-up preparation function.\n"
    );
    exit(1);
}

$checks = 0;
$failures = 0;

$check = static function (
    bool $condition,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
};

$check(
    function_exists(
        'billing_seat_topup_prepare'
    )
    && function_exists(
        'billing_seat_topup_build_checkout_quote'
    )
    && function_exists(
        'billing_seat_topup_request_input'
    ),
    'initiation foundation exposes centralized preparation and snapshot helpers'
);

$sampleSeats = [
    'poultry_manager' => 2,
    'ruminant_manager' => 0,
    'sales_rep' => 0,
    'viewer' => 0,
];

$sampleQuote = [
    'farm_id' => 14,
    'role_code' => 'poultry_manager',
    'quantity' => 1,
    'from_extra_seats' => 1,
    'to_extra_seats' => 2,
    'target_seat_addons' => $sampleSeats,
    'plan_code' => 'starter',
    'modules' => ['poultry'],
    'billing_interval' => 'monthly',
    'pricing_version' => 'v230-test',
    'pricing_hash' => str_repeat('a', 64),
    'currency' => 'NGN',
    'unit_amount' => '2000.00',
    'partial_unit_amount' => '1809.44',
    'future_full_periods' => 0,
    'per_seat_amount' => '1809.44',
    'amount' => '1809.44',
    'quoted_at' => '2026-09-11 12:00:00',
    'lineage_start' => '2026-08-11 12:00:00',
    'segment_start' => '2026-09-01 00:00:00',
    'segment_end' => '2026-10-01 00:00:00',
    'current_period_ends_at' =>
        '2026-10-01 00:00:00',
    'latest_paid_subscription_id' => 101,
    'latest_paid_attempt_id' => 91,
];

$checkout =
    billing_seat_topup_build_checkout_quote(
        $sampleQuote
    );

$paymentQuote =
    $checkout['payment_quote']['quote']
        ?? null;

$check(
    is_array($paymentQuote)
    && $paymentQuote['plan_code']
        === 'starter'
    && $paymentQuote['billing_interval']
        === 'monthly'
    && $paymentQuote['amount']
        === '1809.44'
    && $paymentQuote['currency']
        === 'NGN'
    && $paymentQuote['modules']
        === ['poultry'],
    'provider-ready top-up quote is frozen from canonical server commercial facts'
);

$expectedSeats = $sampleSeats;
ksort($expectedSeats, SORT_STRING);

$check(
    is_array($paymentQuote)
    && $paymentQuote['seat_addons']
        === $expectedSeats
    && preg_match(
        '/^[a-f0-9]{64}$/',
        (string)(
            $checkout[
                'payment_quote'
            ]['quote_hash'] ?? ''
        )
    ) === 1,
    'provider-ready quote freezes the complete target seat snapshot and quote hash'
);

$check(
    ($checkout['pricing'][
        'pricing_version'
    ] ?? null) === 'v230-test'
    && ($checkout['pricing'][
        'pricing_hash'
    ] ?? null) === str_repeat('a', 64)
    && ($checkout['pricing']['amount'] ?? null)
        === '1809.44'
    && ($checkout['pricing']['currency'] ?? null)
        === 'NGN',
    'provider-ready quote carries the authoritative proration pricing identity'
);

$requestInput =
    billing_seat_topup_request_input(
        $sampleQuote,
        123,
        456
    );

$check(
    $requestInput['change_kind'] === 'add'
    && $requestInput['farm_id'] === 14
    && $requestInput['role_code']
        === 'poultry_manager'
    && $requestInput['from_extra_seats'] === 1
    && $requestInput['to_extra_seats'] === 2
    && $requestInput['payment_attempt_id']
        === 123
    && $requestInput['initiated_by_user_id']
        === 456
    && $requestInput['effective_at'] === null,
    'durable request input binds add-seat target, payment identity and initiating actor'
);

$check(
    $requestInput['quoted_at']
        === $sampleQuote['quoted_at']
    && $requestInput['lineage_start_at']
        === $sampleQuote['lineage_start']
    && $requestInput['segment_start_at']
        === $sampleQuote['segment_start']
    && $requestInput['segment_end_at']
        === $sampleQuote['segment_end']
    && $requestInput[
        'latest_paid_subscription_id'
    ] === 101
    && $requestInput[
        'latest_paid_attempt_id'
    ] === 91,
    'durable request input preserves the complete authoritative paid-lineage snapshot'
);

$check(
    strpos(
        $prepareSource,
        'Seat-top-up initiation must own its database transaction.'
    ) !== false
    && strpos(
        $prepareSource,
        '$pdo->beginTransaction()'
    ) !== false
    && strpos(
        $prepareSource,
        '$pdo->commit()'
    ) !== false
    && strpos(
        $prepareSource,
        '$pdo->rollBack()'
    ) !== false,
    'initiation owns its transaction and has explicit commit and rollback boundaries'
);

$farmLockPos = strpos(
    $prepareSource,
    'FOR UPDATE'
);

$quotePos = strpos(
    $prepareSource,
    'billing_seat_proration_quote('
);

$checkoutBuildPos = strpos(
    $prepareSource,
    'billing_seat_topup_build_checkout_quote('
);

$attemptCreatePos = strpos(
    $prepareSource,
    'billing_payment_attempt_create('
);

$requestInputPos = strpos(
    $prepareSource,
    'billing_seat_topup_request_input('
);

$requestInsertPos = strpos(
    $prepareSource,
    'billing_seat_change_request_insert('
);

$commitPos = strpos(
    $prepareSource,
    '$pdo->commit()'
);

$check(
    $farmLockPos !== false
    && $quotePos !== false
    && $checkoutBuildPos !== false
    && $attemptCreatePos !== false
    && $requestInputPos !== false
    && $requestInsertPos !== false
    && $commitPos !== false
    && $farmLockPos < $quotePos
    && $quotePos < $checkoutBuildPos
    && $checkoutBuildPos < $attemptCreatePos
    && $attemptCreatePos < $requestInputPos
    && $requestInputPos < $requestInsertPos
    && $requestInsertPos < $commitPos,
    'tenant lock precedes authoritative quote, frozen checkout quote, payment attempt, durable request and commit'
);

$check(
    preg_match(
        '/billing_payment_attempt_create\s*\('
        . '[\s\S]*?[\'"]seat_topup[\'"]\s*\)/',
        $prepareSource
    ) === 1,
    'new payment attempt is explicitly frozen with seat_topup purpose'
);

$check(
    preg_match(
        '/\$created\s*\[\s*[\'"]inserted[\'"]\s*\]'
        . '\s*\?\?\s*false/',
        $prepareSource
    ) === 1
    && strpos(
        $prepareSource,
        'requires a fresh provider reference'
    ) !== false,
    'initiation refuses provider-reference reuse instead of attaching a new request to an old attempt'
);

$check(
    strpos(
        $prepareSource,
        'hash_equals('
    ) !== false
    && strpos(
        $prepareSource,
        'Seat-top-up payment quote was not frozen exactly.'
    ) !== false,
    'created payment attempt quote hash must equal the provider-ready frozen quote'
);

$check(
    strpos(
        $prepareSource,
        "'awaiting_payment'"
    ) !== false
    && strpos(
        $prepareSource,
        "'payment_attempt_id'"
    ) !== false
    && strpos(
        $prepareSource,
        "'change_kind'"
    ) !== false,
    'durable request is verified as an awaiting-payment add request bound to the new attempt'
);

$directDml =
    '/\b(?:INSERT\s+INTO|DELETE\s+FROM)\b'
    . '|\bUPDATE\s+[A-Za-z_][A-Za-z0-9_]*\b/i';

$check(
    !preg_match(
        $directDml,
        $prepareSource
    ),
    'initiation delegates all persistent writes to centralized payment and request services'
);

$providerCall =
    '/\bcurl_(?:init|exec)|'
    . 'billing_provider_(?:initialize|verify|charge)/i';

$check(
    !preg_match(
        $providerCall,
        $prepareSource
    ),
    'initiation foundation performs no provider or network call'
);

$check(
    strpos(
        $prepareSource,
        'billing_seat_proration_quote('
    ) !== false
    && strpos(
        $prepareSource,
        'billing_payment_attempt_create('
    ) !== false
    && strpos(
        $prepareSource,
        'billing_seat_change_request_insert('
    ) !== false,
    'initiation reuses centralized proration, payment-attempt and durable-request services'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    exit(1);
}

echo "V2.3 SEAT-TOP-UP INITIATION FOUNDATION: PASSED\n";
