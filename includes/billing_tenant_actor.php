<?php
/**
 * Central Farm Admin billing actor resolver.
 *
 * Normal application sessions keep the existing requireLogin() boundary. The
 * only alternate actor is a short-lived subscription-recovery context, and it
 * is accepted only by billing routes that opt in explicitly.
 */

require_once __DIR__ . '/subscription_recovery.php';

if (!function_exists('billing_require_farm_admin_actor')) {
    function billing_require_farm_admin_actor(
        PDO $pdo,
        bool $allowRecovery = false,
        array $recoveryStatuses = ['suspended', 'cancelled']
    ): array {
        if (function_exists('isLoggedIn') && isLoggedIn()) {
            // Preserve the canonical normal-session lifecycle and subscription gate.
            requireLogin();
            $farmId = requireCurrentFarmId();
            if (isPlatformOwner() || !hasRole('farm_admin')) {
                http_response_code(403);
                exit('Farm Admin access is required for subscription billing.');
            }
            return [
                'mode' => 'session',
                'farm_id' => $farmId,
                'user_id' => (int)($_SESSION['user_id'] ?? 0),
            ];
        }

        if ($allowRecovery) {
            $recovery = subscription_recovery_current($pdo, $recoveryStatuses);
            if ($recovery) {
                return [
                    'mode' => 'recovery',
                    'farm_id' => (int)$recovery['farm_id'],
                    'user_id' => (int)$recovery['user_id'],
                ];
            }
        }

        if (!headers_sent()) header('Location: ' . BASE_URL . '/login.php', true, 303);
        exit();
    }
}

if (!function_exists('billing_tenant_actor_farm')) {
    function billing_tenant_actor_farm(PDO $pdo, array $actor): ?array
    {
        $farmId = (int)($actor['farm_id'] ?? 0);
        if ($farmId < 1) return null;
        $stmt = $pdo->prepare(
            "SELECT id, name, slug, contact_name, contact_email, subscription_plan,
                    subscription_status, subscription_starts_at, subscription_ends_at
             FROM farms
             WHERE id = ? AND slug <> 'owner'
             LIMIT 1"
        );
        $stmt->execute([$farmId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('billing_tenant_actor_is_recovery')) {
    function billing_tenant_actor_is_recovery(array $actor): bool
    {
        return ($actor['mode'] ?? '') === 'recovery';
    }
}
?>