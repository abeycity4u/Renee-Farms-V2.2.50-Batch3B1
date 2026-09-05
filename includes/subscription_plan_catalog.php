<?php
/**
 * Central V2.3 subscription plan catalog.
 *
 * Commercial contract:
 * - plans describe included commercial allowances;
 * - farm_modules still decides which operational modules a tenant has;
 * - farm_role_limits stores the tenant's effective seat limits;
 * - paid add-ons may extend plan defaults later without changing role logic.
 *
 * This foundation intentionally does not mutate farms or billing state by itself.
 */

if (!function_exists('subscription_plan_catalog')) {
    function subscription_plan_catalog(): array
    {
        return [
            'starter' => [
                'label' => 'Starter',
                'included_admins' => 1,
                'included_role_limits' => [
                    'poultry_manager' => 1,
                    'ruminant_manager' => 1,
                    'sales_rep' => 1,
                    'viewer' => 1,
                ],
            ],
            'growth' => [
                'label' => 'Growth',
                'included_admins' => 1,
                'included_role_limits' => [
                    'poultry_manager' => 3,
                    'ruminant_manager' => 3,
                    'sales_rep' => 2,
                    'viewer' => 2,
                ],
            ],
            'pro' => [
                'label' => 'Pro',
                'included_admins' => 1,
                'included_role_limits' => [
                    'poultry_manager' => 5,
                    'ruminant_manager' => 5,
                    'sales_rep' => 3,
                    'viewer' => 3,
                ],
            ],
        ];
    }
}

if (!function_exists('subscription_plan_codes')) {
    function subscription_plan_codes(): array
    {
        return array_keys(subscription_plan_catalog());
    }
}

if (!function_exists('subscription_plan_is_valid')) {
    function subscription_plan_is_valid(string $planCode): bool
    {
        return array_key_exists(strtolower(trim($planCode)), subscription_plan_catalog());
    }
}

if (!function_exists('subscription_plan_definition')) {
    function subscription_plan_definition(string $planCode): ?array
    {
        $planCode = strtolower(trim($planCode));
        return subscription_plan_catalog()[$planCode] ?? null;
    }
}

if (!function_exists('subscription_plan_label')) {
    function subscription_plan_label(string $planCode): string
    {
        $plan = subscription_plan_definition($planCode);
        return $plan['label'] ?? ucfirst(strtolower(trim($planCode)));
    }
}

if (!function_exists('subscription_plan_included_role_limits')) {
    function subscription_plan_included_role_limits(string $planCode, array $modules): array
    {
        $plan = subscription_plan_definition($planCode);
        if (!$plan) throw new InvalidArgumentException('Unknown subscription plan.');

        $limits = $plan['included_role_limits'] ?? [];
        if (function_exists('normalize_role_limits_for_entitlements')) {
            return normalize_role_limits_for_entitlements($limits, $modules);
        }
        return $limits;
    }
}

if (!function_exists('subscription_plan_effective_role_limits')) {
    function subscription_plan_effective_role_limits(string $planCode, array $modules, array $seatAddOns = []): array
    {
        $limits = subscription_plan_included_role_limits($planCode, $modules);
        foreach ($limits as $role => $baseLimit) {
            $addOn = filter_var(
                $seatAddOns[$role] ?? 0,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 500]]
            );
            $limits[$role] = (int)$baseLimit + ($addOn === false ? 0 : (int)$addOn);
        }

        if (function_exists('normalize_role_limits_for_entitlements')) {
            return normalize_role_limits_for_entitlements($limits, $modules);
        }
        return $limits;
    }
}
