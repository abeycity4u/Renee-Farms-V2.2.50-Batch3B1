<?php

declare(strict_types=1);

/**
 * GA tenant-authorization source contract.
 *
 * This verifier checks durable authorization/ownership invariants rather than
 * exact formatting or retired helper names. Runtime IDOR and privilege-
 * escalation probing remains an isolated-staging requirement.
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
    $csrf = $read('includes/csrf.php');
    $users = $read('management/users.php');
} catch (Throwable $e) {
    echo 'FAIL: ' . $e->getMessage() . "\n";
    echo "FAILURES=1\n";
    echo "GA_TENANT_AUTHORIZATION_CONTRACT=FAIL\n";
    exit(1);
}

/*
 * Sale deletion is intentionally a thin orchestration route. Preserve both
 * Sales permissions, tenant-scoped lock/delete and the canonical domain
 * reversal/assertion services that protect dependent state.
 */
$check(
    $hasAll($deleteSale, [
        'requireLogin()',
        "require_http_method('POST')",
        'require_csrf_token()',
        "hasPermission(getUserType(), 'sales')",
        "hasPermission(getUserType(), 'sales_delete')",
        'requireCurrentFarmId()',
        'SELECT * FROM sales_records WHERE id=? AND farm_id=? FOR UPDATE',
        'sales_assert_manual_revenue_delete_allowed(',
        'receivable_assert_sale_deletable(',
        'sale_population_effect_assert_deletable(',
        'slaughter_output_sale_assert_deletable(',
        'general_sale_inventory_reverse_for_delete(',
        'DELETE FROM sales_records WHERE id=? AND farm_id=?',
    ]),
    'sale deletion keeps method, CSRF, Sales permissions, tenant lock/delete and dependent-state lifecycle guards'
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
        'FROM farm_expenses WHERE id=? AND farm_id=? LIMIT 1',
        'permission_catalog_expense_operational_can(',
        'UPDATE farm_expenses',
        'WHERE id=? AND farm_id=?',
    ]),
    'expense update keeps authenticated tenant-scoped lookup, authorization and mutation gates'
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

/*
 * permissions_save.php uses the shared combined POST+CSRF helper rather than
 * the API-specific method/token helpers. Verify that helper still enforces
 * both invariants, then verify role authority and tenant-scoped target policy.
 */
$check(
    $hasAll($permissionSave, [
        "!isPlatformOwner() && !hasRole('farm_admin')",
        'require_valid_csrf_post()',
        'requireCurrentFarmId()',
        'SELECT id FROM farms WHERE id = ? AND slug <> \'owner\' LIMIT 1',
        'farm_entitlement_available_specialist_roles(',
        'permission_catalog_applicable_for_farm(',
        'INSERT INTO permissions (farm_id,role,module,allowed)',
    ])
    && $hasAll($csrf, [
        "REQUEST_METHOD",
        "!== 'POST'",
        'csrf_request_is_valid()',
    ]),
    'permission save keeps privileged authority, POST+CSRF enforcement, tenant target validation and canonical applicability policy'
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
        '/(?:UPDATE|DELETE\s+FROM)\s+(?:sales_records|stock_items|farm_expenses|users)\b[^;]{0,600}\bWHERE\s+id\s*=\s*\?(?![^;]{0,300}\bfarm_id\b)/is',
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
