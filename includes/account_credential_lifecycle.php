<?php

declare(strict_types=1);

/**
 * Shared V3.1 account credential lifecycle service.
 *
 * Responsibilities:
 * - account-level email normalization;
 * - activation/password-reset token issuance;
 * - hash-only token persistence;
 * - token expiry and single-use consumption;
 * - credential-state activation;
 * - password reset through the central password policy;
 * - invalidation of outstanding credential tokens;
 * - login eligibility by credential state.
 *
 * Explicit non-responsibilities:
 * - HTTP routing;
 * - CSRF;
 * - rate limiting;
 * - email transport;
 * - public URL construction;
 * - tenant role/seat policy.
 *
 * Those remain owned by their existing shared services/routes.
 */

require_once __DIR__ . '/password_security.php';

if (!function_exists('account_credential_normalize_email')) {
    function account_credential_normalize_email(string $email): string
    {
        $email = strtolower(trim($email));

        if (
            $email === ''
            || strlen($email) > 254
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException(
                'Enter a valid account email address.'
            );
        }

        return $email;
    }
}

if (!function_exists('account_credential_normalize_purpose')) {
    function account_credential_normalize_purpose(string $purpose): string
    {
        $purpose = strtolower(trim($purpose));

        if (!in_array(
            $purpose,
            ['activation', 'password_reset'],
            true
        )) {
            throw new InvalidArgumentException(
                'Unsupported account credential token purpose.'
            );
        }

        return $purpose;
    }
}

if (!function_exists('account_credential_token_hash')) {
    function account_credential_token_hash(string $rawToken): string
    {
        if ($rawToken === '') {
            throw new InvalidArgumentException(
                'Credential token is required.'
            );
        }

        return hash('sha256', $rawToken);
    }
}

