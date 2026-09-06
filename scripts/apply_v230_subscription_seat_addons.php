<?php
/**
 * Apply only V2.3 migration 040_subscription_seat_addons.sql.
 *
 * This targeted runner intentionally does not invoke scripts/run_migrations.php,
 * so historical migrations (including 003_multi_tenant_saas.sql) are untouched.
 */
require_once __DIR__ . '/../config.php';

$migrationName = '040_subscription_seat_addons.sql';
$migrationPath = __DIR__ . '/../migrations/' . $migrationName;

function requiredTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

foreach (['farms', 'farm_role_limits', 'schema_migrations'] as $requiredTable) {
    if (!requiredTableExists($pdo, $requiredTable)) {
        fwrite(STDERR, "FAIL: required table {$requiredTable} is missing. No migration was applied.\n");
        exit(1);
    }
}

if (!is_file($migrationPath)) {
    fwrite(STDERR, "FAIL: migration file {$migrationName} is missing.\n");
    exit(1);
}

$alreadyAppliedStmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE filename = ? LIMIT 1');
$alreadyAppliedStmt->execute([$migrationName]);
$alreadyApplied = (bool)$alreadyAppliedStmt->fetchColumn();
$tableReady = requiredTableExists($pdo, 'farm_subscription_seat_addons');

if ($alreadyApplied && $tableReady) {
    $count = (int)$pdo->query('SELECT COUNT(*) FROM farm_subscription_seat_addons')->fetchColumn();
    echo "PASS: {$migrationName} is already applied.\n";
    echo "farm_subscription_seat_addons rows: {$count}\n";
    exit(0);
}

$sql = file_get_contents($migrationPath);
if ($sql === false) {
    fwrite(STDERR, "FAIL: unable to read {$migrationName}.\n");
    exit(1);
}

$sqlWithoutComments = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_values(array_filter(array_map('trim', explode(';', (string)$sqlWithoutComments))));

try {
    foreach ($statements as $statement) {
        if ($statement === '') continue;
        $pdo->exec($statement);
    }
} catch (Throwable $e) {
    error_log('Targeted subscription seat migration failed: ' . $e->getMessage());
    fwrite(STDERR, "FAIL: {$migrationName} could not be applied. Check error_log.\n");
    exit(1);
}

if (!requiredTableExists($pdo, 'farm_subscription_seat_addons')) {
    fwrite(STDERR, "FAIL: farm_subscription_seat_addons was not created.\n");
    exit(1);
}

$verifyStmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE filename = ? LIMIT 1');
$verifyStmt->execute([$migrationName]);
if (!$verifyStmt->fetchColumn()) {
    fwrite(STDERR, "FAIL: {$migrationName} was not recorded in schema_migrations.\n");
    exit(1);
}

$count = (int)$pdo->query('SELECT COUNT(*) FROM farm_subscription_seat_addons')->fetchColumn();
echo "PASS: applied {$migrationName} only.\n";
echo "PASS: migration 003 was not invoked by this script.\n";
echo "farm_subscription_seat_addons rows: {$count}\n";
