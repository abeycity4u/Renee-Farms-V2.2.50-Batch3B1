<?php
/**
 * Regression contract: first-paid and legacy/manual subscription checkout must
 * remain available without fabricated paid-period lineage. Scheduled seat
 * reductions still require a real paid-period boundary.
 */

$root = dirname(__DIR__);

$targetPath =
    $root . '/includes/billing_renewal_seat_target.php';

$checkoutPath =
    $root . '/includes/billing_subscription_checkout_initiation.php';

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
    is_file($targetPath),
    'renewal-seat target helper exists'
);

$check(
    is_file($checkoutPath),
    'subscription checkout initiation helper exists'
);

$target = is_file($targetPath)
    ? file_get_contents($targetPath)
    : '';

$checkout = is_file($checkoutPath)
    ? file_get_contents($checkoutPath)
    : '';

$targetCompact =
    preg_replace('/\s+/', '', (string)$target);

$checkoutCompact =
    preg_replace('/\s+/', '', (string)$checkout);

$check(
    strpos(
        $targetCompact,
        "billing_seat_change_normalize_datetime(\$latest['current_period_ends_at']??null,true)"
    ) !== false,
    'renewal targeting permits absent paid-period lineage'
);

$check(
    strpos(
        $targetCompact,
        'if($rows&&$periodEnd===null)'
    ) !== false
    && strpos(
        $target,
        'Scheduled seat reduction requires an established paid-period end.'
    ) !== false,
    'scheduled reductions still fail closed without a paid-period boundary'
);

$check(
    strpos(
        $checkoutCompact,
        "billing_seat_change_normalize_datetime(\$target['current_period_ends_at']??null,true)"
    ) !== false,
    'checkout policy permits an absent paid-period end'
);

$check(
    strpos(
        $checkoutCompact,
        '$periodEnd===null?null:newDateTimeImmutable('
    ) !== false,
    'checkout does not construct a synthetic period-end date'
);

$check(
    strpos(
        $checkoutCompact,
        'if($hasScheduled&&$periodEndDate===null)'
    ) !== false,
    'scheduled checkout state requires real period lineage'
);

$check(
    strpos(
        $checkoutCompact,
        '$hasScheduled&&$periodEndDate!==null&&$now<$periodEndDate'
    ) !== false,
    'early renewal still cannot bypass scheduled seat reductions'
);

$forbidden = [
    'UPDATE subscriptions',
    'INSERT INTO subscriptions',
    'DELETE FROM subscriptions',
    'UPDATE farms',
    'INSERT INTO farms',
    'DELETE FROM farms',
];

$mutatesCommercialState = false;

foreach ($forbidden as $needle) {
    if (stripos($target, $needle) !== false
        || stripos($checkout, $needle) !== false) {
        $mutatesCommercialState = true;
        break;
    }
}

$check(
    !$mutatesCommercialState,
    'regression fix performs no direct farm or subscription mutation'
);

echo "Checks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    exit(1);
}

echo "PASS: subscription checkout supports first-paid and legacy/manual tenants without weakening scheduled-seat safeguards.\n";
?>
