<?php
/**
 * V2.3 Billing Stage 2G rollback-only runtime verifier.
 *
 * Requires the normal DB environment variables. Makes no provider/network call.
 * Every temporary billing/commercial mutation is executed inside one transaction
 * and rolled back; existing tenant/operational data is never deleted.
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

function stage2g_farm_state(PDO $pdo, int $farmId): array
{
    $stmt = $pdo->prepare(
        'SELECT subscription_plan, subscription_status, subscription_starts_at, subscription_ends_at '
        . 'FROM farms WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$farmId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function stage2g_modules(PDO $pdo, int $farmId): array
{
    $modules = subscription_record_commercial_modules($pdo, $farmId);
    sort($modules, SORT_STRING);
    return $modules;
}

function stage2g_addons(PDO $pdo, int $farmId, array $farm, array $modules): array
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

function stage2g_limits(PDO $pdo, int $farmId): array
{
    $limits = subscription_seat_load_effective_limits($pdo, $farmId);
    ksort($limits, SORT_STRING);
    return $limits;
}

function stage2g_count(PDO $pdo, string $table, ?int $farmId = null): int
{
    $allowed = [
        'billing_payment_attempts', 'billing_provider_events', 'subscriptions',
        'users', 'sales_records', 'farm_expenses', 'production_cycles', 'stock_transactions',
    ];
    if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('Unsupported Stage 2G count table.');
    if ($farmId === null || in_array($table, ['billing_provider_events'], true)) {
        return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE farm_id = ?');
    $stmt->execute([$farmId]);
    return (int)$stmt->fetchColumn();
}

function stage2g_operational_counts(PDO $pdo, int $farmId): array
{
    $out = [];
    foreach (['users', 'sales_records', 'farm_expenses', 'production_cycles'] as $table) {
        $out[$table] = stage2g_count($pdo, $table, $farmId);
    }
    // stock_transactions is tenant-scoped through stock_items rather than a
    // guaranteed direct farm_id on every lineage, so do not assume its schema.
    return $out;
}

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "FAIL: PDO connection is unavailable.\n");
    exit(1);
}

$check(billing_subscription_application_ready($pdo),
    'Stage 2G commercial application storage reports transactionally ready');

$engines = [];
foreach (['farms', 'farm_modules', 'farm_role_limits', 'farm_subscription_seat_addons', 'subscriptions', 'billing_payment_attempts'] as $table) {
    $engines[$table] = billing_subscription_application_table_engine($pdo, $table);
}
$check(count(array_filter($engines, static fn($engine) => is_string($engine) && strcasecmp($engine, 'InnoDB') === 0)) === count($engines),
    'every Stage 2G commercial mutation table is InnoDB');

$farmStmt = $pdo->query("SELECT id FROM farms WHERE slug <> 'owner' ORDER BY id LIMIT 1");
$farmId = (int)$farmStmt->fetchColumn();
$check($farmId > 0,
    'a tenant farm exists for the rollback-only subscription application probe');
if ($farmId < 1 || $failures > 0) {
    echo "\n{$checks} checks, {$failures} failure(s).\n";
    exit(1);
}

$residueStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM billing_payment_attempts WHERE provider = 'paystack' AND provider_reference LIKE 'qa-stage2g-%'"
);
$residueStmt->execute();
$beforeResidue = (int)$residueStmt->fetchColumn();
$check($beforeResidue === 0,
    'no previous qa-stage2g payment-attempt residue exists before the rollback probe');
if ($beforeResidue !== 0) {
    echo "Refusing to delete or overwrite existing qa-stage2g rows.\n";
    echo "\n{$checks} checks, {$failures} failure(s).\n";
    exit(1);
}

$baselineFarm = stage2g_farm_state($pdo, $farmId);
$baselineModules = stage2g_modules($pdo, $farmId);
$baselineAddons = stage2g_addons($pdo, $farmId, $baselineFarm, $baselineModules);
$baselineLimits = stage2g_limits($pdo, $farmId);
$baselineOperational = stage2g_operational_counts($pdo, $farmId);
$baselineSubscriptions = stage2g_count($pdo, 'subscriptions', $farmId);
$baselineAttempts = stage2g_count($pdo, 'billing_payment_attempts', $farmId);
$baselineEvents = stage2g_count($pdo, 'billing_provider_events');

$rolledBack = false;
$runtimeError = null;
$reference = 'qa-stage2g-' . bin2hex(random_bytes(10));
$subscriptionId = 0;
$attemptId = 0;

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
    $attempt = billing_audit_attempt_by_id($pdo, $attemptId, false);
    $check(($created['inserted'] ?? null) === true
        && $attemptId > 0
        && ($attempt['status'] ?? null) === 'initialized',
        'temporary Stage 2G checkout creates one frozen initialized billing attempt');

    $paidAt = date('Y-m-d H:i:s');
    $paid = billing_audit_apply_verification($pdo, $attemptId, [
        'verified' => true,
        'provider' => 'paystack',
        'status' => 'paid',
        'provider_reference' => $reference,
        'amount' => $pricing['amount'],
        'currency' => $pricing['currency'],
        'provider_transaction_id' => 'qa-stage2g-tx-' . bin2hex(random_bytes(8)),
        'provider_subscription_id' => null,
        'paid_at' => $paidAt,
        'failure_code' => null,
    ]);
    $check(($paid['status'] ?? null) === 'paid'
        && !empty($paid['verified_at'])
        && !empty($paid['paid_at'])
        && !empty($paid['provider_transaction_id']),
        'synthetic provider fact creates the exact verified paid audit prerequisite without a network call');

    $applied = billing_subscription_apply_paid_attempt($pdo, $attemptId, null);
    $subscriptionId = (int)($applied['subscription_record_id'] ?? 0);
    $check(($applied['applied'] ?? null) === true
        && ($applied['idempotent'] ?? null) === false
        && $subscriptionId > 0,
        'verified paid attempt applies once and creates one immutable subscription history row');

    $farmAfterApply = stage2g_farm_state($pdo, $farmId);
    $check(($farmAfterApply['subscription_plan'] ?? null) === 'pro'
        && ($farmAfterApply['subscription_status'] ?? null) === 'active'
        && !empty($farmAfterApply['subscription_starts_at'])
        && !empty($farmAfterApply['subscription_ends_at']),
        'application updates the farm current subscription snapshot to active Pro');

    $modulesAfterApply = stage2g_modules($pdo, $farmId);
    $check($modulesAfterApply === ['poultry', 'ruminant'],
        'application sets the exact frozen Poultry + Ruminant entitlement bundle');

    $addonsAfterApply = stage2g_addons($pdo, $farmId, $farmAfterApply, $modulesAfterApply);
    $check($addonsAfterApply === $seatAddOns,
        'application stores the exact frozen purchased extra-seat quantities');

    $expectedLimits = subscription_plan_effective_role_limits('pro', ['poultry', 'ruminant'], $seatAddOns);
    ksort($expectedLimits, SORT_STRING);
    $limitsAfterApply = stage2g_limits($pdo, $farmId);
    $check($limitsAfterApply === $expectedLimits,
        'application recalculates effective role limits from Pro included seats plus purchased extras');

    $attemptAfterApply = billing_audit_attempt_by_id($pdo, $attemptId, false);
    $check((int)($attemptAfterApply['applied_subscription_record_id'] ?? 0) === $subscriptionId,
        'paid billing attempt links exactly to the created subscription history record');

    $historyStmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id = ? AND farm_id = ? LIMIT 1');
    $historyStmt->execute([$subscriptionId, $farmId]);
    $history = $historyStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $historyModules = json_decode((string)($history['modules_snapshot'] ?? ''), true);
    $historySeats = json_decode((string)($history['seat_addons_snapshot'] ?? ''), true);
    if (is_array($historySeats)) ksort($historySeats, SORT_STRING);
    $check(($history['status'] ?? null) === 'active'
        && ($history['plan_code'] ?? null) === 'pro'
        && ($history['billing_interval'] ?? null) === 'monthly'
        && (string)($history['amount'] ?? '') === $pricing['amount']
        && ($history['currency'] ?? null) === 'NGN'
        && ($history['provider'] ?? null) === 'paystack'
        && ($history['change_reason'] ?? null) === 'billing_payment_applied',
        'immutable history records exact plan/status/interval/amount/currency/provider payment facts');
    $check($historyModules === ['poultry', 'ruminant']
        && $historySeats === $seatAddOns,
        'immutable history snapshots exact applied modules and purchased seats');
    $check(!empty($history['current_period_ends_at'])
        && ($history['current_period_ends_at'] ?? null) === ($farmAfterApply['subscription_ends_at'] ?? null),
        'history current-period end matches the farm paid-term end');

    $subscriptionsInside = stage2g_count($pdo, 'subscriptions', $farmId);
    $check($subscriptionsInside === $baselineSubscriptions + 1,
        'first application appends exactly one subscription history row');

    $second = billing_subscription_apply_paid_attempt($pdo, $attemptId, null);
    $subscriptionsAfterSecond = stage2g_count($pdo, 'subscriptions', $farmId);
    $check(($second['applied'] ?? null) === false
        && ($second['idempotent'] ?? null) === true
        && (int)($second['subscription_record_id'] ?? 0) === $subscriptionId
        && $subscriptionsAfterSecond === $subscriptionsInside,
        'second application of the same paid attempt is idempotent and appends no history');

    $check(stage2g_operational_counts($pdo, $farmId) === $baselineOperational,
        'subscription application does not create/delete users or operational farm records');

    $check(stage2g_count($pdo, 'billing_payment_attempts', $farmId) === $baselineAttempts + 1
        && stage2g_count($pdo, 'billing_provider_events') === $baselineEvents,
        'transaction contains one temporary payment attempt and no provider-event mutation');
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
    'Stage 2G runtime probe transaction was rolled back');

$afterFarm = stage2g_farm_state($pdo, $farmId);
$afterModules = stage2g_modules($pdo, $farmId);
$afterAddons = stage2g_addons($pdo, $farmId, $afterFarm, $afterModules);
$afterLimits = stage2g_limits($pdo, $farmId);
$afterOperational = stage2g_operational_counts($pdo, $farmId);
$afterSubscriptions = stage2g_count($pdo, 'subscriptions', $farmId);
$afterAttempts = stage2g_count($pdo, 'billing_payment_attempts', $farmId);
$afterEvents = stage2g_count($pdo, 'billing_provider_events');

$check($afterFarm === $baselineFarm,
    'rollback restored the tenant farm subscription snapshot exactly');
$check($afterModules === $baselineModules,
    'rollback restored tenant commercial modules exactly');
$check($afterAddons === $baselineAddons,
    'rollback restored durable/implied seat add-ons exactly');
$check($afterLimits === $baselineLimits,
    'rollback restored effective role limits exactly');
$check($afterOperational === $baselineOperational,
    'rollback preserved user and operational farm row counts exactly');
$check($afterSubscriptions === $baselineSubscriptions,
    'rollback restored subscription-history row count exactly');
$check($afterAttempts === $baselineAttempts,
    'rollback restored billing-payment-attempt row count exactly');
$check($afterEvents === $baselineEvents,
    'rollback preserved provider-event row count exactly');

$residueStmt->execute();
$afterResidue = (int)$residueStmt->fetchColumn();
$check($afterResidue === 0,
    'no temporary qa-stage2g payment-attempt rows remain after rollback');

if ($attemptId > 0) {
    $attemptCheck = $pdo->prepare('SELECT COUNT(*) FROM billing_payment_attempts WHERE id = ?');
    $attemptCheck->execute([$attemptId]);
    $check((int)$attemptCheck->fetchColumn() === 0,
        'temporary Stage 2G attempt id is absent after rollback');
}
if ($subscriptionId > 0) {
    $historyCheck = $pdo->prepare('SELECT COUNT(*) FROM subscriptions WHERE id = ?');
    $historyCheck->execute([$subscriptionId]);
    $check((int)$historyCheck->fetchColumn() === 0,
        'temporary Stage 2G subscription-history id is absent after rollback');
}

echo 'Farm ID used: ' . $farmId . "\n";
echo 'Before subscriptions: ' . $baselineSubscriptions . "\n";
echo 'After subscriptions:  ' . $afterSubscriptions . "\n";
echo 'Before attempts:      ' . $baselineAttempts . "\n";
echo 'After attempts:       ' . $afterAttempts . "\n";
echo "\n{$checks} checks, {$failures} failure(s).\n";

if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2G runtime application is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2G paid application is transactional, exactly-once, entitlement-coherent and rollback-clean.\n";
echo "PASS: existing tenant commercial and operational state is unchanged after the probe.\n";
