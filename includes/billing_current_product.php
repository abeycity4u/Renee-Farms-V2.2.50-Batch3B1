<?php
/**
 * Canonical current commercial product service.
 *
 * One shared contract resolves the tenant's current plan, livestock bundle,
 * billing interval, purchased seat add-ons and server-authoritative renewal
 * price. Browser checkout fields are only assertions against this contract;
 * they never become commercial authority.
 */

require_once __DIR__ . '/subscription_plan_catalog.php';
require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_pricing_contract.php';
require_once __DIR__ . '/billing_tenant_actor.php';
require_once __DIR__ . '/subscription_record.php';
require_once __DIR__ . '/subscription_seat_policy.php';
require_once __DIR__ . '/farm_entitlements.php';

if (!function_exists('billing_current_product_normal_statuses')) {
    function billing_current_product_normal_statuses(): array
    {
        return ['trial', 'active', 'past_due'];
    }
}

if (!function_exists('billing_current_product_modules')) {
    function billing_current_product_modules(
        PDO $pdo,
        int $farmId,
        ?array $latest,
        bool $allowSnapshotFallback = false
    ): array {
        $modules = subscription_record_commercial_modules($pdo, $farmId);
        if ($modules) return $modules;
        if (!$allowSnapshotFallback) return [];

        // Compatibility fallback is reserved for recovery. A normal active/trial
        // tenant with no current commercial module rows needs administrator repair;
        // checkout must not resurrect an old bundle from history.
        $decoded = json_decode((string)($latest['modules_snapshot'] ?? ''), true);
        if (!is_array($decoded)) return [];
        try {
            return billing_pricing_normalize_modules($decoded);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('billing_current_product')) {
    function billing_current_product(PDO $pdo, int $farmId, array $allowedStatuses = []): array
    {
        if ($farmId < 1) {
            throw new InvalidArgumentException('A valid tenant is required for subscription billing.');
        }

        $farm = billing_tenant_actor_farm($pdo, ['farm_id' => $farmId]);
        if (!$farm || (int)($farm['id'] ?? 0) !== $farmId) {
            throw new RuntimeException('Tenant farm could not be found for subscription billing.');
        }

        $status = strtolower(trim((string)($farm['subscription_status'] ?? '')));
        if ($allowedStatuses) {
            $allowedStatuses = array_values(array_unique(array_map(
                static fn($value): string => strtolower(trim((string)$value)),
                $allowedStatuses
            )));
            if (!in_array($status, $allowedStatuses, true)) {
                throw new RuntimeException('The current subscription status does not allow this billing action.');
            }
        }

        $planCode = strtolower(trim((string)($farm['subscription_plan'] ?? '')));
        if (!subscription_plan_is_valid($planCode)) {
            throw new RuntimeException('The current subscription plan needs administrator review.');
        }

        $latest = subscription_record_latest($pdo, $farmId);
        $allowSnapshotFallback = in_array($status, ['suspended', 'cancelled'], true);
        $modules = billing_current_product_modules($pdo, $farmId, $latest, $allowSnapshotFallback);
        if (!$modules) {
            throw new RuntimeException('The current livestock subscription bundle needs administrator review.');
        }

        $billingInterval = strtolower(trim((string)($latest['billing_interval'] ?? 'monthly')));
        if (!in_array($billingInterval, ['monthly', 'annual'], true)) $billingInterval = 'monthly';

        $seatAddOns = subscription_seat_load_addons($pdo, $farmId, $planCode, $modules);
        $seatAddOns = subscription_seat_normalize_addons($seatAddOns);
        subscription_seat_assert_capacity($pdo, $farmId, $planCode, $modules, $seatAddOns);

        $pricedQuote = billing_pricing_build_payment_quote(
            $planCode,
            $billingInterval,
            $modules,
            $seatAddOns
        );

        return [
            'farm' => $farm,
            'status' => $status,
            'latest_subscription' => $latest,
            'plan_code' => $planCode,
            'modules' => $modules,
            'seat_addons' => $seatAddOns,
            'pricing' => $pricedQuote['pricing'],
            'payment_quote' => $pricedQuote,
        ];
    }
}

if (!function_exists('billing_current_product_assert_selection')) {
    function billing_current_product_assert_selection(
        PDO $pdo,
        int $farmId,
        array $selection,
        array $allowedStatuses = [],
        string $errorMessage = 'Billing checkout can only renew the current commercial product.'
    ): array {
        $current = billing_current_product($pdo, $farmId, $allowedStatuses);
        $expected = $current['pricing'];

        $actualPlan = strtolower(trim((string)($selection['plan_code'] ?? '')));
        $actualInterval = billing_pricing_normalize_interval((string)($selection['billing_interval'] ?? ''));
        $actualModules = billing_pricing_normalize_modules(
            is_array($selection['modules'] ?? null) ? $selection['modules'] : []
        );
        $actualSeats = billing_pricing_normalize_seat_addons(
            is_array($selection['seat_addons'] ?? null) ? $selection['seat_addons'] : []
        );

        if ($actualPlan !== $expected['plan_code']
            || $actualInterval !== $expected['billing_interval']
            || $actualModules !== $expected['modules']
            || $actualSeats !== $expected['seat_addons']) {
            throw new InvalidArgumentException($errorMessage);
        }

        return $current;
    }
}
?>
