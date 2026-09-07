<?php
/**
 * Read-only runtime verification for the V2.3 billing transactional-integrity repair.
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/subscription_plan_catalog.php';
require_once dirname(__DIR__) . '/includes/subscription_seat_policy.php';
require_once dirname(__DIR__) . '/includes/billing_payment_foundation.php';

$checks = 0;
$failures = 0;
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) {
        echo "PASS: {$message}\n";
    } else {
        $failures++;
        echo "FAIL: {$message}\n";
    }
};

$tableEngine = static function (PDO $pdo, string $table): ?string {
    $stmt = $pdo->prepare(
        'SELECT engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    $engine = $stmt->fetchColumn();
    return $engine === false ? null : (string)$engine;
};

$engines = [];
foreach (['farms', 'subscriptions', 'billing_payment_attempts', 'billing_provider_events'] as $table) {
    $engines[$table] = $tableEngine($pdo, $table);
    echo $table . ' engine: ' . ($engines[$table] ?? 'MISSING') . PHP_EOL;
}

echo PHP_EOL;
$check(strcasecmp((string)$engines['farms'], 'InnoDB') === 0, 'farms remains InnoDB');
$check(strcasecmp((string)$engines['subscriptions'], 'InnoDB') === 0, 'subscriptions remains InnoDB');
$check(strcasecmp((string)$engines['billing_payment_attempts'], 'InnoDB') === 0, 'billing_payment_attempts uses InnoDB');
$check(strcasecmp((string)$engines['billing_provider_events'], 'InnoDB') === 0, 'billing_provider_events uses InnoDB');
$check(billing_payment_foundation_transactional($pdo), 'billing service confirms transactional storage');

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
    $check(
        $row
        && (string)$row['table_name'] === $table
        && (string)$row['referenced_table_name'] === $parent,
        "foreign key {$constraint} is enforced"
    );
}
$check(billing_payment_foreign_keys_ready($pdo), 'billing service confirms all required foreign keys');
$check(billing_payment_foundation_ready($pdo), 'billing foundation service reports ready');

$migrationMarked = false;
$schemaTableStmt = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'"
);
if ((int)$schemaTableStmt->fetchColumn() > 0) {
    $markStmt = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE filename = ?');
    $markStmt->execute(['043_billing_transactional_integrity.sql']);
    $migrationMarked = (int)$markStmt->fetchColumn() > 0;
}
$check($migrationMarked, 'migration 043 is recorded in schema_migrations');

$qaAttemptStmt = $pdo->prepare('SELECT COUNT(*) FROM billing_payment_attempts WHERE provider = ?');
$qaEventStmt = $pdo->prepare('SELECT COUNT(*) FROM billing_provider_events WHERE provider = ?');
$qaAttemptStmt->execute(['qa-stage1']);
$qaEventStmt->execute(['qa-stage1']);
$qaAttempts = (int)$qaAttemptStmt->fetchColumn();
$qaEvents = (int)$qaEventStmt->fetchColumn();

echo "qa-stage1 attempts: {$qaAttempts}\n";
echo "qa-stage1 events: {$qaEvents}\n";
$check($qaAttempts === 0, 'temporary qa-stage1 payment attempts are absent');
$check($qaEvents === 0, 'temporary qa-stage1 provider events are absent');

echo "\n{$checks} checks, {$failures} failure(s).\n";
if ($failures > 0) {
    fwrite(STDERR, "FAIL: V2.3 billing transactional-integrity runtime verification failed.\n");
    exit(1);
}

echo "PASS: V2.3 billing transactional integrity is enforced and QA residue is clean.\n";
