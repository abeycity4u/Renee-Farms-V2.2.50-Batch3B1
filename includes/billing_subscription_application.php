<?php
/**
 * V2.3 Billing Stage 2G verified-payment subscription application bridge.
 *
 * This is the only billing layer allowed to turn a verified paid audit attempt
 * into current tenant commercial state. It is intentionally NOT invoked by the
 * checkout/return/webhook routes yet; Stage 2G first proves it independently.
 *
 * Launch term policy (non-recurring):
 * - monthly payment grants one month;
 * - annual payment grants one year;
 * - renewing the same plan/module bundle before the current term ends extends
 *   from that end so already-paid/trial time is not discarded;
 * - changing plan/module bundle takes effect from the verified paid timestamp;
 * - no automatic renewal, proration or provider subscription is implied here.
 */

require_once __DIR__ . '/subscription_plan_catalog.php';
require_once __DIR__ . '/farm_entitlements.php';
require_once __DIR__ . '/subscription_seat_policy.php';
require_once __DIR__ . '/subscription_record.php';
require_once __DIR__ . '/billing_payment_foundation.php';
require_once __DIR__ . '/billing_payment_audit_state.php';
require_once __DIR__ . '/billing_currency_policy.php';
require_once __DIR__ . '/billing_provider_selection.php';

if (!function_exists('billing_subscription_application_table_exists')) {
    function billing_subscription_application_table_exists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('billing_subscription_application_table_engine')) {
    function billing_subscription_application_table_engine(PDO $pdo, string $table): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        $engine = $stmt->fetchColumn();
        return $engine === false ? null : (string)$engine;
    }
}

if (!function_exists('billing_subscription_application_transactional')) {
    function billing_subscription_application_transactional(PDO $pdo): bool
    {
        foreach ([
            'farms',
            'farm_modules',
            'farm_role_limits',
            'farm_subscription_seat_addons',
            'subscriptions',
            'billing_payment_attempts',
        ] as $table) {
            $engine = billing_subscription_application_table_engine($pdo, $table);
            if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) return false;
        }
        return true;
    }
}

if (!function_exists('billing_subscription_application_ready')) {
    function billing_subscription_application_ready(PDO $pdo): bool
    {
        if (!billing_payment_foundation_ready($pdo) || !subscription_record_table_ready($pdo)) return false;
        if (!subscription_seat_addon_table_exists($pdo)) return false;
        foreach (['farms', 'farm_modules', 'farm_role_limits'] as $table) {
            if (!billing_subscription_application_table_exists($pdo, $table)) return false;
        }
        return billing_subscription_application_transactional($pdo);
    }
}

if (!function_exists('billing_subscription_decode_modules')) {
    function billing_subscription_decode_modules($raw): array
    {
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded) || array_keys($decoded) !== range(0, count($decoded) - 1)) {
            throw new RuntimeException('Frozen billing module snapshot is invalid.');
        }
        $normalized = billing_payment_normalize_modules($decoded);
        if ($decoded !== $normalized) {
            throw new RuntimeException('Frozen billing module snapshot is not canonical.');
        }
        return $normalized;
    }
}

if (!function_exists('billing_subscription_decode_seat_addons')) {
    function billing_subscription_decode_seat_addons($raw): array
    {
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) throw new RuntimeException('Frozen billing seat snapshot is invalid.');
        ksort($decoded, SORT_STRING);
        $normalized = billing_payment_normalize_seat_addons($decoded);
        if ($decoded !== $normalized) {
            throw new RuntimeException('Frozen billing seat snapshot is not canonical.');
        }
        return $normalized;
    }
}

