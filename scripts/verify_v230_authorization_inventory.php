<?php
/**
 * V2.3 formal authorization inventory verifier.
 *
 * Static/read-only. It does not connect to the database and cannot mutate farm data.
 * The explicit endpoint manifests are intentional: adding/removing an API or sensitive
 * legacy mutation route must be reviewed and classified before this verifier passes.
 */

$root = dirname(__DIR__);
$checks = [];
$failures = 0;

function auth_inv_check(string $label, bool $ok): void
{
    global $checks, $failures;
    $checks[] = [$label, $ok];
    if (!$ok) $failures++;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
}

function auth_inv_file(string $root, string $path): string
{
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) return '';
    $content = file_get_contents($full);
    return is_string($content) ? $content : '';
}

function auth_inv_has_all(string $content, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) return false;
    }
    return true;
}

echo "V2.3 Formal Authorization Inventory\n";
echo "===================================\n\n";

// -------------------------------------------------------------------------
// 1. Exhaustive api/*.php manifest.
// -------------------------------------------------------------------------
$expectedApi = [
    'api_helpers.php'          => 'helper',
    'check_pending_tasks.php'  => 'read',
    'check_record.php'         => 'read',
    'check_ruminant_record.php'=> 'read',
    'delete_expense.php'       => 'write',
    'delete_record.php'        => 'write',
    'delete_sale.php'          => 'write',
    'get_chart_data.php'       => 'read',
    'get_item_details.php'     => 'read',
    'get_previous_stock.php'   => 'read',
    'get_record.php'           => 'read',
    'get_stock_history.php'    => 'read',
    'get_stock_summary.php'    => 'read',
    'stock_history.php'        => 'read',
    'update_expense.php'       => 'write',
    'update_stock.php'         => 'write',
];

$actualApi = [];
foreach (glob($root . '/api/*.php') ?: [] as $file) {
    $actualApi[] = basename($file);
}
sort($actualApi);
$expectedApiNames = array_keys($expectedApi);
sort($expectedApiNames);

auth_inv_check('api/*.php manifest is exhaustive and unchanged', $actualApi === $expectedApiNames);
if ($actualApi !== $expectedApiNames) {
    $unexpected = array_values(array_diff($actualApi, $expectedApiNames));
    $missing = array_values(array_diff($expectedApiNames, $actualApi));
    if ($unexpected) echo 'INFO: unclassified API files: ' . implode(', ', $unexpected) . PHP_EOL;
    if ($missing) echo 'INFO: expected API files missing: ' . implode(', ', $missing) . PHP_EOL;
}

echo "\nAPI route inventory:\n";
foreach ($expectedApi as $name => $kind) {
    $content = auth_inv_file($root, 'api/' . $name);
    $exists = $content !== '';
    echo ' - ' . str_pad($name, 27) . strtoupper($kind) . PHP_EOL;
    auth_inv_check("api/$name exists", $exists);
    if (!$exists || $kind === 'helper') continue;

    // Every concrete API must establish authenticated/current-tenant context,
    // either directly or through the common init/bootstrap + central bridge.
    auth_inv_check(
        "api/$name loads application bootstrap/auth context",
        str_contains($content, 'init.php') || str_contains($content, 'api_helpers.php')
    );

    if ($kind === 'write') {
        auth_inv_check(
            "api/$name is POST-only",
            (str_contains($content, 'REQUEST_METHOD') && str_contains($content, 'POST'))
                || str_contains($content, "require_http_method('POST')")
        );
        auth_inv_check(
            "api/$name enforces CSRF",
            str_contains($content, 'verify_csrf_token')
                || str_contains($content, 'require_valid_csrf_post')
                || str_contains($content, 'csrf_request_is_valid')
                || str_contains($content, 'require_csrf_token()')
        );
        auth_inv_check(
            "api/$name binds mutation to current tenant",
            str_contains($content, 'requireCurrentFarmId')
                || str_contains($content, 'getCurrentFarmId')
                || str_contains($content, 'farm_id')
        );
    }
}

