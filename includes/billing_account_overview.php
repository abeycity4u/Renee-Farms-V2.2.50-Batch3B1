<?php
/**
 * Canonical tenant-facing billing account read model.
 *
 * Contract:
 * - this service is read-only and tenant-pinned;
 * - farms remains the current subscription snapshot;
 * - subscriptions remains the append-only commercial history;
 * - billing_payment_attempts remains the provider-neutral payment audit history;
 * - current product/pricing comes from the shared current-product contract;
 * - seat usage/allowance always comes from the canonical subscription seat policy.
 */

require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_current_product.php';
require_once __DIR__ . '/subscription_record.php';
require_once __DIR__ . '/subscription_seat_policy.php';
require_once __DIR__ . '/billing_renewal_seat_target.php';

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

if (!function_exists(
    'billing_account_scheduled_reductions'
)) {
    function billing_account_scheduled_reductions(
        PDO $pdo,
        int $farmId,
        ?array $renewalSeatTarget
    ): array {
        if ($farmId < 1
            || !is_array($renewalSeatTarget)) {
            return [];
        }

        $requestIds =
            is_array(
                $renewalSeatTarget[
                    'scheduled_request_ids'
                ] ?? null
            )
                ? $renewalSeatTarget[
                    'scheduled_request_ids'
                ]
                : [];

        if (!$requestIds) {
            return [];
        }

        $roleLabels =
            subscription_seat_roles();

        $scheduled = [];

        foreach ($requestIds as $requestId) {
            $requestId = (int)$requestId;

            if ($requestId < 1) {
                throw new RuntimeException(
                    'Scheduled seat reduction has an invalid request id.'
                );
            }

            $row =
                billing_seat_change_request_by_id(
                    $pdo,
                    $requestId,
                    false
                );

            if (!$row) {
                throw new RuntimeException(
                    'Scheduled seat reduction could not be loaded for billing display.'
                );
            }

            $state =
                billing_seat_change_row_contract(
                    $row
                );

            $contract =
                is_array(
                    $state['contract'] ?? null
                )
                    ? $state['contract']
                    : [];

            $role =
                (string)(
                    $contract['role_code']
                        ?? ''
                );

            if (($state['status'] ?? '')
                    !== 'scheduled'
                || ($contract[
                    'change_kind'
                ] ?? '') !== 'remove'
                || (int)(
                    $contract['farm_id']
                        ?? 0
                ) !== $farmId
                || !array_key_exists(
                    $role,
                    $roleLabels
                )) {
                throw new RuntimeException(
                    'Scheduled seat reduction is invalid for billing display.'
                );
            }

            $scheduled[] = [
                'request_id' =>
                    $requestId,
                'role' =>
                    $role,
                'label' =>
                    (string)$roleLabels[$role],
                'from_extra_seats' =>
                    (int)(
                        $contract[
                            'from_extra_seats'
                        ] ?? 0
                    ),
                'to_extra_seats' =>
                    (int)(
                        $contract[
                            'to_extra_seats'
                        ] ?? 0
                    ),
                'effective_at' =>
                    (string)(
                        $contract[
                            'effective_at'
                        ] ?? ''
                    ),
            ];
        }

        return $scheduled;
    }
}
if (!function_exists('billing_account_overview')) {
    function billing_account_overview(PDO $pdo, int $farmId): array
    {
        $current = billing_current_product(
            $pdo,
            $farmId,
            billing_current_product_normal_statuses()
        );
        $farm = $current['farm'];
        $planCode = (string)$current['plan_code'];
        $modules = $current['modules'];
        $seatAddOns = $current['seat_addons'];

        $seatChangeReady =
            billing_seat_change_ready($pdo);

        $renewalSeatTarget = null;

        if (($current['status'] ?? '') === 'active'
            && $seatChangeReady) {
            $renewalSeatTarget =
                billing_renewal_seat_target(
                    $pdo,
                    $farmId,
                    false,
                    ['active']
                );
        }

        return [
            'farm' => $farm,
            'latest_subscription' => $current['latest_subscription'],
            'plan_label' => subscription_plan_label($planCode),
            'modules' => $modules,
            'seat_addons' => $seatAddOns,
            'seat_summary' => billing_account_seat_summary($pdo, $farmId, $modules, $seatAddOns),
            'seat_change_ready' => $seatChangeReady,
            'renewal_seat_target' => $renewalSeatTarget,
            'scheduled_reductions' =>
                billing_account_scheduled_reductions(
                    $pdo,
                    $farmId,
                    $renewalSeatTarget
                ),
            'pricing' => $current['pricing'],
            'subscription_history' => subscription_record_history($pdo, $farmId, 12),
            'payment_attempts' => billing_account_payment_attempts($pdo, $farmId, 20),
            'payment_foundation_ready' => billing_payment_foundation_ready($pdo),
        ];
    }
}
?>
