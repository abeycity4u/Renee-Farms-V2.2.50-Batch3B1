<?php
/**
 * Canonical same-product reactivation quote.
 *
 * Recovery never silently changes a tenant's plan, livestock bundle, seats or
 * billing interval. It rebuilds the current commercial product through the
 * versioned server-side price book and lets checkout verify the same contract
 * again before creating a provider attempt.
 */

require_once __DIR__ . '/subscription_plan_catalog.php';
require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_pricing_contract.php';
require_once __DIR__ . '/subscription_record.php';
require_once __DIR__ . '/subscription_seat_policy.php';
require_once __DIR__ . '/farm_entitlements.php';

if (!function_exists('billing_reactivation_modules')) {
    function billing_reactivation_modules(PDO $pdo, int $farmId, ?array $latest): array
    {
        $modules = subscription_record_commercial_modules($pdo, $farmId);
        if ($modules) return $modules;

        $decoded = json_decode((string)($latest['modules_snapshot'] ?? ''), true);
        if (!is_array($decoded)) return [];
        try {
            return billing_pricing_normalize_modules($decoded);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('billing_reactivation_quote')) {
    function billing_reactivation_quote(PDO $pdo, int $farmId): array
    {
        if ($farmId < 1) throw new InvalidArgumentException('A valid tenant is required for subscription recovery.');

        $stmt = $pdo->prepare(
            "SELECT id, name, slug, subscription_plan, subscription_status,
                    subscription_starts_at, subscription_ends_at
             FROM farms WHERE id = ? AND slug <> 'owner' LIMIT 1"
        );
        $stmt->execute([$farmId]);
        $farm = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$farm) throw new RuntimeException('Tenant farm could not be found for subscription recovery.');

        $status = strtolower(trim((string)($farm['subscription_status'] ?? '')));
        if (!in_array($status, ['suspended', 'cancelled'], true)) {
            throw new RuntimeException('This subscription does not require recovery.');
        }

        $planCode = strtolower(trim((string)($farm['subscription_plan'] ?? '')));
        if (!subscription_plan_is_valid($planCode)) {
            throw new RuntimeException('The current subscription plan needs administrator review before recovery.');
        }

        $latest = subscription_record_latest($pdo, $farmId);
        $modules = billing_reactivation_modules($pdo, $farmId, $latest);
        if (!$modules) {
            throw new RuntimeException('The current livestock subscription bundle needs administrator review before recovery.');
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
            'latest_subscription' => $latest,
            'pricing' => $pricedQuote['pricing'],
            'payment_quote' => $pricedQuote,
        ];
    }
}

if (!function_exists('billing_reactivation_assert_selection')) {
    function billing_reactivation_assert_selection(PDO $pdo, int $farmId, array $selection): array
    {
        $reactivation = billing_reactivation_quote($pdo, $farmId);
        $expected = $reactivation['pricing'];

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
            throw new InvalidArgumentException('Subscription recovery can only renew the current commercial product.');
        }

        return $reactivation;
    }
}
?>