// Read APIs that intentionally retain thin legacy bodies are guarded centrally.
$legacyReadBridge = auth_inv_file($root, 'includes/legacy_api_view_permissions.php');
$requiredReadBridgeRoutes = [
    'check_record.php',
    'check_ruminant_record.php',
    'get_chart_data.php',
    'get_item_details.php',
    'get_previous_stock.php',
    'get_record.php',
    'get_stock_history.php',
    'stock_history.php',
];
foreach ($requiredReadBridgeRoutes as $route) {
    auth_inv_check("central legacy read bridge classifies $route", str_contains($legacyReadBridge, $route));
}

$inventoryBridge = auth_inv_file($root, 'includes/inventory_permission_hardening.php');
auth_inv_check('Inventory stock summary read is centrally View-gated', str_contains($inventoryBridge, 'get_stock_summary.php'));
auth_inv_check('Inventory item/history reads enforce manager object scope',
    str_contains($inventoryBridge, 'get_item_details.php')
    && str_contains($inventoryBridge, 'get_stock_history.php'));

$pending = auth_inv_file($root, 'api/check_pending_tasks.php');
auth_inv_check('Pending Tasks independently checks delegated View permissions',
    auth_inv_has_all($pending, ['layer_daily', 'broiler_daily', 'ruminant_daily', 'inventory']));

// -------------------------------------------------------------------------
// 2. Permission administration boundary.
// -------------------------------------------------------------------------
$permissionSave = auth_inv_file($root, 'admin/permissions_save.php');
auth_inv_check('permissions_save is restricted to Platform Owner/Farm Admin',
    str_contains($permissionSave, "hasRole('farm_admin')") && str_contains($permissionSave, 'isPlatformOwner()'));
auth_inv_check('permissions_save requires CSRF POST', str_contains($permissionSave, 'require_valid_csrf_post()'));
auth_inv_check('Farm Admin permission save starts from current tenant', str_contains($permissionSave, 'requireCurrentFarmId()'));
auth_inv_check('only Platform Owner consumes posted farm_id',
    str_contains($permissionSave, "if (isPlatformOwner())") && str_contains($permissionSave, "\$_POST['farm_id']"));
auth_inv_check('Platform Owner posted tenant id is validated against farms and excludes owner workspace',
    str_contains($permissionSave, "SELECT id FROM farms WHERE id = ? AND slug <> 'owner'"));
auth_inv_check('permission roles derive from canonical tenant entitlement helper',
    str_contains($permissionSave, 'farm_entitlement_available_specialist_roles'));
auth_inv_check('permission codes derive from canonical permission catalog',
    str_contains($permissionSave, 'permission_catalog_codes()'));
auth_inv_check('permission writes are tenant keyed',
    str_contains($permissionSave, 'INSERT INTO permissions (farm_id,role,module,allowed)'));

// -------------------------------------------------------------------------
// 3. Sensitive legacy browser mutation manifest / CSRF posture.
// -------------------------------------------------------------------------
$legacyMutationRoutes = [
    'management/farms.php',
    'management/users.php',
    'management/production_cycles.php',
    'management/poultry_cycle.php',
    'management/sales_records.php',
    'management/investigation.php',
    'management/ruminant_investigation.php',
    'management/ruminant_membership_integrity.php',
    'inventory.php',
    'poultry/health.php',
    'poultry/layers_daily_record.php',
    'poultry/broiler_daily_record.php',
    'poultry/layer_feeds.php',
    'poultry/broiler_feeds.php',
    'poultry/layer_expenses.php',
    'poultry/broiler_expenses.php',
    'ruminant/animal_registry.php',
    'ruminant/animal_view.php',
    'ruminant/ruminant_daily_record.php',
    'ruminant/ruminant_feeds_record.php',
    'ruminant/ruminant_expenses.php',
];

