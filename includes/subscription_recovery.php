<?php
/**
 * V2.3 restricted subscription-recovery authentication.
 *
 * A recovery session is deliberately NOT a normal application login. It carries
 * only enough server-side identity to let the protected Farm Admin recover a
 * suspended/cancelled/past-due tenant through billing. Operational routes continue
 * to use requireLogin() and never accept this context.
 */

require_once __DIR__ . '/password_security.php';

if (!function_exists('subscription_recovery_target_statuses')) {
    function subscription_recovery_target_statuses(): array
    {
        return ['suspended', 'cancelled', 'past_due'];
    }
}

if (!function_exists('subscription_recovery_session_key')) {
    function subscription_recovery_session_key(): string
    {
        return 'subscription_recovery';
    }
}

if (!function_exists('subscription_recovery_timeout_seconds')) {
    function subscription_recovery_timeout_seconds(): int
    {
        // Long enough to complete a hosted checkout, while remaining short-lived.
        return 1800;
    }
}

if (!function_exists('subscription_recovery_account_query')) {
    function subscription_recovery_account_query(): string
    {
        return "SELECT
                    u.id AS user_id,
                    u.farm_id,
                    u.username,
                    u.password,
                    u.user_type,
                    u.full_name,
                    u.last_login_at,
                    f.name AS farm_name,
                    f.slug AS farm_slug,
                    f.contact_email,
                    f.subscription_plan,
                    f.subscription_status,
                    f.subscription_starts_at,
                    f.subscription_ends_at,
                    EXISTS(
                        SELECT 1
                        FROM user_roles ur
                        INNER JOIN roles r ON r.id = ur.role_id
                        WHERE ur.user_id = u.id AND r.code = 'farm_admin'
                    ) AS has_farm_admin_role,
                    EXISTS(
                        SELECT 1
                        FROM user_roles urp
                        INNER JOIN roles rp ON rp.id = urp.role_id
                        WHERE urp.user_id = u.id AND rp.code IN ('platform_owner', 'platform_admin')
                    ) AS has_platform_role
                FROM users u
                INNER JOIN farms f ON f.id = u.farm_id";
    }
}