if (!function_exists('account_credential_generate_token')) {
    function account_credential_generate_token(): string
    {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('account_credential_login_allowed')) {
    function account_credential_login_allowed(array $user): bool
    {
        return isset($user['credential_state'])
            && hash_equals(
                'active',
                (string)$user['credential_state']
            );
    }
}

if (!function_exists('account_credential_load_user_for_update')) {
    function account_credential_load_user_for_update(
        PDO $pdo,
        int $userId
    ): array {
        if ($userId < 1) {
            throw new InvalidArgumentException(
                'A valid user is required.'
            );
        }

        $stmt = $pdo->prepare(
            "SELECT
                id,
                farm_id,
                username,
                email,
                credential_state
             FROM users
             WHERE id = ?
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->execute([$userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$user) {
            throw new RuntimeException(
                'Account could not be found.'
            );
        }

        return $user;
    }
}

if (!function_exists('account_credential_invalidate_tokens')) {
    function account_credential_invalidate_tokens(
        PDO $pdo,
        int $userId,
        ?string $purpose = null
    ): int {
        if ($userId < 1) {
            throw new InvalidArgumentException(
                'A valid user is required.'
            );
        }

        if ($purpose !== null) {
            $purpose =
                account_credential_normalize_purpose($purpose);

            $stmt = $pdo->prepare(
                "UPDATE account_credential_tokens
                 SET consumed_at = COALESCE(consumed_at, NOW())
                 WHERE user_id = ?
                   AND purpose = ?
                   AND consumed_at IS NULL"
            );

            $stmt->execute([
                $userId,
                $purpose,
            ]);

            return $stmt->rowCount();
        }

        $stmt = $pdo->prepare(
            "UPDATE account_credential_tokens
             SET consumed_at = COALESCE(consumed_at, NOW())
             WHERE user_id = ?
               AND consumed_at IS NULL"
        );

        $stmt->execute([$userId]);

        return $stmt->rowCount();
    }
}

if (!function_exists('account_credential_issue_token')) {
    function account_credential_issue_token(
        PDO $pdo,
        int $userId,
        string $purpose,
        int $ttlSeconds
    ): array {
        $purpose =
            account_credential_normalize_purpose($purpose);

        if ($ttlSeconds < 60 || $ttlSeconds > 604800) {
            throw new InvalidArgumentException(
                'Credential token lifetime is outside the allowed range.'
            );
        }

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $user =
                account_credential_load_user_for_update(
                    $pdo,
                    $userId
                );

            $email =
                account_credential_normalize_email(
                    (string)($user['email'] ?? '')
                );

            $credentialState =
                (string)($user['credential_state'] ?? '');

            if (
                $purpose === 'activation'
                && $credentialState !== 'pending_activation'
            ) {
                throw new RuntimeException(
                    'Account is not awaiting activation.'
                );
            }

            if (
                $purpose === 'password_reset'
                && $credentialState !== 'active'
            ) {
                throw new RuntimeException(
                    'Password reset is not available for this account.'
                );
            }

            /*
             * Only the latest unconsumed token for the same purpose
             * should remain usable.
             */
            account_credential_invalidate_tokens(
                $pdo,
                $userId,
                $purpose
            );

            $rawToken =
                account_credential_generate_token();

            $tokenHash =
                account_credential_token_hash($rawToken);

            $stmt = $pdo->prepare(
                "INSERT INTO account_credential_tokens (
                    user_id,
                    purpose,
                    token_hash,
                    expires_at
                 ) VALUES (
                    ?,
                    ?,
                    ?,
                    DATE_ADD(NOW(), INTERVAL ? SECOND)
                 )"
            );

            $stmt->execute([
                $userId,
                $purpose,
                $tokenHash,
                $ttlSeconds,
            ]);

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'user_id' => $userId,
                'purpose' => $purpose,
                'email' => $email,
                'token' => $rawToken,
                'expires_in' => $ttlSeconds,
            ];
        } catch (Throwable $e) {
            if (
                $startedTransaction
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}

if (!function_exists('account_credential_lock_token')) {
    function account_credential_lock_token(
        PDO $pdo,
        string $rawToken,
        string $purpose
    ): ?array {
        $purpose =
            account_credential_normalize_purpose($purpose);

        $tokenHash =
            account_credential_token_hash($rawToken);

        $stmt = $pdo->prepare(
            "SELECT
                t.id,
                t.user_id,
                t.purpose,
                t.expires_at,
                t.consumed_at,
                u.farm_id,
                u.username,
                u.email,
                u.credential_state
             FROM account_credential_tokens t
             INNER JOIN users u
                ON u.id = t.user_id
             WHERE t.token_hash = ?
               AND t.purpose = ?
               AND t.expires_at > NOW()
             LIMIT 1
             FOR UPDATE"
        );

        $stmt->execute([
            $tokenHash,
            $purpose,
        ]);

        $token = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$token) {
            return null;
        }

        if (!empty($token['consumed_at'])) {
            return null;
        }


        return $token;
    }
}

if (!function_exists('account_credential_mark_consumed')) {
    function account_credential_mark_consumed(
        PDO $pdo,
        int $tokenId
    ): void {
        $stmt = $pdo->prepare(
            "UPDATE account_credential_tokens
             SET consumed_at = NOW()
             WHERE id = ?
               AND consumed_at IS NULL"
        );

        $stmt->execute([$tokenId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Credential token is no longer available.'
            );
        }
    }
}

if (!function_exists('account_credential_consume_activation')) {
    function account_credential_consume_activation(
        PDO $pdo,
        string $rawToken,
        string $newPassword
    ): array {
        $passwordError =
            password_security_validate($newPassword);

        if ($passwordError !== null) {
            throw new InvalidArgumentException(
                $passwordError
            );
        }

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $token =
                account_credential_lock_token(
                    $pdo,
                    $rawToken,
                    'activation'
                );

            if (!$token) {
                throw new RuntimeException(
                    'Activation link is invalid or has expired.'
                );
            }

            $userId = (int)$token['user_id'];

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET password = ?,
                     credential_state = 'active'
                 WHERE id = ?
                   AND credential_state = 'pending_activation'"
            );

            $stmt->execute([
                password_security_hash($newPassword),
                $userId,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Account is not awaiting activation.'
                );
            }

            account_credential_mark_consumed(
                $pdo,
                (int)$token['id']
            );

            account_credential_invalidate_tokens(
                $pdo,
                $userId
            );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'user_id' => $userId,
                'credential_state' => 'active',
            ];
        } catch (Throwable $e) {
            if (
                $startedTransaction
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}

if (!function_exists('account_credential_consume_password_reset')) {
    function account_credential_consume_password_reset(
        PDO $pdo,
        string $rawToken,
        string $newPassword
    ): array {
        $passwordError =
            password_security_validate($newPassword);

        if ($passwordError !== null) {
            throw new InvalidArgumentException(
                $passwordError
            );
        }

        $startedTransaction = !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $token =
                account_credential_lock_token(
                    $pdo,
                    $rawToken,
                    'password_reset'
                );

            if (!$token) {
                throw new RuntimeException(
                    'Password reset link is invalid or has expired.'
                );
            }

            $userId = (int)$token['user_id'];

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET password = ?
                 WHERE id = ?
                   AND credential_state = 'active'"
            );

            $stmt->execute([
                password_security_hash($newPassword),
                $userId,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Account password could not be updated.'
                );
            }

            account_credential_mark_consumed(
                $pdo,
                (int)$token['id']
            );

            /*
             * A successful password change invalidates every other
             * activation/reset link for this account.
             */
            account_credential_invalidate_tokens(
                $pdo,
                $userId
            );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'user_id' => $userId,
                'credential_state' =>
                    (string)$token['credential_state'],
            ];
        } catch (Throwable $e) {
            if (
                $startedTransaction
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
