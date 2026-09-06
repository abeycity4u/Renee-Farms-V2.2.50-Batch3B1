<?php
/**
 * Canonical V2.3 commercial subscription record service.
 *
 * Contract:
 * - farms remains the runtime/current subscription snapshot used by the app;
 * - subscriptions is the append-only commercial history table;
 * - farm_modules and farm_subscription_seat_addons remain the current entitlement
 *   and purchased-extra-seat sources;
 * - consecutive no-op captures do not create duplicate history rows;
 * - real plan/status/date/module/seat changes create a new immutable snapshot row;
 * - existing billing/provider metadata is carried forward until the billing phase
 *   starts writing those fields deliberately.
 */

if (!function_exists('subscription_record_required_columns')) {
    function subscription_record_required_columns(): array
    {
        return [
            'farm_id',
            'plan_code',
            'status',
            'billing_interval',
            'amount',
            'currency',
            'provider',
            'provider_subscription_id',
            'current_period_ends_at',
            'subscription_starts_at',
            'subscription_ends_at',
            'modules_snapshot',
            'seat_addons_snapshot',
            'change_reason',
            'recorded_by_user_id',
            'snapshot_hash',
            'created_at',
        ];
    }
}

if (!function_exists('subscription_record_table_exists')) {
    function subscription_record_table_exists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'subscriptions'"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('subscription_record_table_ready')) {
    function subscription_record_table_ready(PDO $pdo): bool
    {
        if (!subscription_record_table_exists($pdo)) return false;
        $stmt = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'subscriptions'"
        );
        $stmt->execute();
        $columns = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [], true);
        foreach (subscription_record_required_columns() as $column) {
            if (!isset($columns[$column])) return false;
        }
        return true;
    }
}

if (!function_exists('subscription_record_commercial_modules')) {
    function subscription_record_commercial_modules(PDO $pdo, int $farmId): array
    {
        if ($farmId < 1) return [];
        $modules = function_exists('farm_entitlement_modules')
            ? farm_entitlement_modules($pdo, $farmId)
            : [];
        $modules = array_values(array_intersect(['poultry', 'ruminant'], $modules));
        sort($modules, SORT_STRING);
        return $modules;
    }
}

if (!function_exists('subscription_record_build_snapshot')) {
    function subscription_record_build_snapshot(PDO $pdo, int $farmId): array
    {
        if ($farmId < 1) throw new InvalidArgumentException('A valid farm is required for a subscription record.');

        $stmt = $pdo->prepare(
            "SELECT id, name, slug, subscription_plan, subscription_status,
                    subscription_starts_at, subscription_ends_at
             FROM farms
             WHERE id = ? AND slug <> 'owner'
             LIMIT 1"
        );
        $stmt->execute([$farmId]);
        $farm = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$farm) throw new RuntimeException('Tenant farm could not be found for subscription recording.');

        $planCode = strtolower(trim((string)($farm['subscription_plan'] ?? 'starter')));
        $modules = subscription_record_commercial_modules($pdo, $farmId);
        $seatAddOns = function_exists('subscription_seat_load_addons')
            ? subscription_seat_load_addons($pdo, $farmId, $planCode, $modules)
            : ['poultry_manager' => 0, 'ruminant_manager' => 0, 'sales_rep' => 0, 'viewer' => 0];
        if (function_exists('subscription_seat_normalize_addons')) {
            $seatAddOns = subscription_seat_normalize_addons($seatAddOns);
        }
        ksort($seatAddOns, SORT_STRING);

        $snapshot = [
            'plan_code' => $planCode,
            'status' => strtolower(trim((string)($farm['subscription_status'] ?? 'trial'))),
            'subscription_starts_at' => $farm['subscription_starts_at'] ?: null,
            'subscription_ends_at' => $farm['subscription_ends_at'] ?: null,
            'modules' => $modules,
            'seat_addons' => $seatAddOns,
        ];
        $canonical = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($canonical === false) throw new RuntimeException('Unable to encode the commercial subscription snapshot.');

        return [
            'farm' => $farm,
            'snapshot' => $snapshot,
            'snapshot_json' => $canonical,
            'snapshot_hash' => hash('sha256', $canonical),
        ];
    }
}

