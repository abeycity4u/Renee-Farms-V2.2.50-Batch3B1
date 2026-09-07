<?php
/**
 * Canonical tenant-facing billing account read model.
 *
 * Contract:
 * - this service is read-only and tenant-pinned;
 * - farms remains the current subscription snapshot;
 * - subscriptions remains the append-only commercial history;
 * - billing_payment_attempts remains the provider-neutral payment audit history;
 * - current renewal pricing always comes from the server-authoritative price book;
 * - seat usage/allowance always comes from the canonical subscription seat policy.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_pricing_contract.php';
require_once __DIR__ . '/billing_tenant_actor.php';
require_once __DIR__ . '/subscription_plan_catalog.php';
require_once __DIR__ . '/subscription_record.php';
require_once __DIR__ . '/subscription_seat_policy.php';
require_once __DIR__ . '/farm_entitlements.php';

if (!function_exists('billing_account_payment_attempts')) {
    function billing_account_payment_attempts(PDO $pdo, int $farmId, int $limit = 20): array
    {
        if ($farmId < 1 || !billing_payment_table_exists($pdo, 'billing_payment_attempts')) return [];
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare(
            'SELECT id, status, provider, plan_code, billing_interval, amount, currency,
                    verified_at, paid_at, failed_at, created_at, updated_at
             FROM billing_payment_attempts
             WHERE farm_id = ?
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$farmId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('billing_account_seat_summary')) {
    function billing_account_seat_summary(
        PDO $pdo,
        int $farmId,
        string $planCode,
        array $modules,
        array $seatAddOns
    ): array {
        $effective = subscription_seat_load_effective_limits($pdo, $farmId);
        $used = subscription_seat_used_role_counts($pdo, $farmId);
        $summary = [];

        foreach (subscription_seat_roles() as $role => $label) {
            if (!subscription_seat_role_relevant($role, $modules)) continue;
            $summary[] = [
                'role' => $role,
                'label' => $label,
                'used' => max(0, (int)($used[$role] ?? 0)),
                'limit' => max(0, (int)($effective[$role] ?? 0)),
                'extra' => max(0, (int)($seatAddOns[$role] ?? 0)),
            ];
        }
        return $summary;
    }
}

if (!function_exists('billing_account_overview')) {
    function billing_account_overview(PDO $pdo, int $farmId): array
    {
        if ($farmId < 1) throw new InvalidArgumentException('A valid tenant is required for billing account access.');

        $farm = billing_tenant_actor_farm($pdo, ['farm_id' => $farmId]);
        if (!$farm || (int)($farm['id'] ?? 0) !== $farmId) {
            throw new RuntimeException('Tenant farm could not be found for billing account access.');
        }

        $planCode = strtolower(trim((string)($farm['subscription_plan'] ?? '')));
        if (!subscription_plan_is_valid($planCode)) {
            throw new RuntimeException('The current subscription plan needs administrator review.');
        }

        $modules = subscription_record_commercial_modules($pdo, $farmId);
        if (!$modules) {
            throw new RuntimeException('The current livestock subscription bundle needs administrator review.');
        }

        $latest = subscription_record_latest($pdo, $farmId);
        $billingInterval = strtolower(trim((string)($latest['billing_interval'] ?? 'monthly')));
        if (!in_array($billingInterval, ['monthly', 'annual'], true)) $billingInterval = 'monthly';

        $seatAddOns = subscription_seat_load_addons($pdo, $farmId, $planCode, $modules);
        $seatAddOns = subscription_seat_normalize_addons($seatAddOns);
        $pricedQuote = billing_pricing_build_payment_quote(
            $planCode,
            $billingInterval,
            $modules,
            $seatAddOns
        );

        return [
            'farm' => $farm,
            'latest_subscription' => $latest,
            'plan_label' => subscription_plan_label($planCode),
            'modules' => $modules,
            'seat_addons' => $seatAddOns,
            'seat_summary' => billing_account_seat_summary($pdo, $farmId, $planCode, $modules, $seatAddOns),
            'pricing' => $pricedQuote['pricing'],
            'subscription_history' => subscription_record_history($pdo, $farmId, 12),
            'payment_attempts' => billing_account_payment_attempts($pdo, $farmId, 20),
            'payment_foundation_ready' => billing_payment_foundation_ready($pdo),
        ];
    }
}
?>