echo "\nSensitive legacy mutation inventory:\n";
foreach ($legacyMutationRoutes as $route) {
    $content = auth_inv_file($root, $route);
    echo ' - ' . $route . PHP_EOL;
    auth_inv_check("$route exists", $content !== '');
}

$closure = auth_inv_file($root, 'includes/legacy_authorization_closure.php');
$init = auth_inv_file($root, 'init.php');
$runtime = auth_inv_file($root, 'includes/permission_runtime.php');
$productionCycleBridge = auth_inv_file($root, 'includes/production_cycle_view_permissions.php');

// Sales browser mutations were the remaining CSRF gap found by the formal sweep.
auth_inv_check('Sales Records POSTs now require central valid CSRF',
    str_contains($closure, "'/management/sales_records.php'")
    && str_contains($closure, 'require_valid_csrf_post()'));
auth_inv_check('Sales Records GET injects CSRF into legacy POST forms',
    str_contains($closure, 'legacy_authorization_closure_inject_sales_csrf')
    && str_contains($closure, 'csrf_field()'));

// Ruminant child history and integrity repairs must not inherit broad role-only write access.
auth_inv_check('Animal Profile writes require Ruminant Animal Registry Edit',
    str_contains($closure, "'/ruminant/animal_view.php'")
    && str_contains($closure, "legacy_authorization_closure_require('ruminant_animals_edit')"));
auth_inv_check('Ruminant Membership Integrity requires Farm Intelligence View',
    str_contains($closure, "'/management/ruminant_membership_integrity.php'")
    && str_contains($closure, "legacy_authorization_closure_require('farm_intelligence')"));
auth_inv_check('Ruminant Membership Integrity repair is Farm Admin/Platform Owner only',
    str_contains($closure, '$legacyAuthorizationMethod === \'POST\' && !$legacyAuthorizationPrivileged'));

// Investigation drill-down must inherit the parent Farm Intelligence View boundary.
auth_inv_check('Poultry Investigation direct route requires Farm Intelligence View',
    str_contains($closure, "'/management/investigation.php'")
    && str_contains($closure, "legacy_authorization_closure_require('farm_intelligence')"));
auth_inv_check('Ruminant Investigation direct route requires Farm Intelligence View',
    str_contains($closure, "'/management/ruminant_investigation.php'")
    && str_contains($closure, "legacy_authorization_closure_require('farm_intelligence')"));

// Existing central bridges remain part of the closure contract.
auth_inv_check('Production Cycle delegated mutation bridge remains loaded',
    str_contains($init, 'production_cycle_view_permissions.php')
    && str_contains($productionCycleBridge, 'REQUEST_METHOD'));
auth_inv_check('granular permission runtime remains loaded before final legacy closure',
    strpos($init, 'permission_runtime.php') !== false
    && strpos($init, 'legacy_authorization_closure.php') !== false
    && strpos($init, 'permission_runtime.php') < strpos($init, 'legacy_authorization_closure.php'));
auth_inv_check('final legacy closure is loaded by init.php', str_contains($init, 'legacy_authorization_closure.php'));

// -------------------------------------------------------------------------
// 4. Spot-check tenant scoping on the main legacy mutation families.
// -------------------------------------------------------------------------
$tenantScopedFiles = [
    'management/users.php'              => ['requireCurrentFarmId', 'farm_id'],
    'management/production_cycles.php'  => ['requireCurrentFarmId', 'farm_id'],
    'management/sales_records.php'      => ['requireCurrentFarmId', 'farm_id'],
    'inventory.php'                     => ['requireCurrentFarmId', 'farm_id'],
    'poultry/health.php'                => ['requireCurrentFarmId', 'farm_id'],
    'poultry/layers_daily_record.php'   => ['requireCurrentFarmId', 'farm_id'],
    'poultry/broiler_daily_record.php'  => ['requireCurrentFarmId', 'farm_id'],
    'ruminant/animal_registry.php'      => ['requireCurrentFarmId', 'farm_id'],
    'ruminant/animal_view.php'          => ['requireCurrentFarmId', 'farm_id'],
    'ruminant/ruminant_daily_record.php'=> ['requireCurrentFarmId', 'farm_id'],
];
foreach ($tenantScopedFiles as $route => $needles) {
    $content = auth_inv_file($root, $route);
    auth_inv_check("$route derives tenant scope from current session farm", auth_inv_has_all($content, $needles));
}