if (!function_exists('subscription_recovery_login_candidate')) {
    function subscription_recovery_login_candidate(PDO $pdo, string $farmSlug, string $username): ?array
    {
        $farmSlug = strtolower(trim($farmSlug));
        $username = trim($username);
        if ($farmSlug === '' || $username === '') return null;

        $stmt = $pdo->prepare(
            subscription_recovery_account_query()
            . " WHERE f.slug = ? AND u.username = ? AND f.slug <> 'owner' LIMIT 1"
        );
        $stmt->execute([$farmSlug, $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('subscription_recovery_account_by_ids')) {
    function subscription_recovery_account_by_ids(PDO $pdo, int $farmId, int $userId): ?array
    {
        if ($farmId < 1 || $userId < 1) return null;
        $stmt = $pdo->prepare(
            subscription_recovery_account_query()
            . " WHERE f.id = ? AND u.id = ? AND u.farm_id = ? AND f.slug <> 'owner' LIMIT 1"
        );
        $stmt->execute([$farmId, $userId, $farmId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('subscription_recovery_is_farm_admin')) {
    function subscription_recovery_is_farm_admin(array $account): bool
    {
        $userType = strtolower(trim((string)($account['user_type'] ?? '')));
        $platform = (int)($account['has_platform_role'] ?? 0) === 1
            || in_array($userType, ['platform_owner', 'platform_admin'], true);
        if ($platform) return false;

        return (int)($account['has_farm_admin_role'] ?? 0) === 1 || $userType === 'farm_admin';
    }
}

if (!function_exists('subscription_recovery_status_is_target')) {
    function subscription_recovery_status_is_target(string $status): bool
    {
        return in_array(strtolower(trim($status)), subscription_recovery_target_statuses(), true);
    }
}

if (!function_exists('subscription_recovery_verify_password')) {
    function subscription_recovery_verify_password(PDO $pdo, array $account, string $password): bool
    {
        return password_security_verify($password, (string)($account['password'] ?? ''));
    }
}

if (!function_exists('subscription_recovery_clear_normal_identity')) {
    function subscription_recovery_clear_normal_identity(): void
    {
        foreach ([
            'user_id', 'farm_id', 'farm_name', 'username', 'user_type',
            'full_name', 'last_login_at', 'LAST_ACTIVITY',
        ] as $key) {
            unset($_SESSION[$key]);
        }
    }
}

if (!function_exists('subscription_recovery_start')) {
    function subscription_recovery_start(array $account): void
    {
        if (!subscription_recovery_is_farm_admin($account)) {
            throw new RuntimeException('Farm Admin recovery access is required.');
        }
        if (!subscription_recovery_status_is_target((string)($account['subscription_status'] ?? ''))) {
            throw new RuntimeException('This farm does not require subscription recovery.');
        }

        $farmId = (int)($account['farm_id'] ?? 0);
        $userId = (int)($account['user_id'] ?? 0);
        if ($farmId < 1 || $userId < 1) throw new RuntimeException('Recovery identity is invalid.');

        session_regenerate_id(true);
        subscription_recovery_clear_normal_identity();
        $now = time();
        $_SESSION[subscription_recovery_session_key()] = [
            'user_id' => $userId,
            'farm_id' => $farmId,
            'farm_slug' => (string)($account['farm_slug'] ?? ''),
            'issued_at' => $now,
            'expires_at' => $now + subscription_recovery_timeout_seconds(),
        ];
    }
}

if (!function_exists('subscription_recovery_clear')) {
    function subscription_recovery_clear(): void
    {
        unset($_SESSION[subscription_recovery_session_key()]);
    }
}

if (!function_exists('subscription_recovery_current')) {
    function subscription_recovery_current(PDO $pdo, array $allowedStatuses = []): ?array
    {
        $stored = $_SESSION[subscription_recovery_session_key()] ?? null;
        if (!is_array($stored)) return null;

        $expiresAt = (int)($stored['expires_at'] ?? 0);
        if ($expiresAt < time()) {
            subscription_recovery_clear();
            return null;
        }

        $farmId = (int)($stored['farm_id'] ?? 0);
        $userId = (int)($stored['user_id'] ?? 0);
        $account = subscription_recovery_account_by_ids($pdo, $farmId, $userId);
        if (!$account || !subscription_recovery_is_farm_admin($account)) {
            subscription_recovery_clear();
            return null;
        }

        if (!$allowedStatuses) $allowedStatuses = subscription_recovery_target_statuses();
        $allowedStatuses = array_values(array_unique(array_map(
            static fn($status): string => strtolower(trim((string)$status)),
            $allowedStatuses
        )));
        $status = strtolower(trim((string)($account['subscription_status'] ?? '')));
        if (!in_array($status, $allowedStatuses, true)) {
            subscription_recovery_clear();
            return null;
        }

        $storedSlug = trim((string)($stored['farm_slug'] ?? ''));
        if ($storedSlug === '' || !hash_equals($storedSlug, (string)($account['farm_slug'] ?? ''))) {
            subscription_recovery_clear();
            return null;
        }

        return $account;
    }
}

if (!function_exists('subscription_recovery_require')) {
    function subscription_recovery_require(PDO $pdo, array $allowedStatuses = []): array
    {
        $account = subscription_recovery_current($pdo, $allowedStatuses);
        if ($account) return $account;

        if (!headers_sent()) {
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/login.php', true, 303);
        }
        exit();
    }
}

if (!function_exists('subscription_recovery_promote_to_login')) {
    function subscription_recovery_promote_to_login(PDO $pdo): array
    {
        $account = subscription_recovery_current($pdo, ['active']);
        if (!$account) throw new RuntimeException('The subscription is not active for workspace access.');

        $previousLogin = $account['last_login_at'] ?? null;
        $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ? AND farm_id = ?');
        $stmt->execute([(int)$account['user_id'], (int)$account['farm_id']]);

        session_regenerate_id(true);
        subscription_recovery_clear();
        $_SESSION['user_id'] = (int)$account['user_id'];
        $_SESSION['farm_id'] = (int)$account['farm_id'];
        $_SESSION['farm_name'] = (string)$account['farm_name'];
        $_SESSION['username'] = (string)$account['username'];
        $_SESSION['user_type'] = (string)$account['user_type'];
        $_SESSION['full_name'] = (string)$account['full_name'];
        $_SESSION['last_login_at'] = $previousLogin;
        $_SESSION['LAST_ACTIVITY'] = time();
        return $account;
    }
}
?>