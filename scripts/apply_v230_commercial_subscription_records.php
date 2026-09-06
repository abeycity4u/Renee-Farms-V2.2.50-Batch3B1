<?php
/**
 * Targeted V2.3 commercial subscription record migration + baseline backfill.
 *
 * This script applies migration 041 only. It never invokes the historical all-
 * migration runner and therefore cannot execute migration 003.
 */

require_once dirname(__DIR__) . '/config.php';

$migrationName = '041_commercial_subscription_records.sql';
$migrationPath = dirname(__DIR__) . '/migrations/' . $migrationName;
if (!is_file($migrationPath)) {
    fwrite(STDERR, "FAIL: missing {$migrationName}.\n");
    exit(1);
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )"
);

$sql = file_get_contents($migrationPath);
if ($sql === false) {
    fwrite(STDERR, "FAIL: unable to read {$migrationName}.\n");
    exit(1);
}

$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_values(array_filter(array_map('trim', explode(';', (string)$sql))));
$ignoredMysqlCodes = [1050, 1060, 1061]; // table / column / key already exists

foreach ($statements as $statement) {
    if ($statement === '') continue;
    try {
        $pdo->exec($statement);
    } catch (PDOException $e) {
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        if (!in_array($driverCode, $ignoredMysqlCodes, true)) throw $e;
    }
}

$mark = $pdo->prepare('INSERT IGNORE INTO schema_migrations (filename) VALUES (?)');
$mark->execute([$migrationName]);

echo "PASS: applied {$migrationName} only.\n";
echo "PASS: migration 003 was not invoked by this script.\n";

require_once dirname(__DIR__) . '/includes/farm_entitlements.php';
require_once dirname(__DIR__) . '/includes/subscription_plan_catalog.php';
require_once dirname(__DIR__) . '/includes/subscription_seat_policy.php';
require_once dirname(__DIR__) . '/includes/subscription_record.php';

if (!subscription_record_table_ready($pdo)) {
    fwrite(STDERR, "FAIL: subscriptions table is missing required V2.3 commercial record columns.\n");
    exit(1);
}

$farms = $pdo->query(
    "SELECT id, name, slug FROM farms WHERE slug <> 'owner' ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$inserted = 0;
$alreadyCurrent = 0;
foreach ($farms as $farm) {
    $farmId = (int)$farm['id'];
    $result = subscription_record_capture($pdo, $farmId, 'foundation_backfill', null);
    if (!empty($result['inserted'])) $inserted++;
    else $alreadyCurrent++;

    $expected = subscription_record_build_snapshot($pdo, $farmId);
    $latest = subscription_record_latest($pdo, $farmId);
    if (!$latest || !hash_equals((string)$expected['snapshot_hash'], (string)($latest['snapshot_hash'] ?? ''))) {
        fwrite(STDERR, "FAIL: latest commercial record does not match farm snapshot for tenant {$farmId}.\n");
        exit(1);
    }
}

$totalRows = (int)$pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn();
$tenantBaselines = (int)$pdo->query(
    "SELECT COUNT(DISTINCT s.farm_id)
     FROM subscriptions s
     INNER JOIN farms f ON f.id = s.farm_id
     WHERE f.slug <> 'owner' AND s.snapshot_hash IS NOT NULL"
)->fetchColumn();

echo "Baseline records inserted: {$inserted}\n";
echo "Already-current baselines: {$alreadyCurrent}\n";
echo "subscriptions rows: {$totalRows}\n";
echo "Tenant commercial baselines: {$tenantBaselines}/" . count($farms) . "\n";

if ($tenantBaselines !== count($farms)) {
    fwrite(STDERR, "FAIL: not every tenant has a canonical commercial baseline.\n");
    exit(1);
}

echo "PASS: every tenant latest commercial record matches its current farms/modules/seat snapshot.\n";