// Known broad legacy pages are protected centrally rather than being rewritten.
auth_inv_check('permission runtime contains Sales action-level guards',
    auth_inv_has_all($runtime, ['sales_records.php', 'sales_add', 'sales_edit', 'sales_payment']));
auth_inv_check('permission runtime contains daily record action-level guards',
    auth_inv_has_all($runtime, ['layers_daily_record.php', 'broiler_daily_record.php', 'ruminant_daily_record.php']));
auth_inv_check('permission runtime contains livestock expense action-level guards',
    auth_inv_has_all($runtime, ['layer_expenses.php', 'broiler_expenses.php', 'ruminant_expenses.php']));

// CSRF coverage must remain explicit either in the route or in a central bridge.
$inlineCsrfRoutes = [
    'management/farms.php',
    'management/users.php',
    'management/production_cycles.php',
    'poultry/health.php',
    'poultry/layers_daily_record.php',
    'poultry/broiler_daily_record.php',
    'poultry/layer_feeds.php',
    'poultry/broiler_feeds.php',
    'poultry/layer_expenses.php',
    'poultry/broiler_expenses.php',
    'ruminant/animal_registry.php',
    'ruminant/animal_view.php',
    'ruminant/ruminant_daily_record.php',
    'ruminant/ruminant_feeds_record.php',
    'ruminant/ruminant_expenses.php',
    'management/investigation.php',
    'management/ruminant_investigation.php',
    'management/ruminant_membership_integrity.php',
];
foreach ($inlineCsrfRoutes as $route) {
    $content = auth_inv_file($root, $route);
    $hasCsrf = str_contains($content, 'verify_csrf_token')
        || str_contains($content, 'require_valid_csrf_post')
        || str_contains($content, 'csrf_field()');
    auth_inv_check("$route has explicit CSRF protection/form token", $hasCsrf);
}
auth_inv_check('Inventory legacy POSTs are centrally CSRF-gated',
    str_contains($inventoryBridge, 'REQUEST_METHOD')
    && (str_contains($inventoryBridge, 'require_valid_csrf_post') || str_contains($inventoryBridge, 'csrf_request_is_valid')));
auth_inv_check('Sales legacy POSTs are centrally CSRF-gated',
    str_contains($closure, "'/management/sales_records.php'") && str_contains($closure, 'require_valid_csrf_post()'));

// This verifier itself must remain static/read-only. Inspect PHP tokens rather
// than searching source text, so security keywords inside this check do not
// create false positives.
$self = file_get_contents(__FILE__) ?: '';
$hasBootstrapInclude = false;
$hasPdoConstruction = false;
$afterNew = false;
foreach (token_get_all($self) as $token) {
    if (!is_array($token)) continue;
    [$tokenId, $tokenText] = $token;
    if (in_array($tokenId, [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
        $hasBootstrapInclude = true;
    }
    if ($tokenId === T_NEW) {
        $afterNew = true;
        continue;
    }
    if ($afterNew && $tokenId === T_WHITESPACE) continue;
    if ($afterNew) {
        if ($tokenId === T_STRING && strcasecmp($tokenText, 'PDO') === 0) $hasPdoConstruction = true;
        $afterNew = false;
    }
}
auth_inv_check('authorization verifier contains no database bootstrap',
    !$hasBootstrapInclude && !$hasPdoConstruction);

echo "\n" . count($checks) . " checks, $failures failure(s).\n";
if ($failures === 0) {
    echo "PASS: V2.3 formal authorization inventory is statically closed for the classified routes.\n";
    echo "NOTE: focused runtime negative tests are still required for the newly closed legacy boundaries.\n";
}
exit($failures === 0 ? 0 : 1);
