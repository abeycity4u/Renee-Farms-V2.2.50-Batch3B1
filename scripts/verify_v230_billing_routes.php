<?php
/**
 * V2.3 Billing Stage 2F checkout/return/webhook route verifier.
 * Read-only, database-free and network-free.
 */

$root = dirname(__DIR__);
$paths = [
    'plans' => $root . '/includes/subscription_plan_catalog.php',
    'selection' => $root . '/includes/billing_provider_selection.php',
    'request' => $root . '/includes/billing_route_request.php',
    'audit' => $root . '/includes/billing_payment_audit_state.php',
    'actor' => $root . '/includes/billing_tenant_actor.php',
    'checkout' => $root . '/billing/checkout.php',
    'return' => $root . '/billing/return.php',
    'webhook' => $root . '/billing/webhook.php',
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

require_once $paths['plans'];
require_once $paths['selection'];
require_once $paths['request'];

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

$request = $source['request'];
$audit = $source['audit'];
$actor = $source['actor'];
$checkout = $source['checkout'];
$return = $source['return'];
$webhook = $source['webhook'];
$init = $source['init'];

$normalized = billing_route_normalize_checkout_input([
    'csrf_token' => 'contract-token',
    'plan_code' => ' Growth ',
    'billing_interval' => 'ANNUAL',
    'modules' => ['ruminant', 'poultry', 'poultry'],
    'seat_addons' => ['viewer' => '2', 'sales_rep' => 1],
    'provider' => ' Paystack ',
]);
$check(($normalized['plan_code'] ?? null) === 'growth'
    && ($normalized['billing_interval'] ?? null) === 'annual'
    && ($normalized['modules'] ?? null) === ['poultry', 'ruminant']
    && ($normalized['provider'] ?? null) === 'paystack',
    'checkout selection is canonicalized without a client amount/currency');
$check(($normalized['seat_addons']['viewer'] ?? null) === 2
    && ($normalized['seat_addons']['sales_rep'] ?? null) === 1
    && ($normalized['seat_addons']['poultry_manager'] ?? null) === 0,
    'checkout extra-seat quantities are normalized by the fixed commercial role set');

$reservedRejected = false;
try {
    billing_route_normalize_checkout_input([
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => ['poultry'],
        'amount' => '1.00',
    ]);
} catch (InvalidArgumentException $e) {
    $reservedRejected = str_contains($e->getMessage(), 'server controlled');
}
$check($reservedRejected,
    'client-supplied amount is rejected before pricing or provider activity');

$foreignFarmRejected = false;
try {
    billing_route_normalize_checkout_input([
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => ['poultry'],
        'farm_id' => 999999,
    ]);
} catch (InvalidArgumentException $e) {
    $foreignFarmRejected = true;
}
$check($foreignFarmRejected,
    'client-supplied farm id is rejected');

$unknownRejected = false;
try {
    billing_route_normalize_checkout_input([
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => ['poultry'],
        'unexpected' => 'x',
    ]);
} catch (InvalidArgumentException $e) {
    $unknownRejected = true;
}
$check($unknownRejected,
    'unknown checkout fields fail closed');

$badModuleRejected = false;
try {
    billing_route_normalize_checkout_input([
        'plan_code' => 'starter',
        'billing_interval' => 'monthly',
        'modules' => ['sales'],
    ]);
} catch (InvalidArgumentException $e) {
    $badModuleRejected = true;
}
$check($badModuleRejected,
    'shared Sales cannot enter checkout as a purchasable module');

$oldBase = getenv('BILLING_PUBLIC_BASE_URL');
putenv('BILLING_PUBLIC_BASE_URL=https://billing.example.test/app');
$publicUrl = billing_route_public_url('/billing/return.php', ['provider' => 'paystack']);
if ($oldBase === false) putenv('BILLING_PUBLIC_BASE_URL');
else putenv('BILLING_PUBLIC_BASE_URL=' . $oldBase);
$check($publicUrl === 'https://billing.example.test/app/billing/return.php?provider=paystack',
    'provider return URL comes from the configured HTTPS public application base');

$oldBase = getenv('BILLING_PUBLIC_BASE_URL');
putenv('BILLING_PUBLIC_BASE_URL=http://billing.example.test');
$httpBaseRejected = false;
try {
    billing_route_public_base_url();
} catch (RuntimeException $e) {
    $httpBaseRejected = true;
}
if ($oldBase === false) putenv('BILLING_PUBLIC_BASE_URL');
else putenv('BILLING_PUBLIC_BASE_URL=' . $oldBase);
$check($httpBaseRejected,
    'non-HTTPS billing public base URL is rejected');

$referenceA = billing_route_provider_reference(7, 'paystack');
$referenceB = billing_route_provider_reference(7, 'paystack');
$check($referenceA !== $referenceB
    && preg_match('/^rf-7-paystack-[a-f0-9]{32}$/', $referenceA) === 1,
    'provider references are tenant/provider scoped and cryptographically random');

$check(strpos($request, 'BILLING_PUBLIC_BASE_URL') !== false
    && strpos($request, 'HTTP_HOST') === false,
    'billing callback origin never trusts the request Host header');
$check(strpos($request, "'amount'") !== false
    && strpos($request, "'currency'") !== false
    && strpos($request, "'farm_id'") !== false,
    'route helper explicitly reserves billing-sensitive browser keys');
$check(strpos($request, '1048576') !== false
    && strpos($request, "file_get_contents('php://input'") !== false,
    'webhook raw-body helper enforces the one-MiB payload boundary');

$check(
    strpos(
        $checkout,
        'billing_require_farm_admin_actor('
    ) !== false
    && strpos(
        $checkout,
        'subscription_recovery_target_statuses()'
    ) !== false
    && strpos(
        $actor,
        'requireLogin();'
    ) !== false
    && strpos(
        $actor,
        'requireCurrentFarmId();'
    ) !== false
    && strpos(
        $actor,
        'isPlatformOwner()'
    ) !== false
    && strpos(
        $actor,
        "hasRole('farm_admin')"
    ) !== false,
    'checkout enters through the centralized Farm Admin/recovery actor boundary'
);
$check(strpos($checkout, 'require_valid_csrf_post();') !== false,
    'checkout is POST/CSRF protected through the central helper');
$check(
    strpos(
        $checkout,
        "\$farmId = (int)\$actor['farm_id'];"
    ) !== false
    && strpos(
        $actor,
        '$farmId = requireCurrentFarmId();'
    ) !== false,
    'checkout farm identity comes from the centralized authenticated tenant actor'
);
$check(strpos($checkout, "\$_POST['amount']") === false
    && strpos($checkout, "\$_POST['currency']") === false
    && strpos($checkout, "\$_POST['farm_id']") === false,
    'checkout route never reads client amount, currency or farm id directly');
$check(strpos($checkout, "['contact_email']") !== false
    && strpos($checkout, 'FILTER_VALIDATE_EMAIL') !== false,
    'checkout customer email comes from the current farm profile and is validated');
$check(
    strpos(
        $checkout,
        'billing_current_product_assert_selection('
    ) !== false
    && strpos(
        $checkout,
        'billing_reactivation_assert_selection('
    ) !== false
    && preg_match(
        '/\$pricing\s*=\s*\$currentProduct\s*\[\s*[\'"]pricing[\'"]\s*\]\s*;/',
        $checkout
    ) === 1
    && strpos(
        $checkout,
        "\$pricing['amount']"
    ) !== false
    && strpos(
        $checkout,
        "\$pricing['currency']"
    ) !== false,
    'attempt amount/currency come from server-authoritative current-product pricing'
);
$check(strpos($checkout, 'billing_provider_readiness_resolve_checkout(') !== false
    && strpos($checkout, 'billing_provider_register_configured_adapters($provider)') !== false,
    'checkout resolves and registers exactly the selected mode-ready provider');

$callbackPos = strpos($checkout, 'billing_route_public_url(');
$createPos = strpos($checkout, 'billing_payment_attempt_create(');
$providerInitPos = strpos($checkout, 'billing_provider_initialize_checkout(');
$check($callbackPos !== false && $createPos !== false && $callbackPos < $createPos,
    'callback configuration is resolved before any billing attempt row is created');
$check($createPos !== false && $providerInitPos !== false && $createPos < $providerInitPos,
    'billing attempt is frozen before provider checkout initialization');
$check(strpos($checkout, 'billing_audit_mark_pending(') !== false
    && strpos($checkout, 'billing_audit_mark_initialization_failed(') !== false,
    'checkout records provider initialization success/failure only on the billing attempt');
$check(strpos($checkout, "header('Location: ' . \$checkout['checkout_url'], true, 303)") !== false,
    'successful checkout uses a 303 redirect to the normalized provider URL');

$check(strpos($return, "REQUEST_METHOD") !== false
    && strpos($return, "!== 'GET'") !== false,
    'provider return route accepts GET only');
$check(
    strpos(
        $return,
        'billing_require_farm_admin_actor('
    ) !== false
    && preg_match(
        '/\$farmId\s*=\s*\(int\)\s*\$actor\s*\[\s*[\'"]farm_id[\'"]\s*\]\s*;/',
        $return
    ) === 1
    && strpos(
        $actor,
        'requireCurrentFarmId();'
    ) !== false
    && strpos(
        $actor,
        "hasRole('farm_admin')"
    ) !== false,
    'provider return verification is scoped through the centralized tenant Farm Admin/recovery actor'
);
$check(strpos($return, "\$_GET['status']") === false
    && strpos($return, "\$_GET['amount']") === false
    && strpos($return, "\$_GET['currency']") === false
    && strpos($return, "\$_GET['transaction_id']") === false,
    'return route ignores browser/provider redirect claims about status, amount and transaction id');
$check(strpos($return, 'billing_audit_attempt_by_reference($pdo, $provider, $providerReference, $farmId, false)') !== false,
    'return reference must already belong to the current tenant');
$returnVerifyPos = strpos($return, 'billing_provider_verify_payment(');
$returnApplyPos = strpos($return, 'billing_audit_apply_verification(');
$check($returnVerifyPos !== false && $returnApplyPos !== false && $returnVerifyPos < $returnApplyPos,
    'return route performs fresh provider verification before applying audit state');
$check(strpos($return, 'beginTransaction()') !== false
    && strpos($return, ', $farmId, true)') !== false,
    'return applies verified payment under a tenant-scoped row lock/transaction');

$check(strpos($webhook, "REQUEST_METHOD") !== false
    && strpos($webhook, "!== 'POST'") !== false,
    'webhook route accepts POST only');
$check(strpos($webhook, 'requireLogin(') === false
    && strpos($webhook, 'require_valid_csrf_post(') === false,
    'provider webhook does not depend on browser session authorization or CSRF');
$check(strpos($webhook, 'billing_route_raw_body(1048576)') !== false
    && strpos($webhook, 'billing_route_request_headers()') !== false,
    'webhook uses bounded raw body plus normalized request headers');
$webhookAuthPos = strpos($webhook, 'billing_provider_verify_webhook(');
$eventRegisterPos = strpos($webhook, 'billing_provider_event_register(');
$check($webhookAuthPos !== false && $eventRegisterPos !== false && $webhookAuthPos < $eventRegisterPos,
    'webhook authenticity is verified before any provider event is persisted');
$check(strpos($webhook, 'billing_audit_event_terminal(') !== false,
    'duplicate already-processed provider events short-circuit idempotently');
$check(strpos($webhook, "billing_audit_event_mark(\$pdo, \$registeredEventId, 'ignored')") !== false,
    'authentic unlinked provider events are retained as ignored rather than applied');
$webhookVerifyPos = strpos($webhook, 'billing_provider_verify_payment(');
$webhookApplyPos = strpos($webhook, 'billing_audit_apply_verification(');
$check($eventRegisterPos !== false
    && $webhookVerifyPos !== false
    && $webhookApplyPos !== false
    && $eventRegisterPos < $webhookVerifyPos
    && $webhookVerifyPos < $webhookApplyPos,
    'webhook event registration is followed by fresh provider verification before attempt update');
$check(strpos($webhook, "billing_audit_event_mark(\$pdo, \$registeredEventId, 'processed')") !== false
    && strpos($webhook, 'beginTransaction()') !== false,
    'linked verified attempt update and event processing are transactionally paired');

$check(strpos($audit, 'Verified provider amount does not match the frozen billing attempt.') !== false
    && strpos($audit, 'Verified provider currency does not match the frozen billing attempt.') !== false
    && strpos($audit, 'Provider verification returned a different billing reference.') !== false,
    'audit transition rejects amount, currency and reference mismatches');
$check(strpos($audit, "if (\$current === 'paid' && \$incoming !== 'refunded') return 'paid';") !== false
    && strpos($audit, "if (\$current === 'refunded') return 'refunded';") !== false,
    'paid/refunded attempts cannot be silently downgraded by stale provider states');
$check(strpos($audit, 'FOR UPDATE') !== false,
    'audit state exposes row-lock reads for transactional route processing');

$combinedStage2F = $request . "\n" . $audit . "\n" . $checkout . "\n" . $return . "\n" . $webhook;
$protectedDml = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:farms|farm_modules|farm_role_limits|farm_subscription_seat_addons|subscriptions)\b/i';
$check(!preg_match($protectedDml, $combinedStage2F),
    'route/audit layer still contains no direct subscription or entitlement DML');
