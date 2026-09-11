<?php
/**
 * Focused static verifier for V2.3 scheduled seat-reduction cancellation.
 *
 * Database-free and provider-network-free.
 */

$root = dirname(__DIR__);

$path =
    $root
    . '/includes/billing_seat_reduction_cancellation.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing scheduled reduction cancellation foundation.\n"
    );
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read scheduled reduction cancellation foundation.\n"
    );
    exit(1);
}

$compact = preg_replace(
    '/\s+/',
    '',
    $source
);

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
        "require_once __DIR__"
    ) !== false
    && strpos(
        $source,
        "billing_seat_change_request.php"
    ) !== false,
    'cancellation reuses the durable seat-change foundation'
);

$check(
    strpos(
        $source,
        'function billing_seat_reduction_cancel_scheduled('
    ) !== false,
    'central scheduled reduction cancellation helper exists'
);

$check(
    strpos(
        $source,
        'billing_seat_change_ready($pdo)'
    ) !== false,
    'cancellation fails closed unless durable seat-change storage is ready'
);

$check(
    strpos(
        $source,
        '$pdo->inTransaction()'
    ) !== false
    && strpos(
        $source,
        '$pdo->beginTransaction();'
    ) !== false
    && strpos(
        $source,
        '$pdo->commit();'
    ) !== false
    && strpos(
        $source,
        '$pdo->rollBack();'
    ) !== false,
    'cancellation owns an explicit atomic transaction'
);

$farmLockPos = strpos(
    $compact,
    'SELECTidFROMfarmsWHEREid=?ANDslug<>\'owner\'LIMIT1FORUPDATE'
);

$requestLockPos = strpos(
    $compact,
    'billing_seat_change_request_by_id($pdo,$requestId,true)'
);

$check(
    $farmLockPos !== false
    && $requestLockPos !== false
    && $farmLockPos < $requestLockPos,
    'tenant farm is locked before the durable request to match renewal lock order'
);

$check(
    strpos(
        $source,
        "Scheduled seat-reduction request does not belong to the authenticated tenant."
    ) !== false
    && strpos(
        $source,
        "Only a scheduled seat reduction may be cancelled through this workflow."
    ) !== false,
    'foreign-tenant and non-removal requests fail closed'
);

$check(
    strpos(
        $compact,
        "==='cancelled'"
    ) !== false
    && strpos(
        $source,
        "'idempotent' => true"
    ) !== false,
    'already-cancelled reductions return idempotently'
);

$check(
    strpos(
        $source,
        "Only a currently scheduled seat reduction can be cancelled."
    ) !== false,
    'applied, failed and other non-scheduled states cannot be cancelled'
);

$check(
    strpos(
        $compact,
        "SETstatus='cancelled',cancelled_at=CURRENT_TIMESTAMP"
    ) !== false
    && strpos(
        $compact,
        "ANDchange_kind='remove'ANDstatus='scheduled'ANDapplied_atISNULLANDcancelled_atISNULL"
    ) !== false,
    'workflow update is narrowly constrained to one unapplied scheduled removal'
);

$check(
    strpos(
        $source,
        'Cancelled seat reduction failed post-update verification.'
    ) !== false
    && substr_count(
        $source,
        'billing_seat_change_row_contract('
    ) >= 2,
    'cancelled state is reloaded and integrity-verified after mutation'
);

$forbiddenDml = [
    'UPDATE farms',
    'INSERT INTO farms',
    'DELETE FROM farms',
    'UPDATE subscriptions',
    'INSERT INTO subscriptions',
    'DELETE FROM subscriptions',
    'UPDATE farm_subscription_seat_addons',
    'INSERT INTO farm_subscription_seat_addons',
    'DELETE FROM farm_subscription_seat_addons',
    'UPDATE farm_role_limits',
    'INSERT INTO farm_role_limits',
    'DELETE FROM farm_role_limits',
    'INSERT INTO billing_payment_attempts',
    'UPDATE billing_payment_attempts',
    'DELETE FROM billing_payment_attempts',
];

$hasForbiddenDml = false;

foreach ($forbiddenDml as $needle) {
    if (stripos(
        $source,
        $needle
    ) !== false) {
        $hasForbiddenDml = true;
        break;
    }
}

$check(
    !$hasForbiddenDml,
    'cancellation performs no entitlement, subscription or payment-attempt DML'
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
    'cancellation performs no provider or network work'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 SCHEDULED SEAT REDUCTION CANCELLATION: FAILED\n";
    exit(1);
}

echo "V2.3 SCHEDULED SEAT REDUCTION CANCELLATION: PASSED\n";
?>
