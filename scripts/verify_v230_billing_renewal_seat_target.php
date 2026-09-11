<?php
/**
 * Focused static contract verifier for V2.3 renewal seat targeting.
 *
 * Read-only, database-free and provider-network-free.
 */

$root = dirname(__DIR__);
$path = $root . '/includes/billing_renewal_seat_target.php';

if (!is_file($path)) {
    fwrite(
        STDERR,
        "FAIL: missing renewal seat-target helper.\n"
    );
    exit(1);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "FAIL: unable to read renewal seat-target helper.\n"
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
    ) !== false,
    'renewal target reuses canonical current-product and seat-change services'
);

$check(
    strpos(
        $source,
        'function billing_renewal_seat_target('
    ) !== false,
    'centralized renewal seat-target helper exists'
);

$check(
    strpos(
        $source,
        "array \$allowedStatuses = ['active']"
    ) !== false
    && strpos(
        $source,
        'At least one subscription status is required for renewal seat targeting.'
    ) !== false
    && strpos(
        $source,
        'Unsupported subscription status for renewal seat targeting.'
    ) !== false,
    'renewal seat targeting defaults to active and rejects empty or unknown status scopes'
);

$check(
    strpos(
        $compact,
        'billing_current_product($pdo,$farmId,$allowedStatuses)'
    ) !== false,
    'renewal seat targeting delegates its explicit status scope to the authoritative current product'
);

$check(
    strpos(
        $source,
        'billing_seat_change_ready($pdo)'
    ) !== false,
    'renewal target fails closed unless durable seat-change storage is ready'
);

$check(
    strpos(
        $source,
        '$forUpdate && !$pdo->inTransaction()'
    ) !== false
    && strpos(
        $source,
        "$sql .= ' FOR UPDATE';"
    ) !== false,
    'optional scheduled-request locking requires an active caller transaction'
);

$check(
    strpos(
        $source,
        "change_kind = 'remove'"
    ) !== false
    && strpos(
        $source,
        "status = 'scheduled'"
    ) !== false
    && strpos(
        $source,
        'WHERE farm_id = ?'
    ) !== false,
    'only tenant-pinned scheduled removals can influence renewal'
);

$check(
    strpos(
        $source,
        'billing_seat_change_row_contract('
    ) !== false
    && strpos(
        $source,
        'Scheduled seat removal no longer matches the current renewal lineage.'
    ) !== false,
    'each scheduled removal is hash-validated and rechecked against current commercial lineage'
);

$check(
    strpos(
        $source,
        'More than one scheduled renewal change exists for the same role.'
    ) !== false
    && strpos(
        $source,
        'from_extra_seats'
    ) !== false
    && strpos(
        $source,
        'to_extra_seats'
    ) !== false,
    'renewal target rejects duplicate roles and stale current-seat baselines'
);

$check(
    strpos(
        $source,
        'subscription_seat_assert_capacity('
    ) !== false,
    'future renewal seat target must still fit assigned-user capacity'
);

$check(
    strpos(
        $source,
        'billing_pricing_build_payment_quote('
    ) !== false
    && strpos(
        $source,
        "'renewal_seat_addons'"
    ) !== false
    && strpos(
        $source,
        "'payment_quote'"
    ) !== false,
    'server-authoritative renewal quote is rebuilt from resolved target seats'
);

$check(
    strpos(
        $source,
        "'scheduled_request_ids'"
    ) !== false
    && strpos(
        $source,
        "'has_scheduled_reductions'"
    ) !== false,
    'resolver exposes the durable scheduled removals that produced the renewal target'
);

$directDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '[`A-Za-z_][`A-Za-z0-9_]*/i';

$check(
    !preg_match(
        $directDml,
        $source
    ),
    'renewal seat-target foundation performs no database mutation'
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
    'renewal seat-target foundation performs no provider or network work'
);

echo "\nChecks: {$checks}\n";
echo "Failures: {$failures}\n";

if ($failures > 0) {
    echo "V2.3 RENEWAL SEAT TARGET FOUNDATION: FAILED\n";
    exit(1);
}

echo "V2.3 RENEWAL SEAT TARGET FOUNDATION: PASSED\n";
?>
