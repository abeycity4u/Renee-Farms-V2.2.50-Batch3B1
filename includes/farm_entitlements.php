<?php
/**
 * Canonical farm subscription/module entitlement helpers.
 *
 * V2.3 contract:
 * - farm_modules describes what a tenant subscribes to.
 * - user_roles describes who a user is.
 * - permissions describes what an eligible role may do.
 * - historical operational records are never deleted when an entitlement is disabled.
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
        return farm_entitlement_has($pdo, (int) getCurrentFarmId(), $module);
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
        if (hasRole('farm_admin')) return $enabled;

        $effective = [];
        if (hasRole('poultry_manager') && in_array('poultry', $enabled, true)) $effective[] = 'poultry';
        if (hasRole('ruminant_manager') && in_array('ruminant', $enabled, true)) $effective[] = 'ruminant';
        if (hasRole('sales_rep') && in_array('sales', $enabled, true)) $effective[] = 'sales';
        return $effective;
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

if (!function_exists('normalize_role_limits_for_entitlements')) {
    function normalize_role_limits_for_entitlements(array $input, array $modules): array
    {
        $modules = farm_entitlement_normalize_modules($modules);
        $roleModules = [
            'poultry_manager' => 'poultry',
            'ruminant_manager' => 'ruminant',
            'sales_rep' => 'sales',
        ];
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
            if (isset($roleModules[$role]) && !in_array($roleModules[$role], $modules, true)) {
                $out[$role] = 0;
            }
        }
        return $out;
    }
}
