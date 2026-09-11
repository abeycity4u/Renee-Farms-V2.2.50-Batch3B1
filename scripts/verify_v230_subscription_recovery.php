<?php
/** Static contract verifier for V2.3 restricted subscription recovery. */
$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

function verify_recovery(bool $ok, string $message): void {
    global $failures, $checks;
    $checks++;
    if ($ok) {
        echo "PASS: {$message}\n";
    } else {
        $failures++;
        echo "FAIL: {$message}\n";
    }
}

function recovery_source(string $path): string {
    global $root;
    $source = @file_get_contents($root . '/' . $path);
    return is_string($source) ? $source : '';
}

$login = recovery_source('login.php');
$recovery = recovery_source('includes/subscription_recovery.php');
$actor = recovery_source('includes/billing_tenant_actor.php');
$currentProduct = recovery_source('includes/billing_current_product.php');
$quote = recovery_source('includes/billing_reactivation_quote.php');
$page = recovery_source('billing/recover.php');
$checkout = recovery_source('billing/checkout.php');
$return = recovery_source('billing/return.php');
$config = recovery_source('config.php');

$recoveryCompact = preg_replace('/\\s+/', '', $recovery);
$checkoutCompact = preg_replace('/\\s+/', '', $checkout);
$returnCompact = preg_replace('/\\s+/', '', $return);

verify_recovery($login !== '' && $recovery !== '' && $actor !== '' && $currentProduct !== '' && $quote !== '' && $page !== '', 'recovery and shared current-product source files are present');
verify_recovery(str_contains($login, "require __DIR__ . '/sign.php';"), 'normal sign-in remains delegated to the existing sign.php flow');
verify_recovery(str_contains($login, "require_rate_limit('subscription_recovery_attempt'"), 'recovery credential verification has an independent rate limit');
verify_recovery(str_contains($login, 'subscription_recovery_status_is_target'), 'login interception is limited to explicit recovery statuses');
verify_recovery(str_contains($login, 'subscription_recovery_is_farm_admin'), 'only the protected Farm Admin can enter recovery');
verify_recovery(
    str_contains(
        $recoveryCompact,
        "return['suspended','cancelled','past_due'];"
    ),
    'recovery target statuses are centralized, include past_due and exclude normal active access'
);
verify_recovery(str_contains($recovery, 'return 1800;'), 'recovery authentication is short-lived');
verify_recovery(str_contains($recovery, 'subscription_recovery_clear_normal_identity'), 'restricted recovery clears normal application identity');
verify_recovery(str_contains($recovery, 'session_regenerate_id(true)'), 'recovery authentication regenerates the session id');
verify_recovery(str_contains($recovery, 'subscription_recovery_promote_to_login') && str_contains($recovery, "['active']"), 'normal login promotion requires active commercial state');
verify_recovery(str_contains($config, 'function requireLogin()') && str_contains($config, "['suspended', 'cancelled']"), 'global requireLogin subscription boundary remains intact');
verify_recovery(!str_contains($page, 'requireLogin();'), 'recovery page does not weaken or invoke the normal operational login gate');
verify_recovery(str_contains($page, 'subscription_recovery_require'), 'recovery page uses the dedicated restricted authentication gate');
verify_recovery(!str_contains($page, 'name="farm_id"') && !str_contains($page, 'name="amount"') && !str_contains($page, 'name="currency"'), 'recovery browser form cannot control tenant, amount or currency');
verify_recovery(str_contains($page, 'billing_provider_selection_codes()') && str_contains($page, 'billing_provider_readiness_status'), 'recovery provider choices come from canonical provider readiness');
verify_recovery(str_contains($quote, "require_once __DIR__ . '/billing_current_product.php';"), 'reactivation wrapper explicitly loads the shared current-product contract');
verify_recovery(str_contains($currentProduct, "require_once __DIR__ . '/subscription_plan_catalog.php';"), 'shared current-product helper explicitly loads the canonical plan catalog dependency');
verify_recovery(str_contains($currentProduct, 'billing_pricing_build_payment_quote'), 'reactivation price comes from the shared server-authoritative current-product contract');
verify_recovery(str_contains($quote, 'billing_reactivation_assert_selection') && str_contains($quote, 'billing_current_product_assert_selection'), 'recovery keeps one centralized same-product assertion through the shared contract');
verify_recovery(
    str_contains(
        $checkoutCompact,
        'billing_require_farm_admin_actor($pdo,true,subscription_recovery_target_statuses());'
    ),
    'checkout explicitly opts into the centralized restricted recovery status contract'
);
verify_recovery(str_contains($checkout, 'billing_reactivation_assert_selection'), 'recovery checkout rejects plan/module/seat tampering');
verify_recovery(str_contains($checkout, "(int)\$actor['user_id']"), 'billing attempts record the authenticated actor rather than browser identity');
verify_recovery(!str_contains($checkout, 'requireLogin();'), 'checkout authorization is centralized in the billing actor helper');
verify_recovery(str_contains($actor, 'requireLogin();'), 'normal billing actors still pass through canonical requireLogin');
verify_recovery(
    str_contains(
        $returnCompact,
        "array_merge(subscription_recovery_target_statuses(),['active'])"
    )
    && str_contains(
        $returnCompact,
        'billing_require_farm_admin_actor($pdo,true,$recoveryReturnStatuses);'
    ),
    'return extends centralized recovery statuses with active only for the webhook-before-browser race'
);
verify_recovery(str_contains($return, 'initiated_by_user_id') && str_contains($return, "actor['user_id']"), 'recovery return pins the attempt to the authenticated recovery admin');
verify_recovery(str_contains($return, 'billing_provider_verify_payment'), 'return still performs fresh server-to-server provider verification');
verify_recovery(
    str_contains(
        $return,
        'billing_paid_attempt_dispatch'
    )
    && !str_contains(
        $return,
        'billing_subscription_apply_paid_attempt'
    ),
    'return uses the centralized paid-purpose dispatcher instead of bypassing purpose dispatch'
);

$dispatchPos = strpos(
    $return,
    'billing_paid_attempt_dispatch'
);

$commitPos = strpos(
    $return,
    '$pdo->commit();'
);

$promotionGuardPos = strpos(
    $return,
    "if (\$purpose === 'subscription' && \$recoveryMode)"
);

$promotePos = strpos(
    $return,
    'subscription_recovery_promote_to_login'
);

verify_recovery(
    $dispatchPos !== false
    && $commitPos !== false
    && $promotionGuardPos !== false
    && $promotePos !== false
    && $dispatchPos < $commitPos
    && $commitPos < $promotionGuardPos
    && $promotionGuardPos < $promotePos,
    'recovery identity is promoted only after committed paid-purpose dispatch and only for subscription purpose'
);
verify_recovery(!str_contains($page, 'UPDATE farms') && !str_contains($page, 'INSERT INTO subscriptions'), 'recovery UI performs no direct commercial-state DML');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) exit(1);
echo "PASS: V2.3 subscription recovery is restricted, tenant-pinned, same-product and billing-delegating.\n";
