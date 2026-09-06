<?php
/**
 * Canonical farm subscription/module entitlement helpers.
 *
 * V2.3 contract:
 * - farm_modules describes what a tenant subscribes to.
 * - user_roles describes who a user is.
 * - permissions describes what an eligible role may do.
 * - historical operational records are never deleted when an entitlement is disabled.
 * - basic Sales is a shared farm capability whenever Poultry or Ruminant is enabled.
 *
 * Keep subscription/module decisions centralized here so Dashboard, navigation,
 * farm administration and operational routes do not reimplement entitlement logic.
 */

if (!function_exists('farm_entitlement_known_modules')) {
    function farm_entitlement_known_modules(): array
    {
        return ['poultry', 'ruminant', 'sales'];
    }
}

if (!function_exists('farm_entitlement_module_labels')) {
    function farm_entitlement_module_labels(): array
    {
        return [
            'poultry' => 'Poultry',
            'ruminant' => 'Ruminant',
            'sales' => 'Sales',
        ];
    }
}

if (!function_exists('farm_entitlement_normalize_modules')) {
    function farm_entitlement_normalize_modules(array $modules): array
    {
        $known = farm_entitlement_known_modules();
        $normalized = [];
        foreach ($modules as $module) {
            $module = strtolower(trim((string) $module));
            if ($module !== '' && in_array($module, $known, true) && !in_array($module, $normalized, true)) {
                $normalized[] = $module;
            }
        }
        return $normalized;
    }
}