if (!function_exists('subscription_record_latest')) {
    function subscription_record_latest(PDO $pdo, int $farmId): ?array
    {
        if ($farmId < 1 || !subscription_record_table_exists($pdo)) return null;
        $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE farm_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$farmId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('subscription_record_capture')) {
    function subscription_record_capture(
        PDO $pdo,
        int $farmId,
        string $reason = 'platform_owner_update',
        ?int $recordedByUserId = null
    ): array {
        if (!subscription_record_table_ready($pdo)) {
            throw new RuntimeException(
                'Commercial subscription record storage is not installed. Apply migration 041_commercial_subscription_records.sql first.'
            );
        }

        $built = subscription_record_build_snapshot($pdo, $farmId);
        $snapshot = $built['snapshot'];
        $hash = (string)$built['snapshot_hash'];
        $latest = subscription_record_latest($pdo, $farmId);

        if ($latest && hash_equals((string)($latest['snapshot_hash'] ?? ''), $hash)) {
            return [
                'inserted' => false,
                'id' => (int)$latest['id'],
                'snapshot_hash' => $hash,
                'snapshot' => $snapshot,
            ];
        }

        $billingInterval = strtolower(trim((string)($latest['billing_interval'] ?? 'monthly')));
        if (!in_array($billingInterval, ['monthly', 'annual'], true)) $billingInterval = 'monthly';

        $amount = isset($latest['amount']) && is_numeric($latest['amount'])
            ? number_format((float)$latest['amount'], 2, '.', '')
            : '0.00';

        $currency = strtoupper(trim((string)($latest['currency'] ?? 'USD')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'USD';

        $provider = trim((string)($latest['provider'] ?? ''));
        $provider = $provider !== '' ? $provider : null;
        $providerSubscriptionId = trim((string)($latest['provider_subscription_id'] ?? ''));
        $providerSubscriptionId = $providerSubscriptionId !== '' ? $providerSubscriptionId : null;
        $currentPeriodEndsAt = $latest['current_period_ends_at'] ?? null;

        $reason = strtolower(trim($reason));
        $reason = preg_replace('/[^a-z0-9_.-]+/', '_', $reason) ?: 'subscription_change';
        $reason = substr($reason, 0, 80);
        $recordedByUserId = ($recordedByUserId !== null && $recordedByUserId > 0)
            ? $recordedByUserId
            : null;

        $stmt = $pdo->prepare(
            'INSERT INTO subscriptions (
                farm_id, plan_code, status, billing_interval, amount, currency,
                provider, provider_subscription_id, current_period_ends_at,
                subscription_starts_at, subscription_ends_at,
                modules_snapshot, seat_addons_snapshot,
                change_reason, recorded_by_user_id, snapshot_hash
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $farmId,
            $snapshot['plan_code'],
            $snapshot['status'],
            $billingInterval,
            $amount,
            $currency,
            $provider,
            $providerSubscriptionId,
            $currentPeriodEndsAt,
            $snapshot['subscription_starts_at'],
            $snapshot['subscription_ends_at'],
            json_encode($snapshot['modules'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($snapshot['seat_addons'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $reason,
            $recordedByUserId,
            $hash,
        ]);

        return [
            'inserted' => true,
            'id' => (int)$pdo->lastInsertId(),
            'snapshot_hash' => $hash,
            'snapshot' => $snapshot,
        ];
    }
}

if (!function_exists('subscription_record_history')) {
    function subscription_record_history(PDO $pdo, int $farmId, int $limit = 20): array
    {
        if ($farmId < 1 || !subscription_record_table_exists($pdo)) return [];
        $limit = max(1, min(100, $limit));
        $stmt = $pdo->prepare(
            'SELECT * FROM subscriptions WHERE farm_id = ? ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute([$farmId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
