<?php
/**
 * V2.3 Billing Stage 2H rollback-only integration runtime verifier.
 *
 * Requires normal DB environment variables. Makes no provider/network call.
 * All temporary attempt/event/commercial mutations occur inside one outer
 * transaction and are rolled back completely.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';
require_once dirname(__DIR__) . '/includes/billing_currency_policy.php';
require_once dirname(__DIR__) . '/includes/billing_pricing_contract.php';
require_once dirname(__DIR__) . '/includes/billing_payment_audit_state.php';
require_once dirname(__DIR__) . '/includes/billing_subscription_application.php';

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

function stage2h_farm_state(PDO $pdo, int $farmId): array
{
    $stmt = $pdo->prepare(
        'SELECT subscription_plan, subscription_status, subscription_starts_at, subscription_ends_at '
        . 'FROM farms WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$farmId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function stage2h_modules(PDO $pdo, int $farmId): array
{
    $modules = subscription_record_commercial_modules($pdo, $farmId);
    sort($modules, SORT_STRING);
    return $modules;
}

function stage2h_addons(PDO $pdo, int $farmId, array $farm, array $modules): array
{
    $addons = subscription_seat_load_addons(
        $pdo,
        $farmId,
        strtolower(trim((string)($farm['subscription_plan'] ?? 'starter'))),
        $modules
    );
    $addons = subscription_seat_normalize_addons($addons);
    ksort($addons, SORT_STRING);
    return $addons;
}

function stage2h_limits(PDO $pdo, int $farmId): array
{
    $limits = subscription_seat_load_effective_limits($pdo, $farmId);
    ksort($limits, SORT_STRING);
    return $limits;
}

function stage2h_count(PDO $pdo, string $table, ?int $farmId = null): int
{
    $allowed = ['billing_payment_attempts', 'billing_provider_events', 'subscriptions', 'users'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported Stage 2H count table.');
    }
    if ($farmId === null || $table === 'billing_provider_events') {
        return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE farm_id = ?');
    $stmt->execute([$farmId]);
    return (int)$stmt->fetchColumn();
}

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "FAIL: PDO connection is unavailable.\n");
    exit(1);
}

$check(billing_subscription_application_ready($pdo),
    'Stage 2H subscription application storage reports transactionally ready');

$farmId = (int)$pdo->query("SELECT id FROM farms WHERE slug <> 'owner' ORDER BY id LIMIT 1")->fetchColumn();
$check($farmId > 0,
    'a tenant farm exists for the rollback-only Stage 2H probe');
if ($farmId < 1 || $failures > 0) {
    echo "\n{$checks} checks, {$failures} failure(s).\n";
    exit(1);
}

$residueStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM billing_payment_attempts WHERE provider = 'paystack' AND provider_reference LIKE 'qa-stage2h-%'"
);
$residueStmt->execute();
$beforeResidue = (int)$residueStmt->fetchColumn();
$check($beforeResidue === 0,
    'no previous qa-stage2h payment-attempt residue exists before the probe');
if ($beforeResidue !== 0) {
    echo "Refusing to delete or overwrite existing qa-stage2h rows.\n";
    echo "\n{$checks} checks, {$failures} failure(s).\n";
    exit(1);
}

$baselineFarm = stage2h_farm_state($pdo, $farmId);
$baselineModules = stage2h_modules($pdo, $farmId);
$baselineAddons = stage2h_addons($pdo, $farmId, $baselineFarm, $baselineModules);
$baselineLimits = stage2h_limits($pdo, $farmId);
$baselineUsers = stage2h_count($pdo, 'users', $farmId);
$baselineSubscriptions = stage2h_count($pdo, 'subscriptions', $farmId);
$baselineAttempts = stage2h_count($pdo, 'billing_payment_attempts', $farmId);
$baselineEvents = stage2h_count($pdo, 'billing_provider_events');

$reference = 'qa-stage2h-' . bin2hex(random_bytes(10));
$eventIdValue = 'qa-stage2h-event-' . bin2hex(random_bytes(10));
$attemptId = 0;
$subscriptionId = 0;
$eventRowId = 0;
$rolledBack = false;
$runtimeError = null;

try {
    $pdo->beginTransaction();

    $used = subscription_seat_used_role_counts($pdo, $farmId);
    $baseLimits = subscription_plan_effective_role_limits('pro', ['poultry', 'ruminant'], []);
    $seatAddOns = [];
    foreach (subscription_seat_roles() as $role => $_label) {
        $seatAddOns[$role] = max(0, (int)($used[$role] ?? 0) - (int)($baseLimits[$role] ?? 0));
    }
    $seatAddOns = subscription_seat_normalize_addons($seatAddOns);
    ksort($seatAddOns, SORT_STRING);

    $priced = billing_pricing_build_payment_quote('pro', 'monthly', ['poultry', 'ruminant'], $seatAddOns);
    $pricing = $priced['pricing'];

    $created = billing_payment_attempt_create(
        $pdo,
        $farmId,
        'paystack',
        $reference,
        $pricing['plan_code'],
        $pricing['billing_interval'],
        $pricing['amount'],
        $pricing['currency'],
        $pricing['modules'],
        $pricing['seat_addons'],
        null
    );
    $attemptId = (int)($created['id'] ?? 0);
    $check(($created['inserted'] ?? null) === true && $attemptId > 0,
        'Stage 2H probe creates one frozen temporary billing attempt');

    $event = billing_provider_event_register(
        $pdo,
        'paystack',
        $eventIdValue,
        'charge.success',
        '{"stage":"2h","synthetic":true}',
        $attemptId
    );
    $eventRowId = (int)($event['id'] ?? 0);
    $check(($event['inserted'] ?? null) === true && $eventRowId > 0,
        'Stage 2H probe registers one linked provider event idempotently');

    $verification = [
        'verified' => true,
        'provider' => 'paystack',
        'status' => 'paid',
        'provider_reference' => $reference,
        'amount' => $pricing['amount'],
        'currency' => $pricing['currency'],
        'provider_transaction_id' => 'qa-stage2h-tx-' . bin2hex(random_bytes(8)),
        'provider_subscription_id' => null,
        'paid_at' => date('Y-m-d H:i:s'),
        'failure_code' => null,
    ];

    // Mirror the Stage 2H return/webhook inner transaction sequence.
    $locked = billing_audit_attempt_by_reference($pdo, 'paystack', $reference, null, true);
    if (!$locked || (int)$locked['id'] !== $attemptId) {
        throw new RuntimeException('Stage 2H probe could not lock its temporary attempt.');
    }
    $updated = billing_audit_apply_verification($pdo, $attemptId, $verification);
    $check(($updated['status'] ?? null) === 'paid'
        && !empty($updated['verified_at'])
        && !empty($updated['paid_at']),
        'fresh verified provider fact advances the locked attempt to paid');

    $application = null;
    if ((string)($updated['status'] ?? '') === 'paid') {
        $application = billing_subscription_apply_paid_attempt($pdo, $attemptId);
    }
    $subscriptionId = (int)($application['subscription_record_id'] ?? 0);
    $check(($application['applied'] ?? null) === true
        && ($application['idempotent'] ?? null) === false
        && $subscriptionId > 0,
        'paid audit fact invokes Stage 2G application exactly once inside the route transaction');

    $processedEvent = billing_audit_event_mark($pdo, $eventRowId, 'processed');
    $check(($processedEvent['processing_status'] ?? null) === 'processed',
        'linked provider event is marked processed only after subscription application succeeds');

    $attemptAfter = billing_audit_attempt_by_id($pdo, $attemptId, false);
    $check((int)($attemptAfter['applied_subscription_record_id'] ?? 0) === $subscriptionId,
        'paid attempt links to the exact immutable subscription history row');

    $historyStmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id = ? AND farm_id = ? LIMIT 1');
    $historyStmt->execute([$subscriptionId, $farmId]);
    $history = $historyStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $check(($history['status'] ?? null) === 'active'
        && ($history['plan_code'] ?? null) === 'pro'
        && ($history['provider'] ?? null) === 'paystack'
        && ($history['currency'] ?? null) === 'NGN'
        && ($history['change_reason'] ?? null) === 'billing_payment_applied',
        'Stage 2H integration produces payment-linked active subscription history');

    $farmApplied = stage2h_farm_state($pdo, $farmId);
    $modulesApplied = stage2h_modules($pdo, $farmId);
    $addonsApplied = stage2h_addons($pdo, $farmId, $farmApplied, $modulesApplied);
    $limitsApplied = stage2h_limits($pdo, $farmId);
    $expectedLimits = subscription_plan_effective_role_limits('pro', ['poultry', 'ruminant'], $seatAddOns);
    ksort($expectedLimits, SORT_STRING);
    $check(($farmApplied['subscription_status'] ?? null) === 'active'
        && ($farmApplied['subscription_plan'] ?? null) === 'pro'
        && $modulesApplied === ['poultry', 'ruminant']
        && $addonsApplied === $seatAddOns
        && $limitsApplied === $expectedLimits,
        'Stage 2H integration leaves current plan, modules, purchased seats and effective limits coherent');

    $again = billing_subscription_apply_paid_attempt($pdo, $attemptId);
    $check(($again['applied'] ?? null) === false
        && ($again['idempotent'] ?? null) === true
        && (int)($again['subscription_record_id'] ?? 0) === $subscriptionId,
        'return/webhook replay of the same paid attempt remains exactly-once');

    $check(stage2h_count($pdo, 'subscriptions', $farmId) === $baselineSubscriptions + 1,
        'integrated paid path appends exactly one subscription-history row');
    $check(stage2h_count($pdo, 'billing_payment_attempts', $farmId) === $baselineAttempts + 1,
        'integrated paid path contains exactly one temporary attempt');
    $check(stage2h_count($pdo, 'billing_provider_events') === $baselineEvents + 1,
        'integrated webhook path contains exactly one temporary provider event');
    $check(stage2h_count($pdo, 'users', $farmId) === $baselineUsers,
        'integrated paid application does not create or delete tenant users');
} catch (Throwable $e) {
    $runtimeError = $e;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        $rolledBack = true;
    }
}

if ($runtimeError !== null) {
    echo 'FAIL: runtime exception: ' . $runtimeError->getMessage() . "\n";
    $failures++;
}
$check($rolledBack,
    'Stage 2H outer probe transaction was rolled back');

$afterFarm = stage2h_farm_state($pdo, $farmId);
$afterModules = stage2h_modules($pdo, $farmId);
$afterAddons = stage2h_addons($pdo, $farmId, $afterFarm, $afterModules);
$afterLimits = stage2h_limits($pdo, $farmId);
$afterUsers = stage2h_count($pdo, 'users', $farmId);
$afterSubscriptions = stage2h_count($pdo, 'subscriptions', $farmId);
$afterAttempts = stage2h_count($pdo, 'billing_payment_attempts', $farmId);
$afterEvents = stage2h_count($pdo, 'billing_provider_events');

$check($afterFarm === $baselineFarm,
    'rollback restored tenant subscription snapshot exactly');
$check($afterModules === $baselineModules,
    'rollback restored tenant commercial modules exactly');
$check($afterAddons === $baselineAddons,
    'rollback restored purchased/implied seat add-ons exactly');
$check($afterLimits === $baselineLimits,
    'rollback restored effective role limits exactly');
$check($afterUsers === $baselineUsers,
    'rollback preserved tenant user count exactly');
$check($afterSubscriptions === $baselineSubscriptions,
    'rollback restored subscription-history row count exactly');
$check($afterAttempts === $baselineAttempts,
    'rollback restored billing-attempt row count exactly');
$check($afterEvents === $baselineEvents,
    'rollback restored provider-event row count exactly');

$residueStmt->execute();
$afterResidue = (int)$residueStmt->fetchColumn();
$check($afterResidue === 0,
    'no temporary qa-stage2h billing attempts remain after rollback');

if ($attemptId > 0) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM billing_payment_attempts WHERE id = ?');
    $stmt->execute([$attemptId]);
    $check((int)$stmt->fetchColumn() === 0,
        'temporary Stage 2H attempt id is absent after rollback');
}
if ($subscriptionId > 0) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM subscriptions WHERE id = ?');
    $stmt->execute([$subscriptionId]);
    $check((int)$stmt->fetchColumn() === 0,
        'temporary Stage 2H subscription-history id is absent after rollback');
}
if ($eventRowId > 0) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM billing_provider_events WHERE id = ?');
    $stmt->execute([$eventRowId]);
    $check((int)$stmt->fetchColumn() === 0,
        'temporary Stage 2H provider-event id is absent after rollback');
}

echo 'Farm ID used: ' . $farmId . "\n";
echo 'Before subscriptions: ' . $baselineSubscriptions . "\n";
echo 'After subscriptions:  ' . $afterSubscriptions . "\n";
echo 'Before attempts:      ' . $baselineAttempts . "\n";
echo 'After attempts:       ' . $afterAttempts . "\n";
echo 'Before events:        ' . $baselineEvents . "\n";
echo 'After events:         ' . $afterEvents . "\n";
echo "\n{$checks} checks, {$failures} failure(s).\n";

if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2H integration runtime probe did not close cleanly.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2H verified-paid integration is atomic, exactly-once and rollback-clean.\n";
echo "PASS: existing tenant commercial and operational state is unchanged after the probe.\n";
