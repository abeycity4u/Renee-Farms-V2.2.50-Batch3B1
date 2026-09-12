<?php
/** Static contract verifier for V2.3 tenant Billing & Subscription UI. */
$root = dirname(__DIR__);
$checks = 0;
$failures = 0;

function verify_billing_account(bool $ok, string $message): void {
    global $checks, $failures;
    $checks++;
    if ($ok) echo "PASS: {$message}\n";
    else { $failures++; echo "FAIL: {$message}\n"; }
}

function billing_account_source(string $path): string {
    global $root;
    $source = @file_get_contents($root . '/' . $path);
    return is_string($source) ? $source : '';
}

$currentProduct = billing_account_source('includes/billing_current_product.php');
$helper = billing_account_source('includes/billing_account_overview.php');
$page = billing_account_source('billing/account.php');
$checkout = billing_account_source('billing/checkout.php');
$seatReductionRoute = billing_account_source('billing/seat_reduction_schedule.php');
$navBridge = billing_account_source('includes/platform_owner_nav_discoverability.php');
$init = billing_account_source('init.php');

verify_billing_account($currentProduct !== '' && $helper !== '' && $page !== '', 'current-product helper, billing account helper and page are present');
verify_billing_account(str_contains($currentProduct, "return ['trial', 'active', 'past_due'];"), 'normal self-service billing statuses are centralized');
verify_billing_account(str_contains($currentProduct, 'billing_tenant_actor_farm'), 'current-product contract resolves the tenant through the centralized billing farm helper');
verify_billing_account(str_contains($currentProduct, 'subscription_record_commercial_modules') && str_contains($currentProduct, 'subscription_record_latest'), 'current-product contract uses canonical commercial module and latest-subscription helpers');
verify_billing_account(str_contains($currentProduct, 'subscription_seat_load_addons') && str_contains($currentProduct, 'subscription_seat_assert_capacity'), 'current-product contract uses canonical purchased-seat and capacity policy');
verify_billing_account(str_contains($currentProduct, 'billing_pricing_build_payment_quote'), 'current-product price comes from server-authoritative pricing');
verify_billing_account(str_contains($currentProduct, 'billing_current_product_assert_selection'), 'current-product contract centralizes browser selection assertions');
verify_billing_account(str_contains($helper, "require_once __DIR__ . '/billing_payment_foundation.php';"), 'account read model loads billing payment foundation explicitly');
verify_billing_account(str_contains($helper, "require_once __DIR__ . '/billing_current_product.php';"), 'account read model loads the shared current-product contract explicitly');
verify_billing_account(str_contains($helper, 'billing_current_product(') && str_contains($helper, 'billing_current_product_normal_statuses()'), 'account read model delegates product resolution to the shared contract');
verify_billing_account(str_contains($helper, 'subscription_record_history'), 'account read model uses canonical subscription history helper');
verify_billing_account(str_contains($helper, 'subscription_seat_load_effective_limits') && str_contains($helper, 'subscription_seat_used_role_counts'), 'account read model uses canonical effective-limit and seat-usage helpers');
verify_billing_account(str_contains($helper, 'WHERE farm_id = ?'), 'payment attempt history is tenant-pinned');
verify_billing_account(!str_contains($helper, 'provider_reference') && !str_contains($helper, 'provider_transaction_id'), 'tenant payment history excludes provider references and transaction identifiers');
verify_billing_account(str_contains($page, 'billing_require_farm_admin_actor($pdo, false)'), 'billing account requires a normal Farm Admin billing actor');
verify_billing_account(!str_contains($page, 'subscription_recovery_') && !str_contains($page, 'allowRecovery'), 'billing account does not accept restricted recovery authentication');
verify_billing_account(str_contains($page, 'billing_provider_selection_codes()') && str_contains($page, 'billing_provider_readiness_status'), 'provider choices come from canonical provider selection/readiness');
verify_billing_account(str_contains($page, "BASE_URL . '/billing/checkout.php'"), 'renewal delegates to the canonical checkout route');
verify_billing_account(
    str_contains(
        $page,
        "BASE_URL . '/billing/seat_topup_checkout.php'"
    ),
    'seat allowance UI delegates extra-seat purchase to the dedicated seat-topup checkout route'
);
verify_billing_account(
    str_contains(
        $helper,
        "'seat_change_ready' => \$seatChangeReady"
    )
    && str_contains(
        $page,
        "\$status === 'active'"
    ),
    'seat-change UI is gated by read-model storage readiness and active subscription state'
);
verify_billing_account(
    str_contains(
        $page,
        'name="role_code"'
    )
    && str_contains(
        $page,
        'name="quantity"'
    )
    && str_contains(
        $page,
        'max="500"'
    ),
    'seat-topup UI submits only an explicit role and bounded positive quantity'
);
verify_billing_account(
    str_contains(
        $page,
        'final prorated price is calculated securely on the server'
    ),
    'seat-topup UI explains that commercial pricing remains server authoritative'
);
verify_billing_account(str_contains($page, 'csrf_field()'), 'renewal and seat-topup checkout forms include centralized CSRF protection');
verify_billing_account(
    str_contains(
        $helper,
        "require_once __DIR__ . '/billing_renewal_seat_target.php';"
    )
    && str_contains(
        $helper,
        'billing_renewal_seat_target('
    )
    && str_contains(
        $helper,
        "'renewal_seat_target' => \$renewalSeatTarget"
    ),
    'billing read model exposes integrity-checked next-renewal seat targeting for active tenants'
);
verify_billing_account(
    $seatReductionRoute !== ''
    && str_contains(
        $page,
        "BASE_URL . '/billing/seat_reduction_schedule.php'"
    )
    && str_contains(
        $page,
        'name="schedule_seat_reduction"'
    ),
    'billing workspace exposes scheduled seat reduction through its dedicated route'
);
verify_billing_account(
    str_contains(
        $page,
        'Current paid-term seat limits stay unchanged and no refund is created.'
    )
    && str_contains(
        $page,
        'assigned users and paid-period end'
    ),
    'seat-reduction UI explains no-refund timing and server-side future-capacity validation'
);
verify_billing_account(
    str_contains(
        $helper,
        "'scheduled_reductions' =>"
    )
    && str_contains(
        $helper,
        "'request_id' =>"
    )
    && str_contains(
        $helper,
        "'from_extra_seats' =>"
    )
    && str_contains(
        $helper,
        "'to_extra_seats' =>"
    )
    && str_contains(
        $helper,
        "'effective_at' =>"
    )
    && str_contains(
        $page,
        '$scheduledReductions'
    )
    && str_contains(
        $page,
        "['from_extra_seats']"
    )
    && str_contains(
        $page,
        "['to_extra_seats']"
    )
    && str_contains(
        $page,
        "['effective_at']"
    )
    && str_contains(
        $page,
        "['request_id']"
    ),
    'billing workspace displays centralized scheduled renewal reductions without changing current seat summary'
);
verify_billing_account(!str_contains($page, 'name="farm_id"') && !str_contains($page, 'name="amount"') && !str_contains($page, 'name="currency"'), 'browser checkout form cannot control tenant, amount or currency');
verify_billing_account(!str_contains($page, "\$_GET['farm_id']") && !str_contains($page, "\$_POST['farm_id']"), 'billing page has no browser-selected tenant scope');
verify_billing_account(
    str_contains(
        $checkout,
        'billing_commercial_attempt_reconcile_terminal_candidates_for_replacement('
    )
    && str_contains(
        $checkout,
        'billing_subscription_checkout_prepare('
    )
    && str_contains(
        $checkout,
        'billing_current_product_normal_statuses()'
    ),
    'normal Farm Admin checkout reconciles prior attempts then delegates authoritative renewal preparation'
);
verify_billing_account(
    str_contains(
        $checkout,
        "\$prepared['payment_quote']"
    ),
    'checkout consumes the payment quote returned by centralized preparation'
);
verify_billing_account(!str_contains($checkout, 'billing_pricing_build_payment_quote('), 'checkout does not rebuild a browser-selected product after current-product validation');
verify_billing_account(!str_contains($helper, 'UPDATE farms') && !str_contains($helper, 'INSERT INTO subscriptions') && !str_contains($helper, 'DELETE FROM'), 'account read model performs no commercial-state DML');
verify_billing_account(!str_contains($page, 'UPDATE farms') && !str_contains($page, 'INSERT INTO subscriptions') && !str_contains($page, 'billing_payment_attempt_create'), 'billing UI performs no direct commercial or payment-attempt DML');
verify_billing_account(str_contains($page, 'subscription_history') && str_contains($page, 'payment_attempts') && str_contains($page, 'seat_summary'), 'billing UI exposes subscription, payment and seat summaries from the read model');
verify_billing_account($navBridge !== '' && str_contains($init, "require_once __DIR__ . '/includes/platform_owner_nav_discoverability.php';"), 'shared navigation discoverability bridge is loaded centrally');
verify_billing_account(str_contains($navBridge, '$showBillingAccount = !$showPlatformTenantView') && str_contains($navBridge, "hasRole('farm_admin')"), 'Billing & Subscription navigation is limited to non-owner Farm Admin sessions');
verify_billing_account(str_contains($navBridge, '/billing/account.php') && str_contains($navBridge, 'Billing &amp; Subscription'), 'Farm Admin Account menu exposes the canonical billing workspace');

verify_billing_account(
    str_contains(
        $helper,
        "'seat_change_period_ready' =>"
    )
    && str_contains(
        $helper,
        '&& $seatChangePeriodReady'
    ),
    'billing read model keeps legacy/manual active tenants viewable when no paid commercial period exists'
);

verify_billing_account(
    str_contains(
        $page,
        '$seatChangePeriodReady'
    )
    && str_contains(
        $page,
        'Extra-seat purchases become available after a paid subscription period has been established.'
    )
    && str_contains(
        $page,
        'Seat reductions become available after a paid subscription period has been established.'
    ),
    'billing UI disables paid-period seat changes without hiding the overall billing workspace'
);

verify_billing_account(
    str_contains(
        $page,
        '<th>Subscription end</th><th>Paid period end</th>'
    )
    && str_contains(
        $page,
        "\$history['subscription_ends_at'] ?? null"
    )
    && str_contains(
        $page,
        "\$history['current_period_ends_at'] ?? null"
    ),
    'subscription history distinguishes administrative subscription end from authoritative paid-period end'
);
echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) exit(1);
echo "PASS: V2.3 tenant Billing & Subscription UI is tenant-pinned, same-product enforced, read-model driven and checkout-delegating.\n";
?>
