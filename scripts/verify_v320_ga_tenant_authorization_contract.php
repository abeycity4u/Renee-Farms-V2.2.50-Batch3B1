<?php

declare(strict_types=1);

/**
 * GA tenant-authorization source contract.
 *
 * This verifier intentionally checks durable security invariants rather than
 * exact formatting. Runtime IDOR/privilege-escalation probing still belongs to
 * isolated staging; this contract prevents reviewed source gates from silently
 * disappearing between releases.
 */

$root = dirname(__DIR__);
$failures = 0;

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    $content = @file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('Unable to read ' . $relative);
    }
    return $content;
};

$check = static function (bool $ok, string $label) use (&$failures): void {
    if ($ok) {
        echo "PASS: {$label}\n";
        return;
    }

    echo "FAIL: {$label}\n";
    $failures++;
};

$hasAll = static function (string $content, array $needles): bool {
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            return false;
        }
    }
    return true;
};

try {
    $deleteSale = $read('api/delete_sale.php');
    $updateStock = $read('api/update_stock.php');
    $updateExpense = $read('api/update_expense.php');
    $financialAllocation = $read('api/update_financial_allocation.php');
    $saleAllocation = $read('api/update_sale_revenue_allocation.php');
    $permissionSave = $read('admin/permissions_save.php');
    $users = $read('management/users.php');
} catch (Throwable $e) {
    echo 'FAIL: ' . $e->getMessage() . "\n";
    echo "FAILURES=1\n";
    echo "GA_TENANT_AUTHORIZATION_CONTRACT=FAIL\n";
    exit(1);
}

$check(
    $hasAll($deleteSale, [
        'requireLogin()',
        "require_http_method('POST')",
        'require_csrf_token()',
        "hasPermission(getUserType(), 'delete_sales')",
        'api_require_record_for_farm(',
        "'sales_records'",
        'sales_lifecycle_delete_sale_for_farm(',
    ]),
    'sale deletion keeps login, method, CSRF, permission, tenant lookup and canonical lifecycle gates'
);

$check(
    $hasAll($updateStock, [
        'requireLogin()',
        "require_http_method('POST')",
        'require_csrf_token()',
        'requireCurrentFarmId()',
        'FROM stock_items WHERE id = ? AND farm_id = ? FOR UPDATE',
        'inventory_permission_user_can_access_farm_type(',
        'stock_apply_movement(',
    ]),
    'stock mutation keeps tenant lock, permission scope and canonical movement service'
);

$check(
    $hasAll($updateExpense, [
        'requireLogin()',
        "require_http_method('POST')",
        'require_csrf_token()',
        'requireCurrentFarmId()',
        'WHERE id = ? AND farm_id = ?',
        'UPDATE expenses',
        'farm_id = ?',
    ]),
    'expense update keeps authenticated tenant-scoped mutation gates'
);

$check(
    $hasAll($financialAllocation, [
        'requireLogin()',
        "require_http_method('POST')",
        'require_csrf_token()',
        'requireCurrentFarmId()',
        'financial_allocation_workspace_',
    ]),
    'financial allocation keeps authenticated tenant-aware workspace boundary'
);

$check(
    $hasAll($saleAllocation, [
        'requireLogin()',
        "require_http_method('POST')",
        'require_csrf_token()',
        'requireCurrentFarmId()',
        'sale_revenue_allocation_persistence_parent(',
        'sale_revenue_allocation_workspace_can_access(',
        'sale_revenue_allocation_persistence_apply(',
    ]),
    'sale revenue allocation keeps tenant parent lock and workspace authorization'
);

$check(
    $hasAll($permissionSave, [
        'requireLogin()',
        "require_http_method('POST')",
        'require_csrf_token()',
        'requireCurrentFarmId()',
        'farm_id = ?',
        "'farm_admin'",
        "'platform_owner'",
    ]),
    'permission save keeps tenant target scope and protected privileged roles'
);

$check(
    $hasAll($users, [
        'requireLogin()',
        'verify_csrf_token(',
        'requireCurrentFarmId()',
        'WHERE id = ? AND farm_id = ?',
        'DELETE FROM users WHERE id = ? AND farm_id = ?',
        'account_pending_user_create(',
        "=== 'farm_admin'",
    ]),
    'team-user management keeps CSRF, tenant ownership and Farm Admin protection'
);

$check(
    !preg_match(
        '/(?:UPDATE|DELETE\s+FROM)\s+(?:sales_records|stock_items|expenses|users)\b[^;]{0,600}\bWHERE\s+id\s*=\s*\?(?![^;]{0,300}\bfarm_id\b)/is',
        implode("\n", [$deleteSale, $updateStock, $updateExpense, $users])
    ),
    'reviewed high-value direct mutations do not reduce ownership to bare id-only WHERE clauses'
);

echo "FAILURES={$failures}\n";

if ($failures === 0) {
    echo "GA_TENANT_AUTHORIZATION_CONTRACT=PASS\n";
    exit(0);
}

echo "GA_TENANT_AUTHORIZATION_CONTRACT=FAIL\n";
exit(1);
