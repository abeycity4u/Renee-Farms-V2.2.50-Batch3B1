<?php
/**
 * Focused static contract verifier for V2.3 paid-renewal seat-reduction
 * application foundation.
 *
 * Read-only verifier: no database or provider calls are executed here.
 */

$root = dirname(__DIR__);
$path =
    $root
    . '/includes/billing_renewal_seat_application.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing renewal seat application helper.\n"
    );
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read renewal seat application helper.\n"
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
        "require_once __DIR__ . '/billing_renewal_seat_target.php';"
    ) !== false,
    'paid renewal seat application reuses the canonical renewal target'
);

$check(
    strpos(
        $source,
        'function billing_renewal_seat_apply_paid_snapshot('
    ) !== false,
    'centralized paid-renewal seat application helper exists'
);

$check(
    strpos(
        $source,
        'if (!$pdo->inTransaction())'
    ) !== false
    && strpos(
        $source,
        'requires an active caller transaction'
    ) !== false,
    'renewal seat application requires caller-owned transaction scope'
);

$check(
    strpos(
        $source,
        'FROM farms'
    ) !== false
    && strpos(
        $source,
        "slug <> 'owner'"
    ) !== false
    && strpos(
        $source,
        'FOR UPDATE'
    ) !== false
    && strpos(
        $source,
        'Tenant farm could not be locked for renewal seat application.'
    ) !== false,
    'tenant commercial snapshot is locked before renewal target resolution'
);

$check(
    strpos(
        $compact,
        'billing_renewal_seat_target($pdo,$farmId,true,'
    ) !== false,
    'scheduled renewal requests are resolved and locked after the tenant lock'
);

$check(
    strpos(
        $source,
        "'past_due'"
    ) !== false
    && strpos(
        $source,
        "'suspended'"
    ) !== false
    && strpos(
        $source,
        "'cancelled'"
    ) !== false,
    'paid renewal application supports restricted recovery statuses'
);

$check(
    strpos(
        $source,
        'Paid subscription quote no longer matches the current commercial lineage.'
    ) !== false
    && strpos(
        $source,
        "'plan_code'"
    ) !== false
    && strpos(
        $source,
        "'billing_interval'"
    ) !== false
    && strpos(
        $source,
        "'modules'"
    ) !== false,
    'paid renewal application revalidates current commercial lineage'
);

$check(
    strpos(
        $source,
        'Paid subscription seat snapshot is stale against current tenant seats.'
    ) !== false,
    'renewal without scheduled reductions rejects stale seat snapshots'
);

$check(
    strpos(
        $source,
        '$paidAt < $periodEnd'
    ) !== false
    && strpos(
        $source,
        'Scheduled seat reductions cannot be applied by an early renewal payment.'
    ) !== false,
    'scheduled reductions cannot be consumed before the paid-period boundary'
);

$check(
    strpos(
        $source,
        '$attemptSeats !== $renewalSeats'
    ) !== false
    && strpos(
        $source,
        'Paid subscription seat snapshot does not match the due renewal seat target.'
    ) !== false,
    'due reduction must exactly match the frozen paid seat snapshot'
);

$check(
    strpos(
        $source,
        "SET status = 'applied'"
    ) !== false
    && strpos(
        $source,
        'applied_at = CURRENT_TIMESTAMP'
    ) !== false
    && strpos(
        $source,
        "change_kind = 'remove'"
    ) !== false
    && strpos(
        $source,
        "status = 'scheduled'"
    ) !== false,
    'only scheduled removal rows transition to applied'
);

$check(
    strpos(
        $source,
        '$update->rowCount() !== 1'
    ) !== false
    && strpos(
        $source,
        'could not be marked applied exactly once'
    ) !== false,
    'each scheduled reduction is transitioned exactly once'
);

$check(
    strpos(
        $source,
        'billing_seat_change_request_by_id('
    ) !== false
    && strpos(
        $source,
        'billing_seat_change_row_contract('
    ) !== false
    && strpos(
        $source,
        "'applied_at'"
    ) !== false,
    'applied reduction is reloaded and hash-contract verified'
);

$forbiddenCommercialDml = [
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

$commercialMutation = false;

foreach ($forbiddenCommercialDml as $needle) {
    if (stripos($source, $needle) !== false) {
        $commercialMutation = true;
        break;
    }
}

$check(
    !$commercialMutation,
    'foundation does not directly mutate tenant entitlement or subscription state'
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
    'foundation performs no provider or network work'
);

$check(
    strpos(
        $source,
        "'applied_request_ids'"
    ) !== false
    && strpos(
        $source,
        "'had_scheduled_reductions'"
    ) !== false,
    'foundation exposes the exact scheduled reductions applied'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 RENEWAL SEAT APPLICATION FOUNDATION: FAILED\n";
    exit(1);
}

echo "V2.3 RENEWAL SEAT APPLICATION FOUNDATION: PASSED\n";
?>
