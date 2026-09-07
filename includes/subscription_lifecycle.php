<?php
/**
 * V2.3 canonical subscription lifecycle boundary.
 *
 * Contract:
 * - only trial/active tenants can expire automatically;
 * - expiry moves the current farm snapshot to past_due, never suspended;
 * - the transition is tenant-pinned, idempotent and recorded in immutable
 *   subscription history when commercial history storage is available;
 * - Farm Admins are moved into the existing restricted billing-recovery session;
 * - non-admin tenant users lose operational access at the same boundary;
 * - a verified paid billing application remains the only path back to active.
 */

require_once __DIR__ . '/subscription_record.php';

// Define the recovery target contract before loading the recovery helper. The
// helper deliberately guards its definition with function_exists(), allowing the
// lifecycle layer to extend the existing suspended/cancelled recovery model with
// past_due without duplicating authentication/session code.
if (!function_exists('subscription_recovery_target_statuses')) {
    function subscription_recovery_target_statuses(): array
    {
        return ['suspended', 'cancelled', 'past_due'];
    }
}
require_once __DIR__ . '/subscription_recovery.php';

if (!function_exists('subscription_lifecycle_expirable_statuses')) {
    function subscription_lifecycle_expirable_statuses(): array
    {
        return ['trial', 'active'];
    }
}

if (!function_exists('subscription_lifecycle_parse_datetime')) {
    function subscription_lifecycle_parse_datetime($value): ?DateTimeImmutable
    {
        if ($value === null || trim((string)$value) === '') return null;
        try {
            return new DateTimeImmutable((string)$value, new DateTimeZone(date_default_timezone_get()));
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('subscription_lifecycle_is_expired')) {
    function subscription_lifecycle_is_expired(
        string $status,
        $subscriptionEndsAt,
        ?DateTimeImmutable $now = null
    ): bool {
        $status = strtolower(trim($status));
        if (!in_array($status, subscription_lifecycle_expirable_statuses(), true)) return false;

        $endsAt = subscription_lifecycle_parse_datetime($subscriptionEndsAt);
        if (!$endsAt) return false;

        $now = $now ?: new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        return $endsAt <= $now;
    }
}

if (!function_exists('subscription_lifecycle_farm')) {
    function subscription_lifecycle_farm(PDO $pdo, int $farmId, bool $forUpdate = false): ?array
    {
        if ($farmId < 1) return null;
        $sql = "SELECT id, name, slug, subscription_plan, subscription_status,
                       subscription_starts_at, subscription_ends_at
                FROM farms
                WHERE id = ? AND slug <> 'owner'
                LIMIT 1";
        if ($forUpdate) $sql .= ' FOR UPDATE';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$farmId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('subscription_lifecycle_farm_id_by_slug')) {
    function subscription_lifecycle_farm_id_by_slug(PDO $pdo, string $farmSlug): int
    {
        $farmSlug = strtolower(trim($farmSlug));
        if ($farmSlug === '' || $farmSlug === 'owner') return 0;
        $stmt = $pdo->prepare("SELECT id FROM farms WHERE slug = ? AND slug <> 'owner' LIMIT 1");
        $stmt->execute([$farmSlug]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('subscription_lifecycle_refresh_farm')) {
    function subscription_lifecycle_refresh_farm(
        PDO $pdo,
        int $farmId,
        ?DateTimeImmutable $now = null
    ): ?array {
        if ($farmId < 1) return null;

        // Hot path: ordinary active/trial requests do one non-locking read and
        // return immediately. Only a genuinely expired candidate enters a write
        // transaction and row lock, which keeps the runtime guard cheap at scale.
        $farm = subscription_lifecycle_farm($pdo, $farmId, false);
        if (!$farm) return null;
        if (!subscription_lifecycle_is_expired(
            (string)($farm['subscription_status'] ?? ''),
            $farm['subscription_ends_at'] ?? null,
            $now
        )) {
            $farm['lifecycle_transitioned'] = false;
            return $farm;
        }

        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();

        try {
            // Re-read under lock because another request may already have moved
            // this farm to past_due while we were entering the transaction.
            $farm = subscription_lifecycle_farm($pdo, $farmId, true);
            if (!$farm) {
                if ($startedTransaction) $pdo->commit();
                return null;
            }

            $transitioned = false;
            if (subscription_lifecycle_is_expired(
                (string)($farm['subscription_status'] ?? ''),
                $farm['subscription_ends_at'] ?? null,
                $now
            )) {
                $previousStatus = strtolower(trim((string)$farm['subscription_status']));
                $stmt = $pdo->prepare(
                    "UPDATE farms
                     SET subscription_status = 'past_due'
                     WHERE id = ? AND slug <> 'owner'
                       AND subscription_status IN ('trial', 'active')"
                );
                $stmt->execute([$farmId]);
                $transitioned = $stmt->rowCount() === 1;

                if ($transitioned) {
                    $farm['subscription_status'] = 'past_due';
                    if (subscription_record_table_ready($pdo)) {
                        subscription_record_capture($pdo, $farmId, 'subscription_expired', null);
                    }
                    $farm['previous_subscription_status'] = $previousStatus;
                }
            }

            $farm['lifecycle_transitioned'] = $transitioned;
            if ($startedTransaction) $pdo->commit();
            return $farm;
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('subscription_lifecycle_refresh_slug')) {
    function subscription_lifecycle_refresh_slug(
        PDO $pdo,
        string $farmSlug,
        ?DateTimeImmutable $now = null
    ): ?array {
        $farmId = subscription_lifecycle_farm_id_by_slug($pdo, $farmSlug);
        return $farmId > 0 ? subscription_lifecycle_refresh_farm($pdo, $farmId, $now) : null;
    }
}

if (!function_exists('subscription_lifecycle_redirect')) {
    function subscription_lifecycle_redirect(string $path): void
    {
        if (!headers_sent()) {
            $base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
            header('Location: ' . $base . '/' . ltrim($path, '/'), true, 303);
        }
        exit();
    }
}

if (!function_exists('subscription_lifecycle_request_guard')) {
    function subscription_lifecycle_request_guard(PDO $pdo): void
    {
        // Refresh an attempted tenant login before credentials are evaluated. This
        // is an idempotent lifecycle write only; authentication remains in the
        // existing sign-in/recovery helpers and their rate limiting.
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($method === 'POST'
            && in_array($script, ['sign.php', 'login.php'], true)
            && (($_POST['account_type'] ?? 'farm') !== 'platform')) {
            $farmSlug = strtolower(trim((string)($_POST['farm_slug'] ?? '')));
            if ($farmSlug !== '') subscription_lifecycle_refresh_slug($pdo, $farmSlug);
        }

        $farmId = (int)($_SESSION['farm_id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($farmId < 1 || $userId < 1) return;

        $farm = subscription_lifecycle_refresh_farm($pdo, $farmId);
        if (!$farm || strtolower((string)$farm['subscription_status']) !== 'past_due') return;

        $account = subscription_recovery_account_by_ids($pdo, $farmId, $userId);
        if ($account && subscription_recovery_is_farm_admin($account)) {
            subscription_recovery_start($account);
            subscription_lifecycle_redirect('/billing/recover.php');
        }

        subscription_recovery_clear_normal_identity();
        subscription_recovery_clear();
        $_SESSION['error'] = 'This farm subscription has expired. Please ask the Farm Admin to renew access.';
        subscription_lifecycle_redirect('/sign.php');
    }
}
?>
