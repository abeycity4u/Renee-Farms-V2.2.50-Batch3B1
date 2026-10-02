<?php
/**
 * Central password-security policy for Renee Farms authentication and account management.
 *
 * Keep the minimum requirement, hashing/verification primitives and authenticated
 * session binding in one place so credential flows cannot drift apart during GA
 * hardening. Authenticated sessions are bound to the current stored password hash;
 * any password change therefore revokes older sessions on their next protected request.
 */

if (!function_exists('password_security_min_length')) {
    function password_security_min_length(): int
    {
        return 8;
    }
}

if (!function_exists('password_security_validate')) {
    function password_security_validate(string $password): ?string
    {
        if (strlen($password) < password_security_min_length()) {
            return 'Password must be at least ' . password_security_min_length() . ' characters.';
        }
        return null;
    }
}

if (!function_exists('password_security_hash')) {
    function password_security_hash(string $password): string
    {
        $error = password_security_validate($password);
        if ($error !== null) throw new InvalidArgumentException($error);
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('password_security_verify')) {
    /** Hash-only verification. Plaintext compatibility is intentionally excluded. */
    function password_security_verify(string $password, string $storedHash): bool
    {
        if ($password === '' || $storedHash === '') return false;
        if (password_get_info($storedHash)['algo'] === 0) return false;
        return password_verify($password, $storedHash);
    }
}

if (!function_exists('password_security_session_fingerprint')) {
    /**
     * Store only a one-way fingerprint of the password hash in PHP session state.
     * Changing the password produces a new stored hash and invalidates the old
     * fingerprint without introducing a separate database session-version field.
     */
    function password_security_session_fingerprint(string $storedHash): string
    {
        if ($storedHash === '') {
            throw new InvalidArgumentException('Stored password hash is required.');
        }

        return hash('sha256', 'renee-auth-session-v1|' . $storedHash);
    }
}

if (!function_exists('password_security_bind_authenticated_session')) {
    function password_security_bind_authenticated_session(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Authentication session is not active.');
        }

        $storedHash = (string)($user['password'] ?? '');
        if ($storedHash === '') {
            throw new RuntimeException('Authenticated credential state is unavailable.');
        }

        $_SESSION['credential_session_fingerprint'] =
            password_security_session_fingerprint($storedHash);
    }
}

if (!function_exists('password_security_public_credential_route')) {
    /**
     * Public credential routes may need to render their PRG confirmation after a
     * password reset made an existing authenticated browser session stale. They
     * expose no authenticated tenant data, so defer revocation until the next
     * protected request instead of destroying the success flash prematurely.
     */
    function password_security_public_credential_route(): bool
    {
        $path = '/' . ltrim(
            str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')),
            '/'
        );

        foreach ([
            '/sign.php',
            '/login.php',
            '/account/activate.php',
            '/account/forgot_password.php',
            '/account/reset_password.php',
        ] as $suffix) {
            if ($path === $suffix || str_ends_with($path, $suffix)) return true;
        }

        return false;
    }
}

if (!function_exists('password_security_terminate_authenticated_session')) {
    function password_security_terminate_authenticated_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'] ?? '/',
                    $params['domain'] ?? '',
                    (bool)($params['secure'] ?? false),
                    (bool)($params['httponly'] ?? true)
                );
            }

            session_destroy();
        }
    }
}

if (!function_exists('password_security_enforce_authenticated_session')) {
    /**
     * Revalidate an authenticated PHP session against the user's current stored
     * password hash. Missing fingerprints fail closed so sessions created before
     * this GA contract are rotated out once when the hardening change is deployed.
     */
    function password_security_enforce_authenticated_session(PDO $pdo): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return;

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId < 1) return;

        $farmId = (int)($_SESSION['farm_id'] ?? 0);
        $sessionFingerprint =
            (string)($_SESSION['credential_session_fingerprint'] ?? '');

        if ($farmId < 1 || $sessionFingerprint === '') {
            password_security_terminate_authenticated_session();
            header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/sign.php');
            exit();
        }

        $stmt = $pdo->prepare(
            'SELECT password FROM users WHERE id = ? AND farm_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $farmId]);
        $storedHash = (string)($stmt->fetchColumn() ?: '');

        $valid = false;
        if ($storedHash !== '') {
            $currentFingerprint =
                password_security_session_fingerprint($storedHash);
            $valid = hash_equals($currentFingerprint, $sessionFingerprint);
        }

        if ($valid) return;

        password_security_terminate_authenticated_session();
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/sign.php');
        exit();
    }
}

if (
    isset($pdo)
    && $pdo instanceof PDO
    && session_status() === PHP_SESSION_ACTIVE
    && isset($_SESSION['user_id'])
    && !password_security_public_credential_route()
) {
    password_security_enforce_authenticated_session($pdo);
}
