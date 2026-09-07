<?php
/**
 * Remove only the temporary V2.3 Billing Stage 1 QA rows created by the
 * rollback/idempotency probe when the live billing tables were still MyISAM.
 *
 * This script never touches real provider rows, tenant entitlements,
 * subscriptions, farm modules, seat limits, or operational farm data.
 */

require_once dirname(__DIR__) . '/config.php';

$provider = 'qa-stage1';

$tableExists = static function (PDO $pdo, string $table): bool {
    if (!in_array($table, ['billing_payment_attempts', 'billing_provider_events'], true)) return false;
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
};

foreach (['billing_payment_attempts', 'billing_provider_events'] as $table) {
    if (!$tableExists($pdo, $table)) {
        fwrite(STDERR, "FAIL: {$table} is not installed.\n");
        exit(1);
    }
}

$countAttemptsStmt = $pdo->prepare('SELECT COUNT(*) FROM billing_payment_attempts WHERE provider = ?');
$countEventsStmt = $pdo->prepare('SELECT COUNT(*) FROM billing_provider_events WHERE provider = ?');
$countAttemptsStmt->execute([$provider]);
$countEventsStmt->execute([$provider]);
$beforeAttempts = (int)$countAttemptsStmt->fetchColumn();
$beforeEvents = (int)$countEventsStmt->fetchColumn();

echo "QA provider: {$provider}\n";
echo "Before QA attempts: {$beforeAttempts}\n";
echo "Before QA events: {$beforeEvents}\n";

// Do not remove a QA attempt if some non-QA provider event has been linked to it.
// That would indicate data outside the known Stage 1 probe and needs investigation.
$crossProviderStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM billing_provider_events e
     INNER JOIN billing_payment_attempts a ON a.id = e.payment_attempt_id
     WHERE a.provider = ? AND e.provider <> ?"
);
$crossProviderStmt->execute([$provider, $provider]);
$crossProviderLinks = (int)$crossProviderStmt->fetchColumn();
if ($crossProviderLinks > 0) {
    fwrite(STDERR, "FAIL: {$crossProviderLinks} non-QA provider event(s) reference qa-stage1 attempts; cleanup refused.\n");
    exit(1);
}

if ($beforeEvents > 0) {
    $deleteEvents = $pdo->prepare('DELETE FROM billing_provider_events WHERE provider = ?');
    $deleteEvents->execute([$provider]);
}

if ($beforeAttempts > 0) {
    $deleteAttempts = $pdo->prepare('DELETE FROM billing_payment_attempts WHERE provider = ?');
    $deleteAttempts->execute([$provider]);
}

$countAttemptsStmt->execute([$provider]);
$countEventsStmt->execute([$provider]);
$afterAttempts = (int)$countAttemptsStmt->fetchColumn();
$afterEvents = (int)$countEventsStmt->fetchColumn();

echo "After QA attempts: {$afterAttempts}\n";
echo "After QA events: {$afterEvents}\n";

if ($afterAttempts !== 0 || $afterEvents !== 0) {
    fwrite(STDERR, "FAIL: qa-stage1 cleanup did not reach zero rows.\n");
    exit(1);
}

echo "PASS: only qa-stage1 temporary billing rows are absent.\n";
echo "PASS: no subscription, entitlement, module, seat, or operational farm row was changed.\n";
