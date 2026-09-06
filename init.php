<?php
// init.php - common bootstrap for all pages when included
// Use __DIR__-based includes; this file should be required using an absolute path from each script when possible.
if (!defined('PROJECT_ROOT')) define('PROJECT_ROOT', __DIR__);
// V2.2.27: static asset versioning previously stayed at 2024.06.01, so browsers
// could keep an older CSS/JS file after a deployment. Prefer each local asset's
// mtime as the cache key and keep the release token only as a safe fallback.
if (!defined('ASSET_VERSION')) define('ASSET_VERSION', '2.2.27');

if (!function_exists('versioned_asset')) {
    function versioned_asset(string $path): string
    {
        $assetPath = parse_url($path, PHP_URL_PATH);
        $localPath = PROJECT_ROOT . '/' . ltrim((string) $assetPath, '/');
        $version = is_file($localPath) ? (string) filemtime($localPath) : ASSET_VERSION;
        $delimiter = strpos($path, '?') === false ? '?' : '&';
        return $path . $delimiter . 'v=' . rawurlencode($version);
    }
}
if (!isset($pdo)) {
    // try to load config.php which should create $pdo
    if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';
}

// Canonical V2.3 subscription/module entitlement layer. Keep farm subscription,
// user identity/roles and granular permissions as separate concerns.
require_once __DIR__ . '/includes/farm_entitlements.php';

// Central commercial plan catalog. Plans define included allowances; farm_modules
// and farm_role_limits remain the persisted tenant entitlement/seat sources.
require_once __DIR__ . '/includes/subscription_plan_catalog.php';

// Durable current-subscription seat policy. Purchased extras are stored separately
// from the effective farm_role_limits runtime allowance and plan reductions must
// not leave an active commercial role above its new seat capacity.
require_once __DIR__ . '/includes/subscription_seat_policy.php';

// Canonical commercial subscription history service. farms remains the current
// runtime snapshot while subscriptions stores auditable commercial snapshots.
require_once __DIR__ . '/includes/subscription_record.php';

// Platform Farms bridge: apply plan-driven included seats while keeping livestock
// module selection independent and Sales as a shared core capability.
require_once __DIR__ . '/includes/subscription_plan_farms.php';

// Keep Team Users role pickers clean by omitting roles for unsubscribed modules;
// backend role/module validation in management/users.php remains authoritative.
require_once __DIR__ . '/includes/team_user_role_visibility.php';

// Give Platform Owner a direct route to the existing read-only selected-tenant
// support view without changing session farm identity or impersonating a tenant.
require_once __DIR__ . '/includes/platform_owner_nav_discoverability.php';

// Present a friendly 403 page when a tenant account manually opens the dedicated
// Platform Owner support route. Authorization remains Platform Owner-only.
require_once __DIR__ . '/includes/platform_owner_tenant_access_message.php';

// Extend the dedicated Platform Owner tenant support page with tenant-scoped,
// read-only operational drill-down details while preserving owner identity.
require_once __DIR__ . '/includes/platform_owner_tenant_drilldown.php';
require_once __DIR__ . '/includes/platform_owner_tenant_commercial_drilldown.php';
require_once __DIR__ . '/includes/platform_owner_tenant_expense_drilldown.php';

// Enforce the subscription boundary before legacy role/permission runtime guards
// so Farm Admin bypasses cannot expose a module the tenant no longer subscribes to.
require_once __DIR__ . '/includes/farm_entitlement_runtime.php';

// Central browser-form CSRF rendering and POST enforcement helpers.
require_once __DIR__ . '/includes/csrf.php';

// Keep Inventory View as the parent gate for all Inventory reads/mutations,
// preserve manager module scope, and bridge granular Add Item / Update Stock
// permissions across the large legacy Inventory page and its read endpoints.
require_once __DIR__ . '/includes/inventory_permission_hardening.php';

// Runtime route/action permission enforcement for legacy pages that still carry
// older role/module checks internally. This keeps View/Add/Edit/Delete behavior
// consistent while those individual pages are migrated gradually.
require_once __DIR__ . '/includes/permission_runtime.php';

// Legacy read APIs historically stopped at module-role checks. Align those data
// endpoints with the same explicit View permissions used by their browser pages.
require_once __DIR__ . '/includes/legacy_api_view_permissions.php';

// Reject Team Users edit targets that do not belong to the current/selected
// tenant before legacy role-assignment code can touch user_roles.
require_once __DIR__ . '/includes/user_management_tenant_guard.php';

// Production Cycles is delegated as View only; keep delegated cycle/workspace
// pages read-only and enforce the admin-only mutation boundary server-side.
require_once __DIR__ . '/includes/production_cycle_view_permissions.php';

// Keep Customer Debt Management ledger Edit/Delete independently delegable
// while the large Sales Records page still carries an admin-only legacy flag.
require_once __DIR__ . '/includes/sales_receivable_permissions.php';

// Keep Ruminant Feed Records View and Add permissions independent while the
// legacy feed page still combines those capabilities internally.
require_once __DIR__ . '/includes/ruminant_feed_permissions.php';

// Keep Layer, Broiler and Ruminant expense Edit/Delete controls independent on
// legacy operational expense pages. The APIs remain the authorization boundary.
require_once __DIR__ . '/includes/expense_action_permissions.php';

// Server-side dashboard overview filtering for the large legacy dashboard page.
// This keeps Poultry/Ruminant overview summaries independent from page/action
// permissions without reconstructing dashboard.php during the hardening pass.
require_once __DIR__ . '/includes/dashboard_overview_permissions.php';

// Keep Dashboard Farm Intelligence, Inventory/Sales summary visibility and stock
// quick-actions aligned with delegated permissions while backend routes/APIs remain authoritative.
require_once __DIR__ . '/includes/dashboard_action_permissions.php';

// Hide permission-controlled controls before first paint so read-only users never
// see restricted buttons or links flash briefly while client cleanup runs.
require_once __DIR__ . '/includes/permission_prepaint.php';
?>