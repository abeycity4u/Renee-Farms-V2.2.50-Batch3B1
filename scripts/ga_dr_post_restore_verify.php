<?php
/**
 * Renee AgriSuite GA disaster-recovery post-restore verifier.
 *
 * Run ONLY against an isolated restored database/environment. This script is
 * read-only and reports structural evidence without printing tenant data or
 * credentials.
 */

declare(strict_types=1);

if ((string)(getenv('GA_DR_ISOLATED_RESTORE') ?: '') !== 'YES') {
    fwrite(STDERR, "BLOCKED: set GA_DR_ISOLATED_RESTORE=YES only inside an isolated restore target.\n");
    exit(2);
}

require_once dirname(__DIR__) . '/config.php';

$failures = 0;

function dr_check(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL: {$label}\n";
}

function dr_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() === 1;
}

function dr_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() === 1;
}

$coreTables = [
    'farms',
    'users',
    'roles',
    'user_roles',
    'permissions',
    'subscriptions',
    'billing_payment_attempts',
    'stock_items',
    'stock_ledger',
    'sales',
    'farm_expenses',
    'production_cycles',
    'layer_daily_records',
    'broiler_daily_records',
    'ruminant_animals',
    'account_credential_tokens',
    'schema_migrations',
];

foreach ($coreTables as $table) {
    dr_check(dr_table_exists($pdo, $table), 'restored table exists: ' . $table);
}

foreach ([
    ['users', 'farm_id'],
    ['stock_items', 'farm_id'],
    ['sales', 'farm_id'],
    ['farm_expenses', 'farm_id'],
    ['production_cycles', 'farm_id'],
    ['billing_payment_attempts', 'farm_id'],
] as [$table, $column]) {
    dr_check(dr_column_exists($pdo, $table, $column), "tenant key exists: {$table}.{$column}");
}

if (dr_table_exists($pdo, 'schema_migrations')) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE filename = ?');
    $stmt->execute(['091_permission_architecture_alignment.sql']);
    dr_check((int)$stmt->fetchColumn() === 1, 'migration 091 marker restored');
}

// Aggregate counts prove that the restored dataset is populated without exposing
// any names, emails, financial values, tokens or row contents.
foreach (['farms', 'users', 'subscriptions', 'stock_items', 'sales', 'farm_expenses'] as $table) {
    if (!dr_table_exists($pdo, $table)) continue;
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    echo 'COUNT_' . strtoupper($table) . '=' . $count . PHP_EOL;
}

// Referential sanity checks for the most important tenant-bound records.
$orphanChecks = [
    'users_without_farm' => 'SELECT COUNT(*) FROM users u LEFT JOIN farms f ON f.id = u.farm_id WHERE f.id IS NULL',
    'stock_without_farm' => 'SELECT COUNT(*) FROM stock_items s LEFT JOIN farms f ON f.id = s.farm_id WHERE f.id IS NULL',
    'sales_without_farm' => 'SELECT COUNT(*) FROM sales s LEFT JOIN farms f ON f.id = s.farm_id WHERE f.id IS NULL',
];

foreach ($orphanChecks as $label => $sql) {
    try {
        $orphans = (int)$pdo->query($sql)->fetchColumn();
        dr_check($orphans === 0, str_replace('_', ' ', $label));
    } catch (Throwable $e) {
        dr_check(false, str_replace('_', ' ', $label));
    }
}

echo 'DR_POST_RESTORE_FAILURES=' . $failures . PHP_EOL;
echo 'DR_POST_RESTORE=' . ($failures === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
exit($failures === 0 ? 0 : 1);
