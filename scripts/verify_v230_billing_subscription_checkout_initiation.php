<?php
/**
 * Focused static verifier for the V2.3 subscription checkout initiation
 * foundation.
 */

$root = dirname(__DIR__);
$path =
    $root
    . '/includes/billing_subscription_checkout_initiation.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing subscription checkout initiation helper.\n"
    );
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read subscription checkout initiation helper.\n"
    );
    exit(1);
}

$compact = preg_replace('/\s+/', '', $source);
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
        $source,
        "require_once __DIR__ . '/billing_payment_foundation.php';"
    ) !== false
    && strpos(
        $source,
        "require_once __DIR__ . '/billing_commercial_attempt_coordination.php';"
    ) !== false
    && strpos(
        $source,
        "require_once __DIR__ . '/billing_renewal_seat_target.php';"
    ) !== false,
    'checkout initiation reuses payment, coordination and renewal-target foundations'
);

$check(
    strpos(
        $source,
        'function billing_subscription_checkout_policy('
    ) !== false
    && strpos(
        $source,
        'function billing_subscription_checkout_assert_selection('
    ) !== false
    && strpos(
        $source,
        'function billing_subscription_checkout_prepare('
    ) !== false,
    'checkout policy, browser assertion and transactional preparation are centralized'
);

$check(
    strpos(
        $source,
        'Subscription checkout initiation must own its database transaction.'
    ) !== false
    && strpos(
        $source,
        '$pdo->beginTransaction();'
    ) !== false,
    'checkout preparation owns its payment-attempt transaction'
);

$preparePos = strpos(
    $source,
    'function billing_subscription_checkout_prepare('
);

$coordinationPos = strpos(
    $source,
    'billing_commercial_attempt_assert_clear(',
    $preparePos === false
        ? 0
        : $preparePos
);

$targetPos = strpos(
    $source,
    'billing_renewal_seat_target(',
    $coordinationPos === false
        ? 0
        : $coordinationPos
);

$createPos = strpos(
    $source,
    'billing_payment_attempt_create(',
    $targetPos === false
        ? 0
        : $targetPos
);

$check(
    $preparePos !== false
    && $coordinationPos !== false
    && $targetPos !== false
    && $createPos !== false
    && $preparePos < $coordinationPos
    && $coordinationPos < $targetPos
    && $targetPos < $createPos,
    'farm coordination precedes locked renewal resolution and payment-attempt creation'
);

$check(
    strpos(
        $compact,
        'billing_renewal_seat_target($pdo,$farmId,true,$allowedStatuses)'
    ) !== false,
    'renewal target and scheduled reductions are resolved under the caller transaction'
);

$check(
    strpos(
        $source,
        'Subscription renewal cannot start before scheduled seat reductions reach the current paid-period end.'
    ) !== false
    && strpos(
        $source,
        '$hasScheduled'
    ) !== false
    && strpos(
        $source,
        '$now < $periodEndDate'
    ) !== false,
    'early renewal cannot bypass a scheduled seat reduction'
);

$check(
    strpos(
        $source,
        "'renewal_seat_addons'"
    ) !== false
    && strpos(
        $source,
        "'payment_quote' =>"
    ) !== false
    && strpos(
        $source,
        "'uses_scheduled_reductions' =>"
    ) !== false,
    'checkout policy exposes the authoritative renewal seat target and quote'
);

$check(
    strpos(
        $source,
        'Subscription checkout no longer matches the server-authoritative renewal product.'
    ) !== false
    && strpos(
        $source,
        '$actualSeats'
    ) !== false
    && strpos(
        $source,
        '$expectedSeats'
    ) !== false,
    'browser product fields remain assertions against server-authoritative renewal state'
);

$check(
    strpos(
        $source,
        'billing_payment_normalize_provider('
    ) !== false
    && strpos(
        $source,
        'billing_payment_normalize_reference('
    ) !== false,
    'provider identity and provider reference are normalized before persistence'
);

$check(
    strpos(
        $source,
        "'subscription'"
    ) !== false
    && $createPos !== false,
    'new checkout payment attempts explicitly use subscription purpose'
);

$check(
    strpos(
        $source,
        "(\$created['inserted'] ?? false)"
    ) !== false
    && strpos(
        $source,
        'requires a fresh provider reference'
    ) !== false,
    'checkout preparation requires a newly inserted provider reference'
);

$check(
    strpos(
        $compact,
        "\$attempt=\$created['attempt']??null;"
    ) !== false
    && strpos(
        $compact,
        "billing_payment_attempt_purpose(\$attempt)"
    ) !== false
    && strpos(
        $source,
        'Subscription checkout created an invalid persisted payment attempt.'
    ) !== false,
    'the actual persisted attempt row and payment purpose are revalidated before commit'
);

$check(
    strpos(
        $compact,
        "\$policy['payment_quote']['payment_quote']"
    ) !== false
    && strpos(
        $source,
        '$expectedQuoteHash'
    ) !== false
    && strpos(
        $source,
        '$storedQuoteHash'
    ) !== false
    && strpos(
        $source,
        '$persistedQuoteHash'
    ) !== false
    && strpos(
        $source,
        'hash_equals('
    ) !== false
    && strpos(
        $source,
        'payment quote was not frozen exactly'
    ) !== false,
    'persisted payment attempt is hash-bound to the canonical nested renewal payment quote'
);

$check(
    strpos(
        $source,
        '$pdo->commit();'
    ) !== false
    && strpos(
        $source,
        '$pdo->rollBack();'
    ) !== false,
    'attempt creation commits atomically and rolls back on failure'
);

$forbiddenDml = [
    'UPDATE farms',
    'INSERT INTO farms',
    'DELETE FROM farms',
    'UPDATE farm_modules',
    'INSERT INTO farm_modules',
    'DELETE FROM farm_modules',
    'UPDATE farm_subscription_seat_addons',
    'INSERT INTO farm_subscription_seat_addons',
    'DELETE FROM farm_subscription_seat_addons',
    'UPDATE farm_role_limits',
    'INSERT INTO farm_role_limits',
    'DELETE FROM farm_role_limits',
    'UPDATE subscriptions',
    'INSERT INTO subscriptions',
    'DELETE FROM subscriptions',
];

$hasEntitlementDml = false;

foreach ($forbiddenDml as $needle) {
    if (stripos($source, $needle) !== false) {
        $hasEntitlementDml = true;
        break;
    }
}

$check(
    !$hasEntitlementDml,
    'checkout initiation performs no direct entitlement or subscription-state mutation'
);

$check(
    strpos(
        $source,
        'billing_provider_initialize_checkout('
    ) === false
    && strpos(
        $source,
        'curl_'
    ) === false,
    'checkout initiation performs no provider or network work'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 SUBSCRIPTION CHECKOUT INITIATION FOUNDATION: FAILED\n";
    exit(1);
}

echo "V2.3 SUBSCRIPTION CHECKOUT INITIATION FOUNDATION: PASSED\n";
?>
