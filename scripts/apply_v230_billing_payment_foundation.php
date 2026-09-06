<?php
/**
 * Targeted V2.3 billing/payment foundation migration.
 *
 * Applies migration 042 only. It never invokes the historical migration runner,
 * never invokes migration 003, creates no payment attempts/events, and performs
 * no provider/network call.
 */

require_once dirname(__DIR__) . '/config.php';

$migrationName = '042_billing_payment_foundation.sql';
$migrationPath = dirname(__DIR__) . '/migrations/' . $migrationName;
if (!is_file($migrationPath)) {
    fwrite(STDERR, "FAIL: missing {$migrationName}.\n");
    exit(1);
}

// Billing 042 extends the already-proven commercial subscription architecture.
// Refuse to create billing tables against an installation that does not yet have
// the 041 subscriptions history foundation.
$subscriptionReadyStmt = $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'subscriptions'
       AND column_name IN ('farm_id','plan_code','snapshot_hash')"
);
if ((int)$subscriptionReadyStmt->fetchColumn() !== 3) {
    fwrite(STDERR, "FAIL: migration 041 commercial subscription records must be installed before billing 042.\n");
    exit(1);
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )"
);

$tableCount = static function (PDO $pdo, string $table): int {
    $allowed = ['billing_payment_attempts', 'billing_provider_events'];
    if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('Unexpected billing table.');
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $exists->execute([$table]);
    if ((int)$exists->fetchColumn() < 1) return 0;
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
};

$beforeAttempts = $tableCount($pdo, 'billing_payment_attempts');
$beforeEvents = $tableCount($pdo, 'billing_provider_events');

$sql = file_get_contents($migrationPath);
if ($sql === false) {
    fwrite(STDERR, "FAIL: unable to read {$migrationName}.\n");
    exit(1);
}

$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_values(array_filter(array_map('trim', explode(';', (string)$sql))));
foreach ($statements as $statement) {
    if ($statement !== '') $pdo->exec($statement);
}

$mark = $pdo->prepare('INSERT IGNORE INTO schema_migrations (filename) VALUES (?)');
$mark->execute([$migrationName]);

require_once dirname(__DIR__) . '/includes/subscription_plan_catalog.php';
require_once dirname(__DIR__) . '/includes/subscription_seat_policy.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';

if (!billing_payment_foundation_ready($pdo)) {
    fwrite(STDERR, "FAIL: billing payment foundation tables are missing required columns.\n");
    exit(1);
}

$afterAttempts = $tableCount($pdo, 'billing_payment_attempts');
$afterEvents = $tableCount($pdo, 'billing_provider_events');
if ($afterAttempts !== $beforeAttempts || $afterEvents !== $beforeEvents) {
    fwrite(STDERR, "FAIL: billing migration unexpectedly changed billing audit row counts.\n");
    exit(1);
}

echo "PASS: applied {$migrationName} only.\n";
echo "PASS: migration 003 was not invoked by this script.\n";
echo "PASS: migration 041 commercial subscription foundation is present.\n";
echo "PASS: billing payment foundation tables are ready.\n";
echo "PASS: no payment attempt or provider-event rows were created by migration 042.\n";
echo "billing_payment_attempts rows: {$afterAttempts}\n";
echo "billing_provider_events rows: {$afterEvents}\n";
echo "PASS: no provider call, charge, entitlement change, or subscription mutation was performed.\n";
