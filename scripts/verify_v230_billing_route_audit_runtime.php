<?php
/**
 * V2.3 Billing Stage 2F live audit-state verifier.
 *
 * Uses the real transactional billing tables but no provider network. Every QA
 * row is created inside one transaction and must disappear on rollback.
 */

$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/includes/subscription_plan_catalog.php';
require_once $root . '/includes/billing_payment_foundation.php';
require_once $root . '/includes/billing_currency_policy.php';
require_once $root . '/includes/billing_pricing_contract.php';
require_once $root . '/includes/billing_payment_audit_state.php';

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

if (!$pdo instanceof PDO || !billing_payment_foundation_ready($pdo)) {
    fwrite(STDERR, "FAIL: transactional billing foundation is not ready.\n");
    exit(1);
}

$provider = 'qa-stage2f';
$countAttempts = static function () use ($pdo, $provider): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM billing_payment_attempts WHERE provider = ?');
    $stmt->execute([$provider]);
    return (int)$stmt->fetchColumn();
};
$countEvents = static function () use ($pdo, $provider): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM billing_provider_events WHERE provider = ?');
    $stmt->execute([$provider]);
    return (int)$stmt->fetchColumn();
};
$tableExists = static function (string $table) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
};
$countProtected = static function () use ($pdo, $tableExists): array {
    $counts = [];
    foreach (['farms', 'farm_modules', 'farm_role_limits', 'farm_subscription_seat_addons', 'subscriptions'] as $table) {
        if (!$tableExists($table)) continue;
        $counts[$table] = (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }
    return $counts;
};

$beforeAttempts = $countAttempts();
$beforeEvents = $countEvents();
$protectedBefore = $countProtected();

$check($beforeAttempts === 0 && $beforeEvents === 0,
    'no previous qa-stage2f billing residue exists before the rollback probe');
if ($beforeAttempts !== 0 || $beforeEvents !== 0) {
    fwrite(STDERR, "FAIL: clean qa-stage2f rows before rerunning this verifier; no automatic deletion was performed.\n");
    exit(1);
}

$farmId = (int)$pdo->query("SELECT id FROM farms WHERE slug <> 'owner' ORDER BY id ASC LIMIT 1")->fetchColumn();
$check($farmId > 0,
    'a tenant farm exists for the rollback-only billing audit probe');
if ($farmId < 1) exit(1);

$pricedQuote = billing_pricing_build_payment_quote('starter', 'monthly', ['poultry'], []);
$pricing = $pricedQuote['pricing'];
$reference = 'qa-stage2f-' . bin2hex(random_bytes(12));
$transactionId = 'qa-stage2f-txn-' . bin2hex(random_bytes(8));
$eventPayload = json_encode([
    'event' => 'qa.stage2f.verified',
    'reference' => $reference,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($eventPayload === false) {
    fwrite(STDERR, "FAIL: unable to build temporary provider-event payload.\n");
    exit(1);
}

try {
    $pdo->beginTransaction();

    $created = billing_payment_attempt_create(
        $pdo,
        $farmId,
        $provider,
        $reference,
        $pricing['plan_code'],
        $pricing['billing_interval'],
        $pricing['amount'],
        $pricing['currency'],
        $pricing['modules'],
        $pricing['seat_addons'],
        null
    );
    $attemptId = (int)$created['id'];
    $attempt = $created['attempt'];
    $check(($created['inserted'] ?? null) === true
        && $attemptId > 0
        && ($attempt['status'] ?? null) === 'initialized',
        'temporary checkout creates exactly one initialized billing attempt');

    $pending = billing_audit_mark_pending($pdo, $attemptId, $transactionId, null);
    $check(($pending['status'] ?? null) === 'pending'
        && ($pending['provider_transaction_id'] ?? null) === $transactionId,
        'checkout initialization advances the audit attempt to pending');

    $verification = [
        'provider' => $provider,
        'verified' => true,
        'status' => 'paid',
        'provider_reference' => $reference,
        'amount' => $pricing['amount'],
        'currency' => $pricing['currency'],
        'provider_transaction_id' => $transactionId,
        'provider_subscription_id' => null,
        'paid_at' => '2026-09-07T03:00:00+01:00',
        'failure_code' => null,
    ];
    $paid = billing_audit_apply_verification($pdo, $attemptId, $verification);
    $check(($paid['status'] ?? null) === 'paid'
        && ($paid['verified_at'] ?? null) !== null
        && ($paid['paid_at'] ?? null) !== null,
        'fresh verified provider fact advances the audit attempt to paid');

    $stalePending = $verification;
    $stalePending['status'] = 'pending';
    $stalePending['paid_at'] = null;
    $stillPaid = billing_audit_apply_verification($pdo, $attemptId, $stalePending);
    $check(($stillPaid['status'] ?? null) === 'paid',
        'stale pending provider state cannot downgrade a paid attempt');

    $mismatchRejected = false;
    $wrongAmount = $verification;
    $wrongAmount['amount'] = '9999.99';
    try {
        billing_audit_apply_verification($pdo, $attemptId, $wrongAmount);
    } catch (RuntimeException $e) {
        $mismatchRejected = str_contains($e->getMessage(), 'amount does not match');
    }
    $check($mismatchRejected,
        'verified provider amount mismatch is rejected before audit status mutation');
    $afterMismatch = billing_audit_attempt_by_id($pdo, $attemptId, false);
    $check(($afterMismatch['status'] ?? null) === 'paid',
        'amount mismatch leaves the previously paid audit state unchanged');

    $event = billing_provider_event_register(
        $pdo,
        $provider,
        'qa-stage2f-event-' . substr(hash('sha256', $reference), 0, 24),
        'qa.stage2f.verified',
        $eventPayload,
        $attemptId
    );
    $duplicate = billing_provider_event_register(
        $pdo,
        $provider,
        'qa-stage2f-event-' . substr(hash('sha256', $reference), 0, 24),
        'qa.stage2f.verified',
        $eventPayload,
        $attemptId
    );
    $check(($event['inserted'] ?? null) === true
        && ($duplicate['inserted'] ?? null) === false
        && (int)$event['id'] === (int)$duplicate['id'],
        'provider event registration remains idempotent during Stage 2F processing');

    $processed = billing_audit_event_mark($pdo, (int)$event['id'], 'processed');
    $check(($processed['processing_status'] ?? null) === 'processed'
        && billing_audit_event_terminal($processed),
        'linked provider event is marked processed and terminal');

    $insideAttempts = $countAttempts();
    $insideEvents = $countEvents();
    $check($insideAttempts === 1 && $insideEvents === 1,
        'transaction contains exactly one temporary Stage 2F attempt and one provider event');
    $check($countProtected() === $protectedBefore,
        'Stage 2F audit processing does not alter subscription/entitlement table row counts');

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: Stage 2F rollback probe raised an exception: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$afterAttempts = $countAttempts();
$afterEvents = $countEvents();
$protectedAfter = $countProtected();

$check($afterAttempts === $beforeAttempts,
    'rollback restored qa-stage2f payment-attempt count exactly');
$check($afterEvents === $beforeEvents,
    'rollback restored qa-stage2f provider-event count exactly');
$check($protectedAfter === $protectedBefore,
    'subscription/entitlement table row counts remain unchanged after rollback');

echo 'Before attempts: ' . $beforeAttempts . PHP_EOL;
echo 'After attempts:  ' . $afterAttempts . PHP_EOL;
echo 'Before events:   ' . $beforeEvents . PHP_EOL;
echo 'After events:    ' . $afterEvents . PHP_EOL;

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 Billing Stage 2F runtime audit flow is not closed.\n");
    exit(1);
}

echo "PASS: V2.3 Billing Stage 2F audit transitions are transactional, mismatch-safe, idempotent and rollback-clean.\n";
echo "PASS: no temporary qa-stage2f billing rows remain.\n";
