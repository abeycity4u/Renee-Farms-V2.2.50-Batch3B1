<?php
/**
 * V2.3 tenant-boundary / IDOR structural verifier.
 *
 * This is intentionally a focused regression guard for high-risk routes and
 * shared services that accept record identifiers. It does not mutate data.
 */

$root = dirname(__DIR__);
$checks = [];
$failures = 0;

function source(string $path): string
{
    global $root;
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        return '';
    }
    return (string)file_get_contents($full);
}

function check_contains(string $label, string $path, string $needle): void
{
    global $checks, $failures;
    $ok = str_contains(source($path), $needle);
    $checks[] = [$ok, $label];
    if (!$ok) $failures++;
}

function check_regex(string $label, string $path, string $pattern): void
{
    global $checks, $failures;
    $ok = preg_match($pattern, source($path)) === 1;
    $checks[] = [$ok, $label];
    if (!$ok) $failures++;
}

check_contains('Sale deletion scopes lookup by sale id and farm id', 'api/delete_sale.php', 'WHERE id=? AND farm_id=? FOR UPDATE');
check_contains('Expense deletion scopes lookup by expense id and farm id', 'api/delete_expense.php', 'WHERE id=? AND farm_id=? FOR UPDATE');
check_contains('Expense update scopes existing record by id and farm id', 'api/update_expense.php', 'WHERE id=? AND farm_id=? LIMIT 1');
check_contains('Inventory item detail scopes lookup by id and current farm', 'api/get_item_details.php', 'WHERE id = ? AND farm_id = ?');
check_contains('Stock history scopes item lookup by id and farm id', 'api/get_stock_history.php', 'WHERE id = ? AND farm_id = ?');
check_contains('Daily record delete scopes layer records by id and farm id', 'api/delete_record.php', 'WHERE id=? AND farm_id=? LIMIT 1');
check_contains('Daily record delete scopes broiler records by id and farm id', 'api/delete_record.php', 'WHERE id = ? AND farm_id = ?');
check_contains('Team user guard validates target user inside selected/current farm', 'includes/user_management_tenant_guard.php', 'SELECT id FROM users WHERE id = ? AND farm_id = ? LIMIT 1');
check_contains('Permission save starts from current tenant context', 'admin/permissions_save.php', '$permissionFarmId = requireCurrentFarmId();');
check_contains('Permission writes carry resolved tenant farm id', 'admin/permissions_save.php', 'INSERT INTO permissions (farm_id,role,module,allowed) VALUES (?,?,?,?)');
check_contains('Manual feed delete re-fetches transaction by id, farm and farm type', 'lib/manual_feed_transactions.php', 'WHERE id = ? AND farm_id = ? AND farm_type = ? FOR UPDATE');
check_contains('Receivable sale lookup scopes by sale id and farm id', 'lib/sales_receivables.php', 'WHERE id=? AND farm_id=? LIMIT 1');
check_contains('Sales allocation scopes sale lookup by id and farm id', 'lib/sales_allocation.php', 'WHERE id=? AND farm_id=? LIMIT 1');
check_contains('Ruminant animal edit lookup scopes by id and farm id', 'ruminant/animal_registry.php', 'WHERE id=? AND farm_id=? LIMIT 1');
check_contains('Poultry health event update lookup scopes by id and farm id', 'poultry/health.php', 'WHERE id=? AND farm_id=? LIMIT 1');
check_contains('Production-cycle close resolves selected cycle inside tenant', 'management/production_cycles.php', 'WHERE id = ? AND farm_id = ? AND status = ?');
check_contains('Poultry cycle workspace resolves cycle inside current farm', 'management/poultry_cycle.php', "SELECT * FROM production_cycles WHERE id=? AND farm_id=? AND farm_type='poultry' LIMIT 1");
check_contains('Poultry investigation passes current farm into diagnostics', 'management/investigation.php', 'poultry_diagnostic_investigate($pdo,$farmId,$cycleId,$type,$issue,$asOf)');
check_contains('Ruminant investigation passes current farm into diagnostics', 'management/ruminant_investigation.php', 'ruminant_diagnostic_investigate_weight($pdo,$farmId,$animalId,$asOf)');
check_regex('Billing return resolves payment attempt with tenant farm id', 'billing/return.php', '/billing_audit_attempt_by_reference\(\$pdo,\s*\$provider,\s*\$providerReference,\s*\$farmId,/');

foreach ($checks as [$ok, $label]) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
}

echo PHP_EOL . count($checks) . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
