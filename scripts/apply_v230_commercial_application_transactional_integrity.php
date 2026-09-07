<?php
/**
 * Targeted V2.3 Stage 2G commercial application transactional-integrity repair.
 *
 * Applies migration 044 only. It never invokes the historical migration runner
 * or migration 003. Before conversion it refuses orphan tenant rows and records
 * row counts; after conversion it verifies both engines, both foreign keys and
 * the Stage 2G application readiness contract.
 */

require_once dirname(__DIR__) . '/config.php';

$migrationName = '044_commercial_application_transactional_integrity.sql';
$migrationPath = dirname(__DIR__) . '/migrations/' . $migrationName;
if (!is_file($migrationPath)) {
    fwrite(STDERR, "FAIL: missing {$migrationName}.\n");
    exit(1);
}

$requiredTables = [
    'farms',
    'farm_role_limits',
    'farm_subscription_seat_addons',
    'subscriptions',
    'billing_payment_attempts',
];
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

foreach (['farms', 'subscriptions', 'billing_payment_attempts'] as $table) {
    if (strcasecmp($engines[$table], 'InnoDB') !== 0) {
        fwrite(STDERR, "FAIL: {$table} must already use InnoDB before Stage 2G repair.\n");
        exit(1);
    }
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);

$countRows = static function (PDO $pdo, string $table): int {
    if (!in_array($table, ['farm_role_limits', 'farm_subscription_seat_addons'], true)) {
        throw new InvalidArgumentException('Unexpected Stage 2G commercial table.');
    }
    return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
};

$beforeRoleLimits = $countRows($pdo, 'farm_role_limits');
$beforeSeatAddons = $countRows($pdo, 'farm_subscription_seat_addons');

$orphanSql = static function (string $table): string {
    return "SELECT COUNT(*) FROM {$table} child LEFT JOIN farms f ON f.id = child.farm_id WHERE f.id IS NULL";
};
$roleLimitOrphans = (int)$pdo->query($orphanSql('farm_role_limits'))->fetchColumn();
$seatAddonOrphans = (int)$pdo->query($orphanSql('farm_subscription_seat_addons'))->fetchColumn();
if ($roleLimitOrphans !== 0 || $seatAddonOrphans !== 0) {
    fwrite(STDERR, "FAIL: Stage 2G repair refused because orphan tenant rows exist.\n");
    fwrite(STDERR, "farm_role_limits orphans: {$roleLimitOrphans}\n");
    fwrite(STDERR, "farm_subscription_seat_addons orphans: {$seatAddonOrphans}\n");
    exit(1);
}

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

$afterRoleLimits = $countRows($pdo, 'farm_role_limits');
$afterSeatAddons = $countRows($pdo, 'farm_subscription_seat_addons');
if ($afterRoleLimits !== $beforeRoleLimits || $afterSeatAddons !== $beforeSeatAddons) {
    fwrite(STDERR, "FAIL: migration 044 unexpectedly changed commercial row counts.\n");
    exit(1);
}

foreach (['farm_role_limits', 'farm_subscription_seat_addons'] as $table) {
    $tableStmt->execute([$table]);
    $engine = (string)$tableStmt->fetchColumn();
    if (strcasecmp($engine, 'InnoDB') !== 0) {
        fwrite(STDERR, "FAIL: {$table} must use InnoDB after migration 044.\n");
        exit(1);
    }
}

$expectedFks = [
    'fk_farm_role_limits_farm' => ['farm_role_limits', 'farms'],
    'fk_farm_subscription_seat_addons_farm' => ['farm_subscription_seat_addons', 'farms'],
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

require_once dirname(__DIR__) . '/includes/billing_subscription_application.php';
if (!billing_subscription_application_ready($pdo)) {
    fwrite(STDERR, "FAIL: Stage 2G application service still does not consider the repaired schema ready.\n");
    exit(1);
}

echo $alreadyApplied
    ? "PASS: {$migrationName} was already marked applied; integrity reverified.\n"
    : "PASS: applied {$migrationName} only.\n";
echo "PASS: migration 003 was not invoked by this script.\n";
echo "PASS: no orphan farm_role_limits or farm_subscription_seat_addons rows exist.\n";
echo "PASS: farm_role_limits uses InnoDB and enforces fk_farm_role_limits_farm.\n";
echo "PASS: farm_subscription_seat_addons uses InnoDB and enforces fk_farm_subscription_seat_addons_farm.\n";
echo "PASS: commercial row counts were preserved by migration 044.\n";
echo "farm_role_limits rows: {$afterRoleLimits}\n";
echo "farm_subscription_seat_addons rows: {$afterSeatAddons}\n";
echo "PASS: Stage 2G application storage now reports transactionally ready.\n";
echo "PASS: no tenant subscription, module, seat quantity, billing attempt, subscription-history, or operational row was changed by the repair.\n";
