<?php
/**
 * Transactional rollback/idempotency proof for V2.3 Billing Stage 1.
 *
 * Creates temporary qa-stage1 rows inside one transaction only. A PASS requires
 * that rollback restores both billing-table row counts exactly.
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/subscription_plan_catalog.php';
require_once dirname(__DIR__) . '/includes/subscription_seat_policy.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';

if (!billing_payment_foundation_ready($pdo)) {
    fwrite(STDERR, "FAIL: billing foundation is not transactionally ready.\n");
    exit(1);
}

$farmId = (int)$pdo->query(
    "SELECT id FROM farms WHERE slug <> 'owner' ORDER BY id LIMIT 1"
)->fetchColumn();
if ($farmId < 1) {
    fwrite(STDERR, "FAIL: no tenant farm is available for rollback verification.\n");
    exit(1);
}

$beforeAttempts = (int)$pdo->query('SELECT COUNT(*) FROM billing_payment_attempts')->fetchColumn();
$beforeEvents = (int)$pdo->query('SELECT COUNT(*) FROM billing_provider_events')->fetchColumn();

$provider = 'qa-stage1';
$reference = 'qa-' . bin2hex(random_bytes(8));
$eventId = 'evt-' . bin2hex(random_bytes(8));
$payload = '{"event":"payment.success","qa":true}';

$pdo->beginTransaction();

try {
    $first = billing_payment_attempt_create(
        $pdo,
        $farmId,
        $provider,
        $reference,
        'starter',
        'monthly',
        '1.00',
        'NGN',
        ['poultry'],
        []
    );

    $second = billing_payment_attempt_create(
        $pdo,
        $farmId,
        $provider,
        $reference,
        'starter',
        'monthly',
        '1.00',
        'NGN',
        ['poultry'],
        []
    );

    if (empty($first['inserted'])) throw new RuntimeException('First payment attempt was not inserted.');
    if (!empty($second['inserted'])) throw new RuntimeException('Duplicate payment attempt was inserted.');
    if ((int)$first['id'] !== (int)$second['id']) {
        throw new RuntimeException('Duplicate payment attempt did not resolve to the same row.');
    }
    echo "PASS: first payment attempt inserted once.\n";
    echo "PASS: duplicate provider reference returned the existing attempt.\n";

    $quoteCollisionRejected = false;
    try {
        billing_payment_attempt_create(
            $pdo,
            $farmId,
            $provider,
            $reference,
            'starter',
            'monthly',
            '2.00',
            'NGN',
            ['poultry'],
            []
        );
    } catch (RuntimeException $e) {
        $quoteCollisionRejected = str_contains($e->getMessage(), 'different billing quote');
    }
    if (!$quoteCollisionRejected) {
        throw new RuntimeException('Provider-reference quote collision was not rejected.');
    }
    echo "PASS: reused provider reference with a different quote was rejected.\n";

    $event1 = billing_provider_event_register(
        $pdo,
        $provider,
        $eventId,
        'payment.success',
        $payload,
        (int)$first['id']
    );
    $event2 = billing_provider_event_register(
        $pdo,
        $provider,
        $eventId,
        'payment.success',
        $payload,
        (int)$first['id']
    );

    if (empty($event1['inserted'])) throw new RuntimeException('First provider event was not inserted.');
    if (!empty($event2['inserted'])) throw new RuntimeException('Duplicate provider event was inserted.');
    if ((int)$event1['id'] !== (int)$event2['id']) {
        throw new RuntimeException('Duplicate provider event did not resolve to the same row.');
    }
    echo "PASS: first provider event inserted once.\n";
    echo "PASS: duplicate provider event returned the existing event.\n";

    $eventCollisionRejected = false;
    try {
        billing_provider_event_register(
            $pdo,
            $provider,
            $eventId,
            'payment.success',
            '{"event":"payment.success","qa":false}',
            (int)$first['id']
        );
    } catch (RuntimeException $e) {
        $eventCollisionRejected = str_contains($e->getMessage(), 'different payload');
    }
    if (!$eventCollisionRejected) {
        throw new RuntimeException('Provider-event payload collision was not rejected.');
    }
    echo "PASS: reused provider event id with a different payload was rejected.\n";

    $insideAttempts = (int)$pdo->query('SELECT COUNT(*) FROM billing_payment_attempts')->fetchColumn();
    $insideEvents = (int)$pdo->query('SELECT COUNT(*) FROM billing_provider_events')->fetchColumn();
    if ($insideAttempts !== $beforeAttempts + 1) {
        throw new RuntimeException('Unexpected payment-attempt count inside transaction.');
    }
    if ($insideEvents !== $beforeEvents + 1) {
        throw new RuntimeException('Unexpected provider-event count inside transaction.');
    }
    echo "PASS: transaction contains exactly one temporary payment attempt.\n";
    echo "PASS: transaction contains exactly one temporary provider event.\n";

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Database transaction ended before rollback.');
    }
    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$afterAttempts = (int)$pdo->query('SELECT COUNT(*) FROM billing_payment_attempts')->fetchColumn();
$afterEvents = (int)$pdo->query('SELECT COUNT(*) FROM billing_provider_events')->fetchColumn();

echo "Before attempts: {$beforeAttempts}\n";
echo "After attempts:  {$afterAttempts}\n";
echo "Before events:   {$beforeEvents}\n";
echo "After events:    {$afterEvents}\n";

if ($afterAttempts !== $beforeAttempts || $afterEvents !== $beforeEvents) {
    fwrite(STDERR, "FAIL: rollback did not restore billing audit row counts.\n");
    exit(1);
}

echo "PASS: rollback removed all temporary Stage 1 QA rows.\n";
echo "PASS: Billing / Payment Foundation Stage 1 runtime idempotency is proven.\n";
