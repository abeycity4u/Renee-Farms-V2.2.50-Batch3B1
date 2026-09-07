<?php
/**
 * Targeted V2.3 billing transactional-integrity repair.
 *
 * Applies migration 043 only. It never invokes the historical migration runner
 * or migration 003, and it does not mutate tenant entitlements/subscriptions.
 */

require_once dirname(__DIR__) . '/config.php';

$migrationName = '043_billing_transactional_integrity.sql';
$migrationPath = dirname(__DIR__) . '/migrations/' . $migrationName;
if (!is_file($migrationPath)) {
    fwrite(STDERR, "FAIL: missing {$migrationName}.\n");
    exit(1);
}

$requiredTables = ['farms', 'subscriptions', 'billing_payment_attempts', 'billing_provider_events'];
$tableStmt = $pdo->prepare(
    'SELECT engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
);
$engines = [];
foreach ($requiredTables as $table) {
    $tableStmt->execute([$table]);
    $engine = $tableStmt->fetchColumn();
    if ($engine === false) {
        fwrite(STDERR, "FAIL: required table {$table} is missing.\n");
        exit(1);
    }
    $engines[$table] = (string)$engine;
}

foreach (['farms', 'subscriptions'] as $parentTable) {
    if (strcasecmp($engines[$parentTable], 'InnoDB') !== 0) {
        fwrite(STDERR, "FAIL: {$parentTable} must already use InnoDB before billing foreign keys can be restored.\n");
        exit(1);
    }
}

$subscriptionReadyStmt = $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'subscriptions'
       AND column_name IN ('farm_id','plan_code','snapshot_hash')"
);
if ((int)$subscriptionReadyStmt->fetchColumn() !== 3) {
    fwrite(STDERR, "FAIL: migration 041 commercial subscription records must be installed before billing 043.\n");
    exit(1);
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);

$countRows = static function (PDO $pdo, string $table): int {
    if (!in_array($table, ['billing_payment_attempts', 'billing_provider_events'], true)) {
        throw new InvalidArgumentException('Unexpected billing table.');
    }
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
};

$beforeAttempts = $countRows($pdo, 'billing_payment_attempts');
$beforeEvents = $countRows($pdo, 'billing_provider_events');

$alreadyAppliedStmt = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE filename = ?');
$alreadyAppliedStmt->execute([$migrationName]);
$alreadyApplied = (int)$alreadyAppliedStmt->fetchColumn() > 0;

if (!$alreadyApplied) {
    $sql = file_get_contents($migrationPath);
    if ($sql === false) {
        fwrite(STDERR, "FAIL: unable to read {$migrationName}.\n");
        exit(1);
    }

    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
    foreach ($statements as $statement) {
        if ($statement !== '') $pdo->exec($statement);
    }
}

$afterAttempts = $countRows($pdo, 'billing_payment_attempts');
$afterEvents = $countRows($pdo, 'billing_provider_events');
if ($afterAttempts !== $beforeAttempts || $afterEvents !== $beforeEvents) {
    fwrite(STDERR, "FAIL: migration 043 unexpectedly changed billing audit row counts.\n");
    exit(1);
}

$tableStmt->execute(['billing_payment_attempts']);
$attemptEngine = (string)$tableStmt->fetchColumn();
$tableStmt->execute(['billing_provider_events']);
$eventEngine = (string)$tableStmt->fetchColumn();
if (strcasecmp($attemptEngine, 'InnoDB') !== 0 || strcasecmp($eventEngine, 'InnoDB') !== 0) {
    fwrite(STDERR, "FAIL: both billing tables must use InnoDB after migration 043.\n");
    exit(1);
}

$expectedFks = [
    'fk_billing_attempt_farm' => ['billing_payment_attempts', 'farms'],
    'fk_billing_attempt_subscription_record' => ['billing_payment_attempts', 'subscriptions'],
    'fk_billing_event_attempt' => ['billing_provider_events', 'billing_payment_attempts'],
];
$fkStmt = $pdo->prepare(
    "SELECT table_name, referenced_table_name
     FROM information_schema.referential_constraints
     WHERE constraint_schema = DATABASE() AND constraint_name = ? LIMIT 1"
);
foreach ($expectedFks as $constraint => [$table, $parent]) {
    $fkStmt->execute([$constraint]);
    $row = $fkStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row || (string)$row['table_name'] !== $table || (string)$row['referenced_table_name'] !== $parent) {
        fwrite(STDERR, "FAIL: expected foreign key {$constraint} was not restored correctly.\n");
        exit(1);
    }
}

$mark = $pdo->prepare('INSERT IGNORE INTO schema_migrations (filename) VALUES (?)');
$mark->execute([$migrationName]);

require_once dirname(__DIR__) . '/includes/subscription_plan_catalog.php';
require_once dirname(__DIR__) . '/includes/subscription_seat_policy.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';

if (!billing_payment_foundation_ready($pdo)) {
    fwrite(STDERR, "FAIL: billing foundation service does not consider the repaired schema ready.\n");
    exit(1);
}

echo $alreadyApplied
    ? "PASS: {$migrationName} was already marked applied; integrity reverified.\n"
    : "PASS: applied {$migrationName} only.\n";
echo "PASS: migration 003 was not invoked by this script.\n";
echo "PASS: farms/subscriptions remained InnoDB parent tables.\n";
echo "PASS: billing_payment_attempts uses InnoDB.\n";
echo "PASS: billing_provider_events uses InnoDB.\n";
echo "PASS: all three billing foreign keys are enforced.\n";
echo "PASS: billing audit row counts were preserved by migration 043.\n";
echo "billing_payment_attempts rows: {$afterAttempts}\n";
echo "billing_provider_events rows: {$afterEvents}\n";
echo "PASS: no entitlement, module, seat, subscription-history, or operational farm mutation was performed.\n";
