<?php
/**
 * V2.3 Billing Stage 2H verified-payment integration verifier.
 * Database-free and network-free.
 */

$root = dirname(__DIR__);
$paths = [
    'return' => $root . '/billing/return.php',
    'webhook' => $root . '/billing/webhook.php',
    'checkout' => $root . '/billing/checkout.php',
    'audit' => $root . '/includes/billing_payment_audit_state.php',
    'application' => $root . '/includes/billing_subscription_application.php',
    'init' => $root . '/init.php',
];
$source = [];
foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL: missing {$path}\n");
        exit(1);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }
    $source[$key] = $content;
}

$checks = 0;
$failures = 0;
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$message}\n";
};

$return = $source['return'];
$webhook = $source['webhook'];
$checkout = $source['checkout'];
$audit = $source['audit'];
$app = $source['application'];

$check(strpos($return, "billing_subscription_application.php") !== false,
    'return route loads the proven Stage 2G application service explicitly');
$check(strpos($webhook, "billing_subscription_application.php") !== false,
    'webhook route loads the proven Stage 2G application service explicitly');
$check(strpos($checkout, 'billing_subscription_apply_paid_attempt(') === false,
    'checkout initialization still cannot activate a subscription');
$check(strpos($source['init'], 'billing_subscription_application.php') === false,
    'subscription application is not globally loaded through init.php');

$checkoutCapacity = strpos($checkout, 'subscription_seat_assert_capacity(');
$checkoutAttemptCreate = strpos($checkout, 'billing_payment_attempt_create(');
$checkoutProviderInit = strpos($checkout, 'billing_provider_initialize_checkout(');
$check($checkoutCapacity !== false
    && $checkoutAttemptCreate !== false
    && $checkoutProviderInit !== false
    && $checkoutCapacity < $checkoutAttemptCreate
    && $checkoutCapacity < $checkoutProviderInit,
    'checkout rejects under-capacity plan/seat selections before attempt creation or provider initialization');

$returnVerify = strpos($return, 'billing_provider_verify_payment(');
$returnBegin = strpos($return, 'beginTransaction()');
$returnAudit = strpos($return, 'billing_audit_apply_verification(');
$returnPaidGate = strpos($return, "=== 'paid'");
$returnApp = strpos($return, 'billing_subscription_apply_paid_attempt(');
$returnCommit = strpos($return, '$pdo->commit();');
$check($returnVerify !== false && $returnBegin !== false && $returnAudit !== false
    && $returnApp !== false && $returnCommit !== false
    && $returnVerify < $returnBegin && $returnBegin < $returnAudit
    && $returnAudit < $returnApp && $returnApp < $returnCommit,
    'return path verifies provider, updates audit, applies subscription and commits in that order');
$check($returnPaidGate !== false && $returnPaidGate < $returnApp,
    'return path gates subscription application on the canonical paid audit status');
$check(strpos($return, ', $farmId, true)') !== false,
    'return path retains tenant-scoped attempt locking before activation');
$check(strpos($return, "\$_GET['status']") === false
    && strpos($return, "\$_GET['amount']") === false
    && strpos($return, "\$_GET['currency']") === false,
    'return path still ignores browser payment-status and amount claims');
$check(strpos($return, "Payment verified and subscription activated successfully.") !== false,
    'return success message reflects completed application rather than pending activation');
$check(strpos($return, 'rollBack()') !== false,
    'return path rolls back provider audit and subscription application together on failure');

$webhookAuth = strpos($webhook, 'billing_provider_verify_webhook(');
$eventRegister = strpos($webhook, 'billing_provider_event_register(');
$webhookVerify = strpos($webhook, 'billing_provider_verify_payment(');
$webhookBegin = strpos($webhook, 'beginTransaction()');
$webhookAudit = strpos($webhook, 'billing_audit_apply_verification(');
$webhookPaidGate = strpos($webhook, "=== 'paid'");
$webhookApp = strpos($webhook, 'billing_subscription_apply_paid_attempt(');
$eventProcessed = strpos($webhook, "billing_audit_event_mark(\$pdo, \$registeredEventId, 'processed')");
$webhookCommit = strpos($webhook, '$pdo->commit();');
$check($webhookAuth !== false && $eventRegister !== false && $webhookVerify !== false
    && $webhookBegin !== false && $webhookAudit !== false && $webhookApp !== false
    && $eventProcessed !== false && $webhookCommit !== false
    && $webhookAuth < $eventRegister && $eventRegister < $webhookVerify
    && $webhookVerify < $webhookBegin && $webhookBegin < $webhookAudit
    && $webhookAudit < $webhookApp && $webhookApp < $eventProcessed
    && $eventProcessed < $webhookCommit,
    'webhook path authenticates, re-verifies, applies paid state, marks event processed and commits in order');