if (!function_exists('farm_entitlement_modules')) {
    function farm_entitlement_modules(PDO $pdo, int $farmId): array
    {
        if ($farmId < 1) return [];
        $stmt = $pdo->prepare(
            'SELECT module_code FROM farm_modules WHERE farm_id = ? AND is_enabled = 1 ORDER BY module_code'
        );
        $stmt->execute([$farmId]);
        return farm_entitlement_normalize_modules($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}

if (!function_exists('farm_entitlement_has')) {
    function farm_entitlement_has(PDO $pdo, int $farmId, string $module): bool
    {
        $module = strtolower(trim($module));
        if ($farmId < 1 || !in_array($module, farm_entitlement_known_modules(), true)) return false;
        $stmt = $pdo->prepare(
            'SELECT 1 FROM farm_modules WHERE farm_id = ? AND module_code = ? AND is_enabled = 1 LIMIT 1'
        );
        $stmt->execute([$farmId, $module]);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('farm_entitlement_sales_available')) {
    function farm_entitlement_sales_available(PDO $pdo, int $farmId): bool
    {
        if ($farmId < 1) return false;
        $enabled = farm_entitlement_modules($pdo, $farmId);
        return (bool) array_intersect(['poultry', 'ruminant', 'sales'], $enabled);
    }
}

/**
 * Specialist roles commercially available for a tenant.
 *
 * Keep role visibility and permission-saving code on the same entitlement source
 * of truth. Sales Representative is available whenever shared Sales is available;
 * it does not require a legacy standalone farm_modules.sales row.
 */
if (!function_exists('farm_entitlement_available_specialist_roles')) {
    function farm_entitlement_available_specialist_roles(PDO $pdo, int $farmId): array
    {
        $enabled = farm_entitlement_modules($pdo, $farmId);
        $roles = [];
        if (in_array('poultry', $enabled, true)) $roles[] = 'poultry_manager';
        if (in_array('ruminant', $enabled, true)) $roles[] = 'ruminant_manager';
        if (farm_entitlement_sales_available($pdo, $farmId)) $roles[] = 'sales_rep';
        return $roles;
    }
}

/**
 * Shared Sales is an operational capability, not a standalone specialist module.
 * Farm Admin, livestock managers and the dedicated Sales Representative may use it
 * when the tenant entitlement and granular Sales permission allow the action.
 */
if (!function_exists('user_has_shared_sales_role')) {
    function user_has_shared_sales_role(): bool
    {
        if (!function_exists('hasRole')) return false;
        return hasRole('farm_admin')
            || hasRole('poultry_manager')
            || hasRole('ruminant_manager')
            || hasRole('sales_rep');
    }
}

if (!function_exists('current_farm_entitlement_modules')) {
    function current_farm_entitlement_modules(): array
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !function_exists('getCurrentFarmId')) return [];
        return farm_entitlement_modules($pdo, (int) getCurrentFarmId());
    }
}

if (!function_exists('current_farm_has_entitlement')) {
    function current_farm_has_entitlement(string $module): bool
    {
        global $pdo;
        if (!($pdo instanceof PDO) || !function_exists('getCurrentFarmId')) return false;
        $farmId = (int) getCurrentFarmId();
        $module = strtolower(trim($module));
        if ($module === 'sales') return farm_entitlement_sales_available($pdo, $farmId);
        return farm_entitlement_has($pdo, $farmId, $module);
    }
}

if (!function_exists('effective_user_modules')) {
    function effective_user_modules(): array
    {
        if (function_exists('isPlatformOwner') && isPlatformOwner()) {
            // Platform Owner uses explicit tenant-view context for customer data.
            // Do not infer a customer subscription from the dedicated owner workspace.
            return [];
        }
        if (!function_exists('hasRole')) return [];

        $enabled = current_farm_entitlement_modules();
        $salesAvailable = (bool) array_intersect(['poultry', 'ruminant', 'sales'], $enabled);

        if (hasRole('farm_admin')) {
            $effective = $enabled;
            if ($salesAvailable && !in_array('sales', $effective, true)) $effective[] = 'sales';
            return $effective;
        }

        $effective = [];
        if (hasRole('poultry_manager') && in_array('poultry', $enabled, true)) $effective[] = 'poultry';
        if (hasRole('ruminant_manager') && in_array('ruminant', $enabled, true)) $effective[] = 'ruminant';
        if ($salesAvailable && user_has_shared_sales_role()) $effective[] = 'sales';
        return array_values(array_unique($effective));
    }
}

if (!function_exists('user_can_access_entitled_module')) {
    function user_can_access_entitled_module(string $module): bool
    {
        $module = strtolower(trim($module));
        if (!in_array($module, farm_entitlement_known_modules(), true)) return false;
        if (function_exists('isPlatformOwner') && isPlatformOwner()) return true;
        return in_array($module, effective_user_modules(), true);
    }
}

if (!function_exists('require_entitled_module')) {
    function require_entitled_module(string $module): void
    {
        if (user_can_access_entitled_module($module)) return;
        http_response_code(403);
        if (defined('BASE_URL') && !headers_sent()) {
            header('Location: ' . BASE_URL . '/no_access.php');
            exit();
        }
        exit('Access denied.');
    }
}

if (!function_exists('effective_livestock_scope')) {
    function effective_livestock_scope(): array
    {
        return array_values(array_intersect(['poultry', 'ruminant'], effective_user_modules()));
    }
}

if (!function_exists('sync_farm_entitlements')) {
    function sync_farm_entitlements(PDO $pdo, int $farmId, array $modules): void
    {
        if ($farmId < 1) throw new InvalidArgumentException('A valid farm is required.');
        $modules = farm_entitlement_normalize_modules($modules);

        $pdo->prepare('DELETE FROM farm_modules WHERE farm_id = ?')->execute([$farmId]);
        if (!$modules) return;

        $stmt = $pdo->prepare(
            'INSERT INTO farm_modules (farm_id, module_code, is_enabled) VALUES (?, ?, 1)'
        );
        foreach ($modules as $module) $stmt->execute([$farmId, $module]);
    }
}

if (!function_exists('assign_protected_farm_admin_role')) {
    function assign_protected_farm_admin_role(PDO $pdo, int $farmId, int $userId): void
    {
        if ($farmId < 1 || $userId < 1) throw new InvalidArgumentException('A valid farm admin account is required.');

        $userStmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND farm_id = ? AND user_type = 'farm_admin' LIMIT 1");
        $userStmt->execute([$userId, $farmId]);
        if (!$userStmt->fetchColumn()) throw new RuntimeException('The protected Farm Admin account could not be verified.');

        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE code = 'farm_admin' LIMIT 1");
        $roleStmt->execute();
        $farmAdminRoleId = (int) $roleStmt->fetchColumn();
        if ($farmAdminRoleId < 1) throw new RuntimeException('Required farm_admin role is missing from the roles table.');

        // The protected tenant administrator has one identity only. Module access is
        // controlled by farm_modules, never by attaching specialist roles here.
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
        $insert = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
        $insert->execute([$userId, $farmAdminRoleId]);
    }
}

if (!function_exists('normalize_role_limits_for_entitlements')) {
    function normalize_role_limits_for_entitlements(array $input, array $modules): array
    {
        $modules = farm_entitlement_normalize_modules($modules);
        $salesAvailable = (bool) array_intersect(['poultry', 'ruminant', 'sales'], $modules);
        $defaults = [
            'poultry_manager' => 1,
            'ruminant_manager' => 1,
            'sales_rep' => 1,
            'viewer' => 1,
        ];

        $out = [];
        foreach ($defaults as $role => $default) {
            $value = filter_var(
                $input[$role] ?? $default,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 500]]
            );
            $out[$role] = $value === false ? $default : (int) $value;

            if ($role === 'poultry_manager' && !in_array('poultry', $modules, true)) $out[$role] = 0;
            if ($role === 'ruminant_manager' && !in_array('ruminant', $modules, true)) $out[$role] = 0;
            if ($role === 'sales_rep' && !$salesAvailable) $out[$role] = 0;
        }
        return $out;
    }
}
