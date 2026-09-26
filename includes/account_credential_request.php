<?php

declare(strict_types=1);

/**
 * V3.1 account credential request policy.
 *
 * Responsibilities:
 * - tenant-aware farm password-reset lookup;
 * - platform-account password-reset lookup;
 * - active-state / users.email eligibility;
 * - one neutral durable password-reset outbox enqueue per request;
 * - enumeration-safe public response;
 * - non-secret operator diagnostics.
 *
 * Non-responsibilities:
 * - SMTP/mail transport;
 * - token issuance;
 * - password mutation;
 * - HTTP routing;
 * - CSRF;
 * - guest rate limiting;
 * - activation consumption;
 * - account creation policy.
 */

require_once __DIR__
    . '/account_credential_outbox.php';

if (!function_exists('account_credential_request_text')) {
    function account_credential_request_text(
        mixed $value,
        int $maxLength = 120
    ): string {
        if ($maxLength < 1) {
            throw new InvalidArgumentException(
                'Credential request text length must be positive.'
            );
        }

        $value =
            (string)$value;

        $value =
            preg_replace(
                '/[\x00-\x1F\x7F]+/',
                ' ',
                $value
            ) ?? '';

        return substr(
            trim($value),
            0,
            $maxLength
        );
    }
}

if (!function_exists('account_credential_request_public_result')) {
    function account_credential_request_public_result(): array
    {
        return [
            'accepted' => true,
            'message' =>
                'If the account details are valid, a password reset link will be sent to the email address on the account.',
        ];
    }
}

if (!function_exists('account_credential_request_log_failure')) {
    function account_credential_request_log_failure(
        string $phase,
        Throwable $error
    ): void {
        /*
         * Internal phase names and exception class only.
         * Never log workspace id, username, email, token, URL,
         * supplied form data or exception message.
         */
        $phase =
            preg_replace(
                '/[^a-z0-9_]+/i',
                '_',
                strtolower(trim($phase))
            ) ?? 'request';

        $phase =
            trim(
                $phase,
                '_'
            );

        if ($phase === '') {
            $phase = 'request';
        }

        error_log(
            'Account credential request '
            . substr($phase, 0, 40)
            . ' failure ['
            . get_class($error)
            . '].'
        );
    }
}

if (!function_exists('account_credential_request_farm_candidate')) {
    function account_credential_request_farm_candidate(
        PDO $pdo,
        string $workspaceId,
        string $username
    ): ?array {
        $workspaceId =
            strtolower(
                account_credential_request_text(
                    $workspaceId,
                    120
                )
            );

        $username =
            account_credential_request_text(
                $username,
                120
            );

        $stmt = $pdo->prepare(
            "SELECT
                u.id,
                u.email,
                u.credential_state,
                u.user_type,
                u.farm_id,
                f.slug AS workspace_id
             FROM users u
             INNER JOIN farms f
                ON f.id = u.farm_id
             WHERE f.slug = ?
               AND f.slug <> 'owner'
               AND u.username = ?
             LIMIT 1"
        );

        $stmt->execute([
            $workspaceId,
            $username,
        ]);

        $row =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        return $row ?: null;
    }
}

if (!function_exists('account_credential_request_platform_candidate')) {
    function account_credential_request_platform_candidate(
        PDO $pdo,
        string $username
    ): ?array {
        $username =
            account_credential_request_text(
                $username,
                120
            );

        $stmt = $pdo->prepare(
            "SELECT
                u.id,
                u.email,
                u.credential_state,
                u.user_type,
                u.farm_id,
                f.slug AS workspace_id
             FROM users u
             INNER JOIN farms f
                ON f.id = u.farm_id
             WHERE f.slug = 'owner'
               AND u.username = ?
               AND u.user_type IN (
                    'platform_owner',
                    'platform_admin'
               )
             LIMIT 1"
        );

        $stmt->execute([
            $username,
        ]);

        $row =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: null;

        return $row ?: null;
    }
}

if (!function_exists('account_credential_request_candidate_eligible')) {
    function account_credential_request_candidate_eligible(
        ?array $candidate
    ): bool {
        if (!$candidate) {
            return false;
        }

        if (
            (string)($candidate['credential_state'] ?? '')
            !== 'active'
        ) {
            return false;
        }

        try {
            $email =
                account_credential_normalize_email(
                    (string)($candidate['email'] ?? '')
                );
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return $email !== '';
    }
}

if (!function_exists('account_credential_request_candidate_user_id')) {
    function account_credential_request_candidate_user_id(
        ?array $candidate
    ): ?int {
        if (
            !account_credential_request_candidate_eligible(
                $candidate
            )
        ) {
            return null;
        }

        $userId =
            (int)($candidate['id'] ?? 0);

        return $userId > 0
            ? $userId
            : null;
    }
}

if (!function_exists('account_credential_request_dispatch_candidate')) {
    function account_credential_request_dispatch_candidate(
        PDO $pdo,
        ?array $candidate
    ): void {
        /*
         * Every public reset request reaches this same INSERT-shaped path.
         *
         * Eligible account:
         *     user_id = real user id
         *
         * No match / inactive / missing-invalid email:
         *     user_id = NULL
         *
         * No SMTP, token creation or URL construction occurs here.
         */
        $userId =
            account_credential_request_candidate_user_id(
                $candidate
            );

        account_credential_outbox_enqueue(
            $pdo,
            $userId,
            'password_reset'
        );
    }
}

if (!function_exists('account_credential_request_farm_password_reset')) {
    function account_credential_request_farm_password_reset(
        PDO $pdo,
        string $workspaceId,
        string $username
    ): array {
        $candidate = null;

        try {
            $candidate =
                account_credential_request_farm_candidate(
                    $pdo,
                    $workspaceId,
                    $username
                );
        } catch (Throwable $lookupError) {
            account_credential_request_log_failure(
                'farm_lookup',
                $lookupError
            );

            /*
             * Still proceed to neutral NULL-user enqueue where storage
             * remains available. Browser response never changes.
             */
            $candidate = null;
        }

        try {
            account_credential_request_dispatch_candidate(
                $pdo,
                $candidate
            );
        } catch (Throwable $enqueueError) {
            account_credential_request_log_failure(
                'farm_enqueue',
                $enqueueError
            );
        }

        return account_credential_request_public_result();
    }
}

if (!function_exists('account_credential_request_platform_password_reset')) {
    function account_credential_request_platform_password_reset(
        PDO $pdo,
        string $username
    ): array {
        $candidate = null;

        try {
            $candidate =
                account_credential_request_platform_candidate(
                    $pdo,
                    $username
                );
        } catch (Throwable $lookupError) {
            account_credential_request_log_failure(
                'platform_lookup',
                $lookupError
            );

            $candidate = null;
        }

        try {
            account_credential_request_dispatch_candidate(
                $pdo,
                $candidate
            );
        } catch (Throwable $enqueueError) {
            account_credential_request_log_failure(
                'platform_enqueue',
                $enqueueError
            );
        }

        return account_credential_request_public_result();
    }
}
