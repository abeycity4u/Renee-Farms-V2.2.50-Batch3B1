<?php
/**
 * Focused static verifier for V2.3 tenant payment receipts.
 */

$root = dirname(__DIR__);
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

$read = static function (string $path) use ($root): string {
    $content = @file_get_contents($root . '/' . $path);

    return is_string($content)
        ? $content
        : '';
};

$helper = $read('includes/billing_payment_receipt.php');
$route = $read('billing/receipt.php');
$account = $read('billing/account.php');
$overview = $read('includes/billing_account_overview.php');

$check(
    $helper !== '',
    'receipt read model exists'
);

$check(
    str_contains(
        $helper,
        'function billing_payment_receipt_find'
    ),
    'receipt lookup is centralized'
);

$check(
    preg_match(
        "/WHERE\\s+id\\s*=\\s*\\?\\s+AND\\s+farm_id\\s*=\\s*\\?\\s+AND\\s+status\\s*=\\s*'paid'/is",
        $helper
    ) === 1,
    'receipt lookup is pinned by attempt id, tenant and paid status'
);

$check(
    str_contains(
        $helper,
        'billing_payment_attempt_purpose($row)'
    ),
    'receipt uses canonical payment purpose normalization'
);

$check(
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM)\b/i',
        $helper
    ) !== 1,
    'receipt read model performs no DML'
);

$check(
    str_contains(
        $route,
        'billing_require_farm_admin_actor('
    )
    && preg_match(
        '/billing_require_farm_admin_actor\s*\(\s*\$pdo\s*,\s*false\s*\)/s',
        $route
    ) === 1,
    'receipt requires normal Farm Admin billing authentication'
);

$check(
    str_contains(
        $route,
        "(int)\$actor['farm_id']"
    ),
    'receipt tenant comes from authenticated actor'
);

$check(
    !str_contains(
        $route,
        "\$_GET['farm_id']"
    )
    && !str_contains(
        $route,
        "\$_POST['farm_id']"
    )
    && !str_contains(
        $route,
        'name="farm_id"'
    ),
    'receipt does not accept browser-selected tenant identity'
);

$check(
    str_contains(
        $route,
        "\$_GET['id']"
    )
    && str_contains(
        $route,
        "preg_match('/^[1-9][0-9]*$/'"
    ),
    'receipt id is strictly positive-integer validated'
);

$check(
    preg_match(
        '/billing_payment_receipt_find\s*\(\s*\$pdo\s*,\s*\$farmId\s*,\s*\$attemptId\s*\)/s',
        $route
    ) === 1,
    'receipt delegates to tenant-pinned lookup'
);

$check(
    str_contains(
        $route,
        'Payment receipt could not be found.'
    )
    && str_contains(
        $route,
        'http_response_code(404)'
    ),
    'invalid, unpaid or foreign-tenant receipts fail closed'
);

$check(
    !str_contains(
        $route,
        'billing_provider_verify_payment'
    )
    && !str_contains(
        $route,
        'billing_provider_register_configured_adapters'
    )
    && !str_contains(
        $route,
        'billing_payment_attempt_create'
    ),
    'receipt viewing is payment-provider passive'
);

$check(
    preg_match(
        '/\b(?:INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM)\b/i',
        $route
    ) !== 1,
    'receipt route performs no DML'
);

$check(
    str_contains(
        $route,
        "'subscription' => 'Subscription payment'"
    )
    && str_contains(
        $route,
        "'seat_topup' => 'Seat top-up payment'"
    ),
    'receipt distinguishes subscription and seat-top-up payments'
);

$check(
    str_contains(
        $route,
        "\$commercialDisposition === 'superseded'"
    )
    && str_contains(
        $route,
        'does not indicate that a subscription change'
    ),
    'superseded paid payment is not presented as applied entitlement'
);

$check(
    str_contains(
        $account,
        '<th>Receipt</th>'
    ),
    'Recent payments includes receipt column'
);

$check(
    str_contains(
        $account,
        "\$attemptStatus === 'paid'"
    ),
    'receipt action is paid-only'
);

$check(
    str_contains(
        $account,
        "/billing/receipt.php?id="
    )
    && str_contains(
        $account,
        'View receipt'
    ),
    'paid row links to canonical receipt route'
);

$check(
    str_contains(
        $account,
        'colspan="7" class="empty-state">No payment attempts'
    ),
    'payment empty state matches seven-column table'
);

$overviewStart = strpos(
    $overview,
    'function billing_account_payment_attempts'
);

$overviewEnd = $overviewStart === false
    ? false
    : strpos(
        $overview,
        "if (!function_exists('billing_account_seat_summary'))",
        $overviewStart
    );

$paymentHistory = (
    $overviewStart !== false
    && $overviewEnd !== false
)
    ? substr(
        $overview,
        $overviewStart,
        $overviewEnd - $overviewStart
    )
    : '';

$check(
    $paymentHistory !== ''
    && !str_contains(
        $paymentHistory,
        'provider_reference'
    )
    && !str_contains(
        $paymentHistory,
        'provider_transaction_id'
    ),
    'general payment history still hides provider identifiers'
);

echo "Checks: {$checks}; Failures: {$failures}\n";

if ($failures > 0) {
    echo "FAIL: V2.3 tenant payment receipt contract failed.\n";
    return;
}

echo "PASS: V2.3 tenant payment receipt is paid-only, tenant-pinned, read-only and provider-passive.\n";
?>