if (!function_exists('billing_subscription_attempt_contract')) {
    function billing_subscription_attempt_contract(array $attempt): array
    {
        $attemptId = (int)($attempt['id'] ?? 0);
        $farmId = (int)($attempt['farm_id'] ?? 0);
        if ($attemptId < 1 || $farmId < 1) {
            throw new RuntimeException('Verified billing attempt identity is invalid.');
        }

        $purpose = billing_payment_attempt_purpose($attempt);
        if ($purpose !== 'subscription') {
            throw new RuntimeException(
                'Only subscription-purpose payment attempts can be applied to a subscription.'
            );
        }

        if (strtolower(trim((string)($attempt['status'] ?? ''))) !== 'paid') {
            throw new RuntimeException('Only a verified paid billing attempt can be applied to a subscription.');
        }

        $verifiedAt = billing_audit_datetime($attempt['verified_at'] ?? null);
        $paidAt = billing_audit_datetime($attempt['paid_at'] ?? null);
        if ($verifiedAt === null || $paidAt === null) {
            throw new RuntimeException('Paid billing attempt is missing provider verification timestamps.');
        }

        $provider = billing_payment_normalize_provider((string)($attempt['provider'] ?? ''));
        if (!in_array($provider, billing_provider_selection_codes(), true)) {
            throw new RuntimeException('Paid billing attempt uses an unsupported provider.');
        }
        $providerReference = billing_payment_normalize_reference((string)($attempt['provider_reference'] ?? ''));
        $providerTransactionId = billing_audit_optional_identifier($attempt['provider_transaction_id'] ?? null);
        if ($providerTransactionId === null) {
            throw new RuntimeException('Paid billing attempt is missing its provider transaction id.');
        }

        $planCode = strtolower(trim((string)($attempt['plan_code'] ?? '')));
        if (!subscription_plan_is_valid($planCode)) {
            throw new RuntimeException('Paid billing attempt contains an unknown subscription plan.');
        }
        $billingInterval = billing_payment_normalize_interval((string)($attempt['billing_interval'] ?? ''));
        $amount = billing_payment_normalize_amount((string)($attempt['amount'] ?? ''));
        $currency = billing_currency_policy_normalize(
            billing_payment_normalize_currency((string)($attempt['currency'] ?? ''))
        );
        $modules = billing_subscription_decode_modules($attempt['modules_snapshot'] ?? '');
        $seatAddOns = billing_subscription_decode_seat_addons($attempt['seat_addons_snapshot'] ?? '');

        foreach (['poultry_manager' => 'poultry', 'ruminant_manager' => 'ruminant'] as $role => $module) {
            if ((int)($seatAddOns[$role] ?? 0) > 0 && !in_array($module, $modules, true)) {
                throw new RuntimeException('Paid billing attempt contains extra seats for an unsubscribed livestock module.');
            }
        }

        $rebuilt = billing_payment_build_quote(
            $planCode,
            $billingInterval,
            $amount,
            $currency,
            $modules,
            $seatAddOns
        );
        $storedHash = strtolower(trim((string)($attempt['quote_hash'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $storedHash)
            || !hash_equals($storedHash, (string)$rebuilt['quote_hash'])) {
            throw new RuntimeException('Frozen billing quote integrity check failed.');
        }

        return [
            'attempt_id' => $attemptId,
            'farm_id' => $farmId,
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'provider_transaction_id' => $providerTransactionId,
            'provider_subscription_id' => billing_audit_optional_identifier($attempt['provider_subscription_id'] ?? null),
            'plan_code' => $planCode,
            'billing_interval' => $billingInterval,
            'amount' => $amount,
            'currency' => $currency,
            'modules' => $modules,
            'seat_addons' => $seatAddOns,
            'quote_hash' => $storedHash,
            'verified_at' => $verifiedAt,
            'paid_at' => $paidAt,
            'initiated_by_user_id' => ((int)($attempt['initiated_by_user_id'] ?? 0) > 0)
                ? (int)$attempt['initiated_by_user_id']
                : null,
        ];
    }
}

if (!function_exists('billing_subscription_parse_datetime')) {
    function billing_subscription_parse_datetime($value): ?DateTimeImmutable
    {
        if ($value === null || trim((string)$value) === '') return null;
        try {
            return new DateTimeImmutable((string)$value, new DateTimeZone(date_default_timezone_get()));
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('billing_subscription_add_term')) {
    function billing_subscription_add_term(DateTimeImmutable $anchor, string $billingInterval): DateTimeImmutable
    {
        $billingInterval = billing_payment_normalize_interval($billingInterval);
        $next = $anchor->modify($billingInterval === 'annual' ? '+1 year' : '+1 month');
        if (!$next instanceof DateTimeImmutable || $next <= $anchor) {
            throw new RuntimeException('Unable to calculate the paid subscription term.');
        }
        return $next;
    }
}

if (!function_exists('billing_subscription_term')) {
    function billing_subscription_term(array $farm, array $currentModules, array $contract): array
    {
        $paidAt = billing_subscription_parse_datetime($contract['paid_at'] ?? null);
        if (!$paidAt) throw new RuntimeException('Paid subscription timestamp is invalid.');

        $currentModules = array_values(array_intersect(['poultry', 'ruminant'], $currentModules));
        sort($currentModules, SORT_STRING);
        $newModules = $contract['modules'];
        $sameProduct = strtolower(trim((string)($farm['subscription_plan'] ?? ''))) === $contract['plan_code']
            && $currentModules === $newModules;

        $currentStart = billing_subscription_parse_datetime($farm['subscription_starts_at'] ?? null);
        $currentEnd = billing_subscription_parse_datetime($farm['subscription_ends_at'] ?? null);

        $periodAnchor = $paidAt;
        if ($sameProduct && $currentEnd && $currentEnd > $paidAt) {
            $periodAnchor = $currentEnd;
        }
        $periodEnd = billing_subscription_add_term($periodAnchor, $contract['billing_interval']);

        // Keep the original start for same-product renewal. An upgrade/module
        // bundle change starts a new commercial term at the verified paid time.
        $subscriptionStart = ($sameProduct && $currentStart) ? $currentStart : $paidAt;

        return [
            'subscription_starts_at' => $subscriptionStart->format('Y-m-d H:i:s'),
            'subscription_ends_at' => $periodEnd->format('Y-m-d H:i:s'),
            'current_period_ends_at' => $periodEnd->format('Y-m-d H:i:s'),
            'extended_existing_term' => $sameProduct && $currentEnd && $currentEnd > $paidAt,
        ];
    }
}

if (!function_exists('billing_subscription_save_effective_limits')) {
    function billing_subscription_save_effective_limits(
        PDO $pdo,
        int $farmId,
        string $planCode,
        array $modules,
        array $seatAddOns
    ): array {
        $limits = subscription_plan_effective_role_limits($planCode, $modules, $seatAddOns);
        $stmt = $pdo->prepare(
            'INSERT INTO farm_role_limits (farm_id, role_code, max_users) VALUES (?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE max_users = VALUES(max_users)'
        );
        foreach (subscription_seat_roles() as $role => $_label) {
            $stmt->execute([$farmId, $role, max(0, (int)($limits[$role] ?? 0))]);
        }
        return $limits;
    }
}

if (!function_exists('billing_subscription_existing_application')) {
    function billing_subscription_existing_application(PDO $pdo, array $attempt): ?array
    {
        $subscriptionId = (int)($attempt['applied_subscription_record_id'] ?? 0);
        if ($subscriptionId < 1) return null;

        $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id = ? AND farm_id = ? LIMIT 1');
        $stmt->execute([$subscriptionId, (int)($attempt['farm_id'] ?? 0)]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$subscription) {
            throw new RuntimeException('Billing attempt points to a missing subscription history record.');
        }
        return [
            'applied' => false,
            'idempotent' => true,
            'attempt_id' => (int)$attempt['id'],
            'subscription_record_id' => $subscriptionId,
            'subscription' => $subscription,
        ];
    }
}

if (!function_exists('billing_subscription_insert_history')) {
    function billing_subscription_insert_history(
        PDO $pdo,
        array $contract,
        array $builtSnapshot,
        array $term,
        ?int $recordedByUserId
    ): int {
        $snapshot = $builtSnapshot['snapshot'];
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
            $contract['farm_id'],
            $contract['plan_code'],
            'active',
            $contract['billing_interval'],
            $contract['amount'],
            $contract['currency'],
            $contract['provider'],
            $contract['provider_subscription_id'],
            $term['current_period_ends_at'],
            $snapshot['subscription_starts_at'],
            $snapshot['subscription_ends_at'],
            json_encode($snapshot['modules'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($snapshot['seat_addons'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'billing_payment_applied',
            $recordedByUserId,
            $builtSnapshot['snapshot_hash'],
        ]);
        $id = (int)$pdo->lastInsertId();
        if ($id < 1) throw new RuntimeException('Unable to create immutable subscription history for the paid billing attempt.');
        return $id;
    }
}

if (!function_exists('billing_subscription_apply_paid_attempt')) {
    function billing_subscription_apply_paid_attempt(
        PDO $pdo,
        int $attemptId,
        ?int $recordedByUserId = null
    ): array {
        if ($attemptId < 1) throw new InvalidArgumentException('A valid billing payment attempt is required.');
        if (!billing_subscription_application_ready($pdo)) {
            throw new RuntimeException('Subscription application storage is not transactionally ready.');
        }

        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();

        try {
            $attempt = billing_audit_attempt_by_id($pdo, $attemptId, true);
            if (!$attempt) throw new RuntimeException('Billing payment attempt could not be found for subscription application.');

            $attemptPurpose = billing_payment_attempt_purpose($attempt);
            if ($attemptPurpose !== 'subscription') {
                throw new RuntimeException(
                    'Only subscription-purpose payment attempts can enter subscription application.'
                );
            }

            $existing = billing_subscription_existing_application($pdo, $attempt);
            if ($existing !== null) {
                if ($startedTransaction) $pdo->commit();
                return $existing;
            }

            $contract = billing_subscription_attempt_contract($attempt);
            $farmStmt = $pdo->prepare(
                "SELECT id, slug, subscription_plan, subscription_status,
                        subscription_starts_at, subscription_ends_at
                 FROM farms
                 WHERE id = ? AND slug <> 'owner'
                 LIMIT 1 FOR UPDATE"
            );
            $farmStmt->execute([$contract['farm_id']]);
            $farm = $farmStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$farm) throw new RuntimeException('Tenant farm could not be locked for subscription application.');

            $currentModules = subscription_record_commercial_modules($pdo, $contract['farm_id']);
            subscription_seat_assert_capacity(
                $pdo,
                $contract['farm_id'],
                $contract['plan_code'],
                $contract['modules'],
                $contract['seat_addons']
            );
            $term = billing_subscription_term($farm, $currentModules, $contract);

            $updateFarm = $pdo->prepare(
                "UPDATE farms
                 SET subscription_plan = ?, subscription_status = 'active',
                     subscription_starts_at = ?, subscription_ends_at = ?
                 WHERE id = ? AND slug <> 'owner'"
            );
            $updateFarm->execute([
                $contract['plan_code'],
                $term['subscription_starts_at'],
                $term['subscription_ends_at'],
                $contract['farm_id'],
            ]);
            if ($updateFarm->rowCount() !== 1) {
                throw new RuntimeException('Tenant subscription snapshot could not be updated safely.');
            }

            sync_farm_entitlements($pdo, $contract['farm_id'], $contract['modules']);
            subscription_seat_save_addons($pdo, $contract['farm_id'], $contract['seat_addons']);
            $effectiveLimits = billing_subscription_save_effective_limits(
                $pdo,
                $contract['farm_id'],
                $contract['plan_code'],
                $contract['modules'],
                $contract['seat_addons']
            );

            $built = subscription_record_build_snapshot($pdo, $contract['farm_id']);
            $snapshot = $built['snapshot'];
            if (($snapshot['plan_code'] ?? null) !== $contract['plan_code']
                || ($snapshot['status'] ?? null) !== 'active'
                || ($snapshot['modules'] ?? null) !== $contract['modules']
                || ($snapshot['seat_addons'] ?? null) !== $contract['seat_addons']) {
                throw new RuntimeException('Applied tenant subscription state does not match the frozen paid billing quote.');
            }

            $actor = ($recordedByUserId !== null && $recordedByUserId > 0)
                ? $recordedByUserId
                : $contract['initiated_by_user_id'];
            $subscriptionId = billing_subscription_insert_history($pdo, $contract, $built, $term, $actor);

            $link = $pdo->prepare(
                "UPDATE billing_payment_attempts
                 SET applied_subscription_record_id = ?
                 WHERE id = ? AND status = 'paid' AND applied_subscription_record_id IS NULL"
            );
            $link->execute([$subscriptionId, $attemptId]);
            if ($link->rowCount() !== 1) {
                throw new RuntimeException('Paid billing attempt could not be linked exactly once to subscription history.');
            }

            if ($startedTransaction) $pdo->commit();
            return [
                'applied' => true,
                'idempotent' => false,
                'attempt_id' => $attemptId,
                'subscription_record_id' => $subscriptionId,
                'farm_id' => $contract['farm_id'],
                'plan_code' => $contract['plan_code'],
                'modules' => $contract['modules'],
                'seat_addons' => $contract['seat_addons'],
                'effective_role_limits' => $effectiveLimits,
                'subscription_starts_at' => $term['subscription_starts_at'],
                'subscription_ends_at' => $term['subscription_ends_at'],
                'current_period_ends_at' => $term['current_period_ends_at'],
                'extended_existing_term' => $term['extended_existing_term'],
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
