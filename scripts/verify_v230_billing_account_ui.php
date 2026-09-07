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

$helper = billing_account_source('includes/billing_account_overview.php');
$page = billing_account_source('billing/account.php');
$navBridge = billing_account_source('includes/platform_owner_nav_discoverability.php');
$init = billing_account_source('init.php');

verify_billing_account($helper !== '' && $page !== '', 'billing account helper and page are present');
verify_billing_account(str_contains($helper, "require_once __DIR__ . '/billing_payment_foundation.php';"), 'account read model loads billing payment foundation explicitly');
verify_billing_account(str_contains($helper, "require_once __DIR__ . '/billing_pricing_contract.php';"), 'account read model loads canonical pricing contract explicitly');
verify_billing_account(str_contains($helper, "require_once __DIR__ . '/subscription_record.php';"), 'account read model loads commercial subscription history service explicitly');
verify_billing_account(str_contains($helper, "require_once __DIR__ . '/subscription_seat_policy.php';"), 'account read model loads canonical seat policy explicitly');
verify_billing_account(str_contains($helper, 'billing_tenant_actor_farm'), 'account read model resolves the tenant through the centralized billing farm helper');
verify_billing_account(str_contains($helper, 'subscription_record_commercial_modules'), 'account read model uses canonical commercial module resolution');
verify_billing_account(str_contains($helper, 'subscription_record_latest') && str_contains($helper, 'subscription_record_history'), 'account read model uses canonical subscription latest/history helpers');
verify_billing_account(str_contains($helper, 'subscription_seat_load_addons') && str_contains($helper, 'subscription_seat_load_effective_limits') && str_contains($helper, 'subscription_seat_used_role_counts'), 'account read model uses canonical seat add-on, limit and usage helpers');
verify_billing_account(str_contains($helper, 'billing_pricing_build_payment_quote'), 'current renewal price comes from server-authoritative pricing');
verify_billing_account(str_contains($helper, 'WHERE farm_id = ?'), 'payment attempt history is tenant-pinned');
verify_billing_account(!str_contains($helper, 'provider_reference') && !str_contains($helper, 'provider_transaction_id'), 'tenant payment history excludes provider references and transaction identifiers');
verify_billing_account(str_contains($page, 'billing_require_farm_admin_actor($pdo, false)'), 'billing account requires a normal Farm Admin billing actor');
verify_billing_account(!str_contains($page, 'subscription_recovery_') && !str_contains($page, 'allowRecovery'), 'billing account does not accept restricted recovery authentication');
verify_billing_account(str_contains($page, 'billing_provider_selection_codes()') && str_contains($page, 'billing_provider_readiness_status'), 'provider choices come from canonical provider selection/readiness');
verify_billing_account(str_contains($page, "BASE_URL . '/billing/checkout.php'"), 'renewal delegates to the canonical checkout route');
verify_billing_account(str_contains($page, 'csrf_field()'), 'renewal checkout form includes centralized CSRF protection');
verify_billing_account(!str_contains($page, 'name="farm_id"') && !str_contains($page, 'name="amount"') && !str_contains($page, 'name="currency"'), 'browser checkout form cannot control tenant, amount or currency');
verify_billing_account(!str_contains($page, "\$_GET['farm_id']") && !str_contains($page, "\$_POST['farm_id']"), 'billing page has no browser-selected tenant scope');
verify_billing_account(!str_contains($helper, 'UPDATE farms') && !str_contains($helper, 'INSERT INTO subscriptions') && !str_contains($helper, 'DELETE FROM'), 'account read model performs no commercial-state DML');
verify_billing_account(!str_contains($page, 'UPDATE farms') && !str_contains($page, 'INSERT INTO subscriptions') && !str_contains($page, 'billing_payment_attempt_create'), 'billing UI performs no direct commercial or payment-attempt DML');
verify_billing_account(str_contains($page, 'subscription_history') && str_contains($page, 'payment_attempts') && str_contains($page, 'seat_summary'), 'billing UI exposes subscription, payment and seat summaries from the read model');
verify_billing_account($navBridge !== '' && str_contains($init, "require_once __DIR__ . '/includes/platform_owner_nav_discoverability.php';"), 'shared navigation discoverability bridge is loaded centrally');
verify_billing_account(str_contains($navBridge, '$showBillingAccount = !$showPlatformTenantView') && str_contains($navBridge, "hasRole('farm_admin')"), 'Billing & Subscription navigation is limited to non-owner Farm Admin sessions');
verify_billing_account(str_contains($navBridge, '/billing/account.php') && str_contains($navBridge, 'Billing &amp; Subscription'), 'Farm Admin Account menu exposes the canonical billing workspace');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) exit(1);
echo "PASS: V2.3 tenant Billing & Subscription UI is tenant-pinned, read-model driven and checkout-delegating.\n";
?>