$check(
    strpos(
        $combinedStage2F,
        'subscription_record_capture('
    ) === false
    && strpos(
        $combinedStage2F,
        'farm_entitlement_set'
    ) === false
    && strpos(
        $combinedStage2F,
        'billing_subscription_apply_paid_attempt('
    ) === false
    && substr_count(
        $return,
        'billing_paid_attempt_dispatch('
    ) === 1
    && substr_count(
        $webhook,
        'billing_paid_attempt_dispatch('
    ) === 1,
    'verified-payment routes do not bypass the centralized paid-purpose dispatcher'
);
$check(strpos($audit, 'UPDATE billing_payment_attempts') !== false
    && strpos($audit, 'UPDATE billing_provider_events') !== false,
    'Stage 2F audit helper state mutation remains limited to the billing audit tables');
$check(strpos($init, 'billing/checkout.php') === false
    && strpos($init, 'billing/webhook.php') === false
    && strpos($init, 'billing_payment_audit_state.php') === false,
    'billing routes/state are not globally executed through init.php');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2F route contract is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing route security remains tenant-bound, server-price-authoritative, provider-verified and idempotent under Stage 2H/2I wiring.\n";
echo "NOTE: Stage 2I additionally fail-closes new checkout behind mode-specific deployment readiness.\n";
