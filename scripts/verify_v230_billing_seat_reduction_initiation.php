<?php
/**
 * Focused static contract verifier for V2.3 scheduled seat-reduction
 * initiation foundation.
 */

$root = dirname(__DIR__);
$path =
    $root
    . '/includes/billing_seat_reduction_initiation.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing seat-reduction initiation helper.\n"
    );
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read seat-reduction initiation helper.\n"
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
        "require_once __DIR__ . '/billing_current_product.php';"
    ) !== false
    && strpos(
        $source,
        "require_once __DIR__ . '/billing_seat_change_request.php';"
    ) !== false
    && strpos(
        $source,
        "require_once __DIR__ . '/billing_commercial_attempt_coordination.php';"
    ) !== false,
    'seat reduction reuses current-product, seat-change and commercial coordination foundations'
);

$check(
    strpos(
        $source,
        'function billing_seat_reduction_request_input('
    ) !== false
    && strpos(
        $source,
        'function billing_seat_reduction_schedule('
    ) !== false,
    'centralized scheduled seat-reduction initiation helpers exist'
);

$check(
    strpos(
        $source,
        'if ($pdo->inTransaction())'
    ) !== false
    && strpos(
        $source,
        'Seat-reduction initiation must own its database transaction.'
    ) !== false
    && strpos(
        $source,
        '$pdo->beginTransaction();'
    ) !== false,
    'seat-reduction initiation owns one transaction'
);

$coordinationPos = strpos(
    $source,
    'billing_commercial_attempt_assert_clear('
);

$currentProductPos = strpos(
    $source,
    'billing_current_product(',
    $coordinationPos === false
        ? 0
        : $coordinationPos
);

$insertPos = strpos(
    $source,
    'billing_seat_change_request_insert(',
    $currentProductPos === false
        ? 0
        : $currentProductPos
);

$check(
    $coordinationPos !== false
    && $currentProductPos !== false
    && $insertPos !== false
    && $coordinationPos < $currentProductPos
    && $currentProductPos < $insertPos,
    'commercial coordination precedes active-product resolution and durable insertion'
);

$check(
    strpos(
        $compact,
        "billing_current_product(\$pdo,\$farmId,['active'])"
    ) !== false,
    'only the active commercial product may schedule a mid-term seat reduction'
);

$check(
    strpos(
        $source,
        'Seat-reduction quantity must be an integer between 1 and 500.'
    ) !== false
    && strpos(
        $source,
        'Seat reduction cannot remove more purchased extra seats than currently exist.'
    ) !== false,
    'reduction quantity is positive, bounded and cannot exceed purchased extras'
);

$check(
    strpos(
        $source,
        'subscription_seat_role_relevant('
    ) !== false
    && strpos(
        $source,
        'There are no purchased extra seats to remove for this role.'
    ) !== false,
    'seat reduction is restricted to relevant roles with purchased extras'
);

$check(
    strpos(
        $source,
        'Seat reduction requires a future active paid-period boundary.'
    ) !== false
    && strpos(
        $source,
        "'effective_at' => \$periodEnd"
    ) !== false,
    'reduction schedules exactly at a future current paid-period boundary'
);

$check(
    strpos(
        $source,
        "'change_kind' => 'remove'"
    ) !== false
    && strpos(
        $source,
        "'amount' => '0.00'"
    ) !== false
    && strpos(
        $source,
        "'payment_attempt_id' => null"
    ) !== false,
    'scheduled reduction is a no-refund non-payment request'
);

$check(
    strpos(
        $source,
        "'quoted_at' => null"
    ) !== false
    && strpos(
        $source,
        "'pricing_hash' => null"
    ) !== false
    && strpos(
        $source,
        "'latest_paid_subscription_id' => null"
    ) !== false
    && strpos(
        $source,
        "'latest_paid_attempt_id' => null"
    ) !== false,
    'scheduled reduction carries no paid-proration lineage snapshot'
);

$check(
    strpos(
        $source,
        "!== 'scheduled'"
    ) !== false
    && strpos(
        $source,
        'Scheduled seat reduction was not persisted with the authoritative contract.'
    ) !== false,
    'durable request is post-validated as a scheduled removal'
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
    'seat-reduction initiation commits atomically and rolls back on failure'
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
    'INSERT INTO billing_payment_attempts',
    'UPDATE billing_payment_attempts',
    'DELETE FROM billing_payment_attempts',
];

$forbiddenMutation = false;

foreach ($forbiddenDml as $needle) {
    if (stripos($source, $needle) !== false) {
        $forbiddenMutation = true;
        break;
    }
}

$check(
    !$forbiddenMutation,
    'foundation does not directly mutate tenant entitlement, subscription or payment-attempt state'
);

$check(
    strpos(
        $source,
        'billing_provider_'
    ) === false
    && strpos(
        $source,
        'curl_'
    ) === false,
    'scheduled reduction performs no provider or network work'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 SCHEDULED SEAT REDUCTION FOUNDATION: FAILED\n";
    exit(1);
}

echo "V2.3 SCHEDULED SEAT REDUCTION FOUNDATION: PASSED\n";
?>
