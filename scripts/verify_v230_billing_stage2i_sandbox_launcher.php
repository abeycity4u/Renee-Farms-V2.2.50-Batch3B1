<?php
/**
 * Offline verifier for the V2.3 Billing Stage 2I provider sandbox launcher.
 * No database connection, provider credential, or network call is required.
 */

$root = dirname(__DIR__);
$launcherPath = $root . '/billing/sandbox_checkout.php';
$launcher = is_file($launcherPath) ? file_get_contents($launcherPath) : false;

$checks = 0;
$failures = 0;

$check = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }
    $failures++;
    echo 'FAIL: ' . $label . PHP_EOL;
};

$check(is_string($launcher) && $launcher !== '', 'sandbox checkout launcher source is present');
if (!is_string($launcher)) $launcher = '';

$check(
    str_contains($launcher, "'/includes/billing_payment_foundation.php'"),
    'launcher explicitly loads the billing payment foundation before building its priced quote'
);
$check(str_contains($launcher, 'requireLogin();'), 'launcher requires an authenticated session');
$check(str_contains($launcher, '$farmId = requireCurrentFarmId();'), 'launcher resolves the current tenant farm from the session');
$check(
    str_contains($launcher, "isPlatformOwner() || !hasRole('farm_admin')"),
    'launcher blocks Platform Owner and non-Farm-Admin accounts'
);
$check(
    str_contains($launcher, "billing_provider_payment_mode() !== 'test'"),
    'launcher is unavailable outside explicit billing test mode'
);
$check(
    str_contains($launcher, "BILLING_SANDBOX_LAUNCHER_ENABLED') !== '1'"),
    'launcher requires the exact sandbox enable flag'
);
$check(
    str_contains($launcher, "billing_provider_readiness_env_value('BILLING_SANDBOX_QA_FARM_ID')"),
    'launcher requires a deployment-selected QA farm id'
);
$check(
    str_contains($launcher, '(int)$qaFarmId !== $farmId'),
    'launcher refuses a logged-in tenant that does not match the designated QA farm'
);
$check(
    str_contains($launcher, "billing_provider_readiness_env_value('BILLING_SANDBOX_QA_PROVIDER')"),
    'launcher requires an explicit deployment-selected sandbox provider'
);
$check(
    str_contains($launcher, 'in_array($provider, billing_provider_selection_codes(), true)'),
    'launcher accepts only providers from the canonical provider-selection catalog'
);
$check(
    str_contains($launcher, 'billing_provider_selection_definition($provider)'),
    'launcher derives the provider display label from the canonical provider definition'
);
$check(
    str_contains($launcher, 'billing_provider_readiness_status($provider, true)'),
    'launcher requires full selected-provider test readiness including webhook configuration'
);
$check(
    str_contains($launcher, "(\$readiness['mode'] ?? null) !== 'test'")
        && str_contains($launcher, "(\$readiness['ready'] ?? false) !== true"),
    'launcher fails closed unless selected-provider readiness is green in test mode'
);
$check(
    !str_contains($launcher, "\$_GET['provider']")
        && !str_contains($launcher, "\$_POST['provider']")
        && !preg_match('/<select[^>]+name=["\']provider["\']/i', $launcher),
    'sandbox provider cannot be selected or overridden by the browser'
);
$check(
    str_contains($launcher, 'filter_var($customerEmail, FILTER_VALIDATE_EMAIL)'),
    'launcher requires the same valid farm contact email needed by checkout'
);
$check(
    str_contains($launcher, "array_intersect(['poultry', 'ruminant'], farm_entitlement_modules(\$pdo, \$farmId))"),
    'launcher preserves the current tenant Poultry/Ruminant entitlement bundle'
);
$check(
    str_contains($launcher, "['starter', 'growth', 'pro']")
        && str_contains($launcher, 'for ($i = (int)$currentIndex; $i < count($planOrder); $i++)'),
    'launcher keeps the current valid plan or moves upward rather than silently downgrading it'
);
$check(
    str_contains($launcher, 'subscription_seat_load_addons($pdo, $farmId, $currentPlan, $modules)'),
    'launcher preserves existing purchased or implied seat add-ons'
);
$check(
    substr_count($launcher, 'subscription_seat_assert_capacity(') >= 2,
    'launcher preflights assigned-user capacity before checkout'
);
$check(
    str_contains($launcher, 'subscription_seat_used_role_counts($pdo, $farmId)')
        && str_contains($launcher, 'subscription_plan_included_role_limits($selectedPlan, $modules)'),
    'launcher adds only the seat shortfall if even Pro requires more capacity'
);
$check(
    str_contains($launcher, 'billing_pricing_build_payment_quote($selectedPlan, $billingInterval, $modules, $seatAddOns)'),
    'launcher displays a server-authoritative price-book quote'
);
$check(str_contains($launcher, '<?= csrf_field() ?>'), 'launcher uses the canonical CSRF token field');
$check(
    str_contains($launcher, "BASE_URL . '/billing/checkout.php'"),
    'launcher delegates payment initiation to the hardened checkout route'
);
$check(
    str_contains($launcher, 'name="provider" value="<?= $providerHtml ?>"'),
    'launcher submits exactly the deployment-selected canonical provider to checkout'
);
$check(
    !str_contains($launcher, 'name="provider" value="paystack"')
        && !str_contains($launcher, 'name="provider" value="flutterwave"'),
    'launcher contains no hard-coded provider submission and can drive either approved sandbox provider'
);
$check(
    str_contains($launcher, 'name="billing_interval" value="monthly"'),
    'launcher fixes provider sandbox purchases to a monthly term'
);
$check(
    !preg_match("~name=[\"'](?:amount|currency|farm_id|user_id|status|subscription_status)[\"']~i", $launcher),
    'launcher does not submit billing-sensitive amount, currency, farm, user or status fields'
);
$check(
    !str_contains($launcher, 'billing_provider_initialize_checkout(')
        && !str_contains($launcher, 'billing_http_request(')
        && !str_contains($launcher, 'curl_init('),
    'launcher itself performs no provider network call'
);
$check(
    !preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+[a-z_]|DELETE\s+FROM)\b/i', $launcher),
    'launcher itself performs no billing or commercial DML'
);
$check(
    !str_contains($launcher, 'PAYSTACK_LIVE_SECRET_KEY')
        && !str_contains($launcher, 'FLUTTERWAVE_LIVE_SECRET_KEY')
        && !str_contains($launcher, 'BILLING_LIVE_PAYMENTS_ENABLED'),
    'launcher does not reference live provider credentials or live-payment opt-in'
);
$check(
    str_contains($launcher, 'Disable the sandbox launcher environment flags after Stage 2I provider QA.'),
    'launcher explicitly reminds operators to disable temporary sandbox gates after provider QA'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
if ($failures === 0) {
    echo 'PASS: V2.3 Billing Stage 2I sandbox launcher is test-only, tenant-pinned, provider-pinned, capacity-safe and checkout-delegating.' . PHP_EOL;
    exit(0);
}

echo 'FAIL: V2.3 Billing Stage 2I sandbox launcher contract is not closed.' . PHP_EOL;
exit(1);