$check($webhookPaidGate !== false && $webhookPaidGate < $webhookApp,
    'webhook path gates subscription application on the canonical paid audit status');
$check(strpos($webhook, 'billing_audit_event_terminal(') !== false,
    'already processed or ignored webhook events remain idempotent short-circuits');
$check(strpos($audit, "['processed', 'ignored']") !== false
    && strpos($audit, "'failed'") !== false,
    'failed webhook events remain non-terminal and therefore retryable');
$check(strpos($webhook, 'rollBack()') !== false
    && strpos($webhook, "'failed'") !== false,
    'webhook application failures roll back transaction state and retain retryable failure audit');

$check(strpos($app, '$startedTransaction = !$pdo->inTransaction();') !== false,
    'Stage 2G application joins an existing route transaction instead of committing independently');
$check(strpos($app, 'if ($startedTransaction) $pdo->commit();') !== false,
    'Stage 2G application owns commit only when it started the transaction itself');
$check(strpos($app, 'applied_subscription_record_id IS NULL') !== false
    && strpos($app, "'idempotent' => true") !== false,
    'Stage 2G exactly-once marker and idempotent replay behavior remain intact');
$check(strpos($app, "strtolower(trim((string)(\$attempt['status'] ?? ''))) !== 'paid'") !== false,
    'application service itself still refuses non-paid attempts as a second gate');
$check(strpos($app, 'billing_subscription_application_ready($pdo)') !== false,
    'application still fails closed unless transactional storage is ready');

$combinedRoutes = $return . "\n" . $webhook;
$directCommercialDml = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions)\b/i';
$check(!preg_match($directCommercialDml, $combinedRoutes),
    'Stage 2H routes delegate commercial mutation to the single Stage 2G service instead of duplicating DML');
$check(substr_count($return, 'billing_subscription_apply_paid_attempt(') === 1,
    'return route has exactly one subscription-application call site');
$check(substr_count($webhook, 'billing_subscription_apply_paid_attempt(') === 1,
    'webhook route has exactly one subscription-application call site');
$check(strpos($return, 'billing_provider_initialize_checkout(') === false
    && strpos($webhook, 'billing_provider_initialize_checkout(') === false,
    'verification/application routes never initialize a second provider checkout');
$check(strpos($webhook, 'requireLogin(') === false,
    'webhook activation remains session-independent and provider-authenticated');
$check(strpos($return, 'requireLogin();') !== false
    && strpos($return, "hasRole('farm_admin')") !== false,
    'browser return activation remains authenticated Farm Admin scoped');

$check(strpos($return, 'billing_subscription_apply_paid_attempt($pdo, (int)$locked[\'id\'])') !== false,
    'return applies exactly the locked verified billing attempt');
$check(strpos($webhook, 'billing_subscription_apply_paid_attempt($pdo, (int)$locked[\'id\'])') !== false,
    'webhook applies exactly the locked verified billing attempt');
$check(strpos($webhook, 'Provider payment verification could not be completed.') !== false,
    'provider re-verification failure is recorded without attempting subscription application');
$check(strpos($webhook, 'Verified provider fact or subscription application could not be applied safely.') !== false,
    'webhook failure audit distinguishes safe application failure without exposing internal details');

$check(strpos($return, 'curl_') === false && strpos($webhook, 'curl_') === false,
    'routes continue to use provider adapters rather than embedding network transport details');
$check(strpos($return, 'sync_farm_entitlements(') === false
    && strpos($webhook, 'sync_farm_entitlements(') === false,
    'routes cannot bypass the Stage 2G canonical entitlement application service');


echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2H verified-payment integration is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2H wires freshly verified paid facts to exactly-once subscription application atomically.\n";
echo "NOTE: this verifier is offline; provider credentials and live provider endpoint compatibility remain separately controlled.\n";
