<?php
/**
 * Platform Owner tenant-view context.
 *
 * Platform Owner identity always remains attached to the dedicated owner
 * workspace. These helpers resolve a separately selected customer farm for
 * explicitly migrated read-only support pages; they never mutate session farm
 * identity or impersonate a tenant user.
 */

if (!function_exists('platform_owner_tenant_by_id')) {
    function platform_owner_tenant_by_id(PDO $pdo, int $farmId): ?array
    {
        if (!function_exists('isPlatformOwner') || !isPlatformOwner() || $farmId < 1) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT id, name, slug, subscription_plan, subscription_status,
                    subscription_starts_at, subscription_ends_at, contact_name,
                    contact_email
             FROM farms
             WHERE id = ? AND slug <> 'owner'
             LIMIT 1"
        );
        $stmt->execute([$farmId]);
        $farm = $stmt->fetch(PDO::FETCH_ASSOC);
        return $farm ?: null;
    }
}

if (!function_exists('platform_owner_tenant_from_request')) {
    function platform_owner_tenant_from_request(PDO $pdo, string $parameter = 'farm_id'): ?array
    {
        if (!function_exists('isPlatformOwner') || !isPlatformOwner()) {
            return null;
        }

        $requested = filter_var(
            $_GET[$parameter] ?? 0,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($requested) {
            return platform_owner_tenant_by_id($pdo, (int)$requested);
        }

        $stmt = $pdo->query(
            "SELECT id, name, slug, subscription_plan, subscription_status,
                    subscription_starts_at, subscription_ends_at, contact_name,
                    contact_email
             FROM farms
             WHERE slug <> 'owner'
             ORDER BY name, id
             LIMIT 1"
        );
        $farm = $stmt->fetch(PDO::FETCH_ASSOC);
        return $farm ?: null;
    }
}

if (!function_exists('platform_owner_tenant_modules')) {
    function platform_owner_tenant_modules(PDO $pdo, int $farmId): array
    {
        if (!function_exists('isPlatformOwner') || !isPlatformOwner() || $farmId < 1) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT module_code FROM farm_modules WHERE farm_id = ? AND is_enabled = 1 ORDER BY module_code'
        );
        $stmt->execute([$farmId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

if (!function_exists('platform_owner_tenant_list')) {
    function platform_owner_tenant_list(PDO $pdo): array
    {
        if (!function_exists('isPlatformOwner') || !isPlatformOwner()) {
            return [];
        }

        return $pdo->query(
            "SELECT id, name, slug, subscription_plan, subscription_status
             FROM farms
             WHERE slug <> 'owner'
             ORDER BY name, id"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